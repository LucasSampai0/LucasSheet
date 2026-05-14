<?php

use App\Models\Client;
use Livewire\Component;

new class extends Component
{
    public ?int $editingId = null;
    public string $name = '';
    public string $description = '';
    public bool $active = true;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        Client::updateOrCreate(['id' => $this->editingId], $data);
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $client = Client::findOrFail($id);
        $this->editingId = $client->id;
        $this->name = $client->name;
        $this->description = $client->description ?? '';
        $this->active = $client->active;
    }

    public function toggle(int $id): void
    {
        $client = Client::findOrFail($id);
        $client->update(['active' => ! $client->active]);
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->active = true;
        $this->resetValidation();
    }

    public function clients()
    {
        return Client::withCount(['projects', 'workLogs'])->orderBy('name')->get();
    }
};
?>

<section class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Clientes</h1>
        <p class="mt-1 text-sm text-zinc-500">Cadastre quem recebe as tarefas e mantenha inativos fora dos filtros principais.</p>
    </div>

    <form wire:submit="save" class="rounded-lg border border-zinc-200 bg-white p-5">
        <div class="grid gap-4 lg:grid-cols-[1fr_2fr_auto]">
            <label class="block">
                <span class="text-sm font-medium">Nome</span>
                <input wire:model="name" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none" autofocus>
                @error('name') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="text-sm font-medium">Descricao</span>
                <input wire:model="description" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
            </label>
            <label class="flex items-end gap-2 pb-2 text-sm">
                <input type="checkbox" wire:model="active" class="rounded border-zinc-300">
                Ativo
            </label>
        </div>
        <div class="mt-4 flex gap-2">
            <button class="rounded bg-zinc-950 px-4 py-2 text-sm font-medium text-white">{{ $editingId ? 'Salvar alteracoes' : 'Criar cliente' }}</button>
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
                        <th class="px-5 py-3">Cliente</th>
                        <th class="px-5 py-3">Descricao</th>
                        <th class="px-5 py-3">Projetos</th>
                        <th class="px-5 py-3">Tarefas</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($this->clients() as $client)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $client->name }}</td>
                            <td class="px-5 py-3 text-zinc-600">{{ $client->description }}</td>
                            <td class="px-5 py-3">{{ $client->projects_count }}</td>
                            <td class="px-5 py-3">{{ $client->work_logs_count }}</td>
                            <td class="px-5 py-3">{{ $client->active ? 'Ativo' : 'Inativo' }}</td>
                            <td class="px-5 py-3 text-right">
                                <button wire:click="edit({{ $client->id }})" class="rounded border border-zinc-300 px-3 py-1.5 text-xs font-medium">Editar</button>
                                <button wire:click="toggle({{ $client->id }})" class="rounded border border-zinc-300 px-3 py-1.5 text-xs font-medium">{{ $client->active ? 'Desativar' : 'Ativar' }}</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
