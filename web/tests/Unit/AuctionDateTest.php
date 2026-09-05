<?php

namespace Tests\Unit;

use App\Support\AuctionDate;
use Tests\TestCase;

class AuctionDateTest extends TestCase
{
    public function test_detects_clock_time_vs_date_only(): void
    {
        $this->assertTrue(AuctionDate::hasClockTime('2026-09-05 09:30:00'));
        $this->assertTrue(AuctionDate::hasClockTime('2026-09-05T09:30:00'));
        $this->assertFalse(AuctionDate::hasClockTime('2026-09-05'));
        $this->assertFalse(AuctionDate::hasClockTime('05/09/2026'));
        $this->assertFalse(AuctionDate::hasClockTime(null));
    }

    public function test_date_only_parse_stays_at_start_of_day_unless_end_requested(): void
    {
        $start = AuctionDate::parse('2026-09-05');
        $end = AuctionDate::parseEnd('2026-09-05');

        $this->assertSame('00:00:00', $start?->format('H:i:s'));
        $this->assertSame('23:59:59', $end?->format('H:i:s'));
    }
}
