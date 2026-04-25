<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PointsHoldState;
use App\Models\MallPointsBalance;
use App\Models\PointsFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MallAdminPointsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('mall_agg.admin.api_token', 'test-admin-secret');
    }

    public function test_store_account_requires_token(): void
    {
        $this->postJson('/api/mall/admin/points/accounts', ['uid' => 1])
            ->assertStatus(403);
    }

    public function test_store_account_503_when_token_not_configured(): void
    {
        config()->set('mall_agg.admin.api_token', '');
        $this->withHeader('Authorization', 'Bearer x')->postJson('/api/mall/admin/points/accounts', ['uid' => 1])
            ->assertStatus(503);
    }

    public function test_store_account_creates_balance_and_optional_flow(): void
    {
        $this->withHeader('Authorization', 'Bearer test-admin-secret')
            ->postJson('/api/mall/admin/points/accounts', [
                'uid' => 42,
                'balance_minor' => 100,
            ])
            ->assertCreated()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.account.uid', 42)
            ->assertJsonPath('data.account.balance_minor', 100);

        $this->assertSame(1, (int) MallPointsBalance::query()->where('uid', 42)->count());
        $this->assertSame(1, (int) PointsFlow::query()->where('uid', 42)->where('state', PointsHoldState::AdminLedger)->count());
    }

    public function test_store_account_duplicate_returns_422(): void
    {
        $this->withHeader('Authorization', 'Bearer test-admin-secret')
            ->postJson('/api/mall/admin/points/accounts', ['uid' => 7, 'balance_minor' => 0])
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer test-admin-secret')
            ->postJson('/api/mall/admin/points/accounts', ['uid' => 7, 'balance_minor' => 0])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 40001);
    }

    public function test_adjust_updates_balance_and_inserts_flow(): void
    {
        MallPointsBalance::query()->create([
            'uid' => 99,
            'balance_minor' => 50,
            'ct' => 1,
            'ut' => 1,
        ]);

        $this->withHeader('Authorization', 'Bearer test-admin-secret')
            ->postJson('/api/mall/admin/points/adjust', [
                'uid' => 99,
                'delta_minor' => 25,
                'oid' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.account.balance_minor', 75)
            ->assertJsonPath('data.flow.amount_minor', 25)
            ->assertJsonPath('data.flow.state', PointsHoldState::AdminLedger->value);

        $row = MallPointsBalance::query()->where('uid', 99)->first();
        $this->assertSame(75, (int) $row->balance_minor);
    }

    public function test_adjust_without_account_returns_422(): void
    {
        $this->withHeader('Authorization', 'Bearer test-admin-secret')
            ->postJson('/api/mall/admin/points/adjust', ['uid' => 1, 'delta_minor' => 10])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 40001);
    }

    public function test_adjust_insufficient_returns_422(): void
    {
        MallPointsBalance::query()->create([
            'uid' => 3,
            'balance_minor' => 5,
            'ct' => 1,
            'ut' => 1,
        ]);

        $this->withHeader('Authorization', 'Bearer test-admin-secret')
            ->postJson('/api/mall/admin/points/adjust', ['uid' => 3, 'delta_minor' => -10])
            ->assertStatus(422);
    }
}
