<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Player;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DepositService
{
    public function __construct(
        private readonly PaymentVerifier $verifier,
        private readonly WalletService $wallet,
        private readonly TelegramNotifier $notifier,
    ) {}

    /**
     * Verify a payment reference against the provider and, if genuine,
     * credit the player's wallet with the verified amount.
     *
     * @param  array{suffix?:string,phone?:string}  $opts
     *
     * @throws ValidationException
     */
    public function deposit(Player $player, string $provider, string $reference, array $opts = []): Transaction
    {
        $provider = strtolower(trim($provider));
        $reference = trim($reference);

        if ($player->isBanned()) {
            throw ValidationException::withMessages(['reference' => 'Your account is suspended.']);
        }
        if (! in_array($provider, (array) config('lottery.payments.providers', []), true)) {
            throw ValidationException::withMessages(['provider' => 'Unsupported payment provider.']);
        }
        if ($reference === '') {
            throw ValidationException::withMessages(['reference' => 'Enter the transaction reference.']);
        }

        // A reference can only ever be credited once.
        if (Transaction::where('provider', $provider)->where('reference', $reference)->exists()) {
            throw ValidationException::withMessages(['reference' => 'This reference has already been used.']);
        }

        $result = $this->verifier->verify($reference, $provider, $opts);
        if (! $result['success']) {
            throw ValidationException::withMessages(['reference' => $result['error'] ?? 'Verification failed.']);
        }

        $amount = (float) $result['amount'];
        $min = (float) config('lottery.payments.min_deposit', 10);
        if ($amount < $min) {
            throw ValidationException::withMessages(['reference' => "Minimum deposit is {$min} ".config('lottery.currency').'.']);
        }

        $this->assertPaidToUs($result);

        try {
            $tx = $this->wallet->credit($player->telegram_id, $amount, TransactionType::Deposit, [
                'provider' => $provider,
                'reference' => $reference,
                'note' => 'Verified '.$provider.' payment'.($result['payer'] ? ' from '.$result['payer'] : ''),
                'meta' => $result['raw'],
            ]);
        } catch (QueryException $e) {
            // Unique (provider, reference) backstop against a race.
            throw ValidationException::withMessages(['reference' => 'This reference has already been used.']);
        }

        $this->notifier->send(
            $player->telegram_id,
            "✅ <b>Deposit confirmed!</b>\n+{$amount} ".config('lottery.currency').
            "\n💼 New balance: ".number_format((float) $player->fresh()->balance, 2).' '.config('lottery.currency'),
            'deposit',
        );

        return $tx;
    }

    /** Ensure the verified payment actually landed in one of our accounts. */
    private function assertPaidToUs(array $result): void
    {
        $accounts = (array) config('lottery.payments.deposit_accounts', []);
        if ($accounts === []) {
            return; // not configured — skip the check (dev / trusting mode)
        }

        $haystack = Str::lower(($result['receiver'] ?? '').' '.($result['receiverAccount'] ?? ''));
        foreach ($accounts as $account) {
            if ($account !== '' && str_contains($haystack, Str::lower($account))) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'reference' => 'That payment was not made to our account. Send to the listed account and try again.',
        ]);
    }
}
