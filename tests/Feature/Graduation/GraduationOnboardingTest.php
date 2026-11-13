<?php

namespace Tests\Feature\Graduation;

use App\Enums\UserStatus;
use App\Filament\Resources\AnakResource;
use App\Filament\Resources\AnakResource\Pages\ManageAnak;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use App\Services\GraduationService;
use App\Services\ImportBinaanService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Livewire\Livewire;
use RuntimeException;
use Tests\Support\Absensi\AbsensiTestCase;

/**
 * FASE 2 — jembatan `semarak.anak` ↔ `users`.
 *
 * Yang dikunci oleh tes ini:
 * - definisi alumni resmi: `client` + `published` + `graduated_at` terisi;
 * - akun binaan dibuat Draft tanpa email/kata sandi (tidak bisa login);
 * - kelulusan mengaktifkan akun yang sama dan mengembalikan kata sandi SEKALI;
 * - email unik mengabaikan akun yang sudah tertaut (boleh luluskan ulang);
 * - kegagalan di tengah proses dikompensasi ke keadaan SEBELUMNYA.
 */
class GraduationOnboardingTest extends AbsensiTestCase
{
    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Pendekar',
            'email' => 'admin-graduation@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_ADMIN,
            'status' => UserStatus::Published,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function klien(array $atribut = []): User
    {
        return User::create(array_merge([
            'name' => 'Klien Uji',
            'email' => 'klien-'.uniqid().'@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_CLIENT,
            'status' => UserStatus::Published,
        ], $atribut));
    }

    private function anak(string $id = 'AN-002'): Anak
    {
        return Anak::query()->findOrFail($id);
    }

    public function test_scope_alumni_hanya_klien_aktif_dengan_graduated_at(): void
    {
        $alumni = $this->klien(['graduated_at' => '2026-06-30 08:00:00']);
        $this->klien();                                        // published tanpa graduated_at
        $this->klien(['status' => UserStatus::Draft, 'graduated_at' => '2026-06-30 08:00:00']);
        $this->klien(['status' => UserStatus::Archived, 'graduated_at' => '2026-06-30 08:00:00']);
        $this->klien(['user_type' => User::ROLE_ADMIN, 'graduated_at' => '2026-06-30 08:00:00']);

        $ids = User::query()->alumni()->pluck('id')->all();

        $this->assertSame([$alumni->id], $ids);
        $this->assertTrue($alumni->fresh()->isAlumni());
        $this->assertFalse($this->klien()->isAlumni());
    }

    public function test_import_binaan_membuat_akun_draft_tanpa_email_dan_password(): void
    {
        $admin = $this->admin();
        $anak = $this->anak('AN-001'); // Endang Sri Ayu, Perempuan

        $hasil = app(ImportBinaanService::class)->pastikanAkunBinaan($anak, $admin->id);

        $this->assertSame(ImportBinaanService::STATUS_DIBUAT, $hasil['status']);
        $this->assertTrue($hasil['dibuat']);

        $user = $hasil['user'];
        $this->assertSame('AN-001', $user->anak_id);
        $this->assertSame(User::ROLE_CLIENT, $user->user_type);
        $this->assertSame(UserStatus::Draft, $user->status);
        $this->assertNull($user->email, 'Binaan belum boleh punya email sebelum aktivasi.');
        $this->assertNull($user->password, 'Binaan tidak boleh punya kata sandi sebelum aktivasi.');
        $this->assertNull($user->graduated_at);
        $this->assertSame($admin->id, $user->created_by);
        $this->assertSame('P', $user->userProfile->gender);
        $this->assertFalse($user->isAlumni());
    }

