<?php

namespace Tests\Feature\Graduation;

use App\Enums\UserStatus;
use App\Filament\Pages\ImportData;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use App\Services\GraduationService;
use App\Services\ImportBinaanService;
use App\Services\ImportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\Absensi\AbsensiTestCase;

/**
 * FASE 3 — impor "Remaja Binaan" (default Draft) dan pemenuhan empat syarat
 * wajib onboarding alumni dari sisi impor
 * (docs/BACKUP-LOGIC-SEBELUM-REFACTOR.md §11).
 *
 * Yang dikunci oleh tes ini:
 * - syarat 1: email unik saat kelulusan MENGABAIKAN akun yang sudah tertaut;
 * - syarat 3: kelulusan memberi kata sandi satu kali (bukan kata sandi publik);
 * - syarat 4: akun binaan tanpa kata sandi TIDAK bisa login sebelum diaktifkan;
 * - impor binaan hanya menautkan ke `anak` bila namanya unik dan belum berakun;
 * - mode klien (perilaku lama) tetap membuat akun aktif dan bisa login.
 */
class ImportBinaanTest extends AbsensiTestCase
{
    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Impor Binaan',
            'email' => 'admin-import-binaan@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_ADMIN,
            'status' => UserStatus::Published,
        ]);
    }

    private function tambahAnak(string $id, string $nama): void
    {
        DB::connection('semarak')->table('anak')->insert([
            'id' => $id,
            'nama' => $nama,
            'jenis_kelamin' => 'Laki-laki',
            'asal_daerah' => 'Bandung',
            'umur' => 17,
            'agama' => 'Islam',
            'angkatan' => 'Angkatan III',
            'status' => Anak::STATUS_AKTIF,
        ]);
    }

    public function test_mode_binaan_membuat_akun_draft_tanpa_kata_sandi_dan_tertaut_bila_opsi_dipilih(): void
    {
        $admin = $this->admin();

        (new ImportService)->importData(
            [['name' => 'Doddy', 'email' => 'doddy.binaan@example.test']],
            [],
            $admin->id,
            null,
            ImportService::MODE_BINAAN,
            // Opsi penautan dipilih operator (checkbox di form Import Data).
            true,
        );

        $user = User::query()->where('email', 'doddy.binaan@example.test')->firstOrFail();

        $this->assertSame(User::ROLE_CLIENT, $user->user_type);
        $this->assertSame(UserStatus::Draft, $user->status);
        $this->assertNull($user->password, 'Binaan belum boleh punya kata sandi sebelum diaktifkan.');
        $this->assertNull($user->graduated_at);
        $this->assertSame('AN-002', $user->anak_id, 'Nama unik dan belum berakun → akun harus tertaut.');
        $this->assertSame($admin->id, $user->created_by);
        $this->assertTrue($user->userProfile()->exists());
        $this->assertFalse($user->isAlumni());
    }

    public function test_mode_binaan_tidak_menautkan_nama_kembar_atau_anak_yang_sudah_berakun(): void
    {
        $admin = $this->admin();
        $service = new ImportService;

        // AN-005 juga bernama "Cece" → dua kandidat, jangan menebak.
        $this->tambahAnak('AN-010', 'Cece');

        $service->importData(
            [['name' => 'Cece', 'email' => 'cece.kembar@example.test']],
            [],
            $admin->id,
            null,
            ImportService::MODE_BINAAN,
            true,
        );

        $cece = User::query()->where('email', 'cece.kembar@example.test')->firstOrFail();
        $this->assertNull($cece->anak_id, 'Nama kembar tidak boleh ditautkan otomatis.');

        // Impor kedua untuk anak yang sudah berakun tidak boleh bentrok unique.
        $service->importData(
            [['name' => 'Doddy', 'email' => 'doddy.pertama@example.test']],
            [],
            $admin->id,
            null,
            ImportService::MODE_BINAAN,
            true,
        );
        $service->importData(
            [['name' => 'Doddy', 'email' => 'doddy.kedua@example.test']],
            [],
            $admin->id,
            null,
            ImportService::MODE_BINAAN,
            true,
        );

        $this->assertSame(1, User::query()->where('anak_id', 'AN-002')->count(), 'Satu anak hanya boleh punya satu akun.');
        $this->assertSame(
            'doddy.pertama@example.test',
            User::query()->where('anak_id', 'AN-002')->value('email')
        );
    }

    public function test_akun_binaan_hasil_impor_tidak_bisa_login_sebelum_diluluskan(): void
    {
        $this->admin();

        (new ImportService)->importData(
            [['name' => 'Doddy', 'email' => 'doddy.login@example.test']],
            [],
            null,
            null,
            ImportService::MODE_BINAAN,
        );

        $this->post('/login', [
            'email' => 'doddy.login@example.test',
            'password' => 'apa-saja-yang-ditebak',
        ])->assertInvalid([
            'email' => 'Email atau kata sandi tidak sesuai. Mohon periksa kembali lalu coba lagi.',
        ]);

        $this->assertGuest();
    }

    public function test_mode_binaan_mengabaikan_kata_sandi_awal_operator(): void
    {
        (new ImportService)->importData(
            [['name' => 'Doddy', 'email' => 'doddy.sandi@example.test']],
            [],
            null,
            'Rahasia#2026',
            ImportService::MODE_BINAAN,
        );

        $user = User::query()->where('email', 'doddy.sandi@example.test')->firstOrFail();

        $this->assertNull($user->password, 'Mode binaan tidak boleh menyimpan kata sandi sama sekali.');
        $this->assertSame(UserStatus::Draft, $user->status);
    }

    public function test_kelulusan_memakai_akun_hasil_impor_binaan_dan_mengabaikan_email_akun_tertaut(): void
    {
        $admin = $this->admin();

        (new ImportService)->importData(
            [['name' => 'Doddy', 'email' => 'doddy.binaan@example.test']],
            [],
            $admin->id,
            null,
            ImportService::MODE_BINAAN,
            true,
        );

        $binaan = User::query()->where('anak_id', 'AN-002')->firstOrFail();
        $anak = Anak::query()->findOrFail('AN-002');

        // Email sama (beda kapital) dengan akun yang sudah tertaut ke anak ini:
        // syarat 1 — tidak boleh dianggap "sudah dipakai akun lain".
        $hasil = app(GraduationService::class)->luluskan($anak, '  DODDY.BINAAN@example.test ', null, $admin->id);

        $this->assertFalse($hasil['akun_dibuat'], 'Akun hasil impor harus dipakai ulang, bukan dibuat baru.');
        $this->assertSame($binaan->id, $hasil['user']->id);
        $this->assertSame(UserStatus::Published, $hasil['user']->status);
        $this->assertSame('doddy.binaan@example.test', $hasil['user']->email);
        $this->assertNotNull($hasil['user']->graduated_at);
        $this->assertTrue($hasil['user']->isAlumni());
        $this->assertSame(Anak::STATUS_LULUS, $anak->fresh()->status);
        $this->assertSame(1, User::query()->where('anak_id', 'AN-002')->count());

        // Syarat 3: kata sandi acak dikembalikan sekali dan tersimpan sebagai hash.
        $this->assertIsString($hasil['password']);
        $this->assertTrue(Hash::check($hasil['password'], $hasil['user']->fresh()->password));
    }

    public function test_mode_klien_tetap_membuat_akun_aktif_dan_bisa_login(): void
    {
        (new ImportService)->importData(
            [['name' => 'Siti Aminah', 'email' => 'siti.klien@example.test']],
            [],
            null,
            'Rahasia#2026',
            ImportService::MODE_KLIEN,
        );

        $user = User::query()->where('email', 'siti.klien@example.test')->firstOrFail();

        $this->assertSame(UserStatus::Published, $user->status);
        $this->assertNull($user->anak_id, 'Mode klien tidak menautkan ke data anak.');
        $this->assertTrue(Hash::check('Rahasia#2026', $user->password));

        $this->post('/login', [
            'email' => 'siti.klien@example.test',
            'password' => 'Rahasia#2026',
        ])->assertRedirect('/klien');

        $this->assertAuthenticatedAs($user);
    }

    public function test_mode_binaan_tanpa_opsi_penautan_tidak_menautkan_akun_ke_anak(): void
    {
        $admin = $this->admin();

        (new ImportService)->importData(
            [['name' => 'Doddy', 'email' => 'doddy.tanpa.tautan@example.test']],
            [],
            $admin->id,
            null,
            ImportService::MODE_BINAAN,
        );

        $user = User::query()->where('email', 'doddy.tanpa.tautan@example.test')->firstOrFail();

        $this->assertNull($user->anak_id, 'Tanpa centang opsi, tidak boleh ada penautan otomatis.');
        $this->assertSame(UserStatus::Draft, $user->status);
    }

    public function test_opsi_penautan_diabaikan_untuk_mode_klien(): void
    {
        (new ImportService)->importData(
            [['name' => 'Doddy', 'email' => 'doddy.klien.tautan@example.test']],
            [],
            null,
            'Rahasia#2026',
            ImportService::MODE_KLIEN,
            true,
        );

        $user = User::query()->where('email', 'doddy.klien.tautan@example.test')->firstOrFail();

        $this->assertNull($user->anak_id, 'Penautan hanya berlaku untuk mode binaan.');
        $this->assertSame(UserStatus::Published, $user->status);
    }

    public function test_pratinjau_penautan_memisahkan_yang_akan_dan_tidak_ditautkan(): void
    {
        $admin = $this->admin();

        // AN-020 "Fajar" unik dan belum berakun → satu-satunya kandidat tautan.
        $this->tambahAnak('AN-020', 'Fajar');
        // AN-005 juga bernama "Cece" → nama kembar, tidak boleh ditebak.
        $this->tambahAnak('AN-010', 'Cece');

        // Akun Doddy yang sudah tertaut ke AN-002 membuat kandidat itu tidak
        // boleh ditautkan lagi (unique `users.anak_id`).
        (new ImportService)->importData(
            [['name' => 'Doddy', 'email' => 'doddy.tertaut@example.test']],
            [],
            $admin->id,
            null,
            ImportService::MODE_BINAAN,
            true,
        );

        $hasil = (new ImportService)->petaTautanAnak([
            ['name' => 'fajar', 'email' => 'fajar@example.test', '_row_number' => 2],
            ['name' => 'Cece', 'email' => 'cece@example.test', '_row_number' => 3],
            ['name' => 'Doddy', 'email' => 'doddy.lain@example.test', '_row_number' => 4],
            ['name' => 'Nama Fiktif', 'email' => 'fiktif@example.test', '_row_number' => 5],
        ]);

        $this->assertCount(1, $hasil['akan'], 'Hanya nama unik yang belum berakun yang boleh ditautkan.');
        $this->assertSame('AN-020', $hasil['akan'][0]['anak_id']);
        $this->assertSame('fajar@example.test', $hasil['akan'][0]['email']);
        $this->assertSame(['fajar@example.test' => 'AN-020'], $hasil['peta']);

        $alasan = array_column($hasil['tidak'], 'alasan');
        $this->assertContains('Nama kembar: 2 anak memakai nama ini', $alasan);
        $this->assertContains('Anak AN-002 sudah punya akun klien', $alasan);
        $this->assertContains('Tidak ada data anak dengan nama ini', $alasan);
    }

    public function test_pratinjau_penautan_tidak_menautkan_nama_yang_muncul_dua_kali_di_file(): void
    {
        $this->tambahAnak('AN-030', 'Gita');

        $hasil = (new ImportService)->petaTautanAnak([
            ['name' => 'Gita', 'email' => 'gita.satu@example.test', '_row_number' => 2],
            ['name' => 'gita', 'email' => 'gita.dua@example.test', '_row_number' => 3],
        ]);

        $this->assertSame([], $hasil['akan']);
        $this->assertSame([], $hasil['peta']);
        $this->assertCount(2, $hasil['tidak']);
        $this->assertSame('Nama ini muncul lebih dari sekali di file', $hasil['tidak'][0]['alasan']);
    }

    public function test_form_import_data_menawarkan_mode_binaan_sebagai_pilihan_awal(): void
    {
        $this->actingAs($this->admin());

        // Radio "binaan" terpilih secara default; nilai state inilah yang
        // dikirim ke ImportService saat confirmImport().
        Livewire::test(ImportData::class)
            ->assertSuccessful()
            ->assertSee('Remaja Binaan')
            ->assertSet('mode', ImportService::MODE_BINAAN);
    }

    public function test_kemungkinan_duplikat_dilaporkan_berdasarkan_nama_asal_dan_angkatan(): void
    {
        $hasil = (new ImportService)->deteksiKemungkinanDuplikat([
            // Dua baris identik (nama + asal + angkatan) di dalam file.
            ['name' => 'Sari', 'email' => 'sari.satu@example.test', 'asal_daerah' => 'Bandung', 'angkatan' => 'Angkatan III', '_row_number' => 2],
            ['name' => 'Sari', 'email' => 'sari.dua@example.test', 'asal_daerah' => 'Bandung', 'angkatan' => 'Angkatan III', '_row_number' => 3],
            // Nama sama dengan anak AN-002 (Bogor · Angkatan II) — beda kapital.
            ['name' => 'Doddy', 'email' => 'doddy.dup@example.test', 'asal_daerah' => 'bogor', 'angkatan' => 'angkatan ii', '_row_number' => 4],
            // Nama sama, tetapi asal daerah berbeda → dianggap orang lain.
            ['name' => 'Doddy', 'email' => 'doddy.lain@example.test', 'asal_daerah' => 'Jakarta', 'angkatan' => 'Angkatan I', '_row_number' => 5],
        ]);

        $dalamFile = array_values(array_filter($hasil, fn (array $d) => $d['jenis'] === 'Di dalam file'));
        $this->assertCount(2, $dalamFile);
        $this->assertSame([2, 3], array_column($dalamFile, 'row'));
        $this->assertSame('Tinggi', $dalamFile[0]['keyakinan']);

        $terhadapAnak = array_values(array_filter($hasil, fn (array $d) => $d['jenis'] === 'Data anak'));
        $this->assertCount(1, $terhadapAnak);
        $this->assertSame(4, $terhadapAnak[0]['row']);
        $this->assertSame('Tinggi', $terhadapAnak[0]['keyakinan']);
        $this->assertStringContainsString('AN-002', $terhadapAnak[0]['keterangan']);
    }

    public function test_kemungkinan_duplikat_tidak_menolak_baris_saat_import(): void
    {
        $admin = $this->admin();
        $service = new ImportService;

        $valid = [
            ['name' => 'Doddy', 'email' => 'doddy.ganda.satu@example.test', 'asal_daerah' => 'Bandung', 'angkatan' => 'Angkatan III', '_row_number' => 2],
            ['name' => 'Doddy', 'email' => 'doddy.ganda.dua@example.test', 'asal_daerah' => 'Bandung', 'angkatan' => 'Angkatan III', '_row_number' => 3],
        ];

        $this->assertNotEmpty($service->deteksiKemungkinanDuplikat($valid), 'Duplikat harus dilaporkan.');

        // Dilaporkan, bukan ditolak: kedua baris tetap dibuatkan akun.
        $service->importData($valid, [], $admin->id, null, ImportService::MODE_BINAAN);

        $this->assertSame(2, User::query()
            ->whereIn('email', ['doddy.ganda.satu@example.test', 'doddy.ganda.dua@example.test'])
            ->count());
    }

    public function test_form_import_data_memiliki_opsi_penautan_yang_default_mati(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ImportData::class)
            ->assertSuccessful()
            ->assertSet('tautkan_anak', false)
            ->assertSee('Tautkan ke data anak yang namanya sama');
    }

    public function test_preview_import_menampilkan_rencana_penautan_dan_kemungkinan_duplikat(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ImportData::class)
            ->set('previewData', [
                'total' => 1,
                'valid' => [[
                    'name' => 'Fajar',
                    'email' => 'fajar@example.test',
                    'gender' => 'L',
                    'ktp_city_id' => null,
                    'hire_status' => '0',
                    'photo_filename' => null,
                ]],
                'errors' => [],
                'duplicates' => [],
                'tautan' => [
                    'akan' => [[
                        'row' => 2,
                        'nama' => 'Fajar',
                        'email' => 'fajar@example.test',
                        'anak_id' => 'AN-020',
                        'asal_daerah' => 'Bandung',
                        'angkatan' => 'Angkatan III',
                        'status' => 'Aktif',
                    ]],
                    'tidak' => [[
                        'row' => 3,
                        'nama' => 'Cece',
                        'email' => 'cece@example.test',
                        'alasan' => 'Nama kembar: 2 anak memakai nama ini',
                    ]],
                    'peta' => ['fajar@example.test' => 'AN-020'],
                ],
                'kemungkinan_duplikat' => [[
                    'row' => 2,
                    'nama' => 'Fajar',
                    'email' => 'fajar@example.test',
                    'jenis' => 'Data anak',
                    'keyakinan' => 'Tinggi',
                    'keterangan' => 'Nama sama dengan anak AN-020.',
                ]],
            ])
            ->assertSee('Penautan ke Data Anak')
            ->assertSee('Akan ditautkan')
            ->assertSee('AN-020')
            ->assertSee('Nama kembar: 2 anak memakai nama ini')
            ->assertSee('Kemungkinan Duplikat');
    }

    public function test_import_semua_binaan_melaporkan_ringkasan_per_anak(): void
    {
        $admin = $this->admin();

        $ringkasan = app(ImportBinaanService::class)->importSemua(
            ['AN-001', 'AN-002', 'AN-999'],
            $admin->id,
        );

        $this->assertSame(2, $ringkasan['dibuat']);
        $this->assertSame(0, $ringkasan['sudah_tertaut']);
        $this->assertSame(1, $ringkasan['tidak_ada']);
        $this->assertSame(0, $ringkasan['gagal']);
        $this->assertCount(3, $ringkasan['detail']);

        $this->assertSame(2, User::query()->whereNotNull('anak_id')->count());
        $this->assertSame(
            UserStatus::Draft,
            User::query()->where('anak_id', 'AN-001')->firstOrFail()->status
        );
    }
}
