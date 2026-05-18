<?php

use App\Models\Category;
use App\Models\Client;
use App\Models\Project;
use App\Models\WorkLog;
use App\Services\ReportService;
use App\Services\WorkDuration;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public ?int $editingId = null;
    public ?int $client_id = null;
    public ?int $project_id = null;
    public ?int $category_id = null;
    public string $work_date = '';
    public string $started_at = '';
    public ?string $ended_at = null;
    public ?string $ended_date = null;
    public string $title = '';
    public string $description = '';
    public string $status = WorkLog::STATUS_FINISHED;
    public bool $pausePrevious = false;
    public ?string $warning = null;
    public bool $showTaskModal = false;

    public ?string $filter_start_date = null;
    public ?string $filter_end_date = null;
    public ?int $filter_client_id = null;
    public ?int $filter_project_id = null;
    public ?int $filter_category_id = null;
    public ?string $filter_status = null;
    public string $viewMode = 'table';

    public function mount(): void
    {
        $this->resetForm();
        $this->filter_start_date = now()->toDateString();
        $this->filter_end_date = now()->toDateString();
    }

    protected function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'work_date' => ['required', 'date'],
            'started_at' => ['required', 'date_format:H:i'],
            'ended_at' => ['nullable', 'date_format:H:i'],
            'ended_date' => ['nullable', 'date', 'after_or_equal:work_date'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:in_progress,paused,finished,cancelled'],
        ];
    }

    public function updatedClientId(): void
    {
        $this->project_id = null;
    }

    public function updatedFilterClientId(): void
    {
        $this->filter_project_id = null;
    }

    public function save(): void
    {
        $data = $this->validate();
        $this->ensureProjectBelongsToClient($data);
        $this->ensureValidPeriod($data);

        if (blank($data['ended_at']) && ! in_array($data['status'], [WorkLog::STATUS_PAUSED, WorkLog::STATUS_CANCELLED], true)) {
            $data['status'] = WorkLog::STATUS_IN_PROGRESS;
        }

        if (blank($data['ended_at'])) {
            $data['ended_date'] = null;
        } else {
            $data['ended_date'] = $data['ended_date'] ?: $data['work_date'];
        }

        $this->warning = WorkDuration::outsideBusinessHours($data['started_at'], $data['ended_at'])
            ? 'Tarefa salva. O horario esta fora da janela comum de 08:00 a 18:00.'
            : null;

        $record = WorkLog::updateOrCreate(['id' => $this->editingId], $data);

        if ($record->sessions()->doesntExist()) {
            $record->sessions()->create([
                'work_date' => $data['work_date'],
                'started_at' => $data['started_at'],
                'ended_at' => $data['ended_at'] ?: null,
                'ended_date' => $data['ended_date'] ?: null,
            ]);

            $record->syncDurationFromSessions(
                blank($data['ended_at']) ? WorkLog::STATUS_IN_PROGRESS : WorkLog::STATUS_FINISHED,
                $data['ended_at'] ?: null,
            );
        }

        $this->resetForm(keepWarning: true);
        $this->showTaskModal = false;
    }

    public function startNow(): void
    {
        $this->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $ongoing = WorkLog::where('status', WorkLog::STATUS_IN_PROGRESS)->whereNull('ended_at')->latest()->first();

        if ($ongoing && ! $this->pausePrevious) {
            throw ValidationException::withMessages([
                'pausePrevious' => 'Ja existe uma tarefa em andamento. Marque a opcao para pausar a anterior e iniciar uma nova.',
            ]);
        }

        if ($ongoing) {
            $ongoing->pause(now()->format('H:i'));
        }

        $data = [
            'client_id' => $this->client_id,
            'project_id' => $this->project_id,
            'category_id' => $this->category_id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->format('H:i'),
            'ended_at' => null,
            'ended_date' => null,
            'title' => $this->title ?: 'Tarefa iniciada agora',
            'description' => $this->description,
            'status' => WorkLog::STATUS_IN_PROGRESS,
        ];

        $this->ensureProjectBelongsToClient($data);
        $record = WorkLog::create($data);
        $record->startSession($data['started_at']);
        $this->resetForm();
        $this->showTaskModal = false;
    }

    public function finish(int $id): void
    {
        $record = WorkLog::findOrFail($id);
        $record->finish(now()->format('H:i'));
    }

    public function pause(int $id): void
    {
        $record = WorkLog::findOrFail($id);
        $record->pause(now()->format('H:i'));
    }

    public function resume(int $id): void
    {
        WorkLog::where('status', WorkLog::STATUS_IN_PROGRESS)
            ->whereNull('ended_at')
            ->whereKeyNot($id)
            ->get()
            ->each(fn (WorkLog $record) => $record->pause(now()->format('H:i')));

        $record = WorkLog::findOrFail($id);
        $record->resume(now()->format('H:i'));
    }

    public function edit(int $id): void
    {
        $record = WorkLog::findOrFail($id);
        $this->editingId = $record->id;
        $this->client_id = $record->client_id;
        $this->project_id = $record->project_id;
        $this->category_id = $record->category_id;
        $this->work_date = $record->work_date->toDateString();
        $this->started_at = substr($record->started_at, 0, 5);
        $this->ended_at = $record->ended_at ? substr($record->ended_at, 0, 5) : null;
        $this->ended_date = $record->ended_date?->toDateString();
        $this->title = $record->title;
        $this->description = $record->description ?? '';
        $this->status = $record->status;
        $this->showTaskModal = true;
    }

    public function delete(int $id): void
    {
        WorkLog::findOrFail($id)->delete();
    }

    public function resetForm(bool $keepWarning = false): void
    {
        $warning = $this->warning;
        $this->editingId = null;
        $this->client_id = null;
        $this->project_id = null;
        $this->category_id = null;
        $this->work_date = now()->toDateString();
        $this->started_at = now()->format('H:i');
        $this->ended_at = null;
        $this->ended_date = null;
        $this->title = '';
        $this->description = '';
        $this->status = WorkLog::STATUS_FINISHED;
        $this->pausePrevious = false;
        $this->warning = $keepWarning ? $warning : null;
        $this->resetValidation();
    }

    public function openTaskModal(): void
    {
        $this->resetForm();
        $this->showTaskModal = true;
    }

    public function closeTaskModal(): void
    {
        $this->showTaskModal = false;
        $this->resetForm();
    }

    public function changeStatusFromBoard(int $id, string $status): void
    {
        if (! array_key_exists($status, $this->statusFilterOptions())) {
            return;
        }

        $record = WorkLog::findOrFail($id);

        if ($record->status === $status) {
            return;
        }

        if ($status === WorkLog::STATUS_IN_PROGRESS) {
            WorkLog::where('status', WorkLog::STATUS_IN_PROGRESS)
                ->whereNull('ended_at')
                ->whereKeyNot($id)
                ->get()
                ->each(fn (WorkLog $workLog) => $workLog->pause(now()->format('H:i'), now()->toDateString()));

            $record->resume(now()->format('H:i'));

            return;
        }

        if ($status === WorkLog::STATUS_PAUSED) {
            $record->pause(now()->format('H:i'), now()->toDateString());

            return;
        }

        if ($status === WorkLog::STATUS_FINISHED) {
            $record->finish(now()->format('H:i'), now()->toDateString());

            return;
        }

        if ($record->isInProgress()) {
            $record->pause(now()->format('H:i'), now()->toDateString());
        }

        $record->update([
            'status' => WorkLog::STATUS_CANCELLED,
            'ended_at' => null,
            'ended_date' => null,
        ]);
    }

    public function records()
    {
        [$startDate, $endDate] = $this->normalizedFilterPeriod();

        return app(ReportService::class)->filteredQuery([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'client_id' => $this->filter_client_id,
            'project_id' => $this->filter_project_id,
            'category_id' => $this->filter_category_id,
            'status' => $this->filter_status,
        ])
            ->with(['client', 'project', 'category', 'sessions'])
            ->latest('work_date')
            ->latest('started_at')
            ->limit(80)
            ->get();
    }

    private function normalizedFilterPeriod(): array
    {
        if ($this->filter_start_date && $this->filter_end_date && $this->filter_start_date > $this->filter_end_date) {
            return [$this->filter_end_date, $this->filter_start_date];
        }

        return [$this->filter_start_date, $this->filter_end_date];
    }

    public function clients()
    {
        return Client::where('active', true)->orderBy('name')->get();
    }

    public function projects()
    {
        if (blank($this->client_id)) {
            return collect();
        }

        return Project::query()
            ->where('active', true)
            ->where('client_id', $this->client_id)
            ->orderBy('name')
            ->get();
    }

    public function filterProjects()
    {
        if (blank($this->filter_client_id)) {
            return collect();
        }

        return Project::query()
            ->where('client_id', $this->filter_client_id)
            ->orderBy('name')
            ->get();
    }

    public function categories()
    {
        return Category::where('active', true)->orderBy('name')->get();
    }

    public function statusFilterOptions(): array
    {
        return [
            WorkLog::STATUS_IN_PROGRESS => 'Em andamento',
            WorkLog::STATUS_PAUSED => 'Pausada',
            WorkLog::STATUS_FINISHED => 'Finalizada',
            WorkLog::STATUS_CANCELLED => 'Cancelada',
        ];
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            WorkLog::STATUS_IN_PROGRESS => '#10b981',
            WorkLog::STATUS_PAUSED => '#f59e0b',
            WorkLog::STATUS_FINISHED => '#EB088D',
            WorkLog::STATUS_CANCELLED => '#e11d48',
            default => '#EB088D',
        };
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['table', 'board', 'gantt'], true)) {
            return;
        }

        $this->viewMode = $mode;
    }

    public function ganttData($records): array
    {
        $segments = collect();

        foreach ($records as $record) {
            $sources = $record->sessions->isNotEmpty()
                ? $record->sessions->sortBy([['work_date', 'asc'], ['started_at', 'asc']])->values()
                : collect([$record]);

            foreach ($sources as $index => $source) {
                $startDate = $source->work_date?->toDateString() ?? $record->work_date->toDateString();
                $startTime = substr((string) $source->started_at, 0, 5);

                if (blank($startTime)) {
                    continue;
                }

                $endTime = filled($source->ended_at) ? substr((string) $source->ended_at, 0, 5) : null;
                $endDate = $source->ended_date?->toDateString() ?? $startDate;

                if (blank($endTime)) {
                    if ($record->status !== WorkLog::STATUS_IN_PROGRESS) {
                        continue;
                    }

                    $endTime = now()->format('H:i');
                    $endDate = now()->toDateString();
                }

                $start = Carbon::parse($startDate.' '.$startTime);
                $end = Carbon::parse($endDate.' '.$endTime);

                if ($end->lt($start)) {
                    continue;
                }

                $this->splitGanttSegment($segments, $record, $start, $end, $index);
            }
        }

        if ($segments->isEmpty()) {
            return [
                'range_start' => 8 * 60,
                'range_end' => 18 * 60,
                'labels' => $this->ganttLabels(8 * 60, 18 * 60),
                'days' => [],
            ];
        }

        $rangeStart = max(0, (int) floor(min(8 * 60, $segments->min('start_minutes')) / 60) * 60);
        $rangeEnd = min(24 * 60, (int) ceil(max(18 * 60, $segments->max('end_minutes')) / 60) * 60);
        $rangeEnd = max($rangeEnd, $rangeStart + 60);

        $days = $segments
            ->groupBy('day')
            ->sortKeysDesc()
            ->map(function ($items, string $day) use ($rangeStart, $rangeEnd) {
                return [
                    'day' => $day,
                    'label' => Carbon::parse($day)->format('d/m/Y'),
                    'weekday' => ucfirst(Carbon::parse($day)->translatedFormat('l')),
                    'items' => $items
                        ->sortBy('start_minutes')
                        ->values()
                        ->map(function (array $item) use ($rangeStart, $rangeEnd) {
                            $range = max(1, $rangeEnd - $rangeStart);
                            $left = max(0, (($item['start_minutes'] - $rangeStart) / $range) * 100);
                            $width = max(1.8, (($item['end_minutes'] - $item['start_minutes']) / $range) * 100);

                            return array_merge($item, [
                                'left' => min(100, $left),
                                'width' => min(100 - min(100, $left), $width),
                            ]);
                        })
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'range_start' => $rangeStart,
            'range_end' => $rangeEnd,
            'labels' => $this->ganttLabels($rangeStart, $rangeEnd),
            'days' => $days,
        ];
    }

    private function splitGanttSegment($segments, WorkLog $record, Carbon $start, Carbon $end, int $index): void
    {
        $cursor = $start->copy();

        while ($cursor->toDateString() <= $end->toDateString()) {
            $isFirstDay = $cursor->isSameDay($start);
            $isLastDay = $cursor->isSameDay($end);
            $segmentStart = $isFirstDay ? $start->copy() : $cursor->copy()->startOfDay();
            $segmentEnd = $isLastDay ? $end->copy() : $cursor->copy()->endOfDay();

            $startMinutes = ($segmentStart->hour * 60) + $segmentStart->minute;
            $endMinutes = ($segmentEnd->hour * 60) + $segmentEnd->minute;

            if ($endMinutes > $startMinutes) {
                $segments->push([
                    'id' => $record->id,
                    'key' => $record->id.'-'.$index.'-'.$segmentStart->toDateString(),
                    'day' => $segmentStart->toDateString(),
                    'title' => $record->title,
                    'client' => $record->client->name,
                    'project' => $record->project?->name ?? 'Sem projeto',
                    'category' => $record->category?->name ?? 'Sem categoria',
                    'color' => $record->category?->color ?: $this->statusColor($record->status),
                    'status' => $record->statusLabel(),
                    'start' => $segmentStart->format('H:i'),
                    'end' => $segmentEnd->format('H:i'),
                    'start_minutes' => $startMinutes,
                    'end_minutes' => $endMinutes,
                    'duration' => WorkDuration::formatMinutes($endMinutes - $startMinutes),
                ]);
            }

            $cursor->addDay()->startOfDay();
        }
    }

    private function ganttLabels(int $rangeStart, int $rangeEnd): array
    {
        $labels = [];
        $range = max(1, $rangeEnd - $rangeStart);

        for ($minute = $rangeStart; $minute <= $rangeEnd; $minute += 60) {
            $labels[] = [
                'label' => $minute === 24 * 60 ? '24:00' : sprintf('%02d:00', intdiv($minute, 60) % 24),
                'left' => (($minute - $rangeStart) / $range) * 100,
            ];
        }

        return $labels;
    }

    private function ensureProjectBelongsToClient(array $data): void
    {
        if (! empty($data['project_id']) && Project::whereKey($data['project_id'])->where('client_id', $data['client_id'])->doesntExist()) {
            throw ValidationException::withMessages([
                'project_id' => 'O projeto selecionado nao pertence ao cliente informado.',
            ]);
        }
    }

    private function ensureValidPeriod(array $data): void
    {
        if (blank($data['ended_at'])) {
            return;
        }

        try {
            WorkDuration::minutesBetween(
                $data['work_date'],
                $data['started_at'],
                $data['ended_at'],
                $data['ended_date'] ?: $data['work_date'],
            );
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'ended_at' => 'O horario final nao pode ser menor que o inicio quando a data final for a mesma.',
            ]);
        }
    }
};
?>

