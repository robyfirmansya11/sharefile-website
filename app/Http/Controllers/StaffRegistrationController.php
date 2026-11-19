<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StaffRegistrationController extends Controller
{
    use PasswordValidationRules, ProfileValidationRules;

    public function index(Request $request)
    {
        return Inertia::render('admin/Staff', [
            'staff' => User::query()->latest('id')->paginate(15, ['id', 'name', 'email', 'is_it_admin', 'email_verified_at', 'created_at']),
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
            'password' => $this->passwordRules(),
            // Admin privileges can only be assigned using the server command.
            'is_it_admin' => ['prohibited'],
            'role' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
        ]);
        $user = DB::transaction(fn () => User::create([
            'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
        ]));
        try {
            event(new Registered($user));
            $status = 'staff.created';
        } catch (\Throwable $exception) {
            // The account exists even if mail delivery fails. Never invite a duplicate retry.
            report($exception);
            $status = 'staff.created_mail_failed';
        }

        return to_route('admin.staff.index')->with('staff_status', $status);
    }
}
