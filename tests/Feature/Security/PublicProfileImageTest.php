<?php

namespace Tests\Feature\Security;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regresi foto profil publik: gambar harus tampil di landing page meskipun
 * symlink `public/storage` belum dibuat (fallback route `/storage/{path}`).
 */
class PublicProfileImageTest extends TestCase
{
    use RefreshDatabase;

    private const FOTO_PATH = 'profile-picture/foto-profil.png';

    private function simpanFoto(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('foto-profil.png', 30, 40);

        Storage::disk('public')->put(self::FOTO_PATH, file_get_contents($file->getRealPath()));
    }

    private function buatKlienDenganFoto(string $nama = 'Peserta Berfoto'): User
    {
        $user = User::create([
            'name' => $nama,
            'email' => strtolower(str_replace(' ', '.', $nama)).'@example.test',
            'password' => bcrypt('Secret#12345'),
            'user_type' => 'client',
            'status' => UserStatus::Published->value,
        ]);

        $user->userProfile()->create([
            'description' => 'Deskripsi '.$nama,
            'profile_picture_path' => self::FOTO_PATH,
        ]);

        return $user;
    }

    public function test_landing_page_menampilkan_foto_profil_klien(): void
    {
        $this->simpanFoto();
        $user = $this->buatKlienDenganFoto();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('src="/storage/'.self::FOTO_PATH.'"', false)
            ->assertSee('alt="'.$user->name.'"', false);
    }

    public function test_foto_profil_dapat_diakses_tanpa_symlink_storage(): void
    {
        $this->simpanFoto();

        $this->get('/storage/'.self::FOTO_PATH)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_profil_publik_juga_menampilkan_foto_yang_sama(): void
    {
        $this->simpanFoto();
        $user = $this->buatKlienDenganFoto('Peserta Halaman Profil');

        $this->get(route('front.user.detail', [
            'slug' => 'peserta-halaman-profil-'.$user->id,
        ]))
            ->assertOk()
            ->assertSee('src="/storage/'.self::FOTO_PATH.'"', false);
    }

    public function test_file_yang_tidak_ada_ditolak_404(): void
    {
        Storage::fake('public');

        $this->get('/storage/profile-picture/tidak-ada.png')->assertNotFound();
    }

    public function test_file_bukan_gambar_tidak_dilayani(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile-picture/dokumen.txt', 'bukan gambar');

        $this->get('/storage/profile-picture/dokumen.txt')->assertNotFound();
    }

    public function test_path_traversal_ditolak(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('.env', 'APP_KEY=rahasia');

        $this->get('/storage/..%2F.env')->assertNotFound();
    }
}
