<?php

namespace App\Http\Controllers;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StaffRegistrationController extends Controller
{
    use ProfileValidationRules;

    public function index(Request $request)
    {
        return Inertia::render('admin/Staff', [
            'staff' => User::query()->latest('id')->paginate(15, ['id', 'name', 'email', 'is_it_admin', 'is_active', 'email_verified_at', 'created_at']),
            'status' => $request->session()->get('staff_status'),
        ]);
    }

    public function store(Request $request)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            ...$this->profileRules(),
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
            // Admin privileges can only be assigned using the server command.
            'is_it_admin' => ['prohibited'],
            'role' => ['prohibited'],
            'is_active' => ['prohibited'],
            'session_version' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'invitation_token_hash' => ['prohibited'],
            'invitation_email' => ['prohibited'],
        ]);
        $user = DB::transaction(fn () => User::create([
            'name' => $data['name'], 'email' => $data['email'], 'password' => bin2hex(random_bytes(32)),
        ]));

        return $this->invite($user);
    }

    public function resend(User $user)
    {
        abort_if($user->hasVerifiedEmail() || ! $user->is_active, 409);

        return $this->invite($user);
    }

    private function invite(User $user)
    {
        try {
            $result = Password::sendResetLink(['email' => $user->email], function (User $recipient, string $token) {
                $recipient->forceFill([
                    'invitation_token_hash' => hash('sha256', $token),
                    'invitation_email' => $recipient->email,
                ])->save();
                $recipient->notify(new StaffInvitation($token));
            });
            $status = $result === Password::RESET_LINK_SENT
                ? (config('mail.default') === 'smtp' ? 'staff.invited' : 'staff.invited_local')
                : 'staff.invite_wait';
        } catch (\Throwable $exception) {
            // Avoid logging transport details, credentials, or invitation tokens.
            Log::warning('Staff invitation delivery failed.', ['user_id' => $user->id, 'exception_type' => get_class($exception)]);
            $status = 'staff.invite_failed';
        }

        return to_route('admin.staff.index')->with('staff_status', $status);
    }

    public function status(Request $request, User $user, \App\Services\StaffAccountStatus $accounts)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $accounts->setActive($request->user(), $user, (bool) $data['is_active']);
        return to_route('admin.staff.index')->with('staff_status', $data['is_active'] ? 'staff.enabled' : 'staff.disabled');
    }
}
