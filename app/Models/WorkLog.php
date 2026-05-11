<?php

namespace App\Models;

use App\Services\WorkDuration;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkLog extends Model
{
    /** @use HasFactory<\Database\Factories\WorkLogFactory> */
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'client_id',
        'project_id',
        'category_id',
        'work_date',
        'started_at',
        'ended_at',
        'duration_minutes',
        'title',
        'description',
        'status',
    ];

    protected $casts = [
        'work_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (WorkLog $workLog): void {
            if (blank($workLog->ended_at)) {
                $workLog->duration_minutes = 0;
                $workLog->status = $workLog->status === self::STATUS_CANCELLED
                    ? self::STATUS_CANCELLED
                    : self::STATUS_IN_PROGRESS;

                return;
            }

            $workLog->duration_minutes = WorkDuration::minutesBetween(
                $workLog->work_date?->toDateString() ?? (string) $workLog->work_date,
                $workLog->started_at,
                $workLog->ended_at,
            );

            if ($workLog->status !== self::STATUS_CANCELLED) {
                $workLog->status = self::STATUS_FINISHED;
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS && blank($this->ended_at);
    }

    public function formattedDuration(): string
    {
        return WorkDuration::formatMinutes($this->duration_minutes);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_FINISHED => 'Finalizada',
            self::STATUS_CANCELLED => 'Cancelada',
            default => 'Em andamento',
        };
    }
}
