<?php

namespace App\Models;

use App\Services\WorkDuration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkLogSession extends Model
{
    protected $fillable = [
        'work_log_id',
        'work_date',
        'started_at',
        'ended_at',
        'ended_date',
        'duration_minutes',
    ];

    protected $casts = [
        'work_date' => 'date',
        'ended_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (WorkLogSession $session): void {
            $session->duration_minutes = WorkDuration::minutesBetween(
                $session->work_date?->toDateString() ?? (string) $session->work_date,
                $session->started_at,
                $session->ended_at,
                $session->ended_date?->toDateString() ?? null,
            );
        });
    }

    public function workLog(): BelongsTo
    {
        return $this->belongsTo(WorkLog::class);
    }
}
