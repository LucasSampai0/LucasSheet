<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Login - {{ config('app.name', 'LucasSheet') }}</title>
        <script>
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.classList.add(savedTheme === 'light' ? 'light' : 'dark');
        </script>
        <style>
            html, body { background: #111113; color: #f4f4f5; }
            html.light, html.light body { background: #f7f7f8; color: #171717; }
        </style>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-zinc-50 text-zinc-950 antialiased">
        <main class="app-shell grid min-h-screen place-items-center px-4 py-10">
            <form method="POST" action="{{ route('login.store') }}" class="auth-card rounded-lg border border-zinc-200 bg-white p-6">
                @csrf

                <div class="mb-6 flex items-center justify-between gap-4">
                    <img src="{{ asset('images/logo.png') }}" alt="LucasSheet" class="h-10 w-auto max-w-40 object-contain">
                    <span class="rounded bg-pink-50 px-2 py-1 text-xs font-medium text-pink-700">acesso seguro</span>
                </div>

                <div>
                    <h1 class="text-2xl font-semibold">Entrar</h1>
                    <p class="mt-1 text-sm text-zinc-500">Informe o token de acesso configurado para este ambiente.</p>
                </div>

                @if (! config('lucassheet.access_token') && ! config('lucassheet.access_token_hash'))
                    <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Configure `APP_ACCESS_TOKEN` ou `APP_ACCESS_TOKEN_HASH` no `.env` antes de usar o sistema.
                    </div>
                @endif

                <label class="mt-5 block">
                    <span class="text-sm font-medium">Token</span>
                    <input
                        type="password"
                        name="token"
                        autocomplete="current-password"
                        autofocus
                        class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none"
                    >
                    @error('token') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>

                <button class="mt-5 w-full rounded bg-zinc-950 px-4 py-2 text-sm font-medium text-white">
                    Acessar sistema
                </button>
            </form>
        </main>
    </body>
</html>
