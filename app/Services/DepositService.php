<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Player;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DepositService
{
    public function __construct(
        private readonly PaymentVerifier $verifier,
        private readonly WalletService $wallet,
        private readonly TelegramNotifier $notifier,
        private readonly PaymentMessageParser $parser,
    ) {}

    /**
     * Verify a payment reference against the provider and, if genuine,
     * credit the player's wallet with the verified amount.
     *
     * @param  array{suffix?:string,phone?:string}  $opts
     *
     * @throws ValidationException
     */
    public function deposit(Player $player, string $provider, string $reference, array $opts = [], bool $notify = true): Transaction
    {
        $provider = strtolower(trim($provider));

        if ($player->isBanned()) {
            throw ValidationException::withMessages(['reference' => 'Your account is suspended.']);
        }

        // The player may paste a whole payment SMS or a receipt link — pull the
        // reference (and any CBE suffix / payer phone) out of it. A strongly
        // detected provider (from a receipt link or an FT reference) corrects a
        // mis-selected payment method.
        $parsed = $this->parser->parse($reference, $provider);
        $reference = trim((string) ($parsed['reference'] ?? $reference));
        if ($parsed['provider'] !== null) {
            $provider = $parsed['provider'];
        }
        $opts['suffix'] = ($opts['suffix'] ?? null) ?: $parsed['suffix'];
        $opts['phone'] = ($opts['phone'] ?? null) ?: $parsed['phone'];

        if (! in_array($provider, (array) config('lottery.payments.providers', []), true)) {
            throw ValidationException::withMessages(['provider' => 'Unsupported payment provider.']);
        }
        if ($reference === '') {
            throw ValidationException::withMessages(['reference' => 'Enter the transaction number or paste your payment SMS.']);
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

        // The DM deposit flow replies synchronously, so it opts out of the
        // queued confirmation to avoid a duplicate message.
        if ($notify) {
            $this->notifier->send(
                $player->telegram_id,
                "✅ <b>Deposit confirmed!</b>\n+{$amount} ".config('lottery.currency').
                "\n💼 New balance: ".number_format((float) $player->fresh()->balance, 2).' '.config('lottery.currency'),
                'deposit',
            );
        }

        return $tx;
    }

    /** Ensure the verified payment actually landed in one of our accounts. */
    private function assertPaidToUs(array $result): void
    {
        $accounts = array_filter((array) config('lottery.payments.deposit_accounts', []));
        if ($accounts === []) {
            // Not configured — any verified payment is credited regardless of payee.
            // Acceptable in dev, but a real fail-open risk in production: surface it.
            if (app()->environment('production')) {
                Log::warning('Deposit credited without a receiver check — set DEPOSIT_ACCOUNTS to enforce "paid to us".', [
                    'receiver' => $result['receiver'] ?? null,
                    'receiverAccount' => $result['receiverAccount'] ?? null,
                ]);
            }

            return;
        }

        $receiver = Str::lower(($result['receiver'] ?? '').' '.($result['receiverAccount'] ?? ''));
        $receiverDigits = preg_replace('/\D+/', '', (string) ($result['receiverAccount'] ?? ''));

        // Compare the trailing subscriber digits so a local phone (0912…) still
        // matches its international form (251912…) despite the differing prefix.
        $tail = static fn (string $d): string => strlen($d) >= 9 ? substr($d, -9) : $d;

        foreach ($accounts as $account) {
            $account = (string) $account;
            if ($account === '') {
                continue;
            }

            $accountDigits = preg_replace('/\D+/', '', $account);
            $digitMatch = $accountDigits !== '' && $receiverDigits !== '' && $tail($accountDigits) === $tail($receiverDigits);

            if (str_contains($receiver, Str::lower($account)) || $digitMatch) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'reference' => 'That payment was not made to our account. Send to the listed account and try again.',
        ]);
    }
}
