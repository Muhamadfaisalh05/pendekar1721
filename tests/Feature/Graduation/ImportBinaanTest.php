<?php

namespace Tests\Feature\Graduation;

use App\Enums\UserStatus;
use App\Filament\Pages\ImportData;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use App\Services\GraduationService;
use App\Services\ImportBinaanService;
use App\Services\ImportService;
use App\Support\Import\SpesifikasiKolomImport;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\Import\ImportTestCase;

/**
 * FASE 3 — impor "Remaja Binaan" dan "Klien Umum" pada menu Import Data.
 *
 * Yang dikunci oleh tes ini:
 * - mode binaan membuat data anak (status Aktif) + akun Draft tertaut;
 * - email boleh kosong (kasus "kholis") dan akun baru bisa login setelah lulus;
 * - kolom wajib mode binaan ditolak di langkah Preview bila kosong;
 * - opsi "Tautkan ke anak yang sudah ada" hanya menautkan baris yang cocok;
 * - kegagalan pembuatan akun menghapus kembali anak yang baru dibuat;
 * - mode Klien Umum tidak berubah (akun aktif, tanpa data anak).
 */
class ImportBinaanTest extends ImportTestCase
{
    /**
     * Baris hasil validasi (struktur yang dipakai importData) untuk mode binaan.
     *
     * @param  array<string, mixed>  $ganti
     * @return array<string, mixed>
     */
    private function barisBinaan(array $ganti = []): array
    {
        return array_merge([
            'name' => 'Rizky Pratama',
            'email' => 'rizky.pratama@example.test',
            'gender' => 'L',
            'asal_daerah' => 'Bandung',
            'agama' => 'Islam',
            'umur' => 17,
            'angkatan' => 'Angkatan 2026',
            'birth_date' => null,
            'birth_place' => null,
            'ktp_address' => null,
            'ktp_city_id' => null,
            'education_degree_id' => null,
            'trainings' => [],
            'work_locations' => [],
            'is_current_job' => false,
            'description' => null,
            'hire_status' => '0',
            'photo_filename' => '',
            '_row_number' => 2,
        ], $ganti);
    }

    public function test_import_binaan_membuat_anak_aktif_dan_akun_draft_tertaut(): void
    {
        $admin = $this->admin();
        $sebelum = Anak::query()->count();

        $ringkasan = (new ImportService)->importData(
            [$this->barisBinaan()],
            [],
            $admin->id,
            null,
            ImportService::MODE_BINAAN,
        );

        $this->assertSame(1, $ringkasan['anak_baru']);
        $this->assertSame(0, $ringkasan['ditautkan']);
        $this->assertSame(1, $ringkasan['akun_dibuat']);
        $this->assertSame(0, $ringkasan['gagal']);
        $this->assertSame($sebelum + 1, Anak::query()->count());

        $anak = Anak::query()->where('nama', 'Rizky Pratama')->firstOrFail();
        $this->assertSame(Anak::STATUS_AKTIF, $anak->status);
        $this->assertSame('Laki-laki', $anak->jenis_kelamin);
        $this->assertSame('Bandung', $anak->asal_daerah);
        $this->assertSame('Islam', $anak->agama);
        $this->assertSame(17, $anak->umur);
        $this->assertSame('Angkatan 2026', $anak->angkatan);
        $this->assertMatchesRegularExpression('/^AN-\d{3,}$/', (string) $anak->id);

        $user = User::query()->where('anak_id', $anak->id)->firstOrFail();
        $this->assertSame('Rizky Pratama', $user->name);
        $this->assertSame(User::ROLE_CLIENT, $user->user_type);
        $this->assertSame(UserStatus::Draft, $user->status);
        $this->assertNull($user->password, 'Akun binaan belum boleh punya kata sandi.');
        $this->assertNull($user->graduated_at);
        $this->assertSame($admin->id, $user->created_by);
        $this->assertTrue($user->userProfile()->exists());
        $this->assertFalse($user->isAlumni());

        // Akun Draft belum bisa login sebelum diaktifkan pada proses kelulusan.
        $this->post('/login', [
            'email' => 'rizky.pratama@example.test',
            'password' => 'apa-saja-yang-ditebak',
        ])->assertInvalid([
            'email' => 'Email atau kata sandi tidak sesuai. Mohon periksa kembali lalu coba lagi.',
        ]);

        $this->assertGuest();
    }