    public function test_import_binaan_idempoten_dan_menghormati_nama_kembar(): void
    {
        $service = app(ImportBinaanService::class);
        $anak = $this->anak('AN-002'); // Doddy

        $pertama = $service->pastikanAkunBinaan($anak);
        $kedua = $service->pastikanAkunBinaan($anak);

        $this->assertSame(ImportBinaanService::STATUS_DIBUAT, $pertama['status']);
        $this->assertSame(ImportBinaanService::STATUS_SUDAH_TERPAUT, $kedua['status']);
        $this->assertSame($pertama['user']->id, $kedua['user']->id);
        $this->assertSame(1, User::query()->where('anak_id', 'AN-002')->count());

        // Nama kembar tanpa tautan anak: dilewati agar tidak tertukar orang.
        $kembar = $this->klien(['name' => 'Cece']);
        $hasil = $service->pastikanAkunBinaan($this->anak('AN-005')); // Cece

        $this->assertSame(ImportBinaanService::STATUS_DILEWATI_NAMA_KEMBAR, $hasil['status']);
        $this->assertNull($hasil['user']);
        $this->assertNull(User::query()->where('anak_id', 'AN-005')->first());
        $this->assertNull($kembar->fresh()->anak_id);

        // Operator boleh memaksa setelah verifikasi manual.
        $paksa = $service->pastikanAkunBinaan($this->anak('AN-005'), izinkanNamaGanda: true);
        $this->assertSame(ImportBinaanService::STATUS_DIBUAT, $paksa['status']);
    }

    public function test_kelulusan_mengaktifkan_akun_binaan_dan_mengembalikan_password_sekali(): void
    {
        $admin = $this->admin();
        $anak = $this->anak('AN-002');
        $binaan = app(ImportBinaanService::class)->pastikanAkunBinaan($anak, $admin->id)['user'];

        $hasil = app(GraduationService::class)->luluskan($anak, '  Doddy@Example.TEST ', '2026-11-01 10:00:00', $admin->id);

        $user = $hasil['user'];
        $this->assertFalse($hasil['akun_dibuat']);
        $this->assertSame($binaan->id, $user->id);
        $this->assertSame('doddy@example.test', $user->email);
        $this->assertSame(UserStatus::Published, $user->status);
        $this->assertSame('2026-11-01 10:00:00', $user->graduated_at?->toDateTimeString());
        $this->assertSame(Anak::STATUS_LULUS, $anak->fresh()->status);
        $this->assertTrue($user->isAlumni());

        // Kata sandi acak dikembalikan sekali dan tersimpan sebagai hash.
        $this->assertIsString($hasil['password']);
        $this->assertTrue(Hash::check($hasil['password'], $user->password));

        // Luluskan ulang dengan email yang sama: tidak ditolak (ignore akun
        // tertaut) dan kata sandi lama TIDAK direset diam-diam.
        $ulang = app(GraduationService::class)->luluskan($anak, 'doddy@example.test', null, $admin->id);
        $this->assertNull($ulang['password']);
        $this->assertTrue(Hash::check($hasil['password'], $ulang['user']->password));
    }

    public function test_kelulusan_membuat_akun_baru_bila_binaan_belum_diimpor(): void
    {
        $admin = $this->admin();
        $anak = $this->anak('AN-004'); // Isti Komah Insani

        $hasil = app(GraduationService::class)->luluskan($anak, 'isti@example.test', null, $admin->id);

        $this->assertTrue($hasil['akun_dibuat']);
        $this->assertSame('AN-004', $hasil['user']->anak_id);
        $this->assertSame(UserStatus::Published, $hasil['user']->status);
        $this->assertNotNull($hasil['user']->graduated_at);
        $this->assertSame($admin->id, $hasil['user']->created_by);
        $this->assertSame('P', $hasil['user']->userProfile->gender);
        $this->assertIsString($hasil['password']);
        $this->assertTrue(Hash::check($hasil['password'], $hasil['user']->password));
        $this->assertSame(Anak::STATUS_LULUS, $anak->fresh()->status);
    }

    public function test_email_milik_akun_lain_ditolak_tanpa_mengubah_status_anak(): void
    {
        $admin = $this->admin();
        $pemilik = $this->klien(['email' => 'sudah-dipakai@example.test']);
        $anak = $this->anak('AN-003');

        try {
            app(GraduationService::class)->luluskan($anak, 'Sudah-Dipakai@example.test', null, $admin->id);
            $this->fail('Email milik akun lain seharusnya ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('sudah dipakai akun lain', $e->getMessage());
        }

        $this->assertSame(Anak::STATUS_AKTIF, $anak->fresh()->status);
        $this->assertNull(User::query()->where('anak_id', 'AN-003')->first());
        $this->assertSame(UserStatus::Published, $pemilik->fresh()->status);
        $this->assertNull($pemilik->fresh()->graduated_at);
    }

    public function test_email_tidak_valid_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Email tidak valid');

        app(GraduationService::class)->luluskan($this->anak('AN-003'), 'bukan-email');
    }

