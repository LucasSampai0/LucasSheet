<?php

use App\Models\Client;
use App\Models\Project;
use Livewire\Component;

new class extends Component
{
    public ?int $editingId = null;
    public ?int $client_id = null;
    public string $name = '';
    public string $description = '';
    public bool $active = true;

    protected function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
        ];
    }

    public function save(): void
    {
        Project::updateOrCreate(['id' => $this->editingId], $this->validate());
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $project = Project::findOrFail($id);
        $this->editingId = $project->id;
        $this->client_id = $project->client_id;
        $this->name = $project->name;
        $this->description = $project->description ?? '';
        $this->active = $project->active;
    }

    public function toggle(int $id): void
    {
        $project = Project::findOrFail($id);
        $project->update(['active' => ! $project->active]);
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->client_id = null;
        $this->name = '';
        $this->description = '';
        $this->active = true;
        $this->resetValidation();
    }

    public function clients()
    {
        return Client::orderBy('name')->get();
    }

    public function projects()
    {
        return Project::with(['client'])->withCount('workLogs')->orderBy('name')->get();
    }
};
?>

<section class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Projetos</h1>
        <p class="mt-1 text-sm text-zinc-500">Cada projeto pertence a um cliente e pode ser usado nos filtros dos registros.</p>
    </div>

    <form wire:submit="save" class="rounded-lg border border-zinc-200 bg-white p-5">
        <div class="grid gap-4 lg:grid-cols-2">
            <label class="block">
                <span class="text-sm font-medium">Cliente</span>
                <select wire:model="client_id" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                    <option value="">Selecione</option>
                    @foreach ($this->clients() as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </select>
                @error('client_id') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="text-sm font-medium">Nome</span>
                <input wire:model="name" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                @error('name') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="block lg:col-span-2">
                <span class="text-sm font-medium">Descricao</span>
                <input wire:model="description" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="active" class="rounded border-zinc-300">
                Ativo
            </label>
        </div>
        <div class="mt-4 flex gap-2">
            <button class="rounded bg-zinc-950 px-4 py-2 text-sm font-medium text-white">{{ $editingId ? 'Salvar alteracoes' : 'Criar projeto' }}</button>
            @if ($editingId)
                <button type="button" wire:click="resetForm" class="rounded border border-zinc-300 px-4 py-2 text-sm font-medium">Cancelar</button>
            @endif
        </div>
    </form>

    <div class="rounded-lg border border-zinc-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="px-5 py-3">Projeto</th>
                        <th class="px-5 py-3">Cliente</th>
                        <th class="px-5 py-3">Descricao</th>
                        <th class="px-5 py-3">Registros</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($this->projects() as $project)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $project->name }}</td>
                            <td class="px-5 py-3">{{ $project->client->name }}</td>
                            <td class="px-5 py-3 text-zinc-600">{{ $project->description }}</td>
                            <td class="px-5 py-3">{{ $project->work_logs_count }}</td>
                            <td class="px-5 py-3">{{ $project->active ? 'Ativo' : 'Inativo' }}</td>
                            <td class="px-5 py-3 text-right">
                                <button wire:click="edit({{ $project->id }})" class="rounded border border-zinc-300 px-3 py-1.5 text-xs font-medium">Editar</button>
                                <button wire:click="toggle({{ $project->id }})" class="rounded border border-zinc-300 px-3 py-1.5 text-xs font-medium">{{ $project->active ? 'Desativar' : 'Ativar' }}</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
