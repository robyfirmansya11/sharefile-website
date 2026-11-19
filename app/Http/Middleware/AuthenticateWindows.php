<?php

namespace App\Http\Middleware;

use App\Auth\WindowsUser;
use App\Services\WindowsIdentity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWindows
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('share.driver') !== 'windows') {
            return $next($request);
        }

        $principal = app(WindowsIdentity::class)->principal($request);
        if ($request->session()->get('windows_principal') !== $principal) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('windows_principal', $principal);
        }
        // No Laravel login/remember cookie can supply the Windows identity.
        Auth::guard('web')->setUser(new WindowsUser($principal));

        if ($request->is('/') || ($request->is('login') && $request->isMethod('GET'))) {
            return redirect('/dashboard');
        }

        // Local password, registration, passkeys, settings and logout do not apply to SSO.
        abort_unless($request->is('dashboard', 'share/*'), 404);

        return $next($request);
    }
}
