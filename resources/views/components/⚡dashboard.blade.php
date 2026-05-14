<?php

use App\Models\WorkLog;
use App\Services\WorkDuration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    private const DAILY_JOURNEY_MINUTES = 528;

    public string $statusPeriod = 'month';

    public function stats(): array
    {
        $today = now()->toDateString();
        [$weekStart, $weekEnd] = $this->businessWeekRange();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $todayMinutes = (int) WorkLog::whereDate('work_date', $today)->sum('duration_minutes');
        $weekMinutes = (int) WorkLog::whereBetween('work_date', [$weekStart, $weekEnd])->sum('duration_minutes');
        $monthMinutes = (int) WorkLog::whereBetween('work_date', [$monthStart, $monthEnd])->sum('duration_minutes');

        $businessDays = collect(range(1, now()->daysInMonth))
            ->map(fn (int $day) => now()->copy()->startOfMonth()->addDays($day - 1))
            ->filter(fn (Carbon $date) => $date->isWeekday())
            ->count();

        return [
            'today' => ['label' => 'Hoje', 'minutes' => $todayMinutes, 'target' => 600],
            'week' => ['label' => 'Semana', 'minutes' => $weekMinutes, 'target' => 3000],
            'month' => ['label' => 'Mes', 'minutes' => $monthMinutes, 'target' => max(1, $businessDays * 600)],
        ];
    }

    public function latest()
    {
        return WorkLog::with(['client', 'project', 'category'])
            ->latest('work_date')
            ->latest('started_at')
            ->limit(8)
            ->get();
    }

    public function weeklyChart(): array
    {
        [$startDate, $endDate] = $this->businessWeekRange();
        $start = Carbon::parse($startDate);
        $totals = WorkLog::query()
            ->selectRaw('date(work_date) as day, sum(duration_minutes) as minutes')
            ->whereDate('work_date', '>=', $startDate)
            ->whereDate('work_date', '<=', $endDate)
            ->groupBy('day')
            ->pluck('minutes', 'day');
        $max = self::DAILY_JOURNEY_MINUTES;

        return collect(range(0, 4))->map(function (int $offset) use ($start, $totals, $max): array {
            $date = $start->copy()->addDays($offset);
            $minutes = (int) ($totals[$date->toDateString()] ?? 0);

            return [
                'date' => $date->toDateString(),
                'label' => $date->translatedFormat('D'),
                'day' => $date->format('d/m'),
                'minutes' => $minutes,
                'formatted' => WorkDuration::formatMinutes($minutes),
                'height' => min(100, max(8, (int) round(($minutes / $max) * 100))),
            ];
        })->all();
    }

    public function clientSummary()
    {
        $items = WorkLog::query()
            ->select('clients.name', DB::raw('sum(duration_minutes) as minutes'))
            ->join('clients', 'clients.id', '=', 'work_logs.client_id')
            ->whereDate('work_date', now()->toDateString())
            ->groupBy('clients.name')
            ->orderByDesc('minutes')
            ->get();
        $max = max(1, (int) $items->max('minutes'));

        return $items->map(function ($item) use ($max) {
            $item->percent = (int) round(((int) $item->minutes / $max) * 100);
            return $item;
        });
    }

    public function categorySummary()
    {
        $items = WorkLog::query()
            ->select('categories.name', 'categories.color', DB::raw('sum(duration_minutes) as minutes'))
            ->leftJoin('categories', 'categories.id', '=', 'work_logs.category_id')
            ->whereBetween('work_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->groupBy('categories.name', 'categories.color')
            ->orderByDesc('minutes')
            ->limit(6)
            ->get()
            ->map(function ($item) {
                $item->name = $item->name ?: 'Sem categoria';
                $item->color = $item->color ?: '#EB088D';
                return $item;
            });
        $max = max(1, (int) $items->max('minutes'));

        return $items->map(function ($item) use ($max) {
            $item->percent = (int) round(((int) $item->minutes / $max) * 100);
            return $item;
        });
    }

    public function statusSummary()
    {
        [$startDate, $endDate] = $this->statusPeriodRange();

        $labels = [
            WorkLog::STATUS_IN_PROGRESS => 'Em andamento',
            WorkLog::STATUS_PAUSED => 'Pausadas',
            WorkLog::STATUS_FINISHED => 'Finalizadas',
            WorkLog::STATUS_CANCELLED => 'Canceladas',
        ];

        $totals = WorkLog::query()
            ->select('status', DB::raw('count(*) as total'), DB::raw('sum(duration_minutes) as minutes'))
            ->whereDate('work_date', '>=', $startDate)
            ->whereDate('work_date', '<=', $endDate)
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return collect($labels)->map(fn (string $label, string $status) => [
            'status' => $status,
            'label' => $label,
            'count' => (int) ($totals[$status]->total ?? 0),
            'minutes' => (int) ($totals[$status]->minutes ?? 0),
            'formatted' => WorkDuration::formatMinutes((int) ($totals[$status]->minutes ?? 0)),
        ])->values();
    }

    public function setStatusPeriod(string $period): void
    {
        if (! in_array($period, array_keys($this->statusPeriodOptions()), true)) {
            return;
        }

        $this->statusPeriod = $period;
    }

    public function statusPeriodOptions(): array
    {
        return [
            'day' => 'Dia',
            'week' => 'Semana',
            'month' => 'Mes',
        ];
    }

    public function statusPeriodLabel(): string
    {
        return match ($this->statusPeriod) {
            'day' => 'Hoje',
            'week' => 'Semana atual',
            default => 'Mes atual',
        };
    }

    private function statusPeriodRange(): array
    {
        return match ($this->statusPeriod) {
            'day' => [now()->toDateString(), now()->toDateString()],
            'week' => $this->businessWeekRange(),
            default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }

    private function businessWeekRange(): array
    {
        $monday = now()->startOfWeek(Carbon::MONDAY);

        return [
            $monday->toDateString(),
            $monday->copy()->addDays(4)->toDateString(),
        ];
    }
};
?>

<section class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-zinc-500">{{ now()->format('d/m/Y') }}</p>
            <h1 class="text-2xl font-semibold">Dashboard</h1>
            <p class="mt-1 text-sm text-zinc-500">Acompanhe volume de horas, distribuicao por cliente e evolucao da semana.</p>
        </div>
    </div>

    <div class="dashboard-hidden-controls rounded-lg border border-zinc-200 bg-white p-4" data-dashboard-hidden-controls hidden>
        <div class="flex flex-wrap items-center gap-2">
            <span class="mr-2 text-sm font-medium text-zinc-500">Widgets ocultos</span>
            @foreach ([
                'stats' => 'Totais',
                'weekly' => 'Semana',
                'clients' => 'Clientes hoje',
                'categories' => 'Categorias',
                'status' => 'Status',
                'latest' => 'Ultimas tarefas',
            ] as $key => $label)
                <button type="button" class="rounded border border-zinc-300 px-3 py-1.5 text-xs font-medium" data-dashboard-restore="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @php($stats = $this->stats())
    <div class="grid gap-4 lg:grid-cols-3" data-dashboard-widget="stats">
        @foreach ($stats as $key => $stat)
            @php($percent = min(100, (int) round(($stat['minutes'] / max(1, $stat['target'])) * 100)))
            <div class="dashboard-card rounded-lg border border-zinc-200 bg-white p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-zinc-500">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-3xl font-semibold">{{ WorkDuration::formatMinutes($stat['minutes']) }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ $percent }}% da jornada de referencia</p>
                    </div>
                    <div class="dashboard-ring" style="--value: {{ $percent }}">
                        <span>{{ $percent }}%</span>
                    </div>
                </div>
                @if ($loop->first)
                    <button type="button" class="widget-hide-button mt-4" data-dashboard-hide="stats">Ocultar totais</button>
                @endif
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
        <div class="dashboard-widget rounded-lg border border-zinc-200 bg-white" data-dashboard-widget="weekly">
            <div class="widget-header border-b border-zinc-200 px-5 py-4">
                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500">Grafico</p>
                    <h2 class="font-semibold">Horas na semana</h2>
                </div>
                <button type="button" class="widget-hide-button" data-dashboard-hide="weekly">Ocultar</button>
            </div>
            <div class="weekly-chart px-5 py-5">
                @foreach ($this->weeklyChart() as $day)
                    <div class="weekly-bar-item">
                        <div class="weekly-bar-track">
                            <div class="weekly-bar" style="height: {{ $day['height'] }}%"></div>
                        </div>
                        <span class="weekly-bar-value">{{ $day['formatted'] }}</span>
                        <span class="weekly-bar-label">{{ $day['label'] }}</span>
                        <span class="weekly-bar-date">{{ $day['day'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="dashboard-widget rounded-lg border border-zinc-200 bg-white" data-dashboard-widget="status">
            <div class="widget-header border-b border-zinc-200 px-5 py-4">
                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500">{{ $this->statusPeriodLabel() }}</p>
                    <h2 class="font-semibold">Status das tarefas</h2>
                </div>
                <div class="widget-header-actions">
                    <div class="period-segmented" role="group" aria-label="Filtrar status por periodo">
                        @foreach ($this->statusPeriodOptions() as $period => $label)
                            <button
                                type="button"
                                wire:click="setStatusPeriod('{{ $period }}')"
                                class="{{ $statusPeriod === $period ? 'is-active' : '' }}"
                                aria-pressed="{{ $statusPeriod === $period ? 'true' : 'false' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                    <button type="button" class="widget-hide-button" data-dashboard-hide="status">Ocultar</button>
                </div>
            </div>
            <div class="grid gap-3 p-5">
                @foreach ($this->statusSummary() as $item)
                    <div class="status-widget-row">
                        @include('components.shared.status-badge', ['status' => $item['status'], 'label' => $item['label']])
                        <span>{{ $item['count'] }} tarefas</span>
                        <strong>{{ $item['formatted'] }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="dashboard-widget rounded-lg border border-zinc-200 bg-white" data-dashboard-widget="clients">
            <div class="widget-header border-b border-zinc-200 px-5 py-4">
                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500">Hoje</p>
                    <h2 class="font-semibold">Resumo por cliente</h2>
                </div>
                <button type="button" class="widget-hide-button" data-dashboard-hide="clients">Ocultar</button>
            </div>
            <div class="space-y-4 p-5">
                @forelse ($this->clientSummary() as $item)
                    <div class="chart-list-row">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium">{{ $item->name }}</span>
                            <span class="text-sm text-zinc-500">{{ WorkDuration::formatMinutes((int) $item->minutes) }}</span>
                        </div>
                        <div class="chart-list-track"><span style="width: {{ $item->percent }}%"></span></div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-zinc-500">Sem horas registradas hoje.</p>
                @endforelse
            </div>
        </div>

        <div class="dashboard-widget rounded-lg border border-zinc-200 bg-white" data-dashboard-widget="categories">
            <div class="widget-header border-b border-zinc-200 px-5 py-4">
                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500">Mes atual</p>
                    <h2 class="font-semibold">Horas por categoria</h2>
                </div>
                <button type="button" class="widget-hide-button" data-dashboard-hide="categories">Ocultar</button>
            </div>
            <div class="space-y-4 p-5">
                @forelse ($this->categorySummary() as $item)
                    <div class="chart-list-row">
                        <div class="flex items-center justify-between gap-3">
                            <span class="inline-flex items-center gap-2 font-medium"><span class="h-3 w-3 rounded" style="background: {{ $item->color }}"></span>{{ $item->name }}</span>
                            <span class="text-sm text-zinc-500">{{ WorkDuration::formatMinutes((int) $item->minutes) }}</span>
                        </div>
                        <div class="chart-list-track"><span style="width: {{ $item->percent }}%; background: {{ $item->color }}"></span></div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-zinc-500">Sem categorias registradas neste mes.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="dashboard-widget rounded-lg border border-zinc-200 bg-white" data-dashboard-widget="latest">
        <div class="widget-header border-b border-zinc-200 px-5 py-4">
            <div>
                <p class="text-xs font-medium uppercase text-zinc-500">Historico</p>
                <h2 class="font-semibold">Ultimas tarefas</h2>
            </div>
            <button type="button" class="widget-hide-button" data-dashboard-hide="latest">Ocultar</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="px-5 py-3">Data</th>
                        <th class="px-5 py-3">Tarefa</th>
                        <th class="px-5 py-3">Cliente</th>
                        <th class="px-5 py-3">Periodo</th>
                        <th class="px-5 py-3">Duracao</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($this->latest() as $record)
                        <tr>
                            <td class="px-5 py-3">{{ $record->work_date->format('d/m') }}</td>
                            <td class="px-5 py-3 font-medium">{{ $record->title }}</td>
                            <td class="px-5 py-3">{{ $record->client->name }}</td>
                            <td class="px-5 py-3">{{ $record->periodLabel() }}</td>
                            <td class="px-5 py-3">{{ $record->formattedDuration() }}</td>
                            <td class="px-5 py-3">@include('components.shared.status-badge', ['status' => $record->status, 'label' => $record->statusLabel()])</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-zinc-500">Nenhuma tarefa ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
