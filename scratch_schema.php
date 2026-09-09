<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "users: " . implode(', ', Schema::getColumnListing('users')) . "\n";
echo "user_profiles: " . implode(', ', Schema::getColumnListing('user_profiles')) . "\n";
echo "master_trainings: " . implode(', ', Schema::getColumnListing('master_trainings')) . "\n";
echo "master_cities: " . implode(', ', Schema::getColumnListing('master_cities')) . "\n";
echo "master_education_degrees: " . implode(', ', Schema::getColumnListing('master_education_degrees')) . "\n";
echo "user_trainings: " . implode(', ', Schema::getColumnListing('user_trainings')) . "\n";
echo "user_work_locations: " . implode(', ', Schema::getColumnListing('user_work_locations')) . "\n";
echo "user_experiences: " . implode(', ', Schema::getColumnListing('user_experiences')) . "\n";
