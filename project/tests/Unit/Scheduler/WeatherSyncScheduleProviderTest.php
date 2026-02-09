<?php

namespace App\Tests\Unit\Scheduler;

use App\Scheduler\WeatherSyncScheduleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Schedule;

class WeatherSyncScheduleProviderTest extends TestCase
{
    public function testGetScheduleReturnsScheduleWithMessages(): void
    {
        $cacheTtl = 15;
        $provider = new WeatherSyncScheduleProvider($cacheTtl);

        $schedule = $provider->getSchedule();

        $this->assertInstanceOf(Schedule::class, $schedule);

        $this->assertCount(1, $schedule->getRecurringMessages());
    }
}
