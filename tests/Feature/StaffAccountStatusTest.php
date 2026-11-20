<?php

use App\Models\User;
use App\Services\StaffAccountStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    config(['share.driver' => 'local']);
    $this->admin = User::factory()->create(['is_it_admin' => true]);
    $this->staff = User::factory()->create();
});

it('restricts status changes to verified active IT administrators', function () {
    $url = '/admin/staff/'.$this->staff->id.'/status';
    $this->patchJson($url, ['is_active' => false])->assertUnauthorized();
    $this->actingAs($this->staff)->patchJson($url, ['is_active' => false])->assertForbidden();
    $this->actingAs($this->admin)->patchJson($url, ['is_active' => 'invalid'])->assertUnprocessable();
    expect($this->staff->fresh()->is_active)->toBeTrue();
});

it('deactivates without deleting the account and invalidates reset tokens', function () {
    $token = Password::createToken($this->staff);
    $this->actingAs($this->admin)->patch('/admin/staff/'.$this->staff->id.'/status', ['is_active' => false])->assertRedirect('/admin/staff');
    expect($this->staff->fresh()->is_active)->toBeFalse()->and($this->staff->fresh()->session_version)->toBe(1)->and(Password::tokenExists($this->staff, $token))->toBeFalse();
    $this->patch('/admin/staff/'.$this->staff->id.'/status', ['is_active' => true])->assertRedirect();
    expect($this->staff->fresh()->is_active)->toBeTrue()->and($this->staff->fresh()->session_version)->toBe(1);
});

it('denies password login and post-authentication login for inactive accounts', function () {
    $this->staff->forceFill(['is_active' => false])->save();
    $this->post('/login', ['email' => $this->staff->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
    // Passkey and two-factor flows use the same guard Login event.
    expect(fn () => Auth::guard('web')->login($this->staff))->toThrow(\Illuminate\Validation\ValidationException::class);
    $this->assertGuest();
});

it('rejects active sessions and prevents old sessions returning after reactivation', function () {
    app(StaffAccountStatus::class)->setActive($this->admin, $this->staff, false);
    $this->actingAs($this->staff)->withSession(['account_version' => 0])->getJson('/share/entries')->assertUnauthorized();
    $this->assertGuest();
    app(StaffAccountStatus::class)->setActive($this->admin, $this->staff, true);
    $this->actingAs($this->staff->fresh())->withSession(['account_version' => 0])->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
    $this->post('/login', ['email' => $this->staff->email, 'password' => 'password'])->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertOk();
});

it('protects self deactivation and the last usable administrator', function () {
    $this->actingAs($this->admin)->patchJson('/admin/staff/'.$this->admin->id.'/status', ['is_active' => false])->assertUnprocessable();
    $this->artisan('staff:admin', ['email' => $this->admin->email, '--revoke' => true])->assertFailed();
    User::factory()->unverified()->create(['is_it_admin' => true]);
    User::factory()->create(['is_it_admin' => true, 'is_active' => false]);
    $this->artisan('staff:admin', ['email' => $this->admin->email, '--revoke' => true])->assertFailed();
    $other = User::factory()->create(['is_it_admin' => true]);
    $this->patchJson('/admin/staff/'.$other->id.'/status', ['is_active' => false])->assertRedirect('/admin/staff');
});

it('does not reactivate through password reset or resend invitations to disabled accounts', function () {
    $this->staff->forceFill(['is_active' => false, 'email_verified_at' => null])->save();
    $this->actingAs($this->admin)->post('/admin/staff/'.$this->staff->id.'/invitation')->assertConflict();
    Auth::logout();
    $token = Password::createToken($this->staff);
    $this->post('/reset-password', ['token' => $token, 'email' => $this->staff->email, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertSessionHasErrors('email');
    expect($this->staff->fresh()->is_active)->toBeFalse();
});
