<?php

use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['share.driver' => 'local']);
    Notification::fake();
    $this->staffData = ['name' => 'New Staff', 'email' => 'staff@example.test'];
});

it('rejects guests and ordinary users on both admin endpoints', function () {
    $this->get('/admin/staff')->assertRedirect('/login');
    $this->postJson('/admin/staff', $this->staffData)->assertUnauthorized();
    $this->actingAs(User::factory()->create());
    $this->get('/admin/staff')->assertForbidden();
    $this->postJson('/admin/staff', $this->staffData)->assertForbidden();
    $this->assertDatabaseMissing('users', ['email' => 'staff@example.test']);
});

it('requires email verification even for an IT administrator', function () {
    $this->actingAs(User::factory()->unverified()->create(['is_it_admin' => true]));
    $this->get('/admin/staff')->assertRedirect('/email/verify');
    $this->postJson('/admin/staff', $this->staffData)->assertForbidden();
});

it('allows IT to register staff without changing the admin session or granting privileges', function () {
    $admin = User::factory()->create(['is_it_admin' => true]);
    $this->actingAs($admin)->post('/admin/staff', [...$this->staffData, 'email' => 'STAFF@example.test'])->assertRedirect('/admin/staff')->assertSessionHas('staff_status', 'staff.invited_local');
    $staff = User::where('email', 'staff@example.test')->firstOrFail();
    expect($staff->is_it_admin)->toBeFalse()->and($staff->email_verified_at)->toBeNull();
    $this->assertAuthenticatedAs($admin);
    Notification::assertSentTo($staff, StaffInvitation::class);
    $this->get('/admin/staff')->assertInertia(fn (Assert $page) => $page->component('admin/Staff')->where('auth.can_manage_staff', true)->has('staff.data', 2)->missing('staff.data.0.password'));
});

