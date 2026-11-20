<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        if (! $user->fresh()->is_active) {
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $token = $input['token'] ?? null;
        $acceptInvitation = ! $user->hasVerifiedEmail()
            && is_string($token)
            && is_string($user->invitation_token_hash)
            && $user->invitation_email === $user->email
            && hash_equals($user->invitation_token_hash, hash('sha256', $token))
            && Password::tokenExists($user, $token);

        $attributes = [
            'password' => $input['password'],
            'invitation_token_hash' => null,
            'invitation_email' => null,
        ];
        if ($acceptInvitation) $attributes['email_verified_at'] = now();
        $user->forceFill($attributes)->save();
        if ($acceptInvitation) event(new Verified($user));
    }
}