    public function test_import_binaan_tanpa_email_berhasil(): void
    {
        $ringkasan = (new ImportService)->importData(
            [$this->barisBinaan([
                'name' => 'Kholis',
                'email' => null,
                'gender' => 'L',
                'asal_daerah' => 'Cirebon',
                'umur' => 16,
            ])],
            [],
            null,
            null,
            ImportService::MODE_BINAAN,
        );

        $this->assertSame(0, $ringkasan['gagal']);
        $this->assertSame(1, $ringkasan['anak_baru']);
        $this->assertSame(1, $ringkasan['akun_dibuat']);

        $user = User::query()->where('name', 'Kholis')->firstOrFail();

        $this->assertNull($user->email, 'Email boleh kosong pada mode Remaja Binaan.');
        $this->assertNull($user->password);
        $this->assertSame(UserStatus::Draft, $user->status);
        $this->assertNotNull($user->anak_id);

        $anak = Anak::query()->findOrFail($user->anak_id);
        $this->assertSame(Anak::STATUS_AKTIF, $anak->status);
        $this->assertSame('Cirebon', $anak->asal_daerah);
    }

    public function test_preview_binaan_menolak_kolom_wajib_yang_kosong(): void
    {
        $header = SpesifikasiKolomImport::header();
        $baris = array_fill(0, count($header), '');

        // Hanya Nama & Email yang diisi; kolom wajib lainnya kosong.
        $baris[0] = '1';
        $baris[1] = 'Kholis';
        $baris[8] = 'kholis@example.test';

        $path = $this->tulisXlsx($header, [$baris], 'wajib-kosong.xlsx');

        $hasil = (new ImportService)->parseAndValidate($path, 'xlsx', ImportService::MODE_BINAAN);

        $this->assertSame([], $hasil['valid']);
        $this->assertSame(ImportService::MODE_BINAAN, $hasil['mode']);

        $kolomError = array_column($hasil['errors'], 'col');

        $this->assertContains('Jenis Kelamin', $kolomError);
        $this->assertContains('Asal Daerah', $kolomError);
        $this->assertContains('Agama', $kolomError);
        $this->assertContains('Umur', $kolomError);
        $this->assertContains('Angkatan', $kolomError);
    }

    public function test_preview_binaan_menghitung_umur_dari_tanggal_lahir(): void
    {
        $header = SpesifikasiKolomImport::header();
        $baris = array_fill(0, count($header), '');

        $baris[0] = '1';
        $baris[1] = 'Nadia Putri';
        $baris[2] = 'P';
        $baris[3] = 'Sumedang';
        $baris[4] = 'Islam';
        $baris[5] = '';                       // Umur sengaja kosong
        $baris[6] = (new \DateTimeImmutable('-16 years -1 month'))->format('d/m/Y');
        $baris[7] = 'Angkatan 2026';
        $baris[8] = 'nadia@example.test';

        $path = $this->tulisXlsx($header, [$baris], 'umur-dari-tanggal.xlsx');

        $hasil = (new ImportService)->parseAndValidate($path, 'xlsx', ImportService::MODE_BINAAN);

        $this->assertSame([], $hasil['errors']);
        $this->assertCount(1, $hasil['valid']);
        $this->assertSame(16, $hasil['valid'][0]['umur']);
        $this->assertSame('P', $hasil['valid'][0]['gender']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $hasil['valid'][0]['birth_date']);
    }

    public function test_mode_tautkan_hanya_menautkan_anak_yang_cocok(): void
    {
        $admin = $this->admin();

        // Fajar: nama unik, belum berakun, asal & angkatan sama → kandidat tautan.
        $this->tambahAnak('AN-900', 'Fajar Nugraha', 'Bandung', 'Angkatan 2026');
        $sebelum = Anak::query()->count();

        $valid = [
            $this->barisBinaan([
                'name' => 'Fajar Nugraha',
                'email' => 'fajar@example.test',
                'asal_daerah' => 'Bandung',
                'angkatan' => 'Angkatan 2026',
                '_row_number' => 2,
            ]),
            $this->barisBinaan([
                'name' => 'Tanpa Kembar',
                'email' => 'tanpa.kembar@example.test',
                '_row_number' => 3,
            ]),
        ];

        $ringkasan = (new ImportService)->importData(
            $valid,
            [],
            $admin->id,
            null,
            ImportService::MODE_BINAAN,
            true,
        );

        $this->assertSame(1, $ringkasan['ditautkan'], 'Baris yang cocok tidak boleh membuat anak baru.');
        $this->assertSame(1, $ringkasan['anak_baru']);
        $this->assertSame(0, $ringkasan['gagal']);
        $this->assertSame($sebelum + 1, Anak::query()->count());

        $fajar = User::query()->where('email', 'fajar@example.test')->firstOrFail();
        $this->assertSame('AN-900', $fajar->anak_id);
        $this->assertSame(1, Anak::query()->where('nama', 'Fajar Nugraha')->count());

        $baru = User::query()->where('email', 'tanpa.kembar@example.test')->firstOrFail();
        $this->assertNotSame('AN-900', $baru->anak_id);
        $this->assertSame(Anak::STATUS_AKTIF, $baru->anak?->status);
    }

