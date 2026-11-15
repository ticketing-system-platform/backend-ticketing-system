<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;

/**
 * Ganti password akun lewat terminal, mis. akun demo hasil seeder setelah deploy.
 * Password diketik tersembunyi, jadi tidak tersimpan di riwayat terminal.
 *
 * Contoh: php artisan user:password admin@tiket.test
 */
#[Signature('user:password {email : Email akun yang password-nya diganti}')]
#[Description('Ganti password sebuah akun')]
class ChangeUserPassword extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Akun tidak ditemukan.');

            return self::FAILURE;
        }

        $password = password(
            label: "Password baru untuk {$user->email}",
            required: true,
            validate: fn (string $value) => strlen($value) < 8 ? 'Minimal 8 karakter.' : null,
        );

        $user->update(['password' => $password]); // otomatis di-hash oleh cast 'hashed'

        // Hapus token lama: siapa pun yang sudah login dengan password lama harus login ulang
        $user->tokens()->delete();

        $this->info('Password berhasil diganti.');

        return self::SUCCESS;
    }
}
