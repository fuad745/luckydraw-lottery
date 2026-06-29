<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Transaction;
use App\Services\DepositService;
use App\Services\PaymentVerifier;
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

    public string $suffix = '';       // CBE account suffix

    public string $payerPhone = '';   // CBE Birr / M-Pesa

    // Withdraw form
    public float $amount = 0;

    public string $payoutProvider = 'telebirr';

    public string $payoutAccount = '';

    private function auth(): TelegramAuth
    {
        return app(TelegramAuth::class);
    }

    public function deposit(DepositService $deposits): void
    {
        $player = $this->auth()->player();
        if ($player === null) {
            $this->dispatch('toast', message: 'Open from Telegram to deposit.');

            return;
        }

        try {
            $tx = $deposits->deposit($player, $this->provider, $this->reference, [
                'suffix' => $this->suffix ?: null,
                'phone' => $this->payerPhone ?: null,
            ]);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('reference', 'suffix', 'payerPhone');
        $this->dispatch('haptic', type: 'notification', style: 'success');
        $this->dispatch('toast', message: "Deposit confirmed: +{$tx->amount} ".config('lottery.currency'));
    }

    public function withdraw(WithdrawalService $withdrawals): void
    {
        $player = $this->auth()->player();
        if ($player === null) {
            $this->dispatch('toast', message: 'Open from Telegram to withdraw.');

            return;
        }

        try {
            $withdrawals->request($player, (float) $this->amount, $this->payoutProvider, $this->payoutAccount);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('amount', 'payoutAccount');
        $this->dispatch('haptic', type: 'notification', style: 'warning');
        $this->dispatch('toast', message: 'Withdrawal requested — pending payout.');
    }

    public function render()
    {
        $auth = $this->auth();
        $player = $auth->player();

        $transactions = $player
            ? Transaction::where('telegram_id', $auth->id())->latest('id')->limit(30)->get()
            : collect();

        return view('livewire.wallet', [
            'player' => $player,
            'balance' => (float) ($player->balance ?? 0),
            'transactions' => $transactions,
            'providers' => (array) config('lottery.payments.providers', []),
            'currency' => config('lottery.currency', 'ETB'),
            'minDeposit' => (float) config('lottery.payments.min_deposit', 10),
            'minWithdraw' => (float) config('lottery.payments.min_withdraw', 50),
            'instructions' => config('lottery.payments.deposit_instructions'),
            'verifyReady' => app(PaymentVerifier::class)->configured(),
        ]);
    }
}
