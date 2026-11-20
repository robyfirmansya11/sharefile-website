<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffAccountStatus
{
    public function setActive(User $actor, User $target, bool $active): void
    {
        DB::transaction(function () use ($actor, $target, $active) {
            // Serialize admin changes using a consistent lock order.
            $admins = User::where('is_it_admin', true)->orderBy('id')->lockForUpdate()->get();
            $operator = $admins->firstWhere('id', $actor->id);
            abort_unless($operator && $operator->is_active && $operator->hasVerifiedEmail(), 403);
            $account = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if (! $active && $actor->is($account)) {
                throw ValidationException::withMessages(['is_active' => __('staff.cannot_disable_self')]);
            }
            if (! $active && $account->is_it_admin && ! $admins->contains(fn ($admin) => ! $admin->is($account) && $admin->is_active && $admin->hasVerifiedEmail())) {
                throw ValidationException::withMessages(['is_active' => __('staff.last_admin')]);
            }
            if ($account->is_active === $active) return;
            $account->is_active = $active;
            if (! $active) {
                $account->session_version++;
                $account->remember_token = Str::random(60);
                $account->invitation_token_hash = null;
                $account->invitation_email = null;
                DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))->where('email', $account->email)->delete();
                if (config('session.driver') === 'database') {
                    DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $account->id)->delete();
                }
            }
            $account->save();
        });
    }

    public function setAdmin(User $target, bool $admin): void
    {
        DB::transaction(function () use ($target, $admin) {
            $admins = User::where('is_it_admin', true)->orderBy('id')->lockForUpdate()->get();
            $account = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if (! $admin && $account->is_it_admin && ! $admins->contains(fn ($other) => ! $other->is($account) && $other->is_active && $other->hasVerifiedEmail())) {
                throw ValidationException::withMessages(['is_it_admin' => __('staff.last_admin')]);
            }
            $account->is_it_admin = $admin;
            $account->save();
        });
    }
}
