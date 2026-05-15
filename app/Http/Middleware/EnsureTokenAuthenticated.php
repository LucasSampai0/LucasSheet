<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('access_token_authenticated') === true) {
            return $next($request);
        }

        if ($this->matchesConfiguredToken((string) $request->bearerToken())) {
            $request->session()->put('access_token_authenticated', true);

            return $next($request);
        }

        return redirect()->guest(route('login'));
    }

    private function matchesConfiguredToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $configuredHash = config('lucassheet.access_token_hash');

        if (filled($configuredHash)) {
            return hash_equals((string) $configuredHash, hash('sha256', $token));
        }

        $configuredToken = config('lucassheet.access_token');

        return filled($configuredToken) && hash_equals((string) $configuredToken, $token);
    }
}
