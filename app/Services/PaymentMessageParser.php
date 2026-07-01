<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Pulls the transaction reference (and, where present, the CBE account suffix
 * or the payer phone) out of a payment confirmation the player copy-pastes —
 * a full SMS, a receipt link, or a bare reference.
 *
 * Ethiopian wallets/banks each notify with a recognisable shape (verified
 * against real 2025/2026 messages):
 *
 *   Telebirr  SMS from 127 carries a link
 *             https://transactioninfo.ethiotelecom.et/receipt/{id}
 *             ({id} = 10-ish char uppercase code).
 *   CBE       "…reference FT253089F68Z…" plus a receipt link
 *             https://apps.cbe.com.et:100/?id=FT253089F68Z{last-8-account-digits}
 *             (the id concatenates the FT reference and the account suffix).
 *   M-Pesa    "TD94RNM67E Confirmed. You have received ETB…" — a 10-char code
 *             leads the message; the body mentions M-PESA.
 *   CBE Birr  transfer SMS with a receipt/transaction number and a 09… phone.
 *
 * The verifier's /verify endpoint auto-detects the provider from the reference
 * format, so extracting a clean reference is what matters most; the detected
 * provider is returned as a hint the caller may use to correct a mis-selected
 * payment method.
 */
final class PaymentMessageParser
{
    /**
     * @return array{provider:?string, reference:?string, suffix:?string, phone:?string}
     */
    public function parse(string $raw, ?string $preferProvider = null): array
    {
        $text = trim($raw);
        $prefer = $preferProvider !== null ? strtolower(trim($preferProvider)) : null;

        $out = ['provider' => null, 'reference' => null, 'suffix' => null, 'phone' => null];

        if ($text === '') {
            return $out;
        }

        // 1. Telebirr receipt link — the id after /receipt(s)/ is the reference.
        //    (Accepts the transactioninfo host or any …/receipt(s)/{code} link.)
        if (preg_match('#receipts?/([A-Za-z0-9]{6,32})#i', $text, $m)) {
            $out['provider'] = 'telebirr';
            $out['reference'] = strtoupper($m[1]);

            return $this->withPhone($out, $text);
        }

        // 2. CBE receipt link — id = FT reference + trailing account digits.
        if (preg_match('#apps\.cbe\.com\.et\S*?[?&]id=([A-Za-z0-9]+)#i', $text, $m)) {
            [$reference, $suffix] = $this->splitCbeId(strtoupper($m[1]));
            $out['provider'] = 'cbe';
            $out['reference'] = $reference;
            $out['suffix'] = $suffix ?? $this->cbeSuffix($text);

            return $this->withPhone($out, $text);
        }

        // 3. Bare CBE FT reference anywhere in the text (unless a different
        //    provider was explicitly chosen).
        if (($prefer === null || $prefer === 'cbe')
            && preg_match('#\bFT[0-9A-Z]{8,14}\b#i', $text, $m)) {
            $out['provider'] = 'cbe';
            $out['reference'] = strtoupper($m[0]);
            $out['suffix'] = $this->cbeSuffix($text);

            return $this->withPhone($out, $text);
        }

        // 4. M-Pesa — a 10-char code leads a "Confirmed" message, or the body
        //    is clearly an M-PESA notification.
        $looksMpesa = $prefer === 'mpesa' || preg_match('#\bM[\s\-]?PESA\b#i', $text);
        if ($looksMpesa) {
            if (preg_match('#\b([A-Z0-9]{10})\b(?=\s+Confirmed)#i', $text, $m)
                || preg_match('#^\W*([A-Z0-9]{10})\b#', $text, $m)
                || preg_match('#\b([A-Z][A-Z0-9]{9})\b#', $text, $m)) {
                $out['provider'] = 'mpesa';
                $out['reference'] = strtoupper($m[1]);

                return $this->withPhone($out, $text);
            }
        }

        // 5. Telebirr code by keyword/shape when no link was pasted.
        $looksTelebirr = $prefer === 'telebirr' || preg_match('#telebirr#i', $text);
        if ($looksTelebirr) {
            $code = $this->firstCode($text);
            if ($code !== null) {
                $out['provider'] = 'telebirr';
                $out['reference'] = $code;

                return $this->withPhone($out, $text);
            }
        }

        // 6. CBE Birr transfer message — take the first code-like token + phone.
        $looksCbeBirr = $prefer === 'cbebirr' || preg_match('#cbe[\s\-]?birr#i', $text);
        if ($looksCbeBirr) {
            $code = $this->firstCode($text);
            if ($code !== null) {
                $out['provider'] = 'cbebirr';
                $out['reference'] = $code;

                return $this->withPhone($out, $text);
            }
        }

        // 7. Generic fallback. A single pasted token is used verbatim (keeps the
        //    old "just type the reference" behaviour working); otherwise pull the
        //    first code-like token out of the free text.
        if (! preg_match('#\s#', $text) && mb_strlen($text) <= 40) {
            $out['reference'] = $text;

            return $this->withPhone($out, $text);
        }

        $out['reference'] = $this->firstCode($text) ?? $text;

        return $this->withPhone($out, $text);
    }

    /**
     * Split a CBE receipt id into its FT reference and the trailing account
     * suffix (the last 8 digits of the sender's account CBE appends to the id).
     *
     * @return array{0:string, 1:?string}
     */
    private function splitCbeId(string $id): array
    {
        // Reference then exactly the 8-digit account tail.
        if (preg_match('#^(FT[0-9A-Z]+?)(\d{8})$#', $id, $m)) {
            return [$m[1], $m[2]];
        }

        // A 12-char FT reference with some tail we can't cleanly split.
        if (str_starts_with($id, 'FT') && strlen($id) > 12) {
            return [substr($id, 0, 12), substr($id, 12)];
        }

        return [$id, null];
    }

    /** Last 8 digits of an account number mentioned in the text, if any. */
    private function cbeSuffix(string $text): ?string
    {
        if (preg_match('#\b(\d{8,})\b(?![^\s]*FT)#', $text, $m)) {
            return substr($m[1], -8);
        }

        return null;
    }

    /** First Ethiopian mobile number (normalised to 09XXXXXXXX) in the text. */
    private function withPhone(array $out, string $text): array
    {
        // Ethio Telecom mobiles start 09, Safaricom (M-Pesa) 07 — accept both,
        // in local (0…), national (9…/7…) or international (+251…) form.
        if ($out['phone'] === null
            && preg_match('#(?<!\d)(?:\+?251|0)?([79]\d{8})(?!\d)#', $text, $m)) {
            $out['phone'] = '0'.$m[1];
        }

        return $out;
    }

    /**
     * The most reference-looking token in free text: an 8–14 char alphanumeric
     * run that mixes letters and digits (so it isn't a plain amount, date, or
     * phone). Returned uppercased.
     */
    private function firstCode(string $text): ?string
    {
        if (! preg_match_all('#\b[A-Za-z0-9]{8,14}\b#', $text, $all)) {
            return null;
        }

        foreach ($all[0] as $token) {
            $hasLetter = preg_match('#[A-Za-z]#', $token) === 1;
            $hasDigit = preg_match('#\d#', $token) === 1;
            if ($hasLetter && $hasDigit) {
                return strtoupper($token);
            }
        }

        return null;
    }
}
