<?php

namespace App\Livewire;

use App\Enums\CustomerStatus;
use App\Enums\ErrorStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LicenseStatus;
use App\Models\AppError;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Payment;
use App\Models\UsageRecord;
use App\Models\User;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('داشبورد')]
class Dashboard extends Component
{
    public function render()
    {
        $monthStart = Carbon::instance(Verta::now()->startMonth()->datetime());
        $user = auth()->user();

        return view('livewire.dashboard', [
            'stats' => [
                'customers' => Customer::query()->where('status', CustomerStatus::Active)->count(),
                'licenses' => License::query()->where('status', LicenseStatus::Active)->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', today()))->count(),
                'expiring' => License::query()->where('status', LicenseStatus::Active)->whereBetween('expires_at', [today(), today()->addDays(30)])->count(),
                'openErrors' => AppError::query()->where('status', ErrorStatus::Open)->count(),
                'uninvoiced' => (int) UsageRecord::query()->whereNull('invoice_id')->where('billable', true)->sum('unit_price'),
                'receivable' => (int) Invoice::query()->where('status', '!=', InvoiceStatus::Void)->sum('total') - (int) Payment::query()->sum('amount'),
                'monthRequests' => UsageRecord::query()->where('created_at', '>=', $monthStart)->count(),
                'todayRequests' => UsageRecord::query()->where('created_at', '>=', today())->count(),
            ],
            'chart' => $this->dailyUsage(30),
            'expiringLicenses' => License::query()
                ->with(['customer', 'product'])
                ->where('status', LicenseStatus::Active)
                ->whereBetween('expires_at', [today(), today()->addDays(30)])
                ->orderBy('expires_at')
                ->limit(6)
                ->get(),
            'recentErrors' => AppError::query()
                ->with(['customer', 'product'])
                ->where('status', ErrorStatus::Open)
                ->latest('last_seen_at')
                ->limit(5)
                ->get(),
            'topCustomers' => UsageRecord::query()
                ->where('created_at', '>=', $monthStart)
                ->selectRaw('customer_id, COUNT(*) AS requests, SUM(CASE WHEN billable = 1 THEN unit_price ELSE 0 END) AS amount')
                ->groupBy('customer_id')
                ->orderByDesc('requests')
                ->limit(5)
                ->get()
                ->each(fn ($row) => $row->setRelation('customer', Customer::withTrashed()->find($row->customer_id))),
            'twoFactorDisabled' => $user instanceof User && ! $user->hasTwoFactorEnabled(),
        ]);
    }

    /**
     * Service calls per day, split by outcome, plus license verifications.
     *
     * @return list<array{date: Carbon, success: int, failed: int, error: int, verifications: int}>
     */
    private function dailyUsage(int $days): array
    {
        $from = today()->subDays($days - 1);

        $usage = UsageRecord::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) AS day, status, COUNT(*) AS total')
            ->groupBy('day', 'status')
            ->toBase()
            ->get()
            ->groupBy('day');

        $verifications = DB::table('license_daily_stats')
            ->where('day', '>=', $from->toDateString())
            ->selectRaw('day, SUM(verifications) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];

        for ($date = $from->copy(); $date->lte(today()); $date->addDay()) {
            $key = $date->toDateString();
            $rows = $usage->get($key, collect())->pluck('total', 'status');

            $series[] = [
                'date' => $date->copy(),
                'success' => (int) ($rows['success'] ?? 0),
                'failed' => (int) ($rows['failed'] ?? 0),
                'error' => (int) ($rows['error'] ?? 0),
                'verifications' => (int) ($verifications[$key] ?? 0),
            ];
        }

        return $series;
    }
}
