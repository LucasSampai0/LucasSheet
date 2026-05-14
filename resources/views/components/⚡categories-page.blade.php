<?php

use App\Models\Category;
use Livewire\Component;

new class extends Component
{
    public ?int $editingId = null;
    public string $name = '';
    public string $color = '#EB088D';
    public bool $active = true;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'active' => ['boolean'],
        ];
    }

    public function save(): void
    {
        Category::updateOrCreate(['id' => $this->editingId], $this->validate());
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $category = Category::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->color = $category->color;
        $this->active = $category->active;
    }

    public function toggle(int $id): void
    {
        $category = Category::findOrFail($id);
        $category->update(['active' => ! $category->active]);
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->color = '#EB088D';
        $this->active = true;
        $this->resetValidation();
    }

    public function categories()
    {
        return Category::withCount('workLogs')->orderBy('name')->get();
    }
};
?>

<section class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Categorias</h1>
        <p class="mt-1 text-sm text-zinc-500">Use cores para reconhecer tipos de trabalho nos relatorios.</p>
    </div>

    <form wire:submit="save" class="rounded-lg border border-zinc-200 bg-white p-5">
        <div class="grid gap-4 md:grid-cols-[1fr_auto_auto]">
            <label class="block">
                <span class="text-sm font-medium">Nome</span>
                <input wire:model="name" class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none">
                @error('name') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="text-sm font-medium">Cor</span>
                <input type="color" wire:model="color" class="mt-1 h-10 w-20 rounded border border-zinc-300 p-1">
                @error('color') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="flex items-end gap-2 pb-2 text-sm">
                <input type="checkbox" wire:model="active" class="rounded border-zinc-300">
                Ativa
            </label>
        </div>
        <div class="mt-4 flex gap-2">
            <button class="rounded bg-zinc-950 px-4 py-2 text-sm font-medium text-white">{{ $editingId ? 'Salvar alteracoes' : 'Criar categoria' }}</button>
            @if ($editingId)
                <button type="button" wire:click="resetForm" class="rounded border border-zinc-300 px-4 py-2 text-sm font-medium">Cancelar</button>
            @endif
        </div>
    </form>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->categories() as $category)
            <div class="rounded-lg border border-zinc-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-4 w-4 rounded" style="background: {{ $category->color }}"></span>
                            <h2 class="font-semibold">{{ $category->name }}</h2>
                        </div>
                        <p class="mt-1 text-sm text-zinc-500">{{ $category->work_logs_count }} tarefas</p>
                    </div>
                    <span class="text-xs font-medium {{ $category->active ? 'text-emerald-700' : 'text-zinc-500' }}">{{ $category->active ? 'Ativa' : 'Inativa' }}</span>
                </div>
                <div class="mt-4 flex gap-2">
                    <button wire:click="edit({{ $category->id }})" class="rounded border border-zinc-300 px-3 py-1.5 text-xs font-medium">Editar</button>
                    <button wire:click="toggle({{ $category->id }})" class="rounded border border-zinc-300 px-3 py-1.5 text-xs font-medium">{{ $category->active ? 'Desativar' : 'Ativar' }}</button>
                </div>
            </div>
        @endforeach
    </div>
</section>
