<?php

use App\Auth\WindowsUser;
use App\Models\User;
use App\Services\WindowsIdentity;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['share.driver' => 'windows', 'share.windows.domain' => 'corp.example.com', 'share.windows.netbios_domain' => 'EXAMPLE']);
});

function iisIdentityForTest(): void
{
    // Only substitute the native runtime check; principal validation remains real.
    test()->partialMock(WindowsIdentity::class, function ($mock) {
        $mock->shouldReceive('assertRuntime')->andReturnNull();
    });
}

function iisServerVariables(string $principal = 'EXAMPLE\\alice'): array
{
    return ['LOGON_USER' => $principal, 'AUTH_USER' => $principal, 'AUTH_TYPE' => 'Negotiate'];
}

it('refuses Windows mode under CLI even with claimed IIS variables', function () {
    $this->withServerVariables(iisServerVariables())->getJson('/dashboard')->assertStatus(503);
});

it('does not accept a local login as Windows authentication', function () {
    iisIdentityForTest();
    $this->actingAs(User::factory()->create())->getJson('/dashboard')->assertUnauthorized();
});

it('ignores user identity supplied as HTTP headers', function () {
    iisIdentityForTest();
    $this->withHeaders(['LOGON_USER' => 'EXAMPLE\\alice', 'AUTH_USER' => 'EXAMPLE\\alice', 'AUTH_TYPE' => 'Negotiate'])->getJson('/dashboard')->assertUnauthorized();
});

it('uses IIS identity without creating a local account or requiring email verification', function () {
    iisIdentityForTest();
    $this->withServerVariables(iisServerVariables())->get('/dashboard')
        ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->where('auth.user.windows_principal', 'example\\alice')
            ->where('share.driver', 'windows')->where('share.writesEnabled', false));
    expect(User::count())->toBe(0);
    expect(auth()->user())->toBeInstanceOf(WindowsUser::class);
});

it('redirects the entry pages to the Windows workspace', function () {
    iisIdentityForTest();
    $this->withServerVariables(iisServerVariables())->get('/login')->assertRedirect('/dashboard');
    $this->get('/')->assertRedirect('/dashboard');
});

it('blocks local identity management endpoints in Windows mode', function (string $path) {
    iisIdentityForTest();
    $this->withServerVariables(iisServerVariables())->get($path)->assertNotFound();
})->with(['/register', '/forgot-password', '/settings/profile', '/settings/security']);

it('rejects principals outside the configured domain', function (string $principal) {
    iisIdentityForTest();
    $this->withServerVariables(iisServerVariables($principal))->getJson('/dashboard')->assertForbidden();
})->with(['FILESERVER\\Administrator', 'OTHER\\alice', 'alice@other.example']);

it('rejects absent mismatched or non-Windows authentication', function (array $variables) {
    iisIdentityForTest();
    $this->withServerVariables($variables)->getJson('/dashboard')->assertUnauthorized();
})->with([
    [['AUTH_TYPE' => 'Negotiate', 'LOGON_USER' => 'EXAMPLE\\alice', 'AUTH_USER' => 'EXAMPLE\\bob']],
    [['AUTH_TYPE' => 'Basic', 'LOGON_USER' => 'EXAMPLE\\alice', 'AUTH_USER' => 'EXAMPLE\\alice']],
    [['AUTH_TYPE' => 'Negotiate', 'LOGON_USER' => 'alice', 'AUTH_USER' => 'alice']],
]);

it('accepts the configured UPN suffix and rejects blank domain configuration', function () {
    iisIdentityForTest();
    $this->withServerVariables(iisServerVariables('alice@corp.example.com'))->get('/dashboard')->assertOk();
    config(['share.windows.netbios_domain' => '']);
    $this->withServerVariables(iisServerVariables())->getJson('/dashboard')->assertForbidden();
});

it('replaces the session when IIS supplies a different user', function () {
    iisIdentityForTest();
    $this->withSession(['windows_principal' => 'example\\bob', 'private_marker' => 'old-user'])
        ->withServerVariables(iisServerVariables())->get('/dashboard')
        ->assertOk()->assertSessionMissing('private_marker')->assertSessionHas('windows_principal', 'example\\alice');
});

it('rejects a physical drive root before accessing any share', function () {
    iisIdentityForTest();
    config(['share.windows.root' => 'D:\\SMB']);
    $this->withServerVariables(iisServerVariables())->getJson('/share/entries')->assertStatus(503);
});

it('blocks file mutations in Windows read-only mode without accessing SMB', function (string $endpoint, array $payload) {
    iisIdentityForTest();
    config(['share.windows.writes_enabled' => false]);
    $this->withServerVariables(iisServerVariables())->postJson($endpoint, $payload)->assertForbidden();
})->with([
    ['/share/operations', ['action' => 'file', 'name' => 'test.txt']],
    ['/share/upload', []],
]);

it('blocks local password login and logout in Windows mode', function () {
    iisIdentityForTest();
    $this->withServerVariables(iisServerVariables())->postJson('/login', ['email' => 'local@example.test', 'password' => 'anything'])->assertNotFound();
    $this->postJson('/logout')->assertNotFound();
});
