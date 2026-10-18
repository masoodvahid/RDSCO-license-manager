<?php

namespace App\Services\Shahkar;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Calls "serviceIDmatchingencrypted" (national code + mobile ownership).
 *
 * Retries transient failures (gateway 5xx/429, Shahkar 330/500/501/510/615/617)
 * a limited number of times with backoff and jitter. Every attempt uses a new
 * request id and freshly encrypted values, because Shahkar rejects repeated
 * request ids (312).
 *
 * The whole call, retries and token renewal included, stays within one time
 * budget (license.shahkar.deadline_seconds), and a circuit breaker fails fast
 * while the gateway is down, so a slow gateway cannot exhaust PHP workers.
 */
class ShahkarClient
{
    private const RETRY_DELAYS_MS = [300, 800];

    private const MAX_RESPONSE_BYTES = 1_048_576;

    /** An attempt with less time than this left is not worth starting. */
    private const MIN_ATTEMPT_SECONDS = 3.0;

    private const MAX_REQUEST_SECONDS = 10.0;

    public function __construct(
        private readonly ShahkarEncryptor $encryptor,
        private readonly SitaadTokenProvider $tokens,
        private readonly ShahkarResponseCodes $businessCodes,
        private readonly SitaadGatewayCodes $gatewayCodes,
        private readonly ShahkarCircuit $circuit,
    ) {}

    public function verify(string $nationalCode, string $mobile, ?ShahkarSettings $settings = null): ShahkarResult
    {
        $startedAt = hrtime(true);
        $settings ??= ShahkarSettings::load();

        try {
            if ($settings->missing() !== []) {
                throw new ShahkarException('configuration', 'configuration', 'تنظیمات اتصال شاهکار کامل نیست.');
            }

            if ($this->circuit->isOpen()) {
                return $this->circuitOpenResult($startedAt);
            }

            $deadline = $startedAt + (int) (max(5, (int) config('license.shahkar.deadline_seconds', 15)) * 1e9);
            $attempts = count(self::RETRY_DELAYS_MS) + 1;
            $tokenRenewed = false;

            for ($attempt = 1; ; $attempt++) {
                try {
                    $result = $this->attempt($settings, $nationalCode, $mobile, $startedAt, $deadline);
                } catch (ShahkarException $exception) {
                    if ($exception->category === 'connection') {
                        $this->circuit->recordFailure();
                    }

                    throw $exception;
                }

                if (ShahkarCircuit::isTransportFailure($result)) {
                    $this->circuit->recordFailure();
                } else {
                    $this->circuit->recordSuccess();
                }

                // An expired or revoked token: get a new one once, then retry immediately.
                if ($result->code === 401 && $result->stage === 'gateway' && ! $tokenRenewed) {
                    $this->tokens->forget();
                    $tokenRenewed = true;
                    $attempt--;

                    continue;
                }

                if (! $result->retryable || $attempt >= $attempts) {
                    return $result;
                }

                // Not enough time left for a useful retry, or the gateway was just declared down.
                $delayMs = self::RETRY_DELAYS_MS[$attempt - 1];

                if ($this->remainingSeconds($deadline) - $delayMs / 1000 < self::MIN_ATTEMPT_SECONDS || $this->circuit->isOpen()) {
                    return $result;
                }

                $this->pause($attempt);
            }
        } catch (ShahkarException $exception) {
            return ShahkarResult::fromException($exception, $this->elapsed($startedAt));
        }
    }

    private function attempt(ShahkarSettings $settings, string $nationalCode, string $mobile, int $startedAt, int $deadline): ShahkarResult
    {
        $requestId = $this->newRequestId($settings->rid);

        try {
            $payload = [
                'requestId' => $requestId,
                'identificationType' => 0,
                'identificationNo' => $this->encryptor->encrypt($nationalCode, $settings->publicKey),
                'serviceType' => 2,
                'serviceNumber' => $this->encryptor->encrypt($mobile, $settings->publicKey),
            ];
        } catch (Throwable $exception) {
            report($exception);

            throw new ShahkarException('encryption', 'encryption', 'رمزنگاری اطلاعات ناموفق بود. کلید عمومی شاهکار را بررسی کنید.');
        }

        $accessToken = $this->tokens->token($settings, $this->remainingSeconds($deadline));
        $timeout = min(self::MAX_REQUEST_SECONDS, $this->remainingSeconds($deadline));

        if ($timeout < 1.0) {
            throw new ShahkarException('verify', 'timeout', 'سیتاد در زمان مجاز پاسخ نداد.', retryable: true);
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders([
                    'pid' => $settings->pid,
                    'Authorization' => 'Bearer '.$accessToken,
                    'basicAuthorization' => $settings->craAuthorizationHeader(),
                ])
                ->connectTimeout(min(3.0, $timeout))
                ->timeout($timeout)
                ->withoutRedirecting()
                ->withOptions(['on_headers' => function (ResponseInterface $response): void {
                    if ((int) $response->getHeaderLine('Content-Length') > self::MAX_RESPONSE_BYTES) {
                        throw new RuntimeException('Shahkar response is too large.');
                    }
                }])
                ->post($settings->verifyUrl, $payload);
        } catch (ConnectionException) {
            return $this->gatewayResult(504, $requestId, null, $startedAt, connection: true);
        }

        return $this->interpret($response, $requestId, $startedAt);
    }

