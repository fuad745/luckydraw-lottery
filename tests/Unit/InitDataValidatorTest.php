<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Telegram\InitDataValidator;
use PHPUnit\Framework\TestCase;

final class InitDataValidatorTest extends TestCase
{
    private string $token = '123456:TEST-BOT-TOKEN';

    private function buildInitData(array $fields, ?string $token = null): string
    {
        $token ??= $this->token;

        ksort($fields);
        $dcs = collect($fields)->map(fn ($v, $k) => "$k=$v")->implode("\n");
        $secret = hash_hmac('sha256', $token, 'WebAppData', true);
        $fields['hash'] = hash_hmac('sha256', $dcs, $secret);

        return http_build_query($fields);
    }

    public function test_it_accepts_valid_signed_init_data(): void
    {
        $user = json_encode(['id' => 42, 'first_name' => 'Lucky']);
        $initData = $this->buildInitData([
            'auth_date' => (string) time(),
            'query_id' => 'AAA',
            'user' => $user,
        ]);

        $result = (new InitDataValidator)->validate($initData, $this->token);

        $this->assertNotNull($result);
        $this->assertSame(42, $result['user']['id']);
    }

    public function test_it_rejects_tampered_data(): void
    {
        $initData = $this->buildInitData([
            'auth_date' => (string) time(),
            'user' => json_encode(['id' => 42]),
        ]);
        // Tamper with the user after signing.
        $tampered = str_replace('42', '999', $initData);

        $this->assertNull((new InitDataValidator)->validate($tampered, $this->token));
    }

    public function test_it_rejects_wrong_token(): void
    {
        $initData = $this->buildInitData([
            'auth_date' => (string) time(),
            'user' => json_encode(['id' => 42]),
        ]);

        $this->assertNull((new InitDataValidator)->validate($initData, 'wrong-token'));
    }

    public function test_it_rejects_stale_data(): void
    {
        $initData = $this->buildInitData([
            'auth_date' => (string) (time() - 100_000),
            'user' => json_encode(['id' => 42]),
        ]);

        $this->assertNull((new InitDataValidator)->validate($initData, $this->token, 3600));
    }
}