    public function test_mode_tautkan_tidak_menautkan_nama_kembar_tanpa_alamat_pembeda(): void
    {
        $this->tambahAnak('AN-910', 'Cece', 'Bandung', 'Angkatan III');
        $this->tambahAnak('AN-911', 'Cece', 'Garut', 'Angkatan III');

        $hasil = (new ImportService)->petaTautanAnak([
            $this->barisBinaan([
                'name' => 'Cece',
                'email' => 'cece@example.test',
                'asal_daerah' => 'Bandung',
                '_row_number' => 2,
            ]),
            // Doddy ada di AN-002 (Bogor · Angkatan II) — identitas bertentangan.
            $this->barisBinaan([
                'name' => 'Doddy',
                'email' => 'doddy.lain@example.test',
                'asal_daerah' => 'Jakarta',
                'angkatan' => 'Angkatan I',
                '_row_number' => 3,
            ]),
        ], true);

        $this->assertSame([], $hasil['akan'], 'Nama kembar tanpa alamat KTP tidak boleh ditautkan.');
        $this->assertSame([], $hasil['peta']);

        $alasan = array_column($hasil['tidak'], 'alasan');
        $this->assertContains(
            'Nama kembar: 3 anak memakai nama ini; alamat/tanggal lahir tidak dapat dipastikan',
            $alasan
        );
        $this->assertContains('Asal daerah berbeda dengan data anak AN-002', $alasan);

        $this->assertSame(['anak_baru', 'anak_baru'], array_column($hasil['rencana'], 'aksi'));
    }

    public function test_preview_menampilkan_rencana_anak_baru_atau_tautkan(): void
    {
        $this->tambahAnak('AN-920', 'Fajar', 'Bandung', 'Angkatan 2026');

        $hasil = (new ImportService)->petaTautanAnak([
            $this->barisBinaan([
                'name' => 'Fajar',
                'email' => 'fajar@example.test',
                'angkatan' => 'Angkatan 2026',
                '_row_number' => 2,
            ]),
            $this->barisBinaan([
                'name' => 'Fiktif',
                'email' => 'fiktif@example.test',
                '_row_number' => 3,
            ]),
        ], true);

        $this->assertSame('tautkan', $hasil['rencana'][0]['aksi']);
        $this->assertSame('AN-920', $hasil['rencana'][0]['anak_id']);
        $this->assertStringContainsString('Tautkan ke AN-920', $hasil['rencana'][0]['keterangan']);

        $this->assertSame('anak_baru', $hasil['rencana'][1]['aksi']);
        $this->assertStringContainsString('Anak baru + akun Draft', $hasil['rencana'][1]['keterangan']);

        // Tanpa centang, semua baris selalu membuat anak baru.
        $tanpaTautan = (new ImportService)->petaTautanAnak([
            $this->barisBinaan(['name' => 'Fajar', '_row_number' => 2]),
        ]);

        $this->assertSame('anak_baru', $tanpaTautan['rencana'][0]['aksi']);
    }

    public function test_kegagalan_pembuatan_akun_mengompensasi_anak_baru(): void
    {
        // Email sudah dipakai akun lain → INSERT users gagal (unique).
        User::create([
            'name' => 'Akun Lama',
            'email' => 'email.bentrok@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_CLIENT,
            'status' => UserStatus::Published,
        ]);

        $sebelum = Anak::query()->count();

        $ringkasan = (new ImportService)->importData(
            [$this->barisBinaan([
                'name' => 'Budi Kompensasi',
                'email' => 'email.bentrok@example.test',
            ])],
            [],
            null,
            null,
            ImportService::MODE_BINAAN,
        );

        $this->assertSame(1, $ringkasan['gagal']);
        $this->assertSame(0, $ringkasan['akun_dibuat']);
        $this->assertSame($sebelum, Anak::query()->count(), 'Anak yang baru dibuat harus dihapus kembali.');
        $this->assertSame(0, Anak::query()->where('nama', 'Budi Kompensasi')->count());

        $this->assertSame(2, $ringkasan['gagal_detail'][0]['row']);
        $this->assertSame('Budi Kompensasi', $ringkasan['gagal_detail'][0]['nama']);
        $this->assertArrayNotHasKey('email', $ringkasan['gagal_detail'][0], 'Laporan tidak boleh memuat email (PII).');
    }

