<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessTokenController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('access_token_authenticated') === true) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        if (! $this->matchesConfiguredToken($validated['token'])) {
            return back()->withErrors(['token' => 'Token de acesso invalido.']);
        }

        $request->session()->regenerate();
        $request->session()->put('access_token_authenticated', true);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('access_token_authenticated');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function matchesConfiguredToken(string $token): bool
    {
        $configuredHash = config('lucassheet.access_token_hash');

        if (filled($configuredHash)) {
            return hash_equals((string) $configuredHash, hash('sha256', $token));
        }

        $configuredToken = config('lucassheet.access_token');

        return filled($configuredToken) && hash_equals((string) $configuredToken, $token);
    }
}
