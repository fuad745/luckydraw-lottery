<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\AdminCredentials;
use App\Services\PaymentSettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
final class Settings extends Component
{
    public string $username = '';

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public string $flash = '';

    // Payment settings
    /** @var array<int,string> */
    public array $providers = [];

    public string $depositAccounts = '';

    // Nullable so clearing the input doesn't 500 on a typed float; seeded in mount().
    public ?float $minDeposit = null;

    public ?float $minWithdraw = null;

    public string $depositInstructions = '';

    public string $payFlash = '';

    public function mount(AdminCredentials $credentials, PaymentSettings $payments): void
    {
        $this->username = $credentials->username();

        $snap = $payments->snapshot();
        $this->providers = $snap['providers'];
        $this->depositAccounts = $snap['deposit_accounts'];
        $this->minDeposit = $snap['min_deposit'];
        $this->minWithdraw = $snap['min_withdraw'];
        $this->depositInstructions = $snap['deposit_instructions'];
    }

    public function savePayments(PaymentSettings $payments): void
    {
        $this->validate([
            'providers' => ['required', 'array', 'min:1'],
            'providers.*' => ['in:'.implode(',', PaymentSettings::SUPPORTED)],
            'depositAccounts' => ['nullable', 'string', 'max:1000'],
            'minDeposit' => ['required', 'numeric', 'min:0'],
            'minWithdraw' => ['required', 'numeric', 'min:0'],
            'depositInstructions' => ['nullable', 'string', 'max:1000'],
        ], [
            'providers.required' => 'Enable at least one payment method.',
        ]);

        $payments->save(
            $this->providers,
            $this->depositAccounts,
            (float) $this->minDeposit,
            (float) $this->minWithdraw,
            $this->depositInstructions,
        );

        $this->payFlash = 'Payment settings saved.';
    }

    public function save(AdminCredentials $credentials): void
    {
        $this->validate([
            'username' => ['required', 'string', 'min:3', 'max:64'],
            'current_password' => ['required', 'string'],
            'new_password' => ['nullable', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);

        // Re-authenticate: the current password must be correct to change anything,
        // so a hijacked session can't silently lock out the owner.
        if (! $credentials->verifyPassword($this->current_password)) {
            $this->addError('current_password', 'Current password is incorrect.');

            return;
        }

        $changedPassword = $this->new_password !== '';
        $credentials->update($this->username, $changedPassword ? $this->new_password : null);

        $this->reset('current_password', 'new_password', 'new_password_confirmation');
        $this->flash = $changedPassword ? 'Username and password updated.' : 'Username updated.';
    }

    public function render()
    {
        return view('livewire.admin.settings', [
            'supportedProviders' => PaymentSettings::SUPPORTED,
        ]);
    }
}
