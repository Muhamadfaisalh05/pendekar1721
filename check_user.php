<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'zaqiyyahnurazizah@gmail.com')->first();
if($user) {
    echo "user_type: " . $user->user_type . "\n";
} else {
    echo "User not found\n";
}
