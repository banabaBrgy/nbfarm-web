<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (strtolower((string) $user->status) === 'inactive' || $this->sessionWasRevoked($request, $user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Please sign in again.');
        }

        return $next($request);
    }

    private function sessionWasRevoked(Request $request, $user): bool
    {
        $current = (int) $user->session_version;

        if (! $request->session()->has('session_version')) {
            if ($current === 0) {
                $request->session()->put('session_version', 0);

                return false;
            }

            return true;
        }

        return (int) $request->session()->get('session_version') !== $current;
    }
}
