<?php

namespace App\Models;

use App\Services\WorkDuration;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkLog extends Model
{
    /** @use HasFactory<\Database\Factories\WorkLogFactory> */
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'client_id',
        'project_id',
        'category_id',
        'work_date',
        'started_at',
        'ended_at',
        'ended_date',
        'duration_minutes',
        'title',
        'description',
        'status',
    ];

    protected $casts = [
        'work_date' => 'date',
        'ended_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (WorkLog $workLog): void {
            if (blank($workLog->ended_at)) {
                $workLog->duration_minutes = (int) $workLog->duration_minutes;

                if (! in_array($workLog->status, [self::STATUS_CANCELLED, self::STATUS_PAUSED], true)) {
                    $workLog->status = self::STATUS_IN_PROGRESS;
                }

                return;
            }

            $workLog->duration_minutes = WorkDuration::minutesBetween(
                $workLog->work_date?->toDateString() ?? (string) $workLog->work_date,
                $workLog->started_at,
                $workLog->ended_at,
                $workLog->ended_date?->toDateString() ?? null,
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

    public function sessions(): HasMany
    {
        return $this->hasMany(WorkLogSession::class);
    }

    public function activeSession(): HasOne
    {
        return $this->hasOne(WorkLogSession::class)->whereNull('ended_at')->latestOfMany();
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS && blank($this->ended_at);
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function startSession(?string $startedAt = null, ?string $workDate = null): WorkLogSession
    {
        $startedAt ??= now()->format('H:i');
        $workDate ??= now()->toDateString();

        if ($this->activeSession()->exists()) {
            return $this->activeSession()->first();
        }

        $session = $this->sessions()->create([
            'work_date' => $workDate,
            'started_at' => $startedAt,
        ]);

        $this->updateQuietly([
            'status' => self::STATUS_IN_PROGRESS,
            'ended_at' => null,
        ]);

        return $session;
    }

    public function pause(?string $endedAt = null, ?string $endedDate = null): void
    {
        $endedAt ??= now()->format('H:i');
        $endedDate ??= now()->toDateString();
        $this->ensureSessionHistoryExists();
        $session = $this->activeSession()->first();
        [$pauseEndedAt, $pauseEndedDate] = $this->pauseEndpoint($endedAt, $endedDate);

        if ($session) {
            $session->update([
                'ended_at' => $pauseEndedAt,
                'ended_date' => $pauseEndedDate,
            ]);
        }

        if (! $session && $this->sessions()->doesntExist() && filled($this->started_at)) {
            $this->sessions()->create([
                'work_date' => $this->work_date?->toDateString() ?? now()->toDateString(),
                'started_at' => $this->started_at,
                'ended_at' => $pauseEndedAt,
                'ended_date' => $pauseEndedDate,
            ]);
        }

        $this->syncDurationFromSessions(self::STATUS_PAUSED);
    }

    public function resume(?string $startedAt = null): void
    {
        $this->ensureSessionHistoryExists();

        $this->updateQuietly([
            'status' => self::STATUS_IN_PROGRESS,
            'ended_at' => null,
        ]);

        $this->startSession($startedAt);
    }

    public function finish(?string $endedAt = null, ?string $endedDate = null): void
    {
        $endedAt ??= now()->format('H:i');
        $endedDate ??= now()->toDateString();
        $this->ensureSessionHistoryExists();
        $session = $this->activeSession()->first();

        if ($session) {
            $session->update([
                'ended_at' => $endedAt,
                'ended_date' => $endedDate,
            ]);
        }

        $this->syncDurationFromSessions(self::STATUS_FINISHED, $session ? $endedAt : null);
    }

    public function syncDurationFromSessions(string $status, ?string $endedAt = null): void
    {
        $sessions = $this->sessions()
            ->orderBy('work_date')
            ->orderBy('started_at')
            ->get();

        if ($sessions->isEmpty()) {
            $this->updateQuietly([
                'status' => $status,
                'ended_at' => $status === self::STATUS_FINISHED ? $endedAt : null,
            ]);

            return;
        }

        $first = $sessions->first();
        $lastEndedSession = $sessions->whereNotNull('ended_at')->last();

        $this->updateQuietly([
            'started_at' => $first->started_at,
            'ended_at' => $status === self::STATUS_FINISHED ? ($endedAt ?: $lastEndedSession?->ended_at) : null,
            'ended_date' => $status === self::STATUS_FINISHED ? $lastEndedSession?->ended_date : null,
            'duration_minutes' => (int) $sessions->sum('duration_minutes'),
            'status' => $status,
        ]);
    }

    private function ensureSessionHistoryExists(): void
    {
        if ($this->sessions()->exists() || blank($this->started_at) || blank($this->ended_at)) {
            return;
        }

        $this->sessions()->create([
            'work_date' => $this->work_date?->toDateString() ?? now()->toDateString(),
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'ended_date' => $this->ended_date?->toDateString() ?? $this->work_date?->toDateString() ?? now()->toDateString(),
        ]);
    }

    private function pauseEndpoint(string $endedAt, string $endedDate): array
    {
        if ($this->status !== self::STATUS_IN_PROGRESS && filled($this->ended_at)) {
            return [
                substr((string) $this->ended_at, 0, 5),
                $this->ended_date?->toDateString() ?? $this->work_date?->toDateString() ?? $endedDate,
            ];
        }

        return [$endedAt, $endedDate];
    }

    public function formattedDuration(): string
    {
        return WorkDuration::formatMinutes($this->duration_minutes);
    }

    public function periodLabel(): string
    {
        $startedAt = substr((string) $this->started_at, 0, 5);
        $endedAt = match (true) {
            filled($this->ended_at) => substr((string) $this->ended_at, 0, 5),
            $this->isPaused() => 'pausada',
            default => 'agora',
        };

        return $startedAt.' - '.$endedAt;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_FINISHED => 'Finalizada',
            self::STATUS_CANCELLED => 'Cancelada',
            self::STATUS_PAUSED => 'Pausada',
            default => 'Em andamento',
        };
    }
}
