<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\TestCase;

final class PhoneTest extends TestCase
{
    public function test_ethiopian_local_forms_become_international(): void
    {
        $this->assertSame('+251912345678', Phone::normalize('0912345678'));   // Ethio Telecom
        $this->assertSame('+251712345678', Phone::normalize('0712345678'));   // Safaricom
        $this->assertSame('+251912345678', Phone::normalize('912345678'));    // bare 9…
        $this->assertSame('+251912345678', Phone::normalize('+251 91 234 5678')); // already intl
        $this->assertSame('+251912345678', Phone::normalize('251912345678'));
    }

    public function test_rejects_implausible_numbers(): void
    {
        $this->assertNull(Phone::normalize('123'));
        $this->assertNull(Phone::normalize(''));
        $this->assertNull(Phone::normalize(null));
    }
}
