<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Client for the verify.leul.et payment-verification API
 * (https://verifyapi.leulzenebe.pro). Confirms that a real Telebirr / CBE /
 * CBE Birr / M-Pesa transaction exists for a given reference.
 */
final class PaymentVerifier
{
    public function configured(): bool
    {
        return ! empty(config('lottery.payments.verify_key'));
    }

    /**
     * @param  array{suffix?:string,phone?:string}  $opts
     * @return array{success:bool,amount:float,payer:?string,receiver:?string,receiverAccount:?string,raw:array,error:?string}
     */
    public function verify(string $reference, string $provider, array $opts = []): array
    {
        $fail = fn (string $msg): array => [
            'success' => false, 'amount' => 0.0, 'payer' => null,
            'receiver' => null, 'receiverAccount' => null, 'raw' => [], 'error' => $msg,
        ];

        if (! $this->configured()) {
            return $fail('Payment verification is not configured (set VERIFY_API_KEY).');
        }

        $url = rtrim((string) config('lottery.payments.verify_url'), '/').'/verify';
        $body = ['reference' => trim($reference)];
        if (! empty($opts['suffix'])) {
            $body['suffix'] = $opts['suffix'];
        }
        if (! empty($opts['phone'])) {
            $body['phoneNumber'] = $opts['phone'];
        }

        try {
            $res = Http::withHeaders(['x-api-key' => (string) config('lottery.payments.verify_key')])
                ->asJson()->timeout(25)->post($url, $body);
        } catch (\Throwable $e) {
            return $fail('Could not reach the verification service. Try again shortly.');
        }

        $data = is_array($res->json()) ? $res->json() : [];

        // Treat as verified only when the HTTP call succeeded, the provider did
        // not report an explicit failure/pending state, and a real amount landed.
        // (The previous `!== false` check failed open on null/absent flags.)
        $flag = $data['success'] ?? $data['ok'] ?? null;
        $flagOk = $flag === null ? true : filter_var($flag, FILTER_VALIDATE_BOOLEAN);

        $state = strtolower((string) ($this->dig($data, ['status', 'transactionStatus', 'state', 'result']) ?? ''));
        $stateOk = $state === '' || in_array(
            $state,
            ['success', 'successful', 'completed', 'complete', 'paid', 'settled', 'confirmed', 'done', 'ok'],
            true,
        );

        $success = $res->successful() && $flagOk && $stateOk && $this->extractAmount($data) > 0;

        if (! $success) {
            return $fail($data['message'] ?? $data['error'] ?? 'We could not verify that reference. Double-check it.');
        }

        return [
            'success' => true,
            'amount' => $this->extractAmount($data),
            'payer' => $this->dig($data, ['senderName', 'payerName', 'payer', 'debitedPartyName']),
            'receiver' => $this->dig($data, ['receiverName', 'creditedPartyName', 'receiver', 'creditedParty']),
            'receiverAccount' => $this->dig($data, ['receiverAccountNumber', 'creditedPartyAccount', 'receiverAccount', 'phoneNo', 'phoneNumber', 'account']),
            'raw' => $data,
            'error' => null,
        ];
    }

    /** First positive numeric value among known amount keys (searched recursively). */
    private function extractAmount(array $data): float
    {
        foreach (['transactionAmount', 'settledAmount', 'amount', 'amountPaid', 'paidAmount', 'creditedAmount', 'totalPaidAmount', 'total'] as $key) {
            $val = $this->dig($data, [$key]);
            if (is_numeric($val) && (float) $val > 0) {
                return round((float) $val, 2);
            }
        }

        return 0.0;
    }

    /** Recursively find the first matching key in a nested array. */
    private function dig(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $found = $this->dig($value, $keys);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
