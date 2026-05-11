<?php

use App\Models\WorkLog;
use App\Services\WorkDuration;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        $today = now()->toDateString();

        return [
            'today' => WorkLog::whereDate('work_date', $today)->sum('duration_minutes'),
            'week' => WorkLog::whereBetween('work_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->sum('duration_minutes'),
            'month' => WorkLog::whereBetween('work_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->sum('duration_minutes'),
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

    public function clientSummary()
    {
        return WorkLog::query()
            ->select('clients.name', DB::raw('sum(duration_minutes) as minutes'))
            ->join('clients', 'clients.id', '=', 'work_logs.client_id')
            ->whereDate('work_date', now()->toDateString())
            ->groupBy('clients.name')
            ->orderBy('clients.name')
            ->get();
    }
};
?>

<section class="space-y-6">
    <div>
        <p class="text-sm font-medium text-zinc-500">{{ now()->format('d/m/Y') }}</p>
        <h1 class="text-2xl font-semibold">Dashboard</h1>
    </div>

    @php($stats = $this->stats())
    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <p class="text-sm font-medium text-zinc-500">Hoje</p>
            <p class="mt-2 text-3xl font-semibold">{{ WorkDuration::formatMinutes($stats['today']) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <p class="text-sm font-medium text-zinc-500">Semana</p>
            <p class="mt-2 text-3xl font-semibold">{{ WorkDuration::formatMinutes($stats['week']) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <p class="text-sm font-medium text-zinc-500">Mes</p>
            <p class="mt-2 text-3xl font-semibold">{{ WorkDuration::formatMinutes($stats['month']) }}</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div class="rounded-lg border border-zinc-200 bg-white">
            <div class="border-b border-zinc-200 px-5 py-4">
                <h2 class="font-semibold">Ultimas tarefas</h2>
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
                                <td class="px-5 py-3">{{ substr($record->started_at, 0, 5) }} - {{ $record->ended_at ? substr($record->ended_at, 0, 5) : 'agora' }}</td>
                                <td class="px-5 py-3">{{ $record->formattedDuration() }}</td>
                                <td class="px-5 py-3">@include('components.shared.status-badge', ['status' => $record->status, 'label' => $record->statusLabel()])</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-zinc-500">Nenhum registro ainda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white">
            <div class="border-b border-zinc-200 px-5 py-4">
                <h2 class="font-semibold">Resumo de hoje por cliente</h2>
            </div>
            <div class="divide-y divide-zinc-100">
                @forelse ($this->clientSummary() as $item)
                    <div class="flex items-center justify-between px-5 py-4">
                        <span class="font-medium">{{ $item->name }}</span>
                        <span class="text-sm text-zinc-600">{{ WorkDuration::formatMinutes((int) $item->minutes) }}</span>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-zinc-500">Sem horas registradas hoje.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
