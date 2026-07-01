<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Settings;
use App\Services\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class PaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        session(['admin_authenticated' => true]);
    }

    public function test_saved_overrides_are_applied_to_config(): void
    {
        app(PaymentSettings::class)->save(
            providers: ['telebirr', 'cbe'],
            depositAccounts: "251925278350\nFuad Ahmed",
            minDeposit: 25,
            minWithdraw: 100,
            depositInstructions: 'Send to our account.',
        );

        app(PaymentSettings::class)->apply();

        $this->assertSame(['telebirr', 'cbe'], config('lottery.payments.providers'));
        $this->assertSame(['251925278350', 'Fuad Ahmed'], config('lottery.payments.deposit_accounts'));
        $this->assertSame(25.0, config('lottery.payments.min_deposit'));
        $this->assertSame(100.0, config('lottery.payments.min_withdraw'));
        $this->assertSame('Send to our account.', config('lottery.payments.deposit_instructions'));
    }

    public function test_unknown_providers_are_discarded(): void
    {
        app(PaymentSettings::class)->save(['telebirr', 'bogus'], '', 10, 50, '');
        app(PaymentSettings::class)->apply();

        $this->assertSame(['telebirr'], config('lottery.payments.providers'));
    }

    public function test_admin_can_save_payment_settings_via_the_page(): void
    {
        Livewire::test(Settings::class)
            ->set('providers', ['telebirr', 'mpesa'])
            ->set('depositAccounts', '251925278350, Fuad')
            ->set('minDeposit', 20)
            ->set('minWithdraw', 75)
            ->set('depositInstructions', 'Pay us.')
            ->call('savePayments')
            ->assertHasNoErrors();

        app(PaymentSettings::class)->apply();
        $this->assertSame(['telebirr', 'mpesa'], config('lottery.payments.providers'));
        $this->assertSame(20.0, config('lottery.payments.min_deposit'));
    }

    public function test_at_least_one_method_is_required(): void
    {
        Livewire::test(Settings::class)
            ->set('providers', [])
            ->call('savePayments')
            ->assertHasErrors('providers');
    }
}
