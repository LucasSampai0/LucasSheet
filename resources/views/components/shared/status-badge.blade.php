@php
    $classes = match ($status) {
        'finished' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'cancelled' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        default => 'bg-amber-50 text-amber-700 ring-amber-600/20',
    };
@endphp

<span class="inline-flex items-center rounded px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $classes }}">
    {{ $label }}
</span>
