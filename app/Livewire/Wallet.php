<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\DepositService;
use App\Services\WithdrawalService;
use App\Telegram\TelegramAuth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Wallet extends Component
{
    // Deposit form
    public string $provider = 'telebirr';

    public string $reference = '';

    public string $cbeAccount = '';   // player's full CBE account number (we derive the suffix)

    // Payer identity — sent to the verifier as a lookup hint and kept on the
    // claim if it falls back to manual admin review. Prefilled in mount().
    public string $payerName = '';

    public string $payerPhone = '';

    // Withdraw form. Nullable so clearing the input hydrates to null instead of
    // throwing a 500 on a typed float; withdraw() casts and the service validates.
    public ?float $amount = null;

    public string $payoutProvider = 'telebirr';

    public string $payoutAccount = '';

    private function auth(): TelegramAuth
    {
        return app(TelegramAuth::class);
    }

    public function mount(): void
    {
        $player = $this->auth()->player();
        $this->payerName = (string) ($player?->name ?? '');
        $this->payerPhone = (string) ($player?->phone ?? '');
    }

    /** One button: verify automatically, fall back to manual admin review. */
    public function deposit(DepositService $deposits): void
    {
        $player = $this->auth()->player();
        if ($player === null) {
            $this->dispatch('toast', message: __('Open from Telegram to deposit.'), type: 'error');

            return;
        }

        try {
            $result = $deposits->depositWithFallback($player, $this->provider, $this->reference, $this->payerName, $this->payerPhone, [
                'suffix' => $this->cbeAccount ?: null,
                'phone' => $this->payerPhone ?: null,
            ]);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first(), type: 'error');

            return;
        }

        $this->reset('reference', 'cbeAccount');

        if ($result['status'] === 'credited') {
            $this->dispatch('haptic', type: 'notification', style: 'success');
            $this->dispatch('toast', message: __('Deposit confirmed: +:amount :currency', ['amount' => $result['tx']->amount, 'currency' => config('lottery.currency')]), type: 'success');

            return;
        }

        $this->dispatch('haptic', type: 'notification', style: 'warning');
        $this->dispatch('toast', message: __('Your deposit is being reviewed — please wait until it is approved.'), type: 'info');
    }

    public function withdraw(WithdrawalService $withdrawals): void
    {
        $player = $this->auth()->player();
        if ($player === null) {
            $this->dispatch('toast', message: __('Open from Telegram to withdraw.'), type: 'error');

            return;
        }

        try {
            $withdrawals->request($player, (float) $this->amount, $this->payoutProvider, $this->payoutAccount);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first(), type: 'error');

            return;
        }

        $this->reset('amount', 'payoutAccount');
        $this->dispatch('haptic', type: 'notification', style: 'warning');
        $this->dispatch('toast', message: __('Withdrawal requested — pending payout.'), type: 'success');
    }

    public function render()
    {
        $auth = $this->auth();
        $player = $auth->player();

        $transactions = $player
            ? Transaction::where('telegram_id', $auth->id())->latest('id')->limit(30)->get()
            : collect();

        $withdrawals = $player
            ? Transaction::where('telegram_id', $auth->id())
                ->where('type', TransactionType::Withdrawal->value)
                ->latest('id')->limit(5)->get()
            : collect();

        $providers = (array) config('lottery.payments.providers', []);

        // A manual claim awaiting admin approval — shown as a banner so the
        // player knows to wait instead of resubmitting.
        $pendingDeposit = $player !== null && Transaction::where('telegram_id', $auth->id())
            ->where('type', TransactionType::Deposit->value)
            ->where('status', TransactionStatus::Pending->value)
            ->exists();

        // Operator's receiving accounts to display — only for enabled providers.
        $depositTargets = array_values(array_filter(
            (array) config('lottery.payments.deposit_account_list', []),
            fn ($a): bool => is_array($a) && in_array($a['provider'] ?? '', $providers, true),
        ));

        return view('livewire.wallet', [
            'player' => $player,
            'balance' => (float) ($player->balance ?? 0),
            'transactions' => $transactions,
            'withdrawals' => $withdrawals,
            'providers' => $providers,
            'depositTargets' => $depositTargets,
            'currency' => config('lottery.currency', 'ETB'),
            'minDeposit' => (float) config('lottery.payments.min_deposit', 10),
            'minWithdraw' => (float) config('lottery.payments.min_withdraw', 50),
            'instructions' => config('lottery.payments.deposit_instructions'),
            'pendingDeposit' => $pendingDeposit,
        ]);
    }
}
