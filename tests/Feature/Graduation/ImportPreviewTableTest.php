<?php

namespace Tests\Feature\Graduation;

use App\Enums\UserStatus;
use App\Filament\Pages\ImportData;
use App\Models\MasterCity;
use App\Models\MasterEducationDegree;
use App\Models\MasterReligion;
use App\Models\MasterTraining;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use App\Services\ImportService;
use App\Support\Import\LaporanHasilImportExcel;
use App\Support\Import\SpesifikasiKolomImport;
use App\Support\Import\TemplateExcel;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\Import\ImportTestCase;

/**
 * Kontrak revisi Import Data:
 * - dropdown Excel benar-benar tampil dan memakai named range,
 * - baris CONTOH dilewati (bukan error),
 * - tabel Preview memuat semua baris + filter/paginasi,
 * - tabel Hasil memuat ID anak/status akun/alasan gagal,
 * - laporan hasil tidak memuat email/alamat.
 */
class ImportPreviewTableTest extends ImportTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        MasterReligion::create(['title' => 'Islam']);
        MasterCity::create(['title' => 'Kota Bandung']);
        MasterEducationDegree::create(['title' => 'SMA/SMK']);
        MasterTraining::create(['title' => 'Menjahit']);
    }

    protected function tearDown(): void
    {
        foreach (['xlsx', 'docx'] as $ekstensi) {
            $berkas = storage_path('app/import-templates/template-import-remaja-binaan-'.now()->format('Ymd').'.'.$ekstensi);

            if (is_file($berkas)) {
                @unlink($berkas);
            }
        }

        parent::tearDown();
    }

    /**
     * Satu baris lengkap sesuai urutan header template.
     *
     * @param  array<string, mixed>  $ganti
     * @return array<int, mixed>
     */
    private function barisSesuaiHeader(array $ganti = []): array
    {
        $nilai = array_merge([
            'nama' => 'Peserta Uji',
            'jenis_kelamin' => 'L',
            'asal_daerah' => 'Bandung',
            'agama' => 'Islam',
            'umur' => 17,
            'angkatan' => 'Angkatan 2026',
            'email' => 'peserta@example.test',
            'kota_ktp' => 'Kota Bandung',
            'pendidikan' => 'SMA/SMK',
            'pelatihan' => 'Menjahit',
            'penempatan_wilayah' => 'Kota Bandung',
            'status_pekerjaan' => 'Belum Bekerja',
            'status_profil' => 'Tersedia',
        ], $ganti);

        $baris = [];

        foreach (SpesifikasiKolomImport::kolom() as $kolom) {
            $baris[] = $nilai[$kolom['kunci']] ?? '';
        }

        return $baris;
    }

    private function kolomKe(string $kunci): int
    {
        $index = array_search($kunci, array_column(SpesifikasiKolomImport::kolom(), 'kunci'), true);

        return ($index === false ? 0 : $index) + 1;
    }

    public function test_excel_memakai_named_range_dan_dropdown_tampil(): void
    {
        $path = $this->layananTemplate()->buatExcel();
        $spreadsheet = IOFactory::load($path);
        $data = $spreadsheet->getSheetByName('DATA');
        $daftar = $spreadsheet->getSheetByName('DAFTAR');

        $this->assertNotNull($data);
        $this->assertNotNull($daftar);

        // Isi DAFTAR persis nilai master/enum saat template dibuat.
        $this->assertSame('Islam', $daftar->getCell('A2')->getValue());
        $this->assertSame('L', $daftar->getCell('B2')->getValue());
        $this->assertSame('SMA/SMK', $daftar->getCell('C2')->getValue());
        $this->assertSame('Menjahit', $daftar->getCell('D2')->getValue());
        $this->assertSame('Kota Bandung', $daftar->getCell('F2')->getValue());
        $this->assertSame('Belum Bekerja', $daftar->getCell('G2')->getValue());
        $this->assertSame('Tersedia', $daftar->getCell('H2')->getValue());

        $namedRange = [];
        foreach ($spreadsheet->getDefinedNames() as $range) {
            $namedRange[$range->getName()] = (string) $range->getValue();
        }

        $harapan = [
            'jenis_kelamin' => 'DAF_JENIS_KELAMIN',
            'agama' => 'DAF_AGAMA',
            'pendidikan' => 'DAF_PENDIDIKAN',
            'pelatihan' => 'DAF_PELATIHAN',
            'penempatan_wilayah' => 'DAF_WILAYAH',
            'kota_ktp' => 'DAF_KOTA_KTP',
            'status_pekerjaan' => 'DAF_STATUS_PEKERJAAN',
            'status_profil' => 'DAF_STATUS_PROFIL',
        ];

        $this->assertCount(8, $namedRange);

        foreach ($harapan as $kunci => $namaRange) {
            $this->assertArrayHasKey($namaRange, $namedRange, "Named range {$namaRange} harus ada.");
            $this->assertStringStartsWith("'DAFTAR'!", $namedRange[$namaRange]);

            $huruf = Coordinate::stringFromColumnIndex($this->kolomKe($kunci));
            $rentang = $huruf.TemplateExcel::BARIS_CONTOH.':'.$huruf.(TemplateExcel::BARIS_AWAL_DATA + TemplateExcel::JUMLAH_BARIS_KOSONG - 1);
            $validasi = $data->getDataValidation($rentang);

            $this->assertSame(DataValidation::TYPE_LIST, $validasi->getType(), "{$kunci} harus dropdown list.");
            $this->assertSame($namaRange, (string) $validasi->getFormula1(), "{$kunci} harus menunjuk named range.");
            $this->assertTrue($validasi->getShowDropDown(), "{$kunci} harus menampilkan panah dropdown di Excel.");
            $this->assertTrue($validasi->getAllowBlank(), "{$kunci} harus boleh kosong.");
            $this->assertSame(DataValidation::STYLE_WARNING, $validasi->getErrorStyle(), "{$kunci} memakai peringatan, bukan blokir.");
        }
    }

    public function test_round_trip_nilai_dropdown_dua_baris_lulus_import(): void
    {
        $path = $this->layananTemplate()->buatExcel();
        $spreadsheet = IOFactory::load($path);
        $data = $spreadsheet->getSheetByName('DATA');

        $data->removeRow(TemplateExcel::BARIS_CONTOH);
        $barisAwal = TemplateExcel::BARIS_AWAL_DATA - 1;

        $nilaiDropdown = [
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'pendidikan' => 'SMA/SMK',
            'pelatihan' => 'Menjahit',
            'kota_ktp' => 'Kota Bandung',
            'penempatan_wilayah' => 'Kota Bandung',
            'status_pekerjaan' => 'Belum Bekerja',
            'status_profil' => 'Tersedia',
        ];

        foreach ([
            ['nama' => 'Dropdown Satu', 'email' => 'dropdown.satu@example.test', 'asal_daerah' => 'Bandung'],
            ['nama' => 'Dropdown Dua', 'email' => 'dropdown.dua@example.test', 'asal_daerah' => 'Sumedang'],
        ] as $offset => $identitas) {
            $baris = array_merge($nilaiDropdown, $identitas, ['umur' => 17, 'angkatan' => 'Angkatan 2026']);

            foreach ($baris as $kunci => $isi) {
                $huruf = Coordinate::stringFromColumnIndex($this->kolomKe($kunci));
                $data->setCellValue($huruf.($barisAwal + $offset), $isi);
            }
        }

        $tujuan = $this->folderUji().DIRECTORY_SEPARATOR.'dropdown-round-trip.xlsx';
        (new Xlsx($spreadsheet))->save($tujuan);

        $preview = (new ImportService)->parseAndValidate($tujuan, 'xlsx', ImportService::MODE_BINAAN);

        $this->assertSame([], $preview['errors']);
        $this->assertCount(2, $preview['valid']);
        $this->assertSame([], $preview['dilewati']);

        $ringkasan = (new ImportService)->importData($preview['valid'], [], null, null, ImportService::MODE_BINAAN);

        $this->assertSame(2, $ringkasan['anak_baru']);
        $this->assertSame(2, $ringkasan['akun_dibuat']);
        $this->assertSame(2, User::query()->whereNotNull('anak_id')->where('status', UserStatus::Draft)->count());
    }

    public function test_contoh_dilewati_dan_dua_baris_valid_tetap_diimpor(): void
    {
        $path = $this->tulisXlsx(
            SpesifikasiKolomImport::header(),
            [
                $this->barisSesuaiHeader(['nama' => SpesifikasiKolomImport::PENANDA_CONTOH]),
                $this->barisSesuaiHeader(['nama' => 'Valid Satu', 'email' => 'valid.satu@example.test']),
                $this->barisSesuaiHeader(['nama' => 'Valid Dua', 'email' => 'valid.dua@example.test', 'asal_daerah' => 'Sumedang']),
            ],
            'contoh-plus-valid.xlsx'
        );

        $preview = (new ImportService)->parseAndValidate($path, 'xlsx', ImportService::MODE_BINAAN);

        $this->assertSame([], $preview['errors'], 'Baris CONTOH tidak boleh memblokir import.');
        $this->assertCount(2, $preview['valid']);
        $this->assertCount(1, $preview['dilewati']);
        $this->assertSame(3, $preview['ringkasan']['total']);
        $this->assertSame(2, $preview['ringkasan']['valid']);
        $this->assertSame(1, $preview['ringkasan']['dilewati']);
        $this->assertSame(
            ['Baris contoh dilewati (tidak diimpor)', 'Anak baru + akun Draft', 'Anak baru + akun Draft'],
            array_column($preview['preview'], 'keterangan')
        );

        $relatif = 'imports/uji-contoh-'.uniqid().'.xlsx';
        Storage::disk('local')->put($relatif, (string) file_get_contents($path));

        try {
            $komponen = Livewire::actingAs($this->admin())
                ->test(ImportData::class)
                ->set('file', [$relatif])
                ->set('previewData', $preview)
                ->assertSee('Baris contoh dilewati (tidak diimpor)')
                ->assertSee('Import Data Sekarang');

            $komponen->call('confirmImport');

            $this->assertSame(2, Anak::query()->whereIn('nama', ['Valid Satu', 'Valid Dua'])->count());
            $this->assertSame(0, Anak::query()->where('nama', SpesifikasiKolomImport::PENANDA_CONTOH)->count());
            $this->assertSame(2, User::query()->whereNotNull('anak_id')->where('status', UserStatus::Draft)->count());
        } finally {
            Storage::disk('local')->delete($relatif);
        }
    }

    public function test_file_hanya_contoh_tidak_menawarkan_import(): void
    {
        $path = $this->tulisXlsx(
            SpesifikasiKolomImport::header(),
            [$this->barisSesuaiHeader(['nama' => SpesifikasiKolomImport::PENANDA_CONTOH])],
            'hanya-contoh.xlsx'
        );

        $preview = (new ImportService)->parseAndValidate($path, 'xlsx', ImportService::MODE_BINAAN);

        $this->assertSame([], $preview['valid']);
        $this->assertSame([], $preview['errors']);

        Livewire::actingAs($this->admin())
            ->test(ImportData::class)
            ->set('previewData', $preview)
            ->call('confirmImport')
            ->assertNotified('Tidak ada data untuk diimpor');

        $this->assertSame(0, User::query()->where('user_type', User::ROLE_CLIENT)->count());
    }

    public function test_tabel_preview_menampilkan_semua_status_dengan_filter_dan_paginasi_25(): void
    {
        $this->tambahAnak('AN-900', 'Peserta Peringatan', 'Bogor', 'Angkatan II');
        $baris = [];

        for ($i = 1; $i <= 30; $i++) {
            $baris[] = $this->barisSesuaiHeader([
                'nama' => 'Peserta '.$i,
                'email' => 'peserta'.$i.'@example.test',
            ]);
        }

        $baris[] = $this->barisSesuaiHeader([
            'nama' => 'Baris Error',
            'email' => 'bukan-email',
        ]);
        $baris[] = $this->barisSesuaiHeader([
            'nama' => 'Peserta Peringatan',
            'email' => 'peserta.peringatan@example.test',
            'asal_daerah' => 'Bogor',
            'angkatan' => 'Angkatan II',
        ]);
        $baris[] = $this->barisSesuaiHeader(['nama' => SpesifikasiKolomImport::PENANDA_CONTOH]);

        $path = $this->tulisXlsx(SpesifikasiKolomImport::header(), $baris, 'preview-33.xlsx');
        $preview = (new ImportService)->parseAndValidate($path, 'xlsx', ImportService::MODE_BINAAN);

        $this->assertSame(33, $preview['ringkasan']['total']);
        $this->assertSame(30, $preview['ringkasan']['valid']);
        $this->assertSame(1, $preview['ringkasan']['peringatan']);
        $this->assertSame(1, $preview['ringkasan']['error']);
        $this->assertSame(1, $preview['ringkasan']['dilewati']);

        $komponen = Livewire::actingAs($this->admin())
            ->test(ImportData::class)
            ->set('previewData', $preview)
            ->assertSee('Tabel Preview Data')
            ->assertSee('Peserta 1')
            ->assertDontSee('Peserta 26');

        $halamanSatu = $komponen->instance()->barisPreview;
        $this->assertSame(25, $halamanSatu->count());
        $this->assertSame(33, $halamanSatu->total());
        $this->assertSame('Peserta 1', $halamanSatu->items()[0]['nama']);

        $komponen->call('gotoPreviewPage', 2);
        $halamanDua = $komponen->instance()->barisPreview;

        $this->assertSame(8, $halamanDua->count());
        $this->assertSame(2, $halamanDua->currentPage());

        $komponen->set('previewStatus', 'error');
        $filterError = $komponen->instance()->barisPreview;

        $this->assertSame(1, $filterError->total());
        $this->assertSame('Baris Error', $filterError->items()[0]['nama']);
        $this->assertSame('error', $filterError->items()[0]['status_baris']);
        $this->assertNotEmpty($filterError->items()[0]['masalah']['email']);
        $komponen->assertSee('Baris Error')->assertSee('Error');

        $komponen->set('previewStatus', 'peringatan');
        $filterPeringatan = $komponen->instance()->barisPreview;

        $this->assertSame(1, $filterPeringatan->total());
        $this->assertSame('Peserta Peringatan', $filterPeringatan->items()[0]['nama']);
        $this->assertSame('peringatan', $filterPeringatan->items()[0]['status_baris']);

        $komponen->set('previewStatus', 'semua');
        $komponen->set('previewSearch', 'Peserta 29');
        $cari = $komponen->instance()->barisPreview;

        $this->assertSame(1, $cari->total());
        $this->assertSame('Peserta 29', $cari->items()[0]['nama']);
    }

    public function test_tabel_hasil_menampilkan_id_anak_draft_dan_alasan_gagal(): void
    {
        $hasil = [
            'mode' => ImportService::MODE_BINAAN,
            'anak_baru' => 1,
            'ditautkan' => 0,
            'akun_dibuat' => 1,
            'gagal' => 1,
            'gagal_detail' => [],
            'dilewati' => 0,
            'dilewati_detail' => [],
            'hasil_detail' => [
                [
                    'row' => 2, 'blok' => null, 'nama' => 'Anak Berhasil',
                    'anak_id' => 'AN-777', 'status_akun' => 'Draft',
                    'hasil' => 'Berhasil', 'keterangan' => 'Anak baru + akun Draft',
                ],
                [
                    'row' => 3, 'blok' => null, 'nama' => 'Anak Gagal',
                    'anak_id' => null, 'status_akun' => '-',
                    'hasil' => 'Gagal', 'keterangan' => 'Email sudah dipakai akun lain',
                ],
            ],
            'hasil_ringkasan' => ['total' => 2, 'berhasil' => 1, 'dilewati' => 0, 'gagal' => 1],
        ];

        Livewire::actingAs($this->admin())
            ->test(ImportData::class)
            ->set('hasilImport', $hasil)
            ->assertSee('Hasil Import')
            ->assertSee('AN-777')
            ->assertSee('Draft')
            ->assertSee('Lihat di Anak Binaan')
            ->assertSee('Unduh laporan hasil')
            ->assertSee('Anak Gagal')
            ->assertSee('Email sudah dipakai akun lain');
    }

    public function test_laporan_hasil_import_memuat_id_anak_dan_tanpa_email_alamat(): void
    {
        $path = $this->tulisXlsx(
            SpesifikasiKolomImport::header(),
            [$this->barisSesuaiHeader([
                'nama' => 'Laporan Satu',
                'email' => 'laporan.satu@example.test',
                'alamat_ktp' => 'Jl. Rahasia No. 9',
            ])],
            'laporan.xlsx'
        );

        $preview = (new ImportService)->parseAndValidate($path, 'xlsx', ImportService::MODE_BINAAN);
        $ringkasan = (new ImportService)->importData($preview['valid'], [], null, null, ImportService::MODE_BINAAN);
        $laporan = (new ImportService)->laporanHasilImport($preview['preview'], $ringkasan);

        $this->assertSame('Berhasil', $laporan['baris'][0]['hasil']);
        $this->assertSame('Draft', $laporan['baris'][0]['status_akun']);
        $this->assertNotNull($laporan['baris'][0]['anak_id']);
        $this->assertSame(1, $laporan['ringkasan']['berhasil']);

        // Baris gagal tetap dilaporkan dengan alasannya.
        $laporanGagal = (new ImportService)->laporanHasilImport($preview['preview'], [
            'berhasil_detail' => [],
            'dilewati_detail' => [],
            'gagal_detail' => [[
                'row' => $preview['preview'][0]['row'],
                'blok' => null,
                'nama' => 'Laporan Satu',
                'alasan' => 'Akun gagal dibuat',
            ]],
        ]);

        $this->assertSame('Gagal', $laporanGagal['baris'][0]['hasil']);
        $this->assertSame('Akun gagal dibuat', $laporanGagal['baris'][0]['keterangan']);

        $file = $this->folderUji().DIRECTORY_SEPARATOR.'laporan-hasil.xlsx';
        (new LaporanHasilImportExcel)->simpan($laporan, $file);

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame(
            ['No / Blok', 'Nama', 'ID Anak', 'Status Akun', 'Hasil', 'Keterangan'],
            $sheet->rangeToArray('A4:F4')[0]
        );
        $this->assertSame($laporan['baris'][0]['anak_id'], $sheet->getCell('C5')->getValue());
        $this->assertSame('Draft', $sheet->getCell('D5')->getValue());
        $this->assertSame('Berhasil', $sheet->getCell('E5')->getValue());

        $teks = implode(' ', array_map(
            fn (array $baris): string => implode(' ', array_map('strval', $baris)),
            $sheet->rangeToArray('A1:F20')
        ));

        $this->assertStringNotContainsString('@example.test', $teks, 'Laporan tidak boleh memuat email.');
        $this->assertStringNotContainsString('Jl. Rahasia', $teks, 'Laporan tidak boleh memuat alamat KTP.');
    }

    public function test_mode_klien_umum_tetap_membuat_akun_aktif(): void
    {
        $path = $this->tulisXlsx(
            SpesifikasiKolomImport::header(ImportService::MODE_KLIEN),
            [$this->barisSesuaiHeader([
                'nama' => 'Klien Umum',
                'email' => 'klien.umum@example.test',
            ])],
            'klien-umum.xlsx'
        );

        $preview = (new ImportService)->parseAndValidate($path, 'xlsx', ImportService::MODE_KLIEN);

        $this->assertSame([], $preview['errors']);
        $this->assertSame('valid', $preview['preview'][0]['status_baris']);
        $this->assertSame('Akun klien aktif', $preview['preview'][0]['keterangan']);

        $ringkasan = (new ImportService)->importData(
            $preview['valid'],
            [],
            null,
            'Rahasia#2026',
            ImportService::MODE_KLIEN
        );

        $this->assertSame(1, $ringkasan['akun_dibuat']);
        $this->assertSame(0, $ringkasan['gagal']);

        $user = User::query()->where('email', 'klien.umum@example.test')->firstOrFail();
        $this->assertSame(UserStatus::Published, $user->status);
        $this->assertSame('Aktif', $ringkasan['berhasil_detail'][0]['status_akun']);
        $this->assertNull($ringkasan['berhasil_detail'][0]['anak_id']);
    }

    public function test_nama_sama_dengan_alamat_berbeda_tetap_dua_baris_valid(): void
    {
        $path = $this->tulisXlsx(
            SpesifikasiKolomImport::header(),
            [
                $this->barisSesuaiHeader([
                    'nama' => 'Sari Kembar',
                    'email' => 'sari.satu@example.test',
                    'alamat_ktp' => 'Jl. Merdeka No. 10',
                ]),
                $this->barisSesuaiHeader([
                    'nama' => 'Sari Kembar',
                    'email' => 'sari.dua@example.test',
                    'alamat_ktp' => 'Jl. Cihampelas No. 22',
                ]),
            ],
            'nama-sama.xlsx'
        );

        $preview = (new ImportService)->parseAndValidate($path, 'xlsx', ImportService::MODE_BINAAN);

        $this->assertSame([], $preview['errors']);
        $this->assertCount(2, $preview['valid']);
        $this->assertSame([], $preview['duplikat']['pasti']);

        $ringkasan = (new ImportService)->importData($preview['valid'], [], null, null, ImportService::MODE_BINAAN);

        $this->assertSame(2, $ringkasan['anak_baru']);
        $this->assertSame(2, Anak::query()->where('nama', 'Sari Kembar')->count());
    }

    public function test_file_300_baris_diproses_wajar(): void
    {
        $baris = [];

        for ($i = 1; $i <= 300; $i++) {
            $baris[] = $this->barisSesuaiHeader([
                'nama' => 'Massal '.$i,
                'email' => 'massal'.$i.'@example.test',
            ]);
        }

        $path = $this->tulisXlsx(SpesifikasiKolomImport::header(), $baris, 'massal-300.xlsx');

        $mulai = microtime(true);
        $preview = (new ImportService)->parseAndValidate($path, 'xlsx', ImportService::MODE_BINAAN);
        $durasi = microtime(true) - $mulai;

        $this->assertSame([], $preview['errors']);
        $this->assertCount(300, $preview['valid']);
        $this->assertSame(300, $preview['ringkasan']['valid']);
        $this->assertSame(300, $preview['ringkasan']['total']);
        $this->assertLessThan(25.0, $durasi, 'File 300 baris harus selesai diproses dalam waktu wajar.');
    }
}
