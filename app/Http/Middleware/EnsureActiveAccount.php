<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (config('share.driver') === 'local' && $user instanceof User) {
            $current = User::find($user->id);
            if (! $current || ! $current->is_active || (int) $request->session()->get('account_version', 0) !== $current->session_version) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                if ($request->expectsJson()) return response()->json(['message' => __('staff.session_revoked')], 401);
                return redirect()->route('login')->with('status', __('staff.session_revoked'));
            }
        }

        return $next($request);
    }
}
