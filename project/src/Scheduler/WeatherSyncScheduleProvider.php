<?php

namespace App\Scheduler;

use App\Message\SyncWeatherCacheMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('weather_sync')]
final readonly class WeatherSyncScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private int $cacheTtlMinutes
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::every(
                    sprintf('%d minutes', $this->cacheTtlMinutes),
                    new SyncWeatherCacheMessage()
                )
            );
    }
}