    public function test_mode_klien_tetap_membuat_akun_aktif_tanpa_data_anak(): void
    {
        $sebelum = Anak::query()->count();

        $ringkasan = (new ImportService)->importData(
            [$this->barisBinaan([
                'name' => 'Siti Aminah',
                'email' => 'siti.klien@example.test',
            ])],
            [],
            null,
            'Rahasia#2026',
            ImportService::MODE_KLIEN,
        );

        $this->assertSame(0, $ringkasan['anak_baru']);
        $this->assertSame(0, $ringkasan['ditautkan']);
        $this->assertSame(1, $ringkasan['akun_dibuat']);
        $this->assertSame($sebelum, Anak::query()->count(), 'Mode klien tidak membuat data anak.');

        $user = User::query()->where('email', 'siti.klien@example.test')->firstOrFail();

        $this->assertSame(UserStatus::Published, $user->status);
        $this->assertNull($user->anak_id);
        $this->assertTrue(Hash::check('Rahasia#2026', (string) $user->password));

        $this->post('/login', [
            'email' => 'siti.klien@example.test',
            'password' => 'Rahasia#2026',
        ])->assertRedirect('/klien');

        $this->assertAuthenticatedAs($user);
    }

    public function test_mode_binaan_mengabaikan_kata_sandi_awal_operator(): void
    {
        (new ImportService)->importData(
            [$this->barisBinaan(['name' => 'Doddy Sandi', 'email' => 'doddy.sandi@example.test'])],
            [],
            null,
            'Rahasia#2026',
            ImportService::MODE_BINAAN,
        );

        $user = User::query()->where('email', 'doddy.sandi@example.test')->firstOrFail();

        $this->assertNull($user->password, 'Mode binaan tidak boleh menyimpan kata sandi sama sekali.');
        $this->assertSame(UserStatus::Draft, $user->status);
    }

    public function test_akun_binaan_hasil_import_dipakai_saat_kelulusan(): void
    {
        $admin = $this->admin();

        (new ImportService)->importData(
            [$this->barisBinaan([
                'name' => 'Doddy Binaan',
                'email' => 'doddy.binaan@example.test',
            ])],
            [],
            $admin->id,
            null,
            ImportService::MODE_BINAAN,
        );

        $binaan = User::query()->where('email', 'doddy.binaan@example.test')->firstOrFail();
        $anak = Anak::query()->findOrFail($binaan->anak_id);

        // Email sama (beda kapital) dengan akun yang sudah tertaut: syarat 1 —
        // tidak boleh dianggap "sudah dipakai akun lain".
        $hasil = app(GraduationService::class)->luluskan($anak, '  DODDY.BINAAN@example.test ', null, $admin->id);

        $this->assertFalse($hasil['akun_dibuat'], 'Akun hasil impor harus dipakai ulang, bukan dibuat baru.');
        $this->assertSame($binaan->id, $hasil['user']->id);
        $this->assertSame(UserStatus::Published, $hasil['user']->status);
        $this->assertSame('doddy.binaan@example.test', $hasil['user']->email);
        $this->assertNotNull($hasil['user']->graduated_at);
        $this->assertTrue($hasil['user']->isAlumni());
        $this->assertSame(Anak::STATUS_LULUS, $anak->fresh()->status);
        $this->assertSame(1, User::query()->where('anak_id', $anak->id)->count());

        // Syarat 3: kata sandi acak dikembalikan sekali dan tersimpan sebagai hash.
        $this->assertIsString($hasil['password']);
        $this->assertTrue(Hash::check($hasil['password'], (string) $hasil['user']->fresh()->password));
    }

    public function test_form_import_data_memakai_judul_import_data_dan_opsi_penautan_baru(): void
    {
        $this->actingAs($this->admin());

        $komponen = Livewire::test(ImportData::class)
            ->assertSuccessful()
            ->assertSet('tautkan_anak', false)
            ->assertSet('mode', ImportService::MODE_BINAAN)
            ->assertSee('Tautkan ke anak yang sudah ada (jangan buat anak baru)')
            ->assertSee('Remaja Binaan');

        $this->assertSame('Import Data', $komponen->instance()->getTitle());
    }

