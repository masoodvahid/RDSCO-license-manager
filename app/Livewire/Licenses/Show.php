<?php

namespace App\Livewire\Licenses;

use App\Enums\LicenseStatus;
use App\Models\ActivityLog;
use App\Models\License;
use App\Services\Licensing\SigningKey;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Show extends Component
{
    public License $license;

    /** Plain secret, only right after creation or rotation. */
    public ?string $secret = null;

    public function mount(License $license): void
    {
        $this->license = $license;
        $this->secret = session('license_secret');
    }

    public function rotateSecret(): void
    {
        $this->secret = $this->license->rotateSecret();

        ActivityLog::record('license.secret_rotated', "تعویض کلید محرمانه لایسنس {$this->license->key_id}", $this->license);
        $this->dispatch('toast', type: 'warning', message: 'کلید محرمانه تعویض شد. تا وقتی برنامه مشتری با کلید جدید به‌روز نشود، درخواست‌هایش رد می‌شود.');
    }

    public function setStatus(string $status): void
    {
        $status = LicenseStatus::from($status);
        $this->license->update(['status' => $status]);

        ActivityLog::record('license.status', "تغییر وضعیت لایسنس {$this->license->key_id} به «{$status->label()}»", $this->license);
        $this->dispatch('toast', type: 'success', message: "وضعیت لایسنس «{$status->label()}» شد.");
    }

    public function extend(int $months): void
    {
        $months = max(1, min(60, $months));
        $base = $this->license->expires_at === null || $this->license->expires_at->lt(today())
            ? today()->subDay()
            : $this->license->expires_at;

        $this->license->update(['expires_at' => $base->copy()->addMonthsNoOverflow($months)]);

        ActivityLog::record('license.extended', "تمدید لایسنس {$this->license->key_id} به مدت {$months} ماه", $this->license);
        $this->dispatch('toast', type: 'success', message: "لایسنس {$months} ماه تمدید شد.");
    }

    public function delete(): void
    {
        if ($this->license->usageRecords()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'این لایسنس سابقه مصرف دارد و برای حفظ سوابق مالی قابل حذف نیست. آن را باطل کنید.');

            return;
        }

        $keyId = $this->license->key_id;
        $this->license->delete();

        ActivityLog::record('license.deleted', "حذف لایسنس {$keyId}");
        session()->flash('toast', ['type' => 'success', 'message' => 'لایسنس حذف شد.']);
        $this->redirectRoute('licenses.index', navigate: true);
    }

    public function render(SigningKey $signingKey)
    {
        $this->license->load(['customer', 'product', 'services']);

        $stats = DB::table('license_daily_stats')
            ->where('license_id', $this->license->id)
            ->where('day', '>=', today()->subDays(29)->toDateString())
            ->get()
            ->keyBy(fn ($row) => substr((string) $row->day, 0, 10));

        $days = [];

        for ($date = today()->subDays(29); $date->lte(today()); $date->addDay()) {
            $row = $stats->get($date->toDateString());
            $days[] = ['date' => $date->copy(), 'verifications' => (int) ($row->verifications ?? 0), 'rejections' => (int) ($row->rejections ?? 0)];
        }

        return view('livewire.licenses.show', [
            'days' => $days,
            'usage' => $this->license->usageRecords()
                ->selectRaw('status, COUNT(*) AS total, SUM(CASE WHEN billable = 1 THEN unit_price ELSE 0 END) AS amount')
                ->groupBy('status')
                ->get()
                ->keyBy(fn ($row) => $row->status->value),
            'recentErrors' => $this->license->errors()->latest('last_seen_at')->limit(5)->get(),
            'publicKey' => $signingKey->publicKey(),
            'statuses' => LicenseStatus::cases(),
        ])->title('لایسنس '.$this->license->key_id);
    }
}
