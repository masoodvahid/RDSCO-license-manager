<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateLicense;
use App\Services\Errors\ErrorReportIngestor;
use App\Services\Licensing\LicenseActivity;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErrorReportController extends Controller
{
    public function __construct(
        private readonly ErrorReportIngestor $ingestor,
        private readonly LicenseActivity $activity,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $report = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'level' => ['nullable', 'string', 'in:emergency,alert,critical,error,warning'],
            'exception_class' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'string', 'max:1000'],
            'line' => ['nullable', 'integer', 'min:0'],
            'trace' => ['nullable', 'string', 'max:60000'],
            'context' => ['nullable', 'array'],
            'url' => ['nullable', 'string', 'max:2000'],
            'environment' => ['nullable', 'string', 'max:32'],
            'app_version' => ['nullable', 'string', 'max:64'],
        ]);

        $license = AuthenticateLicense::license($request);
        $error = $this->ingestor->ingest($license, $report);
        $this->activity->touchLastSeen($license, $request->ip(), $report['app_version'] ?? null);

        return ApiResponse::success([
            'error_id' => $error->getKey(),
            'occurrences' => $error->occurrences,
        ], 201);
    }
}
