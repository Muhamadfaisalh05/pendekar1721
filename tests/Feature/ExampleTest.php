<?php

namespace Tests\Feature;

use App\Models\MasterTraining;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_basic_test()
    {
        $this->get(route('home'))->assertSuccessful();
    }

    public function test_halaman_login_menampilkan_nama_aplikasi(): void
    {
        $this->get(route('login'))
            ->assertSuccessful()
            ->assertSee('PENDEKAR1721')
            ->assertDontSee('PENDEKAR1721 - Klien');
    }

    public function test_home_shows_social_guidance_program()
    {
        foreach (['Menjahit', 'Bimbingan Sosial', 'Montir Motor', 'Tata Boga'] as $title) {
            MasterTraining::create(['title' => $title]);
        }

        $this->get(route('home'))
            ->assertSuccessful()
            ->assertDontSee('Admin')
            ->assertSee('Login')
            ->assertDontSee('Masuk Klien')
            ->assertSee('Cari nama siswa')
            ->assertSee('Masukkan nama siswa')
            ->assertSee('Bimbingan Sosial')
            ->assertSee('images/sewing-workshop.png')
            ->assertSee(route('front.program.sewing'))
            ->assertSee('images/bimbingan-sosial.png')
            ->assertSee(route('front.program.social-guidance'))
            ->assertSee('images/motorcycle-workshop.png')
            ->assertSee(route('front.program.detail', ['program' => 'montir-motor']))
            ->assertDontSee('Montir Mobil')
            ->assertSee('images/culinary-workshop.png')
            ->assertSee(route('front.program.detail', ['program' => 'tata-boga']));
    }

    public function test_sewing_program_page_shows_description_and_history()
    {
        $this->get(route('front.program.sewing'))
            ->assertSuccessful()
            ->assertSee('Tentang Menjahit')
            ->assertSee('images/sewing-workshop.png')
            ->assertSee('menyiapkan kain, mengukur')
            ->assertSee('Sejarah Menjahit')
            ->assertSee('gambaran umum perkembangan keterampilan menjahit');
    }

    public function test_social_guidance_program_page_shows_description_and_history()
    {
        $this->get(route('front.program.social-guidance'))
            ->assertSuccessful()
            ->assertSee('Tentang Bimbingan Sosial')
            ->assertSee('Sejarah Bimbingan Sosial')
            ->assertSee('gambaran umum perkembangan pendampingan sosial');
    }

    public function test_vocational_program_pages_show_description_and_history()
    {
        $programs = [
            'montir-motor' => ['Montir Motor', 'Sejarah Montir Motor'],
            'montir-mobil' => ['Montir Mobil', 'Sejarah Montir Mobil'],
            'tata-boga' => ['Tata Boga', 'Sejarah Tata Boga'],
        ];

        foreach ($programs as $slug => [$title, $history]) {
            $response = $this->get(route('front.program.detail', ['program' => $slug]))
                ->assertSuccessful()
                ->assertSee("Tentang {$title}")
                ->assertSee($history);

            if ($slug === 'montir-motor') {
                $response
                    ->assertSee('images/motorcycle-workshop.png')
                    ->assertSee('mengenal komponen utama sepeda motor');
            }

            if ($slug === 'tata-boga') {
                $response
                    ->assertSee('images/culinary-workshop.png')
                    ->assertSee('menimbang bahan');
            }
        }
    }
}
