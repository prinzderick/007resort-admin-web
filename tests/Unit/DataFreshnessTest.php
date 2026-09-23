<?php

namespace Tests\Unit;

use App\Support\DataFreshness;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DataFreshnessTest extends TestCase
{
    private function block(array $o = []): array
    {
        return $o + ['generatedAt' => CarbonImmutable::now('UTC')->toIso8601ZuluString(), 'sourceNode' => 'local', 'lastSyncAt' => CarbonImmutable::now('UTC')->subSeconds(10)->toIso8601ZuluString(), 'stale' => false, 'ageSeconds' => 10, 'staleAfterSeconds' => 300];
    }

    public function test_fresh_block_is_live(): void
    {
        $this->assertSame('live', DataFreshness::assess([$this->block()])->level);
    }

    public function test_stale_flag_wins(): void
    {
        $f = DataFreshness::assess([$this->block(['stale' => true, 'staleReason' => 'site OFFLINE'])]);
        $this->assertSame('stale', $f->level);
        $this->assertStringContainsString('site OFFLINE', $f->message);
    }

    public function test_age_beyond_threshold_is_stale_even_if_flag_says_fresh(): void
    {
        $this->assertSame('stale', DataFreshness::assess([$this->block(['ageSeconds' => 901, 'staleAfterSeconds' => 300])])->level);
    }

    public function test_no_blocks_means_unverified_never_live(): void
    {
        $this->assertSame('unknown', DataFreshness::assess([])->level);
        $this->assertSame('unknown', DataFreshness::assess([null, null])->level);
    }

    public function test_worst_block_wins(): void
    {
        $this->assertSame('stale', DataFreshness::assess([$this->block(), $this->block(['stale' => true]), $this->block()])->level);
    }

    public function test_offline_site_beats_a_fresh_looking_report(): void
    {
        $f = DataFreshness::assess([$this->block()], ['health' => 'OFFLINE', 'lastPeerHeartbeatAt' => CarbonImmutable::now('UTC')->subMinutes(7)->toIso8601ZuluString()]);
        $this->assertSame('offline', $f->level);
        $this->assertStringContainsString('7 minutes ago', $f->message);
    }

    public function test_degraded_site_is_stale(): void
    {
        $this->assertSame('stale', DataFreshness::assess([$this->block()], ['health' => 'DEGRADED'])->level);
    }

    public function test_cloud_mirror_that_never_synced_is_unverified(): void
    {
        $f = DataFreshness::assess([$this->block(['sourceNode' => 'cloud', 'lastSyncAt' => null])], null, 'cloud');
        $this->assertSame('unknown', $f->level);
    }

    public function test_node_verdicts(): void
    {
        $this->assertSame('live', DataFreshness::forNode(['health' => 'ONLINE'])->level);
        $this->assertSame('offline', DataFreshness::forNode(['health' => 'OFFLINE'])->level);
        $this->assertSame('stale', DataFreshness::forNode(['health' => 'DEGRADED'])->level);
        $this->assertSame('unknown', DataFreshness::forNode(null)->level);
        $this->assertSame('unknown', DataFreshness::forNode(['health' => 'WAT'])->level);
    }
}
