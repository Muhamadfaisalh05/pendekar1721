<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun demo untuk tiga role (dipakai lokal/uji coba).
 *
 * Jalankan: php artisan db:seed --class=RoleAccountSeeder
 *
 * Password di-hash otomatis oleh cast `password => hashed` pada model User.
 * Jangan dipakai di produksi tanpa mengganti password.
 */
class RoleAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Administrator Pendekar',
                'email' => 'admin@pendekar1721.test',
                'password' => 'Admin#1721',
                'user_type' => User::ROLE_ADMIN,
            ],
            [
                'name' => 'Admin Absensi SEMARAK',
                'email' => 'absensi@pendekar1721.test',
                'password' => 'Absensi#1721',
                'user_type' => User::ROLE_ADMIN_ABSENSI,
            ],
            [
                'name' => 'Klien Pendekar',
                'email' => 'klien@pendekar1721.test',
                'password' => 'Klien#1721',
                'user_type' => User::ROLE_CLIENT,
            ],
        ];

        foreach ($accounts as $account) {
            User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'user_type' => $account['user_type'],
                    'status' => UserStatus::Published,
                    'email_verified_at' => now(),
                ],
            );

            $this->command?->info(sprintf(
                'Akun %s siap: %s / %s',
                $account['user_type'],
                $account['email'],
                $account['password'],
            ));
        }
    }
}
