<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PointsHoldState;
use App\Models\MallPointsBalance;
use App\Models\PointsFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TccPointsParticipantControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postTry(array $payload): TestResponse
    {
        return $this->postJson('/internal/points/try', [
            'payload' => $payload,
        ]);
    }

    public function test_try_confirm_cancel_round_trip(): void
    {
        MallPointsBalance::query()->create([
            'uid' => 9,
            'balance_minor' => 500,
            'ct' => 1,
            'ut' => 1,
        ]);

        $try = $this->postJson('/internal/points/try', [
            'payload' => [
                'uid' => 9,
                'amount_minor' => 100,
                'order_id' => 1,
                'tcc_idem_key' => 'idem-1',
            ],
        ]);

        $try->assertOk()->assertJsonPath('errorCode', 0);
        $this->assertSame(400, (int) MallPointsBalance::query()->where('uid', 9)->value('balance_minor'));

        $this->postJson('/internal/points/confirm', [], ['X-Request-Id' => 'idem-1'])
            ->assertOk()
            ->assertJsonPath('errorCode', 0);

        $hold = PointsFlow::query()->where('tcc_idem_key', 'idem-1')->first();
        $this->assertNotNull($hold);
        $this->assertSame(PointsHoldState::Confirmed, $hold->state);
    }

    public function test_try_cancel_restores_balance_and_marks_rolled_back(): void
    {
        MallPointsBalance::query()->create([
            'uid' => 11,
            'balance_minor' => 300,
            'ct' => 1,
            'ut' => 1,
        ]);

        $this->postTry([
            'uid' => 11,
            'amount_minor' => 80,
            'tcc_idem_key' => 'idem-cancel-1',
        ])->assertOk()->assertJsonPath('errorCode', 0);

        $this->assertSame(220, (int) MallPointsBalance::query()->where('uid', 11)->value('balance_minor'));

        $this->postJson('/internal/points/cancel', [
            'tcc_idem_key' => 'idem-cancel-1',
        ])
            ->assertOk()
            ->assertJsonPath('errorCode', 0);

        $this->assertSame(300, (int) MallPointsBalance::query()->where('uid', 11)->value('balance_minor'));

        $hold = PointsFlow::query()->where('tcc_idem_key', 'idem-cancel-1')->first();
        $this->assertNotNull($hold);
        $this->assertSame(PointsHoldState::RolledBack, $hold->state);
    }

    public function test_try_rejects_insufficient_points(): void
    {
        MallPointsBalance::query()->create([
            'uid' => 12,
            'balance_minor' => 10,
            'ct' => 1,
            'ut' => 1,
        ]);

        $this->postTry([
            'uid' => 12,
            'amount_minor' => 50,
            'tcc_idem_key' => 'idem-low',
        ])
            ->assertOk()
            ->assertJsonPath('errorCode', 100)
            ->assertJsonPath('message', 'Insufficient points.');

        $this->assertSame(10, (int) MallPointsBalance::query()->where('uid', 12)->value('balance_minor'));
        $this->assertNull(PointsFlow::query()->where('tcc_idem_key', 'idem-low')->first());
    }

    public function test_try_duplicate_tcc_idem_key_is_idempotent_after_try_succeeded(): void
    {
        MallPointsBalance::query()->create([
            'uid' => 13,
            'balance_minor' => 200,
            'ct' => 1,
            'ut' => 1,
        ]);

        $this->postTry([
            'uid' => 13,
            'amount_minor' => 30,
            'tcc_idem_key' => 'idem-dup',
        ])->assertOk()->assertJsonPath('errorCode', 0);

        $this->assertSame(170, (int) MallPointsBalance::query()->where('uid', 13)->value('balance_minor'));

        $this->postTry([
            'uid' => 13,
            'amount_minor' => 30,
            'tcc_idem_key' => 'idem-dup',
        ])->assertOk()->assertJsonPath('errorCode', 0);

        $this->assertSame(170, (int) MallPointsBalance::query()->where('uid', 13)->value('balance_minor'));
        $this->assertSame(1, (int) PointsFlow::query()->where('tcc_idem_key', 'idem-dup')->count());
    }

    public function test_confirm_rejects_missing_idem(): void
    {
        $this->postJson('/internal/points/confirm', [])
            ->assertOk()
            ->assertJsonPath('errorCode', 100)
            ->assertJsonPath('message', 'X-Request-Id or branch idempotency key required.');
    }

}
