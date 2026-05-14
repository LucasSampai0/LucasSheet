<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'LucasSheet') }}</title>
        <script>
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.classList.add(savedTheme === 'light' ? 'light' : 'dark');
        </script>
        <style>
            html, body { background: #111113; color: #f4f4f5; }
            html.light, html.light body { background: #f7f7f8; color: #171717; }
        </style>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-zinc-50 text-zinc-950 antialiased">
        <div class="app-shell min-h-screen lg:flex">
            <aside class="app-sidebar border-b border-zinc-200 bg-white lg:fixed lg:inset-y-0 lg:w-64 lg:border-b-0 lg:border-r">
                <div class="sidebar-brand flex h-16 items-center justify-between gap-3 px-5 lg:h-20">
                    <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3" wire:navigate>
                        <img src="{{ asset('images/logo.png') }}" alt="LucasSheet" class="h-10 w-auto max-w-36 object-contain">
                    </a>
                    <span class="rounded bg-pink-50 px-2 py-1 text-xs font-medium text-pink-700">local</span>
                </div>
                <nav class="flex gap-1 overflow-x-auto px-3 pb-3 lg:block lg:space-y-1 lg:overflow-visible">
                    @php
                        $links = [
                            ['dashboard', 'Dashboard', '/'],
                            ['clients', 'Clientes', '/clientes'],
                            ['projects', 'Projetos', '/projetos'],
                            ['categories', 'Categorias', '/categorias'],
                            ['work-logs', 'Tarefas', '/tarefas'],
                            ['reports', 'Relatorios', '/relatorios'],
                        ];
                    @endphp

                    @foreach ($links as [$route, $label, $path])
                        <a href="{{ route($route) }}" wire:navigate
                            class="nav-link block whitespace-nowrap rounded px-3 py-2 text-sm font-medium {{ request()->is(ltrim($path, '/') ?: '/') || request()->routeIs($route) ? 'is-active bg-zinc-950 text-white' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
                <div class="px-3 pb-4">
                    <button
                        type="button"
                        class="theme-toggle flex w-full items-center justify-between rounded border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm font-medium text-zinc-600"
                        data-theme-toggle
                        aria-label="Alternar tema"
                    >
                        <span data-theme-label>Modo escuro</span>
                        <span class="theme-dot h-3 w-3 rounded-full"></span>
                    </button>
                </div>
            </aside>

            <main class="app-main min-w-0 lg:ml-64 lg:flex-1">
                <div class="w-full px-4 py-6 sm:px-6 lg:px-8">
                    {{ $slot }}
                </div>
            </main>
        </div>
        @livewireScripts
    </body>
</html>
