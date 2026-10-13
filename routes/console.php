<?php

use App\Services\Licensing\NonceGuard;
use Illuminate\Support\Facades\Schedule;

// Optional: only runs if a cron job calls "schedule:run". Without it,
// expired nonces are also pruned opportunistically during API requests.
Schedule::call(fn () => app(NonceGuard::class)->prune())->hourly()->name('prune-api-nonces');
