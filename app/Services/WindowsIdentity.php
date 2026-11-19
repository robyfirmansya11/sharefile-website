<?php

namespace App\Services;

use Illuminate\Http\Request;

class WindowsIdentity
{
    public function assertRuntime(Request $request): void
    {
        abort_unless(PHP_OS_FAMILY === 'Windows' && PHP_SAPI === 'cgi-fcgi', 503, 'Mode AD harus dijalankan melalui IIS FastCGI di Windows.');
        abort_unless(str_starts_with((string) $request->server('SERVER_SOFTWARE'), 'Microsoft-IIS/'), 503, 'Windows Authentication memerlukan IIS.');
        abort_unless(filter_var(ini_get('fastcgi.impersonate'), FILTER_VALIDATE_BOOLEAN), 503, 'FastCGI impersonation belum aktif.');
        abort_unless($request->isSecure(), 503, 'Mode AD memerlukan HTTPS.');
    }

    public function principal(Request $request): string
    {
        $this->assertRuntime($request);
        // These are IIS server variables, never HTTP headers or request input.
        $principal = (string) $request->server('LOGON_USER', '');
        $authenticated = (string) $request->server('AUTH_USER', '');
        $method = strtolower((string) $request->server('AUTH_TYPE', ''));
        abort_unless(in_array($method, ['negotiate', 'ntlm', 'kerberos'], true), 401, 'Windows Authentication diperlukan.');
        abort_unless($principal !== '' && strcasecmp($principal, $authenticated) === 0, 401, 'Identitas IIS tidak valid.');
        abort_if(preg_match('/[\x00-\x20\x7f]/', $principal), 401, 'Identitas Windows tidak valid.');

        if (preg_match('/^([^\\\\]+)\\\\([^\\\\@]+)$/', $principal, $parts)) {
            $allowedDomain = (string) config('share.windows.netbios_domain');
            abort_unless($allowedDomain !== '' && strcasecmp($parts[1], $allowedDomain) === 0, 403, 'Domain Windows tidak diizinkan.');
        } elseif (preg_match('/^([^@\\\\]+)@([^@\\\\]+)$/', $principal, $parts)) {
            $allowedDomain = (string) config('share.windows.domain');
            abort_unless($allowedDomain !== '' && strcasecmp($parts[2], $allowedDomain) === 0, 403, 'Domain AD tidak diizinkan.');
        } else {
            abort(401, 'Gunakan identitas domain, bukan akun Windows lokal.');
        }

        return strtolower($principal);
    }
}