    public function test_preview_halaman_menampilkan_rencana_per_baris(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ImportData::class)
            ->set('previewData', [
                'total' => 2,
                'mode' => ImportService::MODE_BINAAN,
                'valid' => [
                    ['name' => 'Fajar', 'email' => 'fajar@example.test', 'gender' => 'L', 'ktp_city_id' => null, 'hire_status' => '0', 'photo_filename' => null],
                ],
                'errors' => [],
                'duplicates' => [],
                'tautan' => [
                    'akan' => [],
                    'tidak' => [],
                    'peta' => ['2' => 'AN-920'],
                    'rencana' => [
                        [
                            'row' => 2,
                            'nama' => 'Fajar',
                            'email' => 'fajar@example.test',
                            'aksi' => 'tautkan',
                            'anak_id' => 'AN-920',
                            'keterangan' => 'Tautkan ke AN-920 (akun Draft, tanpa anak baru)',
                        ],
                        [
                            'row' => 3,
                            'nama' => 'Fiktif',
                            'email' => null,
                            'aksi' => 'anak_baru',
                            'anak_id' => null,
                            'keterangan' => 'Anak baru (status Aktif) + akun Draft',
                        ],
                    ],
                ],
                'duplikat' => [
                    'pasti' => [[
                        'row' => 2,
                        'nama' => 'Fajar',
                        'alamat' => 'Jl. Merdeka No. 10',
                        'sumber' => 'Data lama',
                        'anak_id' => 'AN-920',
                        'keterangan' => 'Nama, alamat, dan angkatan sama dengan anak AN-920 (Data lama) sehingga kemungkinan data ganda.',
                    ]],
                    'kemungkinan' => [[
                        'row' => 3,
                        'nama' => 'Fiktif',
                        'alamat' => null,
                        'jenis' => 'Peringatan',
                        'sumber' => 'Di dalam file',
                        'anak_id' => null,
                        'keterangan' => 'Nama, asal daerah, dan angkatan sama dengan baris 2 (Di dalam file), tetapi alamat KTP kosong.',
                    ]],
                    'dilewati' => [2],
                ],
                'impor_ganda' => false,
            ])
            ->assertSee('Rencana Import per Baris')
            ->assertSee('Tautkan ke AN-920')
            ->assertSee('Anak baru + akun Draft')
            ->assertSee('Duplikat & Nama Sama')
            ->assertSee('Ganda pasti (1)')
            ->assertSee('Nama sama, identitas belum pasti (1)')
            ->assertSee('tidak akan diimpor');
    }

    public function test_analisis_duplikat_binaan_memakai_identitas_lengkap(): void
    {
        $hasil = (new ImportService)->analisisDuplikatBinaan([
            // Dua baris dengan identitas lengkap sama → baris kedua ganda pasti.
            $this->barisBinaan(['name' => 'Sari', 'email' => 'sari.satu@example.test', 'asal_daerah' => 'Bandung', 'angkatan' => 'Angkatan III', 'ktp_address' => 'Jl. Merdeka No. 10', '_row_number' => 2]),
            $this->barisBinaan(['name' => 'Sari', 'email' => 'sari.dua@example.test', 'asal_daerah' => 'bandung', 'angkatan' => 'angkatan iii', 'ktp_address' => 'jl.  merdeka no. 10', '_row_number' => 3]),
            // Nama sama dengan anak AN-002 (Bogor · Angkatan II) tetapi anak itu
            // belum punya akun/alamat KTP → hanya peringatan, bukan ganda pasti.
            $this->barisBinaan(['name' => 'Doddy', 'email' => 'doddy.dup@example.test', 'asal_daerah' => 'bogor', 'angkatan' => 'angkatan ii', '_row_number' => 4]),
            // Nama sama, asal daerah & angkatan berbeda → dianggap orang lain.
            $this->barisBinaan(['name' => 'Doddy', 'email' => 'doddy.lain@example.test', 'asal_daerah' => 'Jakarta', 'angkatan' => 'Angkatan I', '_row_number' => 5]),
        ]);

        $this->assertSame([3], array_column($hasil['pasti'], 'row'));
        $this->assertSame('Di dalam file', $hasil['pasti'][0]['sumber']);
        $this->assertSame('jl.  merdeka no. 10', $hasil['pasti'][0]['alamat']);

        $this->assertSame([4], array_column($hasil['kemungkinan'], 'row'));
        $this->assertSame('Peringatan', $hasil['kemungkinan'][0]['jenis']);
        $this->assertSame('AN-002', $hasil['kemungkinan'][0]['anak_id']);

        $this->assertSame([3], $hasil['dilewati']);
    }

    public function test_duplikat_dicek_terhadap_akun_klien_lama_lewat_alamat_ktp(): void
    {
        $lama = User::create([
            'name' => 'Sari Lama',
            'email' => 'sari.lama@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_CLIENT,
            'status' => UserStatus::Published,
        ]);
        $lama->userProfile()->create([
            'ktp_address' => 'Jl. Merdeka No. 10',
            'birth_date' => '2008-02-03',
        ]);

        $hasil = (new ImportService)->analisisDuplikatBinaan([
            // Nama + alamat + tanggal lahir sama dengan akun lama → ganda pasti.
            $this->barisBinaan(['name' => 'sari lama', 'email' => 'sari.baru@example.test', 'ktp_address' => 'Jl. Merdeka No. 10', 'birth_date' => '2008-02-03', '_row_number' => 2]),
            // Nama sama, alamat & tanggal lahir berbeda → orang lain, tidak dilaporkan.
            $this->barisBinaan(['name' => 'Sari Lama', 'email' => 'sari.lain@example.test', 'ktp_address' => 'Jl. Cihampelas No. 22', 'birth_date' => '2009-05-06', '_row_number' => 3]),
        ]);

        $this->assertSame([2], array_column($hasil['pasti'], 'row'));
        $this->assertSame('Akun', $hasil['pasti'][0]['sumber']);
        $this->assertNull($hasil['pasti'][0]['anak_id'], 'Akun lama belum tertaut ke data anak.');
        $this->assertSame([], $hasil['kemungkinan'], 'Alamat/tanggal lahir berbeda berarti orang lain.');
        $this->assertSame([2], $hasil['dilewati']);
    }

    public function test_nama_sama_alamat_berbeda_tetap_diimpor_sebagai_anak_berbeda(): void
    {
        $ringkasan = (new ImportService)->importData([
            $this->barisBinaan(['name' => 'Sari', 'email' => 'sari.satu@example.test', 'ktp_address' => 'Jl. Merdeka No. 10', '_row_number' => 2]),
            $this->barisBinaan(['name' => 'Sari', 'email' => 'sari.dua@example.test', 'ktp_address' => 'Jl. Cihampelas No. 22', '_row_number' => 3]),
        ], [], null, null, ImportService::MODE_BINAAN);

        $this->assertSame(2, $ringkasan['anak_baru'], 'Nama sama dengan alamat berbeda bukan duplikat.');
        $this->assertSame(0, $ringkasan['dilewati']);
        $this->assertSame(2, Anak::query()->where('nama', 'Sari')->count());
    }

    public function test_import_melewati_ganda_pasti_dan_bisa_dipaksa_dengan_opsi_impor_ganda(): void
    {
        $admin = $this->admin();
        $baris = [
            $this->barisBinaan(['name' => 'Sari', 'email' => 'sari.satu@example.test', 'ktp_address' => 'Jl. Merdeka No. 10', '_row_number' => 2]),
            $this->barisBinaan(['name' => 'Sari', 'email' => 'sari.dua@example.test', 'ktp_address' => 'Jl. Merdeka No. 10', '_row_number' => 3]),
        ];

        $ringkasan = (new ImportService)->importData($baris, [], $admin->id, null, ImportService::MODE_BINAAN);

        $this->assertSame(1, $ringkasan['anak_baru'], 'Baris ganda pasti harus dilewati.');
        $this->assertSame(1, $ringkasan['dilewati']);
        $this->assertSame([3], array_column($ringkasan['dilewati_detail'], 'row'));
        $this->assertSame(1, Anak::query()->where('nama', 'Sari')->count());

        // Opsi "Impor baris ganda" memaksa semua baris masuk. Email harus baru
        // karena email pada impor pertama sudah terpakai (unique).
        $paksa = (new ImportService)->importData(
            [
                array_merge($baris[0], ['email' => 'sari.tiga@example.test']),
                array_merge($baris[1], ['email' => 'sari.empat@example.test']),
            ],
            [],
            $admin->id,
            null,
            ImportService::MODE_BINAAN,
            false,
            true,
        );

        $this->assertSame(2, $paksa['anak_baru']);
        $this->assertSame(0, $paksa['gagal']);
        $this->assertSame(0, $paksa['dilewati']);
        $this->assertSame(3, Anak::query()->where('nama', 'Sari')->count());
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
