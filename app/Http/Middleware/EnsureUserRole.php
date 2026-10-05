<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Allow only users whose normalized role matches one of the given roles.
     * Wrong-role users are sent to their own dashboard.
     *
     * @param  string  ...$roles  Route role prefixes (owner, manager, staff)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $userRole = User::routeRole($user->role);
        $allowed = array_map(
            static fn (string $role): string => User::routeRole($role),
            $roles
        );

        if (in_array($userRole, $allowed, true)) {
            return $next($request);
        }

        $dashboard = match ($userRole) {
            'owner' => 'owner.dashboard',
            'manager' => 'manager.dashboard',
            'staff' => 'staff.dashboard',
            default => null,
        };

        if ($dashboard && Route::has($dashboard)) {
            return redirect()->route($dashboard);
        }

        abort(403, 'Unauthorized role.');
    }
}
