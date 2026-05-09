<?php

namespace Tests\Unit;

use App\Models\TradingAccount;
use App\Support\Mt5ConnectorStatus;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Mt5ConnectorStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-09 12:00:00'));
        config([
            'trading.platforms.mt5.freshness.stale_seconds' => 300,
            'trading.platforms.mt5.freshness.heartbeat_seconds' => 90,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_recent_sync_is_connected(): void
    {
        $account = new TradingAccount([
            'platform_slug' => 'mt5',
            'platform_status' => 'connected',
            'sync_source' => 'mt5_ea',
            'last_synced_at' => now()->subMinutes(4),
        ]);

        $status = $this->connectorStatus()->forAccount($account);

        $this->assertSame(Mt5ConnectorStatus::CONNECTED, $status['status']);
        $this->assertTrue($status['is_connected']);
    }

    public function test_recent_metric_update_is_connected_when_last_synced_at_is_missing(): void
    {
        $account = new TradingAccount([
            'platform_slug' => 'mt5',
            'platform_status' => 'connected',
            'sync_source' => 'mt5_ea',
            'meta' => [
                'mt5_sync' => [
                    'last_successful_metric_update_at' => now()->subMinutes(2)->toIso8601String(),
                ],
            ],
        ]);

        $status = $this->connectorStatus()->forAccount($account);

        $this->assertSame(Mt5ConnectorStatus::CONNECTED, $status['status']);
        $this->assertTrue($status['is_connected']);
    }

    public function test_sync_older_than_five_minutes_is_stale(): void
    {
        $account = new TradingAccount([
            'platform_slug' => 'mt5',
            'platform_status' => 'connected',
            'sync_source' => 'mt5_ea',
            'last_synced_at' => now()->subMinutes(6),
        ]);

        $status = $this->connectorStatus()->forAccount($account);

        $this->assertSame(Mt5ConnectorStatus::STALE, $status['status']);
        $this->assertSame('Disconnected/Stale', $status['label']);
        $this->assertFalse($status['is_connected']);
        $this->assertTrue($status['is_stale']);
    }

    public function test_recent_heartbeat_marks_connector_connected_even_when_metric_time_is_stale(): void
    {
        $account = new TradingAccount([
            'platform_slug' => 'mt5',
            'platform_status' => 'connected',
            'sync_source' => 'mt5_ea',
            'last_synced_at' => now()->subHours(2),
            'meta' => [
                'mt5_sync' => [
                    'last_successful_metric_update_at' => now()->subHours(2)->toIso8601String(),
                    'last_ea_ping_at' => now()->subMinute()->toIso8601String(),
                ],
            ],
        ]);

        $status = $this->connectorStatus()->forAccount($account);

        $this->assertSame(Mt5ConnectorStatus::CONNECTED, $status['status']);
        $this->assertTrue($status['is_connected']);
    }

    public function test_stale_heartbeat_marks_connector_stale_even_when_metric_sync_is_recent(): void
    {
        $account = new TradingAccount([
            'platform_slug' => 'mt5',
            'platform_status' => 'connected',
            'sync_source' => 'mt5_ea',
            'last_synced_at' => now()->subMinute(),
            'meta' => [
                'mt5_sync' => [
                    'last_successful_metric_update_at' => now()->subMinute()->toIso8601String(),
                    'last_ea_ping_at' => now()->subMinutes(3)->toIso8601String(),
                ],
            ],
        ]);

        $status = $this->connectorStatus()->forAccount($account);

        $this->assertSame(Mt5ConnectorStatus::STALE, $status['status']);
        $this->assertFalse($status['is_connected']);
    }

    public function test_recent_heartbeat_without_metric_sync_is_connected(): void
    {
        $account = new TradingAccount([
            'platform_slug' => 'mt5',
            'platform_status' => 'connected',
            'sync_source' => 'mt5_ea',
            'meta' => [
                'mt5_sync' => [
                    'last_ea_ping_at' => now()->subMinute()->toIso8601String(),
                ],
            ],
        ]);

        $status = $this->connectorStatus()->forAccount($account);

        $this->assertSame(Mt5ConnectorStatus::CONNECTED, $status['status']);
        $this->assertTrue($status['is_connected']);
    }

    public function test_future_metric_timestamp_is_not_treated_as_connected(): void
    {
        $account = new TradingAccount([
            'platform_slug' => 'mt5',
            'platform_status' => 'connected',
            'sync_source' => 'mt5_ea',
            'last_synced_at' => now()->addMinutes(10),
        ]);

        $status = $this->connectorStatus()->forAccount($account);

        $this->assertSame(Mt5ConnectorStatus::STALE, $status['status']);
        $this->assertFalse($status['is_connected']);
    }

    public function test_no_sync_is_not_connected_even_when_stored_flag_says_connected(): void
    {
        $account = new TradingAccount([
            'platform_slug' => 'mt5',
            'platform_status' => 'connected',
            'sync_source' => 'mt5_ea',
        ]);

        $status = $this->connectorStatus()->forAccount($account);

        $this->assertSame(Mt5ConnectorStatus::NOT_CONNECTED, $status['status']);
        $this->assertFalse($status['is_connected']);
    }

    private function connectorStatus(): Mt5ConnectorStatus
    {
        return $this->app->make(Mt5ConnectorStatus::class);
    }
}
