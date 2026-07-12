<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Player;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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

        // CBE looks a receipt up by transaction reference + the last 8 digits of
        // the paying account. Players give their full CBE account number (or paste
        // the SMS) and we derive the 8-digit suffix — simpler and less error-prone
        // than asking them to count digits themselves.
        if ($provider === 'cbe') {
            $digits = preg_replace('/\D+/', '', (string) ($opts['suffix'] ?? ''));
            if (strlen((string) $digits) < 8) {
                throw ValidationException::withMessages([
                    'reference' => 'Enter your CBE account number (or paste your full CBE SMS) so we can find the receipt.',
                ]);
            }
            $opts['suffix'] = substr((string) $digits, -8);
        }

        // A deposit reference can only ever be credited once (matches the
        // unique(provider, deposit_reference) index below).
        if (Transaction::where('provider', $provider)->where('deposit_reference', $reference)->exists()) {
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

    /**
     * Fallback when automatic verification fails or is unavailable: record the
     * player's claim (name, phone, pasted SMS) as a PENDING deposit for an
     * admin to review. No funds move until approveManual().
     *
     * @throws ValidationException
     */
    public function requestManual(Player $player, string $provider, string $name, string $phone, string $sms): Transaction
    {
        $provider = strtolower(trim($provider));
        $name = trim($name);
        $phone = trim($phone);
        $sms = trim($sms);

        if ($player->isBanned()) {
            throw ValidationException::withMessages(['reference' => 'Your account is suspended.']);
        }
        if (! in_array($provider, (array) config('lottery.payments.providers', []), true)) {
            throw ValidationException::withMessages(['provider' => 'Unsupported payment provider.']);
        }
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Enter your full name.']);
        }
        if ($phone === '') {
            throw ValidationException::withMessages(['phone' => 'Enter your phone number.']);
        }
        if ($sms === '') {
            throw ValidationException::withMessages(['reference' => 'Paste the deposit SMS you received.']);
        }

        // One open review per player — stops accidental double-submits.
        if (Transaction::where('telegram_id', $player->telegram_id)
            ->where('type', TransactionType::Deposit->value)
            ->where('status', TransactionStatus::Pending->value)->exists()) {
            throw ValidationException::withMessages(['reference' => 'You already have a deposit awaiting review.']);
        }

        // Reference (if we can parse one) doubles as the dedupe key so the same
        // SMS can't be credited twice — manually or automatically. The parser
        // falls back to the whole text; only keep a real code-like token.
        $reference = (string) ($this->parser->parse($sms, $provider)['reference'] ?? '');
        if ($reference === '' || strlen($reference) > 64 || preg_match('/\s/', $reference)) {
            $reference = null;
        }
        if ($reference !== null
            && Transaction::where('provider', $provider)->where('deposit_reference', $reference)->exists()) {
            throw ValidationException::withMessages(['reference' => 'This reference has already been used or is awaiting review.']);
        }

        try {
            $tx = Transaction::create([
                'telegram_id' => $player->telegram_id,
                'type' => TransactionType::Deposit,
                'status' => TransactionStatus::Pending,
                'amount' => $this->parser->amount($sms) ?? 0,
                'provider' => $provider,
                'reference' => $reference,
                'deposit_reference' => $reference,
                'note' => 'Manual deposit review',
                'meta' => ['manual' => true, 'name' => $name, 'phone' => $phone, 'sms' => $sms],
            ]);
        } catch (QueryException) {
            // Unique (provider, deposit_reference) backstop against a race.
            throw ValidationException::withMessages(['reference' => 'This reference has already been used or is awaiting review.']);
        }

        foreach ((array) config('lottery.admin_telegram_ids', []) as $adminId) {
            $this->notifier->send((int) $adminId, "📥 Manual deposit to review (#{$tx->id}) from {$player->name} — {$name}, {$phone}.", 'deposit_admin');
        }

        return $tx;
    }

    /** Admin confirmed the money arrived: credit the wallet with the real amount. */
    public function approveManual(Transaction $tx, float $amount): void
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter the amount to credit.']);
        }

        // Lock + re-check so two admins clicking at once can't credit twice.
        $claimed = DB::transaction(function () use ($tx, $amount): bool {
            $locked = Transaction::whereKey($tx->getKey())->lockForUpdate()->first();
            if (! $locked || $locked->type !== TransactionType::Deposit || $locked->status !== TransactionStatus::Pending) {
                return false;
            }

            $player = Player::whereKey($locked->telegram_id)->lockForUpdate()->firstOrFail();
            $player->balance = Money::toAmount(Money::toCents($player->balance) + Money::toCents($amount));
            $player->save();

            $locked->update([
                'status' => TransactionStatus::Completed,
                'amount' => $amount,
                'balance_after' => $player->balance,
                'processed_at' => now(),
            ]);

            return true;
        });

        if (! $claimed) {
            return;
        }

        $this->notifier->send(
            (int) $tx->telegram_id,
            "✅ <b>Deposit approved!</b>\n+{$amount} ".config('lottery.currency').' has been added to your balance.',
            'deposit',
        );
    }

    /** Admin rejected the claim; the reference is freed so the player can retry. */
    public function rejectManual(Transaction $tx, ?string $reason = null): void
    {
        $claimed = DB::transaction(function () use ($tx, $reason): bool {
            $locked = Transaction::whereKey($tx->getKey())->lockForUpdate()->first();
            if (! $locked || $locked->type !== TransactionType::Deposit || $locked->status !== TransactionStatus::Pending) {
                return false;
            }

            $locked->update([
                'status' => TransactionStatus::Rejected,
                'deposit_reference' => null, // free the reference for a retry
                'processed_at' => now(),
                'note' => 'Manual deposit review'.($reason ? ' — '.$reason : ''),
            ]);

            return true;
        });

        if (! $claimed) {
            return;
        }

        $this->notifier->send(
            (int) $tx->telegram_id,
            "❌ <b>Deposit declined</b>\nWe could not confirm your payment.".($reason ? "\nReason: ".$reason : '').' Contact support if you believe this is a mistake.',
            'deposit',
        );
    }

    /** Ensure the verified payment actually landed in one of our accounts. */
    private function assertPaidToUs(array $result): void
    {
        $accounts = array_filter((array) config('lottery.payments.deposit_accounts', []));
        if ($accounts === []) {
            // Without configured receiving accounts we cannot prove a payment was
            // made to *us*, so any genuine third-party receipt would be credited.
            // Fail CLOSED in production (set DEPOSIT_ACCOUNTS to enable deposits);
            // stay open in local/dev so the flow is testable without real accounts.
            if (app()->environment('production')) {
                Log::error('Deposit blocked: DEPOSIT_ACCOUNTS is not configured, so "paid to us" cannot be verified.');

                throw ValidationException::withMessages([
                    'reference' => 'Deposits are temporarily unavailable. Please contact support.',
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
