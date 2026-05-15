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
                    <p class="mt-1 text-sm text-zinc-500">Use seu e-mail e senha para acessar o sistema.</p>
                </div>

                <label class="mt-5 block">
                    <span class="text-sm font-medium">E-mail</span>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', 'lucas.bueno@arkus.com.br') }}"
                        autocomplete="username"
                        autofocus
                        class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none"
                    >
                    @error('email') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="mt-4 block">
                    <span class="text-sm font-medium">Senha</span>
                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        class="mt-1 w-full rounded border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-950 focus:outline-none"
                    >
                    @error('password') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="mt-4 flex items-center gap-2 text-sm text-zinc-500">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-zinc-300">
                    <span>Manter conectado</span>
                </label>

                <button class="mt-5 w-full rounded bg-zinc-950 px-4 py-2 text-sm font-medium text-white">
                    Acessar sistema
                </button>
            </form>
        </main>
    </body>
</html>
