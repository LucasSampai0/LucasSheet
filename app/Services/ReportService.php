<?php

namespace App\Services;

use App\Models\WorkLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    public function filteredQuery(array $filters = []): Builder
    {
        return WorkLog::query()
            ->with(['client', 'project', 'category'])
            ->when($filters['start_date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('work_date', '>=', $date))
            ->when($filters['end_date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('work_date', '<=', $date))
            ->when($filters['date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('work_date', $date))
            ->when($filters['client_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('client_id', $id))
            ->when($filters['project_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('project_id', $id))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('category_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    public function totalsBy(Collection $records, string $relation): Collection
    {
        return $records
            ->groupBy(fn (WorkLog $record) => optional($record->{$relation})->name ?? 'Sem '.str_replace('_', ' ', $relation))
            ->map(fn (Collection $items, string $name) => [
                'name' => $name,
                'minutes' => (int) $items->sum('duration_minutes'),
                'formatted' => WorkDuration::formatMinutes((int) $items->sum('duration_minutes')),
            ])
            ->sortBy('name')
            ->values();
    }

    public function totalsFor(array $filters = []): array
    {
        $records = $this->filteredQuery($filters)
            ->orderBy('work_date')
            ->orderBy('started_at')
            ->get();

        return [
            'records' => $records,
            'clients' => $this->totalsBy($records, 'client'),
            'projects' => $this->totalsBy($records, 'project'),
            'categories' => $this->totalsBy($records, 'category'),
            'total_minutes' => (int) $records->sum('duration_minutes'),
        ];
    }
}
