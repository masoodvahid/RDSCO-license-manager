<?php

namespace Rdsco\LicenseClient\Console;

use Illuminate\Console\Command;
use Rdsco\LicenseClient\LicenseManager;

class LicenseStatusCommand extends Command
{
    protected $signature = 'rdsco:license {--refresh : Check online now}';

    protected $description = 'Show the RDSCO license status of this installation';

    public function handle(LicenseManager $license): int
    {
        $status = $license->status($this->option('refresh'));

        $this->components->twoColumnDetail('Valid', $status->isValid() ? '<fg=green>yes</>' : '<fg=red>no</>');
        $this->components->twoColumnDetail('Reason', $status->reason ?? '-');
        $this->components->twoColumnDetail('Expires', $status->expiresAt() ?? 'never');
        $this->components->twoColumnDetail('Max users', (string) ($status->maxUsers() ?? 'unlimited'));
        $this->components->twoColumnDetail('Offline until', $status->offlineUntil > 0 ? date('Y-m-d H:i', $status->offlineUntil) : '-');
        $this->components->twoColumnDetail('Answer source', $status->offline ? 'cached (server unreachable)' : 'server');

        return $status->isValid() ? self::SUCCESS : self::FAILURE;
    }
}
