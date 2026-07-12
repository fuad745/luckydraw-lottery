<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * Runtime-editable payment configuration. Values are seeded from config/.env but
 * can be overridden from the admin Settings page (stored in the `settings`
 * table). apply() pushes any overrides into the live config so every existing
 * config('lottery.payments.*') read transparently sees them — no redeploy.
 */
final class PaymentSettings
{
    /** All payment methods the verifier/app supports (the enable/disable menu). */
    public const SUPPORTED = ['telebirr', 'cbe', 'cbebirr', 'mpesa'];

    /** setting key => the config key it overrides. */
    private const MAP = [
        'pay_providers' => 'lottery.payments.providers',
        'pay_deposit_accounts' => 'lottery.payments.deposit_accounts',
        'pay_min_deposit' => 'lottery.payments.min_deposit',
        'pay_min_withdraw' => 'lottery.payments.min_withdraw',
        'pay_deposit_instructions' => 'lottery.payments.deposit_instructions',
        'pay_deposit_account_list' => 'lottery.payments.deposit_account_list',
        'pay_verify_key' => 'lottery.payments.verify_key',
    ];

    /** Merge stored overrides into runtime config (called once per request in boot). */
    public function apply(): void
    {
        try {
            $rows = Setting::whereIn('key', array_keys(self::MAP))->pluck('value', 'key');
        } catch (\Throwable) {
            return; // settings table not migrated yet — fall back to config/.env
        }

        foreach (self::MAP as $key => $configKey) {
            if (! $rows->has($key)) {
                continue;
            }
            $value = (string) $rows->get($key);

            // An empty stored key means "not set here" — keep the .env value.
            if ($key === 'pay_verify_key' && trim($value) === '') {
                continue;
            }

            config([$configKey => match ($key) {
                'pay_providers', 'pay_deposit_accounts' => $this->toList($value),
                'pay_min_deposit', 'pay_min_withdraw' => (float) $value,
                'pay_deposit_account_list' => $this->toAccountList($value),
                default => $value,
            }]);
        }
    }

    /**
     * Current effective values (override or config default), for the admin form.
     *
     * @return array{providers:array<int,string>, deposit_accounts:string, min_deposit:float, min_withdraw:float, deposit_instructions:string, account_list:array<int,array{provider:string,name:string,number:string}>, verify_key:string}
     */
    public function snapshot(): array
    {
        // Read from live config, which apply() has already populated.
        return [
            'providers' => array_values(array_intersect(self::SUPPORTED, (array) config('lottery.payments.providers', []))),
            'deposit_accounts' => implode(', ', (array) config('lottery.payments.deposit_accounts', [])),
            'min_deposit' => (float) config('lottery.payments.min_deposit', 10),
            'min_withdraw' => (float) config('lottery.payments.min_withdraw', 50),
            'deposit_instructions' => (string) config('lottery.payments.deposit_instructions', ''),
            'account_list' => $this->normaliseAccounts((array) config('lottery.payments.deposit_account_list', [])),
            'verify_key' => (string) config('lottery.payments.verify_key', ''),
        ];
    }

    /**
     * Persist the admin form. Only known providers are stored; a comma/newline
     * list is normalised for accounts.
     *
     * @param  array<int,string>  $providers
     * @param  array<int,array{provider?:string,name?:string,number?:string}>  $accountList
     */
    public function save(array $providers, string $depositAccounts, float $minDeposit, float $minWithdraw, string $depositInstructions, array $accountList = [], string $verifyKey = ''): void
    {
        $providers = array_values(array_intersect(self::SUPPORTED, $providers));

        Setting::put('pay_verify_key', trim($verifyKey));
        Setting::put('pay_providers', implode(',', $providers));
        Setting::put('pay_deposit_accounts', implode(',', $this->toList($depositAccounts)));
        Setting::put('pay_min_deposit', (string) max(0, $minDeposit));
        Setting::put('pay_min_withdraw', (string) max(0, $minWithdraw));
        Setting::put('pay_deposit_instructions', trim($depositInstructions));
        Setting::put('pay_deposit_account_list', json_encode($this->normaliseAccounts($accountList)) ?: '[]');
    }

    /** Split a comma/newline separated string into a trimmed, non-empty list. */
    private function toList(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,\n\r]+/', $value) ?: [])));
    }

    /**
     * Decode the stored JSON account list (displayed to players on deposit).
     *
     * @return array<int,array{provider:string,name:string,number:string}>
     */
    private function toAccountList(string $value): array
    {
        $decoded = json_decode($value, true);

        return $this->normaliseAccounts(is_array($decoded) ? $decoded : []);
    }

    /**
     * Keep only well-formed rows: a supported provider and a non-empty number.
     *
     * @param  array<int,mixed>  $rows
     * @return array<int,array{provider:string,name:string,number:string}>
     */
    private function normaliseAccounts(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $provider = strtolower(trim((string) ($row['provider'] ?? '')));
            $number = trim((string) ($row['number'] ?? ''));
            if ($number === '' || ! in_array($provider, self::SUPPORTED, true)) {
                continue;
            }
            $clean[] = [
                'provider' => $provider,
                'name' => trim((string) ($row['name'] ?? '')),
                'number' => $number,
            ];
        }

        return $clean;
    }
}
