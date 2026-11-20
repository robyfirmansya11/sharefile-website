<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetItAdmin extends Command
{
    protected $signature = 'staff:admin {email : Existing application account} {--revoke : Remove IT administrator privileges}';
    protected $description = 'Assign or revoke the IT administrator role for an existing local account';

    public function handle(): int
    {
        if (config('share.driver') !== 'local') {
            $this->error('Perintah ini hanya untuk akun aplikasi lokal, bukan AD.');
            return self::FAILURE;
        }
        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($this->argument('email')))])->first();
        if (! $user) {
            $this->error('Akun tidak ditemukan. Tidak ada akun atau hak akses yang diubah.');
            return self::FAILURE;
        }
        try {
            app(\App\Services\StaffAccountStatus::class)->setAdmin($user, ! $this->option('revoke'));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->error(__('staff.last_admin'));
            return self::FAILURE;
        }
        $user->refresh();
        $this->info($user->is_it_admin ? 'Role admin IT telah diberikan.' : 'Role admin IT telah dicabut.');
        if (! $user->hasVerifiedEmail()) $this->warn('Akun masih harus memverifikasi email sebelum mengakses menu admin.');

        return self::SUCCESS;
    }
}
