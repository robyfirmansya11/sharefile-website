<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class RequireItAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless(config('share.driver') === 'local' && $user instanceof User && $user->is_it_admin, 403);

        return $next($request);
    }
}
