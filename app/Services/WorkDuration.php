<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class WorkDuration
{
    public static function minutesBetween(string $date, string $startedAt, ?string $endedAt): int
    {
        if (blank($endedAt)) {
            return 0;
        }

        $start = Carbon::parse($date.' '.$startedAt);
        $end = Carbon::parse($date.' '.$endedAt);

        if ($end->lt($start)) {
            throw new InvalidArgumentException('O horario final nao pode ser menor que o horario inicial.');
        }

        return (int) $start->diffInMinutes($end);
    }

    public static function formatMinutes(?int $minutes): string
    {
        $minutes = max(0, (int) $minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $remaining);
    }

    public static function outsideBusinessHours(string $startedAt, ?string $endedAt = null): bool
    {
        $start = Carbon::createFromFormat('H:i', substr($startedAt, 0, 5));
        $businessStart = Carbon::createFromFormat('H:i', '08:00');
        $businessEnd = Carbon::createFromFormat('H:i', '18:00');

        if ($start->lt($businessStart) || $start->gt($businessEnd)) {
            return true;
        }

        if (blank($endedAt)) {
            return false;
        }

        $end = Carbon::createFromFormat('H:i', substr($endedAt, 0, 5));

        return $end->lt($businessStart) || $end->gt($businessEnd);
    }
}
