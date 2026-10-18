<?php

namespace Tests\Feature\Panel;

use App\Enums\InvoiceStatus;
use App\Enums\LicenseStatus;
use App\Enums\UsageStatus;
use App\Livewire\Account\Profile;
use App\Livewire\Customers\Form as CustomerForm;
use App\Livewire\Customers\Show as CustomerShow;
use App\Livewire\Invoices\Create as InvoiceCreate;
use App\Livewire\Licenses\Form as LicenseForm;
use App\Livewire\Licenses\Show as LicenseShow;
use App\Livewire\Payments\Index as Payments;
use App\Livewire\Settings\Shahkar as ShahkarSettingsPage;
use App\Livewire\System\Health;
use App\Livewire\Usage\Index;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;
use App\Models\UsageRecord;
use App\Services\Billing\UsageRecorder;
use App\Services\Licensing\LicenseSnapshot;
use App\Services\Shahkar\ShahkarCircuit;
use App\Support\Jalali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PanelActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    public function test_a_customer_can_be_created(): void
    {
        Livewire::test(CustomerForm::class)
            ->set('name', 'علی رضایی')
            ->set('company_name', 'شرکت نمونه')
            ->set('mobile', '09121234567')
            ->call('save')
            ->assertHasNoErrors();

        $customer = Customer::query()->sole();
        $this->assertSame('شرکت نمونه', $customer->display_name);
        $this->assertTrue(ActivityLog::query()->where('action', 'customer.created')->exists());
    }

    public function test_the_shahkar_circuit_can_be_reopened_from_the_health_page(): void
    {
        config(['license.shahkar.circuit_threshold' => 1]);
        $circuit = app(ShahkarCircuit::class);
        $circuit->recordFailure();
        $this->assertTrue($circuit->isOpen());

        Livewire::test(Health::class)
            ->assertSee('موقتاً متوقف است')
            ->call('resetCircuit')
            ->assertSee('فعال');

        $this->assertFalse($circuit->isOpen());
    }

    public function test_usage_can_be_searched_by_national_code_or_mobile(): void
    {
        $license = License::factory()->create();
        $service = Service::byCode(Service::SHAHKAR);
        $recorder = app(UsageRecorder::class);
        $snapshot = LicenseSnapshot::fromModel($license->load(['customer', 'product', 'services']));

        $recorder->record($snapshot, $service, UsageStatus::Success, true, reference: 'ref-a', nationalCode: '0499370899', mobile: '09121111111');
        $recorder->record($snapshot, $service, UsageStatus::Failed, true, reference: 'ref-b', nationalCode: '0012345679', mobile: '09122222222');

        Livewire::test(Index::class)
            ->assertSee('0499370899')
            ->assertSee('09122222222')
            ->set('subject', '۰۴۹۹۳۷۰۸۹۹')
            ->assertSee('ref-a')
            ->assertDontSee('ref-b')
            ->set('subject', '+98 912 222 2222')
            ->assertSee('ref-b')
            ->assertDontSee('ref-a');
    }

    public function test_every_license_gets_a_unique_six_digit_code_and_keeps_it(): void
    {
        $licenses = License::factory()->count(20)->create();
        $codes = $licenses->pluck('code');

        $this->assertCount(20, $codes->unique());
        $codes->each(fn (string $code) => $this->assertMatchesRegularExpression('/^[1-9]\d{5}$/', $code));

        $license = $licenses->first();

        Livewire::test(LicenseForm::class, ['license' => $license])
            ->set('app_url', 'https://fishino.example.com/')
            ->call('save')
            ->assertHasNoErrors();

        $license->refresh();
        $this->assertSame('https://fishino.example.com', $license->app_url);
        $this->assertSame($codes->first(), $license->code, 'Editing must not change the code.');

        Livewire::test(LicenseForm::class, ['license' => $license])
            ->set('app_url', 'not a url')
            ->call('save')
            ->assertHasErrors('app_url');

        $this->assertSame($license->id, License::query()->search($license->code)->sole()->id);
    }

    public function test_customer_contact_details_are_saved(): void
    {
        Livewire::test(CustomerForm::class)
            ->set('type', 'legal')
            ->set('name', 'مریم احمدی')
            ->set('national_id', '10101234567')
            ->set('registration_number', '123456')
            ->set('postal_code', '۱۲۳۴۵۶۷۸۹۰')
            ->set('website', 'example.com')
            ->set('contact_channels', ['telegram', 'email'])
            ->call('save')
            ->assertHasNoErrors();

        $customer = Customer::query()->sole();
        $this->assertSame('123456', $customer->registration_number);
        $this->assertSame('شناسه ملی', $customer->nationalIdLabel());
        $this->assertSame('1234567890', $customer->postal_code);
        $this->assertSame('https://example.com', $customer->websiteUrl());
        $this->assertSame(['telegram', 'email'], $customer->contact_channels);

        Livewire::test(CustomerForm::class, ['customer' => $customer])
            ->assertSet('contact_channels', ['telegram', 'email'])
            ->set('postal_code', '123')
            ->set('contact_channels', ['fax'])
            ->set('national_id', '1234')
            ->call('save')
            ->assertHasErrors(['postal_code', 'contact_channels.0', 'national_id']);

        // A natural person needs a valid 10-digit national code and has no registration number.
        Livewire::test(CustomerForm::class, ['customer' => $customer])
            ->set('type', 'individual')
            ->set('national_id', '0499370899')
            ->call('save')
            ->assertHasNoErrors();

        $customer->refresh();
        $this->assertSame('کد ملی', $customer->nationalIdLabel());
        $this->assertNull($customer->registration_number);
    }

    public function test_contact_log_entries_can_be_added_and_removed(): void
    {
        $customer = Customer::factory()->create();

        $component = Livewire::test(CustomerShow::class, ['customer' => $customer])
            ->set('contactDate', '۱۴۰۵/۰۷/۲۰')
            ->set('contactChannel', 'bale')
            ->set('contactSummary', 'درباره تمدید لایسنس صحبت شد.')
            ->call('addContact')
            ->assertHasNoErrors()
            ->assertSet('contactSummary', '')
            ->assertSee('درباره تمدید لایسنس صحبت شد.');

        $contact = $customer->contacts()->sole();
        $this->assertSame('2026-10-12', $contact->contacted_on->toDateString());
        $this->assertSame('bale', $contact->channel->value);

        $component->set('contactSummary', '')->call('addContact')->assertHasErrors('contactSummary');

        $component->call('deleteContact', $contact->id);
        $this->assertSame(0, $customer->contacts()->count());
    }

    public function test_a_license_is_issued_with_restrictions_and_its_secret_is_shown_once(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $shahkar = Service::byCode(Service::SHAHKAR);

        Livewire::withQueryParams(['customer' => $customer->id])
            ->test(LicenseForm::class)
            ->assertSet('customer_id', (string) $customer->id)
            ->set('product_id', (string) $product->id)
            ->set('allowed_domains', "https://www.crm.example.com/\n*.example.org")
            ->set('allowed_ips', "185.10.20.30\n10.0.0.0/8")
            ->set('features', "reports=true\nmax_branches=3\nplan=pro")
            ->set('expires_at', '1406/01/15')
            ->set('services', [(string) $shahkar->id])
            ->call('save')
            ->assertHasNoErrors();

        $license = License::query()->sole();
        $this->assertSame(['crm.example.com', '*.example.org'], $license->allowed_domains);
        $this->assertSame(['185.10.20.30', '10.0.0.0/8'], $license->allowed_ips);
        $this->assertSame(['reports' => true, 'max_branches' => 3, 'plan' => 'pro'], $license->features);
        $this->assertSame('1406/01/15', Jalali::date($license->expires_at));
        $this->assertTrue($license->services->contains($shahkar));
        $this->assertSame($license->secret, session('license_secret'));
    }

    public function test_invalid_license_input_is_rejected(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        Livewire::test(LicenseForm::class)
            ->set('customer_id', (string) $customer->id)
            ->set('product_id', (string) $product->id)
            ->set('allowed_ips', '999.1.1.1')
            ->call('save')
            ->assertHasErrors('allowed_ips');

        Livewire::test(LicenseForm::class)
            ->set('customer_id', (string) $customer->id)
            ->set('product_id', (string) $product->id)
            ->set('starts_at', '1405/06/01')
            ->set('expires_at', '1405/01/01')
            ->call('save')
            ->assertHasErrors('expires_at');

        $this->assertSame(0, License::query()->count());
    }

    public function test_license_actions_rotate_extend_and_change_status(): void
    {
        $license = License::factory()->create(['expires_at' => today()->addDays(10)]);
        $oldSecret = $license->secret;

        $component = Livewire::test(LicenseShow::class, ['license' => $license])
            ->call('rotateSecret');

        $newSecret = $component->get('secret');
        $this->assertNotSame($oldSecret, $newSecret);
        $this->assertSame($newSecret, $license->fresh()->secret);

        $component->call('extend', 12);
        $this->assertTrue($license->fresh()->expires_at->isSameDay(today()->addDays(10)->addMonthsNoOverflow(12)));

        $component->call('setStatus', 'suspended');
        $this->assertSame(LicenseStatus::Suspended, $license->fresh()->status);
    }

    public function test_licenses_with_usage_cannot_be_deleted(): void
    {
        $license = License::factory()->create();
        UsageRecord::query()->create([
            'customer_id' => $license->customer_id,
            'license_id' => $license->id,
            'service_id' => Service::byCode(Service::SHAHKAR)->id,
            'status' => UsageStatus::Success,
        ]);

        Livewire::test(LicenseShow::class, ['license' => $license])->call('delete');

        $this->assertModelExists($license);
    }

    public function test_invoicing_and_payment_flow_from_the_panel(): void
    {
        $license = License::factory()->create();
        Service::query()->where('code', Service::SHAHKAR)->update(['unit_price' => 1000]);

        Livewire::test(CustomerShow::class, ['customer' => $license->customer])
            ->set('prices.'.Service::byCode(Service::SHAHKAR)->id, '800')
            ->call('savePrices');

        $this->assertSame(800, Service::byCode(Service::SHAHKAR)->priceFor($license->customer_id));

        foreach (range(1, 5) as $index) {
            UsageRecord::query()->create([
                'customer_id' => $license->customer_id,
                'license_id' => $license->id,
                'service_id' => Service::byCode(Service::SHAHKAR)->id,
                'status' => UsageStatus::Success,
                'billable' => true,
                'unit_price' => 800,
            ]);
        }

        Livewire::test(InvoiceCreate::class)
            ->set('customer_id', (string) $license->customer_id)
            ->assertSee('4,000')
            ->set('discount', '500')
            ->call('issue');

        $invoice = Invoice::query()->sole();
        $this->assertSame(3_500, $invoice->total);

        Livewire::withQueryParams(['invoice' => $invoice->id, 'create' => 1])
            ->test(Payments::class)
            ->assertSet('showForm', true)
            ->assertSet('amount', '3500')
            ->set('paid_on', Jalali::date(today()))
            ->set('reference', 'TR-1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_shahkar_secrets_are_encrypted_and_never_sent_back_to_the_browser(): void
    {
        $component = Livewire::test(ShahkarSettingsPage::class)
            ->set('values.shahkar_username', 'gw-user')
            ->set('values.shahkar_password', 'super-secret')
            ->set('values.shahkar_rid', '0501')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('values.shahkar_password', '');

        $this->assertSame('super-secret', Setting::get('shahkar.password'));
        $this->assertNotSame('super-secret', Setting::query()->find('shahkar.password')->value);

        // Leaving a secret empty keeps the stored value.
        $component->set('values.shahkar_username', 'gw-user-2')->call('save');
        $this->assertSame('super-secret', Setting::get('shahkar.password'));
        $this->assertSame('gw-user-2', Setting::get('shahkar.username'));
    }

    public function test_two_factor_can_be_enabled_from_the_profile(): void
    {
        $component = Livewire::test(Profile::class)->call('startTwoFactor');
        $secret = $component->get('pendingSecret');

        $component->set('confirmationCode', '000000')->call('confirmTwoFactor')->assertHasErrors('confirmationCode');

        $component
            ->set('confirmationCode', (new Google2FA)->getCurrentOtp($secret))
            ->call('confirmTwoFactor')
            ->assertHasNoErrors();

        $this->assertTrue(auth()->user()->fresh()->hasTwoFactorEnabled());
        $this->assertCount(8, $component->get('recoveryCodes'));
    }
}
