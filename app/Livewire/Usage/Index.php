<?php

namespace App\Livewire\Usage;

use App\Enums\UsageStatus;
use App\Http\Controllers\Api\V1\ShahkarController;
use App\Models\Customer;
use App\Models\Service;
use App\Models\UsageRecord;
use App\Rules\IranianNationalCode;
use App\Services\Shahkar\ShahkarResponseCodes;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('مصرف سرویس‌ها')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $customer = '';

    #[Url(except: '')]
    public string $license = '';

    #[Url(except: '')]
    public string $service = '';

    #[Url(except: '')]
    public string $status = '';

    /** '' | uninvoiced | invoiced | free */
    #[Url(except: '')]
    public string $billing = '';

    /** National code or mobile number. */
    #[Url(except: '')]
    public string $subject = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['customer', 'license', 'service', 'status', 'billing', 'subject', 'from', 'to']);
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        $query = $this->query()->with(['customer', 'license', 'service', 'invoice']);
        $codes = app(ShahkarResponseCodes::class);

        return response()->streamDownload(function () use ($query, $codes): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($output, ['شناسه', 'تاریخ', 'مشتری', 'کلید لایسنس', 'سرویس', 'کد ملی', 'شماره همراه', 'نتیجه', 'کد پاسخ', 'توضیح', 'مبلغ (ریال)', 'فاکتور', 'شماره پیگیری', 'مدت (ms)']);

            $query->chunkById(1000, function ($records) use ($output, $codes): void {
                foreach ($records as $record) {
                    fputcsv($output, [
                        $record->id,
                        Jalali::dateTime($record->created_at),
                        $record->customer?->display_name,
                        $record->license?->key_id,
                        $record->service?->name,
                        $record->national_code,
                        $record->mobile,
                        $record->status->label(),
                        $record->upstream_code,
                        $record->upstream_message ?? ($record->upstream_code !== null ? $codes->map($record->upstream_code)['message'] : ''),
                        $record->billable ? $record->unit_price : 0,
                        $record->invoice?->number,
                        $record->reference,
                        $record->duration_ms,
                    ]);
                }
            });

            fclose($output);
        }, 'usage-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The subject search as [hash column, normalized value]: a valid national
     * code, otherwise anything that normalizes to 09xxxxxxxxx is a mobile.
     *
     * @return array{0: string, 1: string}|null
     */
    private function subjectFilter(): ?array
    {
        $value = preg_replace('/\D+/', '', Jalali::toLatinDigits($this->subject)) ?? '';

        if ($value === '') {
            return null;
        }

        // A valid 10-digit national code wins over a mobile written without its leading zero.
        if (strlen($value) <= 10 && IranianNationalCode::isValid(str_pad($value, 10, '0', STR_PAD_LEFT))) {
            return ['national_code_hash', str_pad($value, 10, '0', STR_PAD_LEFT)];
        }

        $mobile = ShahkarController::normalizeMobile($value);

        return preg_match('/^09\d{9}$/', $mobile) === 1
            ? ['mobile_hash', $mobile]
            : ['national_code_hash', str_pad($value, 10, '0', STR_PAD_LEFT)];
    }

    /**
     * @return Builder<UsageRecord>
     */
    private function query(): Builder
    {
        $from = Jalali::parse($this->from);
        $to = Jalali::parse($this->to)?->endOfDay();

        return UsageRecord::query()
            ->when($this->customer !== '', fn ($query) => $query->where('customer_id', $this->customer))
            ->when($this->license !== '', fn ($query) => $query->where('license_id', $this->license))
            ->when($this->service !== '', fn ($query) => $query->where('service_id', $this->service))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->billing === 'uninvoiced', fn ($query) => $query->where('billable', true)->whereNull('invoice_id'))
            ->when($this->billing === 'invoiced', fn ($query) => $query->whereNotNull('invoice_id'))
            ->when($this->billing === 'free', fn ($query) => $query->where('billable', false))
            ->when($this->subjectFilter() !== null, function ($query): void {
                [$column, $value] = $this->subjectFilter();
                $query->where($column, UsageRecord::lookupHash($value));
            })
            ->when($from !== null, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->where('created_at', '<=', $to));
    }

    public function render(ShahkarResponseCodes $codes)
    {
        $totals = $this->query()
            ->selectRaw('COUNT(*) AS requests, SUM(CASE WHEN billable = 1 THEN 1 ELSE 0 END) AS billable_requests, SUM(CASE WHEN billable = 1 THEN unit_price ELSE 0 END) AS amount')
            ->first();

        return view('livewire.usage.index', [
            'records' => $this->query()->with(['customer', 'license', 'service', 'invoice'])->latest('id')->paginate(50),
            'totals' => $totals,
            'customers' => Customer::withTrashed()->orderBy('name')->get()->mapWithKeys(fn (Customer $customer) => [$customer->id => $customer->display_name]),
            'services' => Service::query()->orderBy('id')->pluck('name', 'id'),
            'statuses' => UsageStatus::options(),
            'codes' => $codes,
        ]);
    }
}