    private function interpret(Response $response, string $requestId, int $startedAt): ShahkarResult
    {
        $body = $response->json();
        $trackingId = $response->header('requestId') ?: null;

        if (! $response->successful()) {
            return $this->gatewayResult($response->status(), $requestId, $trackingId, $startedAt);
        }

        $gatewayStatus = is_array($body) ? $this->gatewayStatus($body) : null;

        if ($gatewayStatus !== null && ($gatewayStatus < 200 || $gatewayStatus >= 300)) {
            return $this->gatewayResult($gatewayStatus, $requestId, $trackingId, $startedAt);
        }

        $answer = is_array($body) ? data_get($body, 'result.data.result.data') : null;
        $answer = is_array($answer) ? $answer : (is_array($body) ? $body : []);
        $code = $answer['response'] ?? null;

        if (! is_numeric($code)) {
            Log::warning('Unexpected Shahkar response shape.', ['http_status' => $response->status()]);

            return new ShahkarResult(
                matched: null,
                code: 0,
                category: 'invalid_upstream_response',
                message: 'پاسخ شاهکار قابل پردازش نیست.',
                retryable: false,
                httpStatus: 502,
                trackingId: $trackingId,
                requestId: $requestId,
                durationMs: $this->elapsed($startedAt),
            );
        }

        $mapped = $this->businessCodes->map((int) $code);

        return new ShahkarResult(
            matched: $mapped['matched'],
            code: (int) $code,
            category: $mapped['category'],
            message: $mapped['message'],
            retryable: $mapped['retryable'],
            httpStatus: $mapped['http_status'],
            trackingId: is_string($answer['requestId'] ?? null) ? $answer['requestId'] : $trackingId,
            requestId: $requestId,
            durationMs: $this->elapsed($startedAt),
            stage: 'shahkar',
        );
    }

    private function gatewayResult(int $status, string $requestId, ?string $trackingId, int $startedAt, bool $connection = false): ShahkarResult
    {
        $mapped = $connection
            ? ['message' => 'ارتباط با سیتاد در زمان مجاز برقرار نشد.', 'category' => 'connection', 'retryable' => true, 'http_status' => 504]
            : $this->gatewayCodes->map($status);

        return new ShahkarResult(
            matched: null,
            code: $status,
            category: $mapped['category'],
            message: $mapped['message'],
            retryable: $mapped['retryable'],
            httpStatus: $mapped['http_status'],
            trackingId: $trackingId,
            requestId: $requestId,
            durationMs: $this->elapsed($startedAt),
            stage: 'gateway',
        );
    }

    /**
     * The gateway wraps answers in several "status" envelopes; the first
     * non-2xx one decides.
     *
     * @param  array<mixed>  $body
     */
    private function gatewayStatus(array $body): ?int
    {
        foreach (['status.statusCode', 'result.status.statusCode', 'result.data.status.statusCode', 'result.data.result.status.statusCode'] as $path) {
            $status = data_get($body, $path);

            if (is_numeric($status) && ((int) $status < 200 || (int) $status >= 300)) {
                return (int) $status;
            }
        }

        return null;
    }

    /**
     * RID prefix + date/time with microseconds (24 digits for a 4-digit RID).
     */
    private function newRequestId(string $rid): string
    {
        // Shahkar rejects a reused requestId (code 312). Retries inside one PHP
        // process can land on the same clock tick, so keep the stamp strictly
        // increasing within the process.
        static $last = null;

        $moment = now();

        if ($last !== null && $moment->lte($last)) {
            $moment = $last->copy()->addMicrosecond();
        }

        $last = $moment;

        return $rid.$moment->format('YmdHisu');
    }

    private function pause(int $attempt): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        usleep((self::RETRY_DELAYS_MS[$attempt - 1] + random_int(0, 150)) * 1000);
    }

    private function remainingSeconds(int $deadline): float
    {
        return max(0.0, ($deadline - hrtime(true)) / 1e9);
    }

    private function circuitOpenResult(int $startedAt): ShahkarResult
    {
        $seconds = max(1, (int) $this->circuit->openUntil() - time());

        return new ShahkarResult(
            matched: null,
            code: 0,
            category: 'circuit_open',
            message: "سیتاد چند بار پشت سر هم پاسخ نداد. استعلام‌ها حدود {$seconds} ثانیه متوقف است؛ کمی بعد دوباره تلاش کنید.",
            retryable: true,
            httpStatus: 503,
            trackingId: null,
            requestId: null,
            durationMs: $this->elapsed($startedAt),
            stage: 'gateway',
        );
    }

    private function elapsed(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
