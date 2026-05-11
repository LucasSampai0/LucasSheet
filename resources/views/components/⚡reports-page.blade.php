<?php

use App\Exports\WorkReportXlsx;
use App\Models\Category;
use App\Models\Client;
use App\Models\Project;
use App\Services\ReportService;
use App\Services\WorkDuration;
use Livewire\Component;

new class extends Component
{
    public string $start_date = '';
    public string $end_date = '';
    public ?int $client_id = null;
    public ?int $project_id = null;
    public ?int $category_id = null;

    public function mount(): void
    {
        $this->start_date = now()->startOfMonth()->toDateString();
        $this->end_date = now()->toDateString();
    }

    public function report(): array
    {
        return app(ReportService::class)->totalsFor($this->filters());
    }

    public function export()
    {
        try {
            $export = new WorkReportXlsx($this->report());
            $contents = $export->contents();

            return response()->streamDownload(function () use ($contents): void {
                echo $contents;
            }, $export->downloadName(), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (Throwable $exception) {
            $this->addError('export', $exception->getMessage());
            return null;
        }
    }

    public function filters(): array
    {
        return [
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'client_id' => $this->client_id,
            'project_id' => $this->project_id,
            'category_id' => $this->category_id,
        ];
    }

    public function clients()
    {
        return Client::orderBy('name')->get();
    }

    public function projects()
    {
        return Project::query()
            ->when($this->client_id, fn ($query) => $query->where('client_id', $this->client_id))
            ->orderBy('name')
            ->get();
    }

    public function categories()
    {
        return Category::orderBy('name')->get();
    }
};
?>

<section class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Relatorios</h1>
            <p class="mt-1 text-sm text-zinc-500">Filtre o periodo e exporte uma planilha com abas de registros e resumos.</p>
        </div>
        <button wire:click="export" class="rounded bg-zinc-950 px-4 py-2 text-sm font-medium text-white">Exportar Excel</button>
    </div>

    @error('export')
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</div>
    @enderror

    <div class="rounded-lg border border-zinc-200 bg-white p-5">
        <div class="grid gap-3 md:grid-cols-5">
            <label class="block">
                <span class="text-xs font-medium uppercase text-zinc-500">Inicio</span>
                <input type="date" wire:model.live="start_date" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="text-xs font-medium uppercase text-zinc-500">Fim</span>
                <input type="date" wire:model.live="end_date" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="text-xs font-medium uppercase text-zinc-500">Cliente</span>
                <select wire:model.live="client_id" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($this->clients() as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-xs font-medium uppercase text-zinc-500">Projeto</span>
                <select wire:model.live="project_id" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($this->projects() as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-xs font-medium uppercase text-zinc-500">Categoria</span>
                <select wire:model.live="category_id" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    @foreach ($this->categories() as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    @php($report = $this->report())
    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <p class="text-sm font-medium text-zinc-500">Total</p>
            <p class="mt-2 text-2xl font-semibold">{{ WorkDuration::formatMinutes($report['total_minutes']) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <p class="text-sm font-medium text-zinc-500">Registros</p>
            <p class="mt-2 text-2xl font-semibold">{{ $report['records']->count() }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <p class="text-sm font-medium text-zinc-500">Clientes</p>
            <p class="mt-2 text-2xl font-semibold">{{ $report['clients']->count() }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-5">
            <p class="text-sm font-medium text-zinc-500">Projetos</p>
            <p class="mt-2 text-2xl font-semibold">{{ $report['projects']->count() }}</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        @foreach ([['Cliente', $report['clients']], ['Projeto', $report['projects']], ['Categoria', $report['categories']]] as [$label, $items])
            <div class="rounded-lg border border-zinc-200 bg-white">
                <div class="border-b border-zinc-200 px-5 py-4 font-semibold">Resumo por {{ $label }}</div>
                <div class="divide-y divide-zinc-100">
                    @forelse ($items as $item)
                        <div class="flex items-center justify-between px-5 py-3 text-sm">
                            <span>{{ $item['name'] }}</span>
                            <span class="font-medium">{{ $item['formatted'] }}</span>
                        </div>
                    @empty
                        <p class="px-5 py-6 text-center text-sm text-zinc-500">Sem dados.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <div class="rounded-lg border border-zinc-200 bg-white">
        <div class="border-b border-zinc-200 px-5 py-4 font-semibold">Registros filtrados</div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="px-5 py-3">Data</th>
                        <th class="px-5 py-3">Inicio</th>
                        <th class="px-5 py-3">Fim</th>
                        <th class="px-5 py-3">Duracao</th>
                        <th class="px-5 py-3">Cliente</th>
                        <th class="px-5 py-3">Projeto</th>
                        <th class="px-5 py-3">Categoria</th>
                        <th class="px-5 py-3">Titulo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($report['records'] as $record)
                        <tr>
                            <td class="px-5 py-3">{{ $record->work_date->format('d/m/Y') }}</td>
                            <td class="px-5 py-3">{{ substr($record->started_at, 0, 5) }}</td>
                            <td class="px-5 py-3">{{ $record->ended_at ? substr($record->ended_at, 0, 5) : '-' }}</td>
                            <td class="px-5 py-3">{{ $record->formattedDuration() }}</td>
                            <td class="px-5 py-3">{{ $record->client->name }}</td>
                            <td class="px-5 py-3">{{ $record->project?->name ?? '-' }}</td>
                            <td class="px-5 py-3">{{ $record->category?->name ?? '-' }}</td>
                            <td class="px-5 py-3 font-medium">{{ $record->title }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-8 text-center text-zinc-500">Nenhum registro encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