it('rejects duplicate emails password mismatch and privilege injection', function () {
    $this->actingAs(User::factory()->create(['is_it_admin' => true]));
    User::factory()->create(['email' => 'staff@example.test']);
    $this->postJson('/admin/staff', $this->staffData)->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/admin/staff', [...$this->staffData, 'email' => 'other@example.test', 'password' => 'not-allowed'])->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson('/admin/staff', [...$this->staffData, 'email' => 'other@example.test', 'is_it_admin' => true, 'role' => 'admin', 'email_verified_at' => now()])->assertUnprocessable()->assertJsonValidationErrors(['is_it_admin', 'role', 'email_verified_at']);
    $this->assertDatabaseMissing('users', ['email' => 'other@example.test']);
    $this->postJson('/admin/staff', [...$this->staffData, 'email' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('keeps the account and reports a verification delivery failure', function () {
    $admin = User::factory()->create(['is_it_admin' => true]);
    Notification::shouldReceive('send')->andThrow(new \RuntimeException('Test mail failure'));
    $this->actingAs($admin)->post('/admin/staff', $this->staffData)->assertRedirect('/admin/staff')->assertSessionHas('staff_status', 'staff.invite_failed');
    $this->assertDatabaseHas('users', ['email' => 'staff@example.test', 'is_it_admin' => false, 'email_verified_at' => null]);
    $this->assertAuthenticatedAs($admin);
});

it('hides management capability from staff and honors revocation', function () {
    $user = User::factory()->create(['is_it_admin' => true]);
    $user->is_it_admin = false;
    $user->save();
    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.can_manage_staff', false));
    $this->get('/admin/staff')->assertForbidden();
});

it('assigns admin only through an explicit existing-account command', function () {
    $user = User::factory()->create();
    $this->artisan('staff:admin', ['email' => $user->email])->assertSuccessful();
    expect($user->fresh()->is_it_admin)->toBeTrue();
    $this->artisan('staff:admin', ['email' => $user->email, '--revoke' => true])->assertFailed();
    User::factory()->create(['is_it_admin' => true]);
    $this->artisan('staff:admin', ['email' => $user->email, '--revoke' => true])->assertSuccessful();
    expect($user->fresh()->is_it_admin)->toBeFalse();
    $this->artisan('staff:admin', ['email' => 'missing@example.test'])->assertFailed();
});

it('verifies the invited email when a valid single-use token sets the password', function () {
    $admin = User::factory()->create(['is_it_admin' => true]);
    $this->actingAs($admin)->post('/admin/staff', $this->staffData)->assertRedirect();
    $staff = User::where('email', $this->staffData['email'])->firstOrFail();
    $notification = Notification::sent($staff, StaffInvitation::class)->first();
    expect(Password::tokenExists($staff, $notification->token))->toBeTrue();
    $this->post('/admin/staff/'.$staff->id.'/invitation')->assertSessionHas('staff_status', 'staff.invite_wait');
    auth()->logout();
    $payload = ['email' => $staff->email, 'token' => $notification->token, 'password' => 'NewPrivatePassword123!', 'password_confirmation' => 'NewPrivatePassword123!'];
    $this->post('/reset-password', $payload)->assertSessionHasNoErrors();
    expect(Hash::check($payload['password'], $staff->fresh()->password))->toBeTrue()->and($staff->fresh()->email_verified_at)->not->toBeNull();
    expect($staff->fresh()->invitation_token_hash)->toBeNull();
    expect(Password::tokenExists($staff, $notification->token))->toBeFalse();
    $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
});

it('protects invitation resends and invalidates old tokens after a resend', function () {
    $staff = User::factory()->unverified()->create();
    $this->actingAs($staff)->post('/admin/staff/'.$staff->id.'/invitation')->assertRedirect('/email/verify');
    $ordinary = User::factory()->create();
    $this->actingAs($ordinary)->post('/admin/staff/'.$staff->id.'/invitation')->assertForbidden();
    $this->actingAs(User::factory()->create(['is_it_admin' => true]));
    $this->post('/admin/staff/'.$ordinary->id.'/invitation')->assertConflict();
    $old = Password::createToken($staff);
    $this->travel(61)->seconds();
    $this->post('/admin/staff/'.$staff->id.'/invitation')->assertSessionHas('staff_status', 'staff.invited_local');
    expect(Password::tokenExists($staff, $old))->toBeFalse();
    $new = Notification::sent($staff, StaffInvitation::class)->first()->token;
    $this->travel(61)->minutes();
    expect(Password::tokenExists($staff, $new))->toBeFalse();
});

it('builds translated invitation links from the configured application URL', function () {
    config(['app.url' => 'https://share.example.test']);
    app()->setLocale('zh-CN');
    $user = User::factory()->create();
    $mail = (new StaffInvitation('test-token'))->toMail($user);
    expect($mail->subject)->toBe('Sharefolder 账户邀请')
        ->and($mail->actionUrl)->toStartWith('https://share.example.test/reset-password/test-token')
        ->and($mail->actionUrl)->toContain('email=');
});

it('does not verify an ordinary reset or a reset replacing an invitation', function () {
    $user = User::factory()->unverified()->create();
    $old = Password::createToken($user);
    $user->forceFill(['invitation_token_hash' => hash('sha256', $old), 'invitation_email' => $user->email])->save();
    $token = Password::createToken($user);
    $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'NewPrivatePassword123!', 'password_confirmation' => 'NewPrivatePassword123!'])->assertSessionHasNoErrors();
    expect($user->fresh()->email_verified_at)->toBeNull()->and($user->fresh()->invitation_token_hash)->toBeNull();
});

it('does not verify on invalid tokens or failed password validation', function () {
    $user = User::factory()->unverified()->create();
    $token = Password::createToken($user);
    $user->forceFill(['invitation_token_hash' => hash('sha256', $token), 'invitation_email' => $user->email])->save();
    $data = ['email' => $user->email, 'token' => 'invalid', 'password' => 'NewPrivatePassword123!', 'password_confirmation' => 'NewPrivatePassword123!'];
    $this->post('/reset-password', $data)->assertSessionHasErrors('email');
    $this->post('/reset-password', [...$data, 'token' => $token, 'password_confirmation' => 'wrong'])->assertSessionHasErrors('password');
    expect($user->fresh()->email_verified_at)->toBeNull()->and(Password::tokenExists($user, $token))->toBeTrue();
});

it('does not use proof issued for a different email address', function () {
    $user = User::factory()->unverified()->create();
    $token = Password::createToken($user);
    $user->forceFill(['invitation_token_hash' => hash('sha256', $token), 'invitation_email' => 'previous@example.test'])->save();
    $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'NewPrivatePassword123!', 'password_confirmation' => 'NewPrivatePassword123!'])->assertSessionHasNoErrors();
    expect($user->fresh()->email_verified_at)->toBeNull();
});
