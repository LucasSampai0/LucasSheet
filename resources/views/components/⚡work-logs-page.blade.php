<?php

use App\Models\Category;
use App\Models\Client;
use App\Models\Project;
use App\Models\WorkLog;
use App\Services\ReportService;
use App\Services\WorkDuration;
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
    public string $title = '';
    public string $description = '';
    public string $status = WorkLog::STATUS_FINISHED;
    public bool $finishPrevious = false;
    public ?string $warning = null;

    public ?string $filter_date = null;
    public ?int $filter_client_id = null;
    public ?int $filter_project_id = null;
    public ?int $filter_category_id = null;

    public function mount(): void
    {
        $this->resetForm();
        $this->filter_date = now()->toDateString();
    }

    protected function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'work_date' => ['required', 'date'],
            'started_at' => ['required', 'date_format:H:i'],
            'ended_at' => ['nullable', 'date_format:H:i', 'after:started_at'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:in_progress,finished,cancelled'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();
        $this->ensureProjectBelongsToClient($data);

        if (blank($data['ended_at'])) {
            $data['status'] = WorkLog::STATUS_IN_PROGRESS;
        }

        $this->warning = WorkDuration::outsideBusinessHours($data['started_at'], $data['ended_at'])
            ? 'Registro salvo. O horario esta fora da janela comum de 08:00 a 18:00.'
            : null;

        WorkLog::updateOrCreate(['id' => $this->editingId], $data);
        $this->resetForm(keepWarning: true);
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

        if ($ongoing && ! $this->finishPrevious) {
            throw ValidationException::withMessages([
                'finishPrevious' => 'Ja existe uma tarefa em andamento. Marque a opcao para finalizar a anterior e iniciar uma nova.',
            ]);
        }

        if ($ongoing) {
            $ongoing->update([
                'ended_at' => now()->format('H:i'),
                'status' => WorkLog::STATUS_FINISHED,
            ]);
        }

        $data = [
            'client_id' => $this->client_id,
            'project_id' => $this->project_id,
            'category_id' => $this->category_id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->format('H:i'),
            'ended_at' => null,
            'title' => $this->title ?: 'Tarefa iniciada agora',
            'description' => $this->description,
            'status' => WorkLog::STATUS_IN_PROGRESS,
        ];

        $this->ensureProjectBelongsToClient($data);
        WorkLog::create($data);
        $this->resetForm();
    }

    public function finish(int $id): void
    {
        $record = WorkLog::findOrFail($id);
        $record->update([
            'ended_at' => now()->format('H:i'),
            'status' => WorkLog::STATUS_FINISHED,
        ]);
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
        $this->title = $record->title;
        $this->description = $record->description ?? '';
        $this->status = $record->status;
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
        $this->title = '';
        $this->description = '';
        $this->status = WorkLog::STATUS_FINISHED;
        $this->finishPrevious = false;
        $this->warning = $keepWarning ? $warning : null;
        $this->resetValidation();
    }

    public function records()
    {
        return app(ReportService::class)->filteredQuery([
            'date' => $this->filter_date,
            'client_id' => $this->filter_client_id,
            'project_id' => $this->filter_project_id,
            'category_id' => $this->filter_category_id,
        ])
            ->latest('work_date')
            ->latest('started_at')
            ->limit(80)
            ->get();
    }

    public function clients()
    {
        return Client::where('active', true)->orderBy('name')->get();
    }

    public function projects()
    {
        return Project::query()
            ->where('active', true)
            ->when($this->client_id, fn ($query) => $query->where('client_id', $this->client_id))
            ->orderBy('name')
            ->get();
    }

    public function filterProjects()
    {
        return Project::query()
            ->when($this->filter_client_id, fn ($query) => $query->where('client_id', $this->filter_client_id))
            ->orderBy('name')
            ->get();
    }

    public function categories()
    {
        return Category::where('active', true)->orderBy('name')->get();
    }

    private function ensureProjectBelongsToClient(array $data): void
    {
        if (! empty($data['project_id']) && Project::whereKey($data['project_id'])->where('client_id', $data['client_id'])->doesntExist()) {
            throw ValidationException::withMessages([
                'project_id' => 'O projeto selecionado nao pertence ao cliente informado.',
            ]);
        }
    }
};
?>

