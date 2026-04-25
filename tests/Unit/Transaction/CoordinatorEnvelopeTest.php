<?php

declare(strict_types=1);

namespace Tests\Unit\Transaction;

use App\Services\Transaction\CoordinatorEnvelope;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CoordinatorEnvelopeTest extends TestCase
{
    public function test_data_or_fail_returns_data_when_error_code_zero(): void
    {
        $data = CoordinatorEnvelope::dataOrFail(
            ['errorCode' => 0, 'data' => ['global_tx_id' => 'a', 'idem_key' => 9]],
            'tcc begin'
        );

        $this->assertSame(['global_tx_id' => 'a', 'idem_key' => 9], $data);
    }

    public function test_data_or_fail_throws_on_nonzero_error_code(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('saga start: bad branch (errorCode=10002)');

        CoordinatorEnvelope::dataOrFail(
            ['errorCode' => 10002, 'message' => 'bad branch', 'data' => []],
            'saga start'
        );
    }

    public function test_data_or_fail_throws_on_null_json(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tcc cancel: empty JSON response.');

        CoordinatorEnvelope::dataOrFail(null, 'tcc cancel');
    }

    public function test_data_or_fail_throws_when_data_not_array(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tcc detail: invalid data payload.');

        CoordinatorEnvelope::dataOrFail(['errorCode' => 0, 'data' => 'x'], 'tcc detail');
    }
}
