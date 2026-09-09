<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_syncs_lots_throughout_the_day(): void
    {
        $event = $this->scheduledEvent('radar:sync-lots');

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * *', $event->expression);
        $this->assertSame('America/Sao_Paulo', (string) $event->timezone);
    }

    public function test_dispatches_match_alerts_hourly(): void
    {
        $event = $this->scheduledEvent('radar:dispatch-alerts');

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertSame('America/Sao_Paulo', (string) $event->timezone);
    }

    private function scheduledEvent(string $needle): ?object
    {
        return collect(app(Schedule::class)->events())->first(
            function ($event) use ($needle): bool {
                $command = $event->command ?? $event->description ?? '';

                return is_string($command) && str_contains($command, $needle);
            }
        );
    }
}
