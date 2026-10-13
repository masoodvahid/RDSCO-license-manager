<?php

namespace App\Services\Shahkar;

use App\Models\Setting;

/**
 * Connection settings for Sitaad (gateway) and Shahkar (CRA), stored in the
 * settings table. Secrets are encrypted at rest.
 */
final readonly class ShahkarSettings
{
    public const DEFAULT_TOKEN_URL = 'https://op1.pgsb.ir/oauth/token';

    public const DEFAULT_VERIFY_URL = 'https://op1.pgsb.ir/api/client/apim/v1/shahkaar/gwsh/serviceIDmatchingencrypted';

    /**
     * Setting key => [label, is_secret]. Order is the order of the settings form.
     */
    public const FIELDS = [
        'shahkar.token_url' => ['آدرس دریافت توکن سیتاد', false],
        'shahkar.verify_url' => ['آدرس سرویس شاهکار رمزشده', false],
        'shahkar.gateway_authorization' => ['کلید Basic سیتاد (Client ID/Secret)', true],
        'shahkar.username' => ['نام کاربری سیتاد', false],
        'shahkar.password' => ['رمز عبور سیتاد', true],
        'shahkar.pid' => ['شناسه پکیج (PID)', false],
        'shahkar.cra_username' => ['نام کاربری شاهکار (سازمان تنظیم)', false],
        'shahkar.cra_password' => ['رمز عبور شاهکار (سازمان تنظیم)', true],
        'shahkar.rid' => ['پیشوند شماره درخواست (RID)', false],
        'shahkar.public_key' => ['کلید عمومی شاهکار (PEM)', false],
    ];

    public function __construct(
        public string $tokenUrl,
        public string $verifyUrl,
        public string $gatewayAuthorization,
        public string $username,
        public string $password,
        public string $pid,
        public string $craUsername,
        public string $craPassword,
        public string $rid,
        public string $publicKey,
    ) {}

    public static function load(): self
    {
        $value = fn (string $key, string $default = ''): string => trim((string) Setting::get($key, $default));

        return new self(
            tokenUrl: $value('shahkar.token_url', self::DEFAULT_TOKEN_URL),
            verifyUrl: $value('shahkar.verify_url', self::DEFAULT_VERIFY_URL),
            gatewayAuthorization: $value('shahkar.gateway_authorization'),
            username: $value('shahkar.username'),
            password: $value('shahkar.password'),
            pid: $value('shahkar.pid'),
            craUsername: $value('shahkar.cra_username'),
            craPassword: $value('shahkar.cra_password'),
            rid: $value('shahkar.rid'),
            publicKey: (string) Setting::get('shahkar.public_key', ''),
        );
    }

    /**
     * @return list<string> Labels of required settings that are empty.
     */
    public function missing(): array
    {
        $missing = [];

        foreach ([
            'shahkar.token_url' => $this->tokenUrl,
            'shahkar.verify_url' => $this->verifyUrl,
            'shahkar.gateway_authorization' => $this->gatewayAuthorization,
            'shahkar.username' => $this->username,
            'shahkar.password' => $this->password,
            'shahkar.pid' => $this->pid,
            'shahkar.cra_username' => $this->craUsername,
            'shahkar.cra_password' => $this->craPassword,
            'shahkar.rid' => $this->rid,
            'shahkar.public_key' => $this->publicKey,
        ] as $key => $value) {
            if (trim($value) === '') {
                $missing[] = self::FIELDS[$key][0];
            }
        }

        return $missing;
    }

    /**
     * Value for the gateway "Authorization" header (always "Basic ...").
     */
    public function gatewayAuthorizationHeader(): string
    {
        return str_starts_with(strtolower($this->gatewayAuthorization), 'basic ')
            ? $this->gatewayAuthorization
            : 'Basic '.$this->gatewayAuthorization;
    }

    /**
     * Value for the Shahkar "basicAuthorization" header.
     */
    public function craAuthorizationHeader(): string
    {
        return 'Basic '.base64_encode($this->craUsername.':'.$this->craPassword);
    }

    public function fingerprint(): string
    {
        return hash('sha256', implode('|', [$this->tokenUrl, $this->gatewayAuthorization, $this->username, $this->password]));
    }
}