    public function test_kegagalan_tulis_semarak_mengembalikan_atribut_akun_sebelumnya(): void
    {
        Log::spy();

        $admin = $this->admin();
        $anak = $this->anak('AN-006'); // Qatrunada Mahardika

        // Akun lama: status NULL (data legacy) dan sudah punya kata sandi.
        $user = $this->klien([
            'name' => 'Qatrunada Mahardika',
            'email' => 'qatrunada@example.test',
            'status' => null,
            'anak_id' => 'AN-006',
        ]);
        $hashSebelum = $user->password;

        // Simulasi kegagalan menulis status anak ke koneksi `semarak` setelah
        // transaksi database aplikasi commit.
        Anak::saving(static function (): void {
            throw new RuntimeException('simulasi gagal tulis semarak');
        });

        try {
            app(GraduationService::class)->luluskan($anak, 'qatrunada@example.test', '2026-11-02 09:00:00', $admin->id);
            $this->fail('Kegagalan menulis status anak seharusnya dilempar ulang.');
        } catch (RuntimeException $e) {
            $this->assertSame('simulasi gagal tulis semarak', $e->getMessage());
        } finally {
            Anak::flushEventListeners();
        }

        $segar = $user->fresh();
        $this->assertNull($segar->status, 'Status harus kembali ke nilai sebelumnya (null), bukan Draft.');
        $this->assertNull($segar->graduated_at);
        $this->assertSame('qatrunada@example.test', $segar->email);
        $this->assertSame($hashSebelum, $segar->password);
        $this->assertSame(Anak::STATUS_AKTIF, $anak->fresh()->status);

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $pesan, array $konteks = []): bool => $pesan === 'graduation.rollback'
                && ($konteks['anak_id'] ?? null) === 'AN-006')
            ->atLeast()->once();
    }

    /**
     * Syarat 3 (UI): aksi "Luluskan & Aktifkan Akun Klien" mengaktifkan akun
     * dan menampilkan kata sandi sementara SATU kali lewat modal.
     */
    public function test_aksi_luluskan_di_panel_menampilkan_kata_sandi_sekali(): void
    {
        $admin = $this->admin();
        $anak = $this->anak('AN-002');

        $this->actingAs($admin);

        $komponen = Livewire::test(ManageAnak::class)
            ->callTableAction('luluskan', $anak->getKey(), ['email' => 'Doddy@Example.test']);

        $komponen->assertHasNoTableActionErrors();

        $user = User::query()->where('anak_id', 'AN-002')->firstOrFail();

        $this->assertSame(UserStatus::Published, $user->status);
        $this->assertSame('doddy@example.test', $user->email);
        $this->assertNotNull($user->graduated_at);
        $this->assertSame(Anak::STATUS_LULUS, $anak->fresh()->status);

        // Modal kata sandi menggantikan modal form (bukan menumpuk di atasnya).
        $komponen->assertSet('mountedTableActions', ['kataSandiSekali']);

        $password = $komponen->get('mountedTableActionsArguments.0.password');

        $this->assertIsString($password);
        $this->assertNotSame('', $password);
        $this->assertTrue(Hash::check($password, $user->fresh()->password));
        $komponen->assertSee($password);

        // Kata sandi tidak tersimpan sebagai plaintext di kolom mana pun.
        $this->assertNotSame($password, $user->fresh()->getRawOriginal('password'));
    }

    /**
     * Syarat 1 (UI): email milik akun lain ditolak, data tidak berubah, dan
     * modal tetap terbuka agar admin bisa memperbaiki email.
     */
    public function test_aksi_luluskan_menolak_email_milik_akun_lain(): void
    {
        $admin = $this->admin();
        $pemilik = $this->klien(['email' => 'sudah-dipakai@example.test']);
        $anak = $this->anak('AN-003');

        $this->actingAs($admin);

        Livewire::test(ManageAnak::class)
            ->callTableAction('luluskan', $anak->getKey(), ['email' => 'sudah-dipakai@example.test'])
            ->assertNotified('Kelulusan gagal')
            ->assertSet('mountedTableActions', ['luluskan']);

        $this->assertSame(Anak::STATUS_AKTIF, $anak->fresh()->status);
        $this->assertNull(User::query()->where('anak_id', 'AN-003')->first());
        $this->assertSame(UserStatus::Published, $pemilik->fresh()->status);
        $this->assertNull($pemilik->fresh()->graduated_at);
    }

    /**
     * Aksi kelulusan hanya untuk Administrator Pendekar dan anak yang belum Lulus.
     */
    public function test_aksi_luluskan_hanya_untuk_admin_dan_anak_belum_lulus(): void
    {
        $admin = $this->admin();
        $adminAbsensi = User::create([
            'name' => 'Admin Absensi',
            'email' => 'admin-absensi-graduation@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_ADMIN_ABSENSI,
            'status' => UserStatus::Published,
        ]);
        $anak = $this->anak('AN-002');

        $this->assertTrue(AnakResource::bolehLuluskan($anak, $admin));
        $this->assertFalse(AnakResource::bolehLuluskan($anak, $adminAbsensi));
        $this->assertFalse(AnakResource::bolehLuluskan($anak, $this->klien()));
        $this->assertFalse(AnakResource::bolehLuluskan($anak, null));

        $anak->forceFill(['status' => Anak::STATUS_LULUS])->save();

        $this->assertFalse(
            AnakResource::bolehLuluskan($anak->fresh(), $admin),
            'Anak yang sudah Lulus tidak boleh diluluskan lagi lewat tombol.'
        );
    }

    /**
     * Kolom "Akun Klien" membaca relasi lintas koneksi `anak.akunKlien`.
     */
    public function test_kolom_akun_klien_menampilkan_status_akun_tertaut(): void
    {
        $admin = $this->admin();

        $draft = app(ImportBinaanService::class)->pastikanAkunBinaan($this->anak('AN-002'), $admin->id)['user'];
        $aktif = $this->klien(['name' => 'Isti Komah Insani', 'email' => 'isti@example.test', 'anak_id' => 'AN-004']);

        $this->actingAs($admin);

        Livewire::test(ManageAnak::class)
            ->assertSee('Akun Klien')
            ->assertSee('isti@example.test');

        $this->assertSame('Draft', AnakResource::labelAkunKlien($this->anak('AN-002')));
        $this->assertSame('warning', AnakResource::warnaAkunKlien($this->anak('AN-002')));
        $this->assertSame('Aktif', AnakResource::labelAkunKlien($this->anak('AN-004')));
        $this->assertSame('success', AnakResource::warnaAkunKlien($this->anak('AN-004')));
        $this->assertSame('Belum ada', AnakResource::labelAkunKlien($this->anak('AN-003')));
        $this->assertSame('gray', AnakResource::warnaAkunKlien($this->anak('AN-003')));

        // Relasi lintas database mengembalikan akun yang benar.
        $this->assertTrue($this->anak('AN-002')->akunKlien->is($draft));
        $this->assertTrue($this->anak('AN-004')->akunKlien->is($aktif));
        $this->assertNull($this->anak('AN-003')->akunKlien);
    }

    /**
     * Kolom "Alamat (KTP)" membaca profil akun klien tertaut dan bisa dicari.
     */
    public function test_kolom_alamat_ktp_menampilkan_dan_mencari_alamat_akun_tertaut(): void
    {
        $admin = $this->admin();

        $draft = app(ImportBinaanService::class)->pastikanAkunBinaan($this->anak('AN-002'), $admin->id)['user'];
        $draft->userProfile()->update(['ktp_address' => 'Jl. Merdeka No. 10']);

        $this->actingAs($admin);

        Livewire::test(ManageAnak::class)
            ->assertSee('Alamat (KTP)')
            ->assertSee('Jl. Merdeka No. 10')
            ->searchTable('merdeka')
            ->assertCanSeeTableRecords([$this->anak('AN-002')])
            ->assertCanNotSeeTableRecords([$this->anak('AN-003')]);

        $this->assertSame('Jl. Merdeka No. 10', AnakResource::alamatKtp($this->anak('AN-002')));
        $this->assertNull(
            AnakResource::alamatKtp($this->anak('AN-003')),
            'Anak tanpa akun tertaut belum punya alamat KTP.'
        );
        $this->assertStringContainsString(
            'Jl. Merdeka No. 10',
            AnakResource::ringkasanIdentitas($this->anak('AN-002'))
        );
    }
}
