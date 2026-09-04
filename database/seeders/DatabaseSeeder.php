<?php

namespace Database\Seeders;

use App\Models\MasterCity;
use App\Models\MasterEducationDegree;
use App\Models\MasterEthnicGroup;
use App\Models\MasterReligion;
use App\Models\MasterSkill;
use App\Models\MasterTraining;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'user_type' => 'admin',
        ]);

        MasterReligion::create(['title' => 'Islam']);
        MasterReligion::create(['title' => 'Kristen']);
        MasterReligion::create(['title' => 'Katholik']);
        MasterReligion::create(['title' => 'Hindu']);
        MasterReligion::create(['title' => 'Budha']);

        MasterEthnicGroup::create(['title' => 'Sunda']);
        MasterEthnicGroup::create(['title' => 'Betawi']);
        MasterEthnicGroup::create(['title' => 'Jawa']);

        MasterCity::create(['title' => 'Kota Bandung']);

        MasterEducationDegree::create(['title' => 'SMP']);
        MasterEducationDegree::create(['title' => 'SMA']);
        MasterEducationDegree::create(['title' => 'MA']);
        MasterEducationDegree::create(['title' => 'MAN']);

        MasterSkill::create(['title' => 'Memasak']);
        MasterSkill::create(['title' => 'Memperbaiki Perangkat Elektronik']);

        MasterTraining::create(['title' => 'Pelatihan Reparasi']);
        MasterTraining::create(['title' => 'Pelatihan Memasak']);
    }
}
