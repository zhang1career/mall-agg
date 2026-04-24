<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MallOrderStatus;
use App\Models\MallOrder;
use App\Models\ProductInventory;
use App\Models\ProductPrice;
use App\Services\mall\OrderCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class XxlJobControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TRIGGER_LOG_DATE_TIME_MS = 1_700_000_000_000;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('xxl.token', 'xxl-test-token');
        config()->set('xxl.admin_address', 'http://xxl-admin.test');
    }

    public function test_beat_rejects_invalid_token(): void
    {
        $this->getJson('/api/xxl-job/beat')
            ->assertOk()
            ->assertJsonPath('code', 500);
    }

    public function test_beat_accepts_valid_token(): void
    {
        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->getJson('/api/xxl-job/beat')
            ->assertOk()
            ->assertJsonPath('code', 200);
    }

    public function test_run_dispatches_mall_close_overdue_job_and_callbacks(): void
    {
        // Callback uses Guzzle directly; not intercepted by Http::fake. Admin may be unreachable in CI; sweep still runs.
        config()->set('mall_agg.orders.pending_payment_timeout_ms', 60_000);
        config()->set('mall_agg.checkout.use_saga_coordinators', false);

        ProductPrice::query()->create([
            'pid' => 1,
            'price' => 10,
            'ct' => 1,
            'ut' => 1,
        ]);
        ProductInventory::query()->create([
            'pid' => 1,
            'quantity' => 5,
            'ct' => 1,
            'ut' => 1,
        ]);

        $order = app(OrderCommandService::class)
            ->createPendingOrderForCheckout(7, [['product_id' => 1, 'quantity' => 1]]);

        MallOrder::query()->where('id', $order->id)->update([
            'ct' => MallOrder::nowMillis() - 120_000,
            'ut' => MallOrder::nowMillis() - 120_000,
        ]);

        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->postJson('/api/xxl-job/run', [
                'jobId' => 9001,
                'executorHandler' => 'closeExpiredOrders',
                'executorParams' => '',
                'logId' => 55_001,
                'logDateTime' => self::TRIGGER_LOG_DATE_TIME_MS,
            ])
            ->assertOk()
            ->assertJsonPath('code', 200);

        $order->refresh();
        $this->assertSame(MallOrderStatus::Cancelled, $order->status);
    }

    public function test_run_returns_500_when_handler_unknown(): void
    {
        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->postJson('/api/xxl-job/run', [
                'jobId' => 9002,
                'executorHandler' => 'nonexistentHandler',
                'logId' => 55_002,
                'logDateTime' => self::TRIGGER_LOG_DATE_TIME_MS,
            ])
            ->assertOk()
            ->assertJsonPath('code', 500);
    }

    public function test_run_returns_500_when_log_date_time_invalid(): void
    {
        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->postJson('/api/xxl-job/run', [
                'jobId' => 9003,
                'executorHandler' => 'closeExpiredOrders',
                'logId' => 55_003,
                'logDateTime' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('code', 500);
    }

    public function test_kill_removes_lock_file_when_present(): void
    {
        $storage = Storage::disk('local');
        $storage->put('jobs/777.job', '777');

        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->postJson('/api/xxl-job/kill', ['jobId' => 777])
            ->assertOk()
            ->assertJsonPath('code', 200);

        $this->assertFalse($storage->exists('jobs/777.job'));
    }

    public function test_kill_idempotent_when_file_missing(): void
    {
        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->postJson('/api/xxl-job/kill', ['jobId' => 999999])
            ->assertOk()
            ->assertJsonPath('code', 200);
    }
}
