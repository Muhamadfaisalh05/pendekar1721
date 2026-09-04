<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Ubah admin
$admin = User::where('user_type', 'admin')->first();
if ($admin) {
    $admin->email = 'admin@admin.com';
    $admin->password = Hash::make('admin123');
    $admin->save();
    echo "AKUN ADMIN:\nEmail: admin@admin.com\nPassword: admin123\n\n";
} else {
    echo "Akun admin tidak ditemukan.\n\n";
}

// Ubah satu klien agar mudah login
$client = User::where('user_type', 'client')->first();
if ($client) {
    $client->password = Hash::make('klien123');
    $client->save();
    echo "AKUN KLIEN (Untuk testing):\nEmail: {$client->email}\nPassword: klien123\n";
} else {
    echo "Akun klien tidak ditemukan.\n";
}