<section class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Registros de Trabalho</h1>
        <p class="mt-1 text-sm text-zinc-500">Registre tarefas manuais ou inicie uma nova atividade com o horario atual.</p>
    </div>

    @if ($warning)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $warning }}</div>
    @endif

    <form wire:submit="save" class="rounded-lg border border-zinc-200 bg-white p-5">
        <div class="grid gap-4 lg:grid-cols-4">
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
                <select wire:model="project_id" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                    <option value="">Sem projeto</option>
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
                <span class="text-sm font-medium">Data</span>
                <input type="date" wire:model="work_date" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                @error('work_date') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="text-sm font-medium">Inicio</span>
                <input type="time" wire:model="started_at" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                @error('started_at') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="text-sm font-medium">Fim</span>
                <input type="time" wire:model="ended_at" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                @error('ended_at') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="block lg:col-span-2">
                <span class="text-sm font-medium">Titulo</span>
                <input wire:model="title" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                @error('title') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="block lg:col-span-4">
                <span class="text-sm font-medium">Descricao</span>
                <textarea wire:model="description" rows="2" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none"></textarea>
            </label>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button class="rounded bg-zinc-950 px-4 py-2 text-sm font-medium text-white">{{ $editingId ? 'Salvar alteracoes' : 'Salvar registro' }}</button>
            <button type="button" wire:click="startNow" class="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Iniciar agora</button>
            @if ($editingId)
                <button type="button" wire:click="resetForm" class="rounded border border-zinc-300 px-4 py-2 text-sm font-medium">Cancelar</button>
            @endif
            <label class="ml-auto flex items-center gap-2 text-sm text-zinc-600">
                <input type="checkbox" wire:model="finishPrevious" class="rounded border-zinc-300">
                Finalizar tarefa anterior em andamento
            </label>
            @error('finishPrevious') <span class="basis-full text-xs text-rose-600">{{ $message }}</span> @enderror
        </div>
    </form>

    <div class="rounded-lg border border-zinc-200 bg-white p-5">
        <div class="grid gap-3 md:grid-cols-4">
            <input type="date" wire:model.live="filter_date" class="rounded border border-zinc-300 px-3 py-2 text-sm">
            <select wire:model.live="filter_client_id" class="rounded border border-zinc-300 px-3 py-2 text-sm">
                <option value="">Todos os clientes</option>
                @foreach ($this->clients() as $client)
                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="filter_project_id" class="rounded border border-zinc-300 px-3 py-2 text-sm">
                <option value="">Todos os projetos</option>
                @foreach ($this->filterProjects() as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="filter_category_id" class="rounded border border-zinc-300 px-3 py-2 text-sm">
                <option value="">Todas as categorias</option>
                @foreach ($this->categories() as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

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
                    @forelse ($this->records() as $record)
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
                            <td class="px-5 py-3">{{ substr($record->started_at, 0, 5) }} - {{ $record->ended_at ? substr($record->ended_at, 0, 5) : 'agora' }}</td>
                            <td class="px-5 py-3">{{ $record->formattedDuration() }}</td>
                            <td class="px-5 py-3">@include('components.shared.status-badge', ['status' => $record->status, 'label' => $record->statusLabel()])</td>
                            <td class="px-5 py-3 text-right">
                                @if ($record->isInProgress())
                                    <button wire:click="finish({{ $record->id }})" class="rounded border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700">Finalizar</button>
                                @endif
                                <button wire:click="edit({{ $record->id }})" class="rounded border border-zinc-300 px-3 py-1.5 text-xs font-medium">Editar</button>
                                <button wire:click="delete({{ $record->id }})" wire:confirm="Excluir este registro?" class="rounded border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-8 text-center text-zinc-500">Nenhum registro encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
