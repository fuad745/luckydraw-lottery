<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PaymentMessageParser;
use PHPUnit\Framework\TestCase;

final class PaymentMessageParserTest extends TestCase
{
    private PaymentMessageParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new PaymentMessageParser;
    }

    public function test_bare_reference_is_returned_verbatim(): void
    {
        $out = $this->parser->parse('FT253089F68Z', 'cbe');

        $this->assertSame('FT253089F68Z', $out['reference']);
    }

    public function test_short_bare_token_is_untouched(): void
    {
        // Backward-compatibility with the old "just type the reference" flow.
        $out = $this->parser->parse('TRX123', 'telebirr');

        $this->assertSame('TRX123', $out['reference']);
    }

    public function test_telebirr_receipt_link_is_extracted(): void
    {
        $msg = 'Dear customer, you received ETB 500.00. '
            .'Receipt: https://transactioninfo.ethiotelecom.et/receipt/CGH7K2M9Q2';

        $out = $this->parser->parse($msg, 'telebirr');

        $this->assertSame('telebirr', $out['provider']);
        $this->assertSame('CGH7K2M9Q2', $out['reference']);
    }

    public function test_telebirr_link_overrides_wrongly_selected_provider(): void
    {
        $msg = 'https://transactioninfo.ethiotelecom.et/receipt/CGH7K2M9Q2';

        // Player left "cbe" selected but pasted a Telebirr link.
        $out = $this->parser->parse($msg, 'cbe');

        $this->assertSame('telebirr', $out['provider']);
        $this->assertSame('CGH7K2M9Q2', $out['reference']);
    }

    public function test_cbe_receipt_link_splits_reference_and_suffix(): void
    {
        $msg = 'Dear Customer, your account has been debited ETB 500.00. '
            .'https://apps.cbe.com.et:100/?id=FT253089F68Z12345678';

        $out = $this->parser->parse($msg, 'cbe');

        $this->assertSame('cbe', $out['provider']);
        $this->assertSame('FT253089F68Z', $out['reference']);
        $this->assertSame('12345678', $out['suffix']);
    }

    public function test_cbe_ft_reference_inside_full_sms(): void
    {
        $msg = 'Dear Abebe, You have transferred ETB 1,000.00 to LUCKY DRAW '
            .'on 01/07/2026. Your transaction reference is FT25182ABCDX. Thank you.';

        $out = $this->parser->parse($msg, 'cbe');

        $this->assertSame('cbe', $out['provider']);
        $this->assertSame('FT25182ABCDX', $out['reference']);
    }

    public function test_mpesa_confirmation_code_is_extracted(): void
    {
        $msg = 'TD94RNM67E Confirmed. You have received ETB500.00 from '
            .'JOHN DOE 251712345678 on 9/4/26 at 3:04 PM. New M-PESA balance is ETB750.00.';

        $out = $this->parser->parse($msg, 'mpesa');

        $this->assertSame('mpesa', $out['provider']);
        $this->assertSame('TD94RNM67E', $out['reference']);
        $this->assertSame('0712345678', $out['phone']);
    }

    public function test_mpesa_detected_from_body_when_provider_mismatched(): void
    {
        $msg = 'TAR2NLXUH8 Confirmed. Sent to LUCKY DRAW. New M-PESA balance ETB0.00.';

        $out = $this->parser->parse($msg, 'telebirr');

        $this->assertSame('mpesa', $out['provider']);
        $this->assertSame('TAR2NLXUH8', $out['reference']);
    }

    public function test_empty_input_returns_nulls(): void
    {
        $out = $this->parser->parse('   ', 'telebirr');

        $this->assertNull($out['reference']);
        $this->assertNull($out['provider']);
    }
}
