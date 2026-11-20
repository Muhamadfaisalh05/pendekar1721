<?php

namespace Tests\Feature\Front;

use App\Enums\UserStatus;
use App\Models\MasterSkill;
use App\Models\MasterTraining;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Konten Pelatihan/Keterampilan yang diisi lewat CRUD admin harus tampil
 * di landing page dan halaman detail program.
 */
class ProgramContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_membuka_daftar_pelatihan_dan_keterampilan(): void
    {
        $admin = User::create([
            'name' => 'Admin Program',
            'email' => 'admin.program@example.test',
            'password' => bcrypt('Secret#12345'),
            'user_type' => User::ROLE_ADMIN,
            'status' => UserStatus::Published->value,
        ]);

        MasterTraining::create([
            'title' => 'Pelatihan Reparasi',
            'description_1' => 'Deskripsi pelatihan.',
            'image_path' => 'training-images/reparasi.jpg',
        ]);

        MasterSkill::create([
            'title' => 'Teknik Las',
            'description_1' => 'Deskripsi keterampilan.',
            'image_path' => 'skill-images/teknik-las.jpg',
        ]);

        $this->actingAs($admin)->get('/admin/master-trainings')
            ->assertOk()
            ->assertSee('Pelatihan Reparasi');

        $this->actingAs($admin)->get('/admin/master-skills')
            ->assertOk()
            ->assertSee('Teknik Las');
    }

    public function test_landing_page_menampilkan_foto_dan_deskripsi_pelatihan_dari_database(): void
    {
        $training = MasterTraining::create([
            'title' => 'Pelatihan Reparasi',
            'description_1' => 'Peserta mempelajari dasar reparasi peralatan rumah tangga.',
            'description_2' => 'Praktik dilakukan dengan pendampingan instruktur.',
            'image_path' => 'training-images/reparasi.jpg',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($training->title)
            ->assertSee($training->description_1)
            ->assertSee($training->description_2)
            ->assertSee(Storage::disk('public')->url($training->image_path))
            ->assertSee('program-badge--pelatihan', false);
    }

    public function test_landing_page_menampilkan_foto_dan_deskripsi_keterampilan_dari_database(): void
    {
        $skill = MasterSkill::create([
            'title' => 'Teknik Las',
            'description_1' => 'Peserta mengenal alat las dan prosedur keselamatan kerja.',
            'description_2' => 'Latihan menyambung logam dilakukan secara bertahap.',
            'image_path' => 'skill-images/teknik-las.jpg',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee($skill->title)
            ->assertSee($skill->description_1)
            ->assertSee($skill->description_2)
            ->assertSee(Storage::disk('public')->url($skill->image_path));

        $this->assertSame(1, substr_count($response->getContent(), 'class="program-section"'));
        $response->assertSee('id="keterampilan"', false);
        $response->assertSee('program-badge--keterampilan', false);
        $response->assertSee('Program, Pelatihan', false);
    }

    public function test_halaman_bimbingan_sosial_memakai_deskripsi_dan_foto_dari_database(): void
    {
        $training = MasterTraining::create([
            'title' => 'Bimbingan Sosial',
            'description_1' => 'Deskripsi bimbingan sosial pertama dari admin.',
            'description_2' => 'Deskripsi bimbingan sosial kedua dari admin.',
            'image_path' => 'training-images/bimbingan-sosial.jpg',
        ]);

        $this->get(route('front.program.social-guidance'))
            ->assertOk()
            ->assertSee($training->description_1)
            ->assertSee($training->description_2)
            ->assertSee(Storage::disk('public')->url($training->image_path));
    }

    public function test_halaman_menjahit_memakai_deskripsi_dan_foto_dari_database(): void
    {
        $training = MasterTraining::create([
            'title' => 'Menjahit',
            'description_1' => 'Deskripsi menjahit pertama dari admin.',
            'description_2' => 'Deskripsi menjahit kedua dari admin.',
            'image_path' => 'training-images/menjahit.jpg',
        ]);

        $this->get(route('front.program.sewing'))
            ->assertOk()
            ->assertSee($training->description_1)
            ->assertSee($training->description_2)
            ->assertSee(Storage::disk('public')->url($training->image_path));
    }

    public function test_halaman_detail_program_memakai_deskripsi_dan_foto_dari_database(): void
    {
        $training = MasterTraining::create([
            'title' => 'Montir Motor',
            'description_1' => 'Deskripsi montir motor pertama dari admin.',
            'description_2' => 'Deskripsi montir motor kedua dari admin.',
            'image_path' => 'training-images/montir-motor.jpg',
        ]);

        $this->get(route('front.program.detail', ['program' => 'montir-motor']))
            ->assertOk()
            ->assertSee($training->description_1)
            ->assertSee($training->description_2)
            ->assertSee(Storage::disk('public')->url($training->image_path));
    }
}
