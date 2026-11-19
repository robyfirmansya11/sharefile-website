<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['share.driver' => 'local']);
    Event::fake([Registered::class]);
    $this->staffData = ['name' => 'New Staff', 'email' => 'staff@example.test', 'password' => 'TemporaryPassword123!', 'password_confirmation' => 'TemporaryPassword123!'];
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
    $this->actingAs($admin)->post('/admin/staff', [...$this->staffData, 'email' => 'STAFF@example.test'])->assertRedirect('/admin/staff')->assertSessionHas('staff_status', 'staff.created');
    $staff = User::where('email', 'staff@example.test')->firstOrFail();
    expect($staff->is_it_admin)->toBeFalse()->and($staff->email_verified_at)->toBeNull()->and(Hash::check($this->staffData['password'], $staff->password))->toBeTrue();
    $this->assertAuthenticatedAs($admin);
    Event::assertDispatched(Registered::class, fn ($event) => $event->user->is($staff));
    $this->get('/admin/staff')->assertInertia(fn (Assert $page) => $page->component('admin/Staff')->where('auth.can_manage_staff', true)->has('staff.data', 2)->missing('staff.data.0.password'));
});

it('rejects duplicate emails password mismatch and privilege injection', function () {
    $this->actingAs(User::factory()->create(['is_it_admin' => true]));
    User::factory()->create(['email' => 'staff@example.test']);
    $this->postJson('/admin/staff', $this->staffData)->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/admin/staff', [...$this->staffData, 'email' => 'other@example.test', 'password_confirmation' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson('/admin/staff', [...$this->staffData, 'email' => 'other@example.test', 'is_it_admin' => true, 'role' => 'admin', 'email_verified_at' => now()])->assertUnprocessable()->assertJsonValidationErrors(['is_it_admin', 'role', 'email_verified_at']);
    $this->assertDatabaseMissing('users', ['email' => 'other@example.test']);
    $this->postJson('/admin/staff', [...$this->staffData, 'email' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('keeps the account and reports a verification delivery failure', function () {
    $admin = User::factory()->create(['is_it_admin' => true]);
    $dispatcher = new \Illuminate\Events\Dispatcher(app());
    $dispatcher->listen(Registered::class, fn () => throw new \RuntimeException('Test mail failure'));
    Event::swap($dispatcher);
    $this->actingAs($admin)->post('/admin/staff', $this->staffData)->assertRedirect('/admin/staff')->assertSessionHas('staff_status', 'staff.created_mail_failed');
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
    $this->artisan('staff:admin', ['email' => $user->email, '--revoke' => true])->assertSuccessful();
    expect($user->fresh()->is_it_admin)->toBeFalse();
    $this->artisan('staff:admin', ['email' => 'missing@example.test'])->assertFailed();
});
