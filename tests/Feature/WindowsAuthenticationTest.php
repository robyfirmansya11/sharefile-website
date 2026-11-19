<?php

use App\Auth\WindowsUser;
use App\Models\User;
use App\Services\WindowsIdentity;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['share.driver' => 'windows', 'share.windows.netbios_domain' => 'ASI001']);
});

function iisIdentityForTest(): void
{
    // Only substitute the native runtime check; principal validation remains real.
    test()->partialMock(WindowsIdentity::class, function ($mock) {
        $mock->shouldReceive('assertRuntime')->andReturnNull();
    });
}

function iisServerVariables(string $principal = 'ASI001\\alice'): array
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
    $this->withHeaders(['LOGON_USER' => 'ASI001\\alice', 'AUTH_USER' => 'ASI001\\alice', 'AUTH_TYPE' => 'Negotiate'])->getJson('/dashboard')->assertUnauthorized();
});

it('uses IIS identity without creating a local account or requiring email verification', function () {
    iisIdentityForTest();
    $this->withServerVariables(iisServerVariables())->get('/dashboard')
        ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->where('auth.user.windows_principal', 'asi001\\alice')
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
})->with(['ASI01\\Administrator', 'OTHER\\alice', 'alice@other.example']);

it('rejects absent mismatched or non-Windows authentication', function (array $variables) {
    iisIdentityForTest();
    $this->withServerVariables($variables)->getJson('/dashboard')->assertUnauthorized();
})->with([
    [['AUTH_TYPE' => 'Negotiate', 'LOGON_USER' => 'ASI001\\alice', 'AUTH_USER' => 'ASI001\\bob']],
    [['AUTH_TYPE' => 'Basic', 'LOGON_USER' => 'ASI001\\alice', 'AUTH_USER' => 'ASI001\\alice']],
    [['AUTH_TYPE' => 'Negotiate', 'LOGON_USER' => 'alice', 'AUTH_USER' => 'alice']],
]);

it('accepts the configured UPN suffix and rejects blank domain configuration', function () {
    iisIdentityForTest();
    $this->withServerVariables(iisServerVariables('alice@artabumisentraindustri.com'))->get('/dashboard')->assertOk();
    config(['share.windows.netbios_domain' => '']);
    $this->withServerVariables(iisServerVariables())->getJson('/dashboard')->assertForbidden();
});

it('replaces the session when IIS supplies a different user', function () {
    iisIdentityForTest();
    $this->withSession(['windows_principal' => 'asi001\\bob', 'private_marker' => 'old-user'])
        ->withServerVariables(iisServerVariables())->get('/dashboard')
        ->assertOk()->assertSessionMissing('private_marker')->assertSessionHas('windows_principal', 'asi001\\alice');
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