<section class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Tarefas</h1>
            <p class="mt-1 text-sm text-zinc-500">Acompanhe tarefas manuais, pausas, retomadas e mudancas de status.</p>
        </div>
        <button type="button" wire:click="openTaskModal" class="rounded bg-zinc-950 px-4 py-2 text-sm font-medium text-white">Nova tarefa</button>
    </div>

    @if ($warning)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $warning }}</div>
    @endif

    @if ($showTaskModal)
        <div class="task-modal-backdrop" wire:click.self="closeTaskModal">
            <form wire:submit="save" class="task-modal rounded-lg border border-zinc-200 bg-white p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $editingId ? 'Editar tarefa' : 'Nova tarefa' }}</h2>
                        <p class="mt-1 text-sm text-zinc-500">Preencha os dados da tarefa e acompanhe seu status.</p>
                    </div>
                    <button type="button" wire:click="closeTaskModal" class="rounded border border-zinc-300 px-3 py-1.5 text-sm font-medium">Fechar</button>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-4">
                    <label class="block">
                        <span class="text-sm font-medium">Cliente</span>
                        <select wire:model.live="client_id" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                            <option value="">Selecione</option>
                            @foreach ($this->clients() as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </select>
                        @error('client_id') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Projeto</span>
                        <select wire:model="project_id" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none" @disabled(blank($client_id))>
                            <option value="">{{ blank($client_id) ? 'Selecione um cliente primeiro' : 'Sem projeto' }}</option>
                            @foreach ($this->projects() as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                        @error('project_id') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Categoria</span>
                        <select wire:model="category_id" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                            <option value="">Sem categoria</option>
                            @foreach ($this->categories() as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Status</span>
                        <select wire:model="status" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                            @foreach ($this->statusFilterOptions() as $status => $label)
                                <option value="{{ $status }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Data inicio</span>
                        <input type="date" wire:model="work_date" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                        @error('work_date') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Inicio</span>
                        <input type="time" wire:model="started_at" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                        @error('started_at') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Data fim</span>
                        <input type="date" wire:model="ended_date" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                        @error('ended_date') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Fim</span>
                        <input type="time" wire:model="ended_at" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                        @error('ended_at') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block lg:col-span-4">
                        <span class="text-sm font-medium">Titulo</span>
                        <input wire:model="title" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                        @error('title') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block lg:col-span-4">
                        <span class="text-sm font-medium">Descricao</span>
                        <textarea wire:model="description" rows="3" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none"></textarea>
                    </label>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <button class="rounded bg-zinc-950 px-4 py-2 text-sm font-medium text-white">{{ $editingId ? 'Salvar alteracoes' : 'Salvar tarefa' }}</button>
                    <button type="button" wire:click="startNow" class="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Iniciar agora</button>
                    <label class="ml-auto flex items-center gap-2 text-sm text-zinc-600">
                        <input type="checkbox" wire:model="pausePrevious" class="rounded border-zinc-300">
                        Pausar tarefa anterior em andamento
                    </label>
                    @error('pausePrevious') <span class="basis-full text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>
            </form>
        </div>
    @endif

    <div class="rounded-lg border border-zinc-200 bg-white p-5">
        <div class="grid gap-3 md:grid-cols-6">
            <label class="block">
                <span class="text-xs font-medium uppercase text-zinc-500">Data inicial</span>
                <input type="date" wire:model.live="filter_start_date" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="text-xs font-medium uppercase text-zinc-500">Data final</span>
                <input type="date" wire:model.live="filter_end_date" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="text-xs font-medium uppercase text-zinc-500">Cliente</span>
                <select wire:model.live="filter_client_id" class="mt-1 rounded border border-zinc-300 px-3 py-2 text-sm">
                    <option value="">Todos os clientes</option>
                    @foreach ($this->clients() as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-xs font-medium uppercase text-zinc-500">Projeto</span>
                <select wire:model.live="filter_project_id" class="mt-1 rounded border border-zinc-300 px-3 py-2 text-sm" @disabled(blank($filter_client_id))>
                    <option value="">{{ blank($filter_client_id) ? 'Selecione um cliente primeiro' : 'Todos os projetos' }}</option>
                    @foreach ($this->filterProjects() as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-xs font-medium uppercase text-zinc-500">Categoria</span>
                <select wire:model.live="filter_category_id" class="mt-1 rounded border border-zinc-300 px-3 py-2 text-sm">
                    <option value="">Todas as categorias</option>
                    @foreach ($this->categories() as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-xs font-medium uppercase text-zinc-500">Status</span>
                <select wire:model.live="filter_status" class="mt-1 rounded border border-zinc-300 px-3 py-2 text-sm">
                    <option value="">Todos os status</option>
                    @foreach ($this->statusFilterOptions() as $status => $label)
                        <option value="{{ $status }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    @php($records = $this->records())
    @php($statusColumns = $filter_status ? [$filter_status => $this->statusFilterOptions()[$filter_status]] : $this->statusFilterOptions())
    @php($recordsByStatus = $records->groupBy('status'))

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-zinc-500">{{ $records->count() }} tarefas encontradas</p>
        <div class="period-segmented" role="group" aria-label="Alternar visualizacao das tarefas">
            <button type="button" wire:click="setViewMode('table')" class="{{ $viewMode === 'table' ? 'is-active' : '' }}" aria-pressed="{{ $viewMode === 'table' ? 'true' : 'false' }}">Tabela</button>
            <button type="button" wire:click="setViewMode('board')" class="{{ $viewMode === 'board' ? 'is-active' : '' }}" aria-pressed="{{ $viewMode === 'board' ? 'true' : 'false' }}">Quadro</button>
            <button type="button" wire:click="setViewMode('gantt')" class="{{ $viewMode === 'gantt' ? 'is-active' : '' }}" aria-pressed="{{ $viewMode === 'gantt' ? 'true' : 'false' }}">Gantt</button>
        </div>
    </div>

    @if ($viewMode === 'table')
        <div class="rounded-lg border border-zinc-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                        <tr>
                            <th class="px-5 py-3">Data</th>
                            <th class="px-5 py-3">Tarefa</th>
                            <th class="px-5 py-3">Cliente</th>
                            <th class="px-5 py-3">Projeto</th>
                            <th class="px-5 py-3">Categoria</th>
                            <th class="px-5 py-3">Periodo</th>
                            <th class="px-5 py-3">Duracao</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($records as $record)
                            <tr>
                                <td class="px-5 py-3">{{ $record->work_date->format('d/m/Y') }}</td>
                                <td class="px-5 py-3 font-medium">{{ $record->title }}</td>
                                <td class="px-5 py-3">{{ $record->client->name }}</td>
                                <td class="px-5 py-3">{{ $record->project?->name ?? '-' }}</td>
                                <td class="px-5 py-3">
                                    @if ($record->category)
                                        <span class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded" style="background: {{ $record->category->color }}"></span>{{ $record->category->name }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-5 py-3">{{ $record->periodLabel() }}</td>
                                <td class="px-5 py-3">{{ $record->formattedDuration() }}</td>
                                <td class="px-5 py-3">@include('components.shared.status-badge', ['status' => $record->status, 'label' => $record->statusLabel()])</td>
                                <td class="action-menu-cell px-5 py-3 text-right">
                                    <div class="action-menu" data-action-menu>
                                        <button type="button" class="action-menu-trigger" data-action-menu-trigger aria-haspopup="menu" aria-expanded="false" aria-label="Abrir acoes">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M12 20h9" />
                                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                            </svg>
                                        </button>

                                        <div class="action-menu-panel" data-action-menu-panel role="menu">
                                            @if ($record->isInProgress())
                                                <button type="button" wire:click="pause({{ $record->id }})" class="action-menu-item text-amber-700" data-action-menu-item role="menuitem">Pausar</button>
                                                <button type="button" wire:click="finish({{ $record->id }})" class="action-menu-item text-emerald-700" data-action-menu-item role="menuitem">Finalizar</button>
                                            @elseif ($record->isPaused())
                                                <button type="button" wire:click="resume({{ $record->id }})" class="action-menu-item text-emerald-700" data-action-menu-item role="menuitem">Retomar</button>
                                                <button type="button" wire:click="finish({{ $record->id }})" class="action-menu-item" data-action-menu-item role="menuitem">Finalizar</button>
                                            @elseif ($record->isFinished())
                                                <button type="button" wire:click="resume({{ $record->id }})" class="action-menu-item text-emerald-700" data-action-menu-item role="menuitem">Voltar em andamento</button>
                                                <button type="button" wire:click="pause({{ $record->id }})" class="action-menu-item text-amber-700" data-action-menu-item role="menuitem">Voltar pausada</button>
                                            @endif

                                            <button type="button" wire:click="edit({{ $record->id }})" class="action-menu-item" data-action-menu-item role="menuitem">Editar</button>
                                            <button type="button" wire:click="delete({{ $record->id }})" wire:confirm="Excluir esta tarefa?" class="action-menu-item text-rose-700" data-action-menu-item role="menuitem">Excluir</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-5 py-8 text-center text-zinc-500">Nenhuma tarefa encontrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($viewMode === 'board')
        <div class="status-board">
            @foreach ($statusColumns as $status => $label)
                @php($items = $recordsByStatus->get($status, collect()))
                <div class="status-board-column" wire:key="task-column-{{ $status }}" data-task-drop-status="{{ $status }}" style="--status-color: {{ $this->statusColor($status) }}">
                    <div class="status-board-header">
                        @include('components.shared.status-badge', ['status' => $status, 'label' => $label])
                        <span>{{ $items->count() }}</span>
                    </div>

                    <div class="status-board-list">
                        @forelse ($items as $record)
                            <details class="work-card" wire:key="task-card-{{ $record->id }}" draggable="true" data-task-card data-task-id="{{ $record->id }}" data-task-status="{{ $record->status }}" style="--status-color: {{ $this->statusColor($record->status) }}">
                                <summary class="work-card-summary">
                                    <div>
                                        <p class="work-card-date">{{ $record->work_date->format('d/m/Y') }} · {{ $record->periodLabel() }}</p>
                                        <h3>{{ $record->title }}</h3>
                                    </div>
                                    <strong>{{ $record->formattedDuration() }}</strong>
                                </summary>

                                <div class="work-card-body">
                                    <div class="action-menu" data-action-menu>
                                        <button type="button" class="action-menu-trigger" data-action-menu-trigger aria-haspopup="menu" aria-expanded="false" aria-label="Abrir acoes">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M12 20h9" />
                                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                            </svg>
                                        </button>
                                        <div class="action-menu-panel" data-action-menu-panel role="menu">
                                            @if ($record->isInProgress())
                                                <button type="button" wire:click="pause({{ $record->id }})" class="action-menu-item text-amber-700" data-action-menu-item role="menuitem">Pausar</button>
                                                <button type="button" wire:click="finish({{ $record->id }})" class="action-menu-item text-emerald-700" data-action-menu-item role="menuitem">Finalizar</button>
                                            @elseif ($record->isPaused())
                                                <button type="button" wire:click="resume({{ $record->id }})" class="action-menu-item text-emerald-700" data-action-menu-item role="menuitem">Retomar</button>
                                                <button type="button" wire:click="finish({{ $record->id }})" class="action-menu-item" data-action-menu-item role="menuitem">Finalizar</button>
                                            @elseif ($record->isFinished())
                                                <button type="button" wire:click="resume({{ $record->id }})" class="action-menu-item text-emerald-700" data-action-menu-item role="menuitem">Voltar em andamento</button>
                                                <button type="button" wire:click="pause({{ $record->id }})" class="action-menu-item text-amber-700" data-action-menu-item role="menuitem">Voltar pausada</button>
                                            @endif
                                            <button type="button" wire:click="edit({{ $record->id }})" class="action-menu-item" data-action-menu-item role="menuitem">Editar</button>
                                            <button type="button" wire:click="delete({{ $record->id }})" wire:confirm="Excluir esta tarefa?" class="action-menu-item text-rose-700" data-action-menu-item role="menuitem">Excluir</button>
                                        </div>
                                    </div>

                                    <div class="work-card-meta">
                                        <span>{{ $record->client->name }}</span>
                                        <span>{{ $record->project?->name ?? 'Sem projeto' }}</span>
                                    </div>
                                    <div class="work-card-footer">
                                        <span>{{ $record->category?->name ?? 'Sem categoria' }}</span>
                                    </div>
                                </div>
                            </details>
                        @empty
                            <p class="status-board-empty">Sem tarefas neste status.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @else
        @php($gantt = $this->ganttData($records))
        <div class="gantt-panel rounded-lg border border-zinc-200 bg-white">
            <div class="gantt-toolbar">
                <div>
                    <h2 class="text-sm font-semibold">Linha do tempo das tarefas</h2>
                    <p class="mt-1 text-xs text-zinc-500">As barras representam os trechos trabalhados por data e horario.</p>
                </div>
                <span class="text-xs font-medium text-zinc-500">{{ $records->count() }} tarefas</span>
            </div>

            @if (empty($gantt['days']))
                <p class="px-5 py-8 text-center text-sm text-zinc-500">Nenhuma tarefa com periodo definido para exibir no Gantt.</p>
            @else
                <div class="gantt-scroll">
                    <div class="gantt-grid" style="--gantt-step: {{ 100 / max(1, count($gantt['labels']) - 1) }}%">
                        <div class="gantt-scale">
                            <div class="gantt-scale-spacer"></div>
                            <div class="gantt-scale-track">
                                @foreach ($gantt['labels'] as $label)
                                    <span style="left: {{ $label['left'] }}%">{{ $label['label'] }}</span>
                                @endforeach
                            </div>
                        </div>

                        @foreach ($gantt['days'] as $day)
                            <div class="gantt-day" wire:key="gantt-day-{{ $day['day'] }}">
                                <div class="gantt-day-label">
                                    <strong>{{ $day['label'] }}</strong>
                                    <span>{{ $day['weekday'] }}</span>
                                </div>
                                <div class="gantt-track">
                                    @foreach ($day['items'] as $item)
                                        <button
                                            type="button"
                                            wire:click="edit({{ $item['id'] }})"
                                            class="gantt-bar"
                                            wire:key="gantt-item-{{ $item['key'] }}"
                                            style="--bar-left: {{ $item['left'] }}%; --bar-width: {{ $item['width'] }}%; --bar-color: {{ $item['color'] }}"
                                            title="{{ $item['title'] }} · {{ $item['start'] }}-{{ $item['end'] }}"
                                        >
                                            <span>{{ $item['title'] }}</span>
                                            <small>{{ $item['start'] }}-{{ $item['end'] }} · {{ $item['duration'] }}</small>
                                            <em>{{ $item['client'] }} · {{ $item['project'] }}</em>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</section>
