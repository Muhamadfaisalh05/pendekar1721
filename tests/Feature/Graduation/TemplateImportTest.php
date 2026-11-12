<?php

namespace Tests\Feature\Graduation;

use App\Models\MasterCity;
use App\Models\MasterEducationDegree;
use App\Models\MasterReligion;
use App\Models\MasterTraining;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use App\Services\ImportService;
use App\Support\Import\SpesifikasiKolomImport;
use App\Support\Import\TemplateExcel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory as ExcelIOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\Support\Import\ImportTestCase;

/**
 * Template import resmi (Excel & Word) untuk mode Remaja Binaan.
 *
 * Yang dikunci oleh tes ini:
 * - struktur template (PETUNJUK/DATA/DAFTAR, header, dropdown, freeze panes);
 * - template dapat diisi lalu diimpor (round-trip) menjadi anak + akun Draft;
 * - baris CONTOH yang lupa dihapus ditolak dengan pesan jelas;
 * - template LAMA (Excel & Word) tetap bisa diimpor (kompatibilitas).
 */
class TemplateImportTest extends ImportTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['pendekar.instansi_nama' => 'Pemerintah Provinsi Jawa Barat']);

        // Data master minimal supaya sheet DAFTAR berisi pilihan nyata.
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
     * Cari validasi data yang dipasang pada rentang tertentu.
     */
    private function validasiPada(Worksheet $sheet, string $rentang): ?DataValidation
    {
        return $sheet->getDataValidationCollection()[$rentang] ?? null;
    }

    /**
     * Nomor kolom Excel (1-based) untuk satu kunci kolom template.
     */
    private function kolomKe(string $kunci): int
    {
        $index = array_search($kunci, array_column(SpesifikasiKolomImport::kolom(), 'kunci'), true);

        return ($index === false ? 0 : $index) + 1;
    }

    /**
     * Tulis satu baris data ke sheet DATA pada nomor baris tertentu.
     *
     * @param  array<string, mixed>  $nilai
     */
    private function isiBarisExcel(Worksheet $sheet, int $baris, array $nilai): void
    {
        foreach ($nilai as $kunci => $isi) {
            $sheet->setCellValueExplicit(
                Coordinate::stringFromColumnIndex($this->kolomKe($kunci)).$baris,
                (string) $isi,
                DataType::TYPE_STRING
            );
        }
    }

    /**
     * Cari tabel DATA (tabel dengan header "Nama*") pada dokumen Word.
     */
    private function tabelDataWord(PhpWord $phpWord): ?Table
    {
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (! $element instanceof Table) {
                    continue;
                }

                $header = array_map(
                    fn ($cell): string => trim($this->teksSelWord($cell)),
                    $element->getRows()[0]->getCells()
                );

                if (in_array('Nama*', $header, true)) {
                    return $element;
                }
            }
        }

        return null;
    }

    /**
     * Teks satu sel Word (TextRun/Text).
     */
    private function teksSelWord($cell): string
    {
        $teks = '';

        foreach ($cell->getElements() as $element) {
            if ($element instanceof Text) {
                $teks .= $element->getText();

                continue;
            }

            if ($element instanceof TextRun) {
                foreach ($element->getElements() as $anak) {
                    if ($anak instanceof Text) {
                        $teks .= $anak->getText();
                    }
                }
            }
        }

        return $teks;
    }

    private function kosongkanSelWord(Cell $cell): void
    {
        foreach ($cell->getElements() as $element) {
            $cell->removeElement($element);
        }
    }

    public function test_template_excel_baru_punya_sheet_header_dropdown_dan_freeze_panes(): void
    {
        $path = $this->layananTemplate()->buatExcel();

        $spreadsheet = ExcelIOFactory::load($path);

        $this->assertSame(['PETUNJUK', 'DATA', 'DAFTAR'], $spreadsheet->getSheetNames());
        $this->assertSame(
            'template-import-remaja-binaan-'.now()->format('Ymd').'.xlsx',
            basename($path),
            'Nama unduhan harus mengikuti pola template-import-remaja-binaan-YYYYMMDD.xlsx.'
        );
        $this->assertSame('hidden', $spreadsheet->getSheetByName('DAFTAR')->getSheetState());
        $this->assertTrue($spreadsheet->getSheetByName('PETUNJUK')->getProtection()->getSheet());
        $this->assertStringContainsString(
            'Pemerintah Provinsi Jawa Barat',
            (string) $spreadsheet->getSheetByName('PETUNJUK')->getCell('A3')->getValue()
        );
        $teksPetunjuk = implode(' ', array_map(
            fn (array $baris): string => (string) ($baris[0] ?? ''),
            $spreadsheet->getSheetByName('PETUNJUK')->rangeToArray('A1:A60')
        ));
        $this->assertStringContainsString('data pribadi anak', $teksPetunjuk);

        $data = $spreadsheet->getSheetByName('DATA');
        $hurufAkhir = Coordinate::stringFromColumnIndex(count(SpesifikasiKolomImport::header()));
        $header = $data->rangeToArray('A'.TemplateExcel::BARIS_HEADER.':'.$hurufAkhir.TemplateExcel::BARIS_HEADER)[0];

        // Header template persis sama dengan header yang dikenali parser.
        $this->assertSame(SpesifikasiKolomImport::header(), $header);

        // Freeze panes di header dan kolom Nama, plus autofilter.
        $this->assertSame('C4', $data->getFreezePane());
        $this->assertNotNull($data->getAutoFilter());

        $awal = TemplateExcel::BARIS_AWAL_DATA;
        $akhir = $awal + TemplateExcel::JUMLAH_BARIS_KOSONG - 1;

        $kolomJk = Coordinate::stringFromColumnIndex($this->kolomKe('jenis_kelamin'));
        $validasiJk = $this->validasiPada($data, $kolomJk.$awal.':'.$kolomJk.$akhir);

        $this->assertNotNull($validasiJk, 'Dropdown jenis kelamin wajib ada.');
        $this->assertSame(DataValidation::TYPE_LIST, $validasiJk->getType());
        $this->assertStringStartsWith('DAFTAR!', (string) $validasiJk->getFormula1());
        $this->assertStringContainsString('L (laki-laki)', (string) $validasiJk->getPrompt());

        $kolomUmur = Coordinate::stringFromColumnIndex($this->kolomKe('umur'));
        $validasiUmur = $this->validasiPada($data, $kolomUmur.$awal.':'.$kolomUmur.$akhir);

        $this->assertNotNull($validasiUmur);
        $this->assertSame(DataValidation::TYPE_WHOLE, $validasiUmur->getType());
        $this->assertSame('5', $validasiUmur->getFormula1());
        $this->assertSame('30', $validasiUmur->getFormula2());

        $kolomTanggal = Coordinate::stringFromColumnIndex($this->kolomKe('tanggal_lahir'));
        $validasiTanggal = $this->validasiPada($data, $kolomTanggal.$awal.':'.$kolomTanggal.$akhir);

        $this->assertNotNull($validasiTanggal);
        $this->assertSame(DataValidation::TYPE_DATE, $validasiTanggal->getType());

        // Baris CONTOH berlabel jelas + 200 baris siap isi dengan nomor otomatis.
        $this->assertSame(
            SpesifikasiKolomImport::PENANDA_CONTOH,
            (string) $data->getCell('B'.TemplateExcel::BARIS_CONTOH)->getValue()
        );
        $this->assertSame('=ROW()-4', $data->getCell('A'.$awal)->getValue());
        $this->assertSame('=ROW()-4', $data->getCell('A'.$akhir)->getValue());
    }

    public function test_baris_contoh_excel_yang_lupa_dihapus_ditolak(): void
    {
        $path = $this->layananTemplate()->buatExcel();

        $preview = (new ImportService)->parseAndValidate($path, 'xlsx', ImportService::MODE_BINAAN);

        $this->assertSame([], $preview['valid']);
        $this->assertStringContainsString(
            SpesifikasiKolomImport::PENANDA_CONTOH,
            implode(' | ', array_column($preview['errors'], 'msg'))
        );
        $this->assertContains('Baris Contoh', array_column($preview['errors'], 'col'));
    }

    public function test_template_excel_lama_tetap_bisa_diimpor(): void
    {
        $path = $this->tulisXlsx(
            ['Nama*', 'Email*', 'Jenis Kelamin*', 'Tempat Lahir', 'Tanggal Lahir', 'Asal Daerah', 'Angkatan'],
            [['Siti Aminah', 'siti.lama@example.test', 'P', 'Cimahi', '1998-12-01', 'Bandung', 'Angkatan III']],
            'template-lama.xlsx'
        );

        $preview = (new ImportService)->parseAndValidate($path, 'xlsx', ImportService::MODE_KLIEN);

        $this->assertSame([], $preview['errors']);
        $this->assertCount(1, $preview['valid']);

        (new ImportService)->importData($preview['valid'], [], null, 'Rahasia#2026', ImportService::MODE_KLIEN);

        $this->assertTrue(User::query()->where('email', 'siti.lama@example.test')->exists());
    }

    public function test_round_trip_template_excel_menghasilkan_dua_anak_dan_dua_akun(): void
    {
        $path = $this->layananTemplate()->buatExcel();

        $spreadsheet = ExcelIOFactory::load($path);
        $data = $spreadsheet->getSheetByName('DATA');

        // Operator menghapus baris CONTOH (semua baris di bawahnya bergeser).
        $data->removeRow(TemplateExcel::BARIS_CONTOH);
        $barisAwal = TemplateExcel::BARIS_AWAL_DATA - 1;

        $this->isiBarisExcel($data, $barisAwal, [
            'nama' => 'Rizky Pratama',
            'jenis_kelamin' => 'L',
            'asal_daerah' => 'Kabupaten Bandung',
            'agama' => 'Islam',
            'umur' => 17,
            'angkatan' => 'Angkatan 2026',
            'email' => 'rizky.template@example.test',
            'kota_ktp' => 'Kota Bandung',
            'pendidikan' => 'SMA/SMK',
            'pelatihan' => 'Menjahit',
        ]);

        $this->isiBarisExcel($data, $barisAwal + 1, [
            'nama' => 'Nadia Putri',
            'jenis_kelamin' => 'P',
            'asal_daerah' => 'Sumedang',
            'agama' => 'Islam',
            'umur' => 16,
            'angkatan' => 'Angkatan 2026',
            'email' => 'nadia.template@example.test',
        ]);

        $tujuan = $this->folderUji().DIRECTORY_SEPARATOR.'round-trip.xlsx';
        (new Xlsx($spreadsheet))->save($tujuan);

        $service = new ImportService;
        $preview = $service->parseAndValidate($tujuan, 'xlsx', ImportService::MODE_BINAAN);

        $this->assertSame([], $preview['errors']);
        $this->assertCount(2, $preview['valid']);
        $this->assertSame(17, $preview['valid'][0]['umur']);
        $this->assertSame('L', $preview['valid'][0]['gender']);
        $this->assertSame('P', $preview['valid'][1]['gender']);
        $this->assertNotNull($preview['valid'][0]['ktp_city_id'], 'Kota KTP harus dikenali dari master wilayah.');
        $this->assertSame([], $preview['tautan']['akan'], 'Tanpa centang, tidak ada penautan.');

        $ringkasan = $service->importData($preview['valid'], [], null, null, ImportService::MODE_BINAAN);

        $this->assertSame(2, $ringkasan['anak_baru']);
        $this->assertSame(2, $ringkasan['akun_dibuat']);
        $this->assertSame(0, $ringkasan['gagal']);
        $this->assertSame(
            2,
            Anak::query()->whereIn('nama', ['Rizky Pratama', 'Nadia Putri'])->count()
        );
        $this->assertSame(2, User::query()->whereNotNull('anak_id')->count());
    }

    public function test_template_word_baru_punya_header_footer_dan_header_tabel_terbaca(): void
    {
        $path = $this->layananTemplate()->buatWord();

        $phpWord = WordIOFactory::load($path);

        $this->assertSame('Pendekar 1721', $phpWord->getDocInfo()->getCreator());
        $this->assertSame(SpesifikasiKolomImport::judulTemplate(), $phpWord->getDocInfo()->getTitle());
        $this->assertSame(
            'template-import-remaja-binaan-'.now()->format('Ymd').'.docx',
            basename($path),
            'Nama unduhan harus mengikuti pola template-import-remaja-binaan-YYYYMMDD.docx.'
        );

        $tabel = $this->tabelDataWord($phpWord);
        $this->assertNotNull($tabel, 'Tabel DATA harus ada dan header-nya terbaca parser.');

        $header = array_map(fn ($cell): string => trim($this->teksSelWord($cell)), $tabel->getRows()[0]->getCells());
        $this->assertSame(SpesifikasiKolomImport::header(), $header);

        // Header + baris CONTOH + minimal 15 baris kosong siap isi.
        $this->assertGreaterThanOrEqual(17, count($tabel->getRows()));

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $document = (string) $zip->getFromName('word/document.xml');
        $headerXml = (string) $zip->getFromName('word/header1.xml');
        $footerXml = (string) $zip->getFromName('word/footer1.xml');
        $zip->close();

        // Header tabel diulang di setiap halaman, tanpa sel gabungan.
        $this->assertStringContainsString('tblHeader', $document);
        $this->assertStringNotContainsString('gridSpan', $document, 'Tabel data tidak boleh memakai sel gabungan.');

        // Header dokumen: nama instansi. Footer: "Halaman X dari Y".
        $this->assertStringContainsString('Pemerintah Provinsi Jawa Barat', $headerXml);
        $this->assertStringContainsString('Halaman', $footerXml);
        $this->assertStringContainsString('PAGE', $footerXml);
        $this->assertStringContainsString('NUMPAGES', $footerXml);
    }

    public function test_round_trip_template_word_menghasilkan_dua_anak_dan_dua_akun(): void
    {
        $path = $this->layananTemplate()->buatWord();

        $phpWord = WordIOFactory::load($path);
        $tabel = $this->tabelDataWord($phpWord);

        $this->assertNotNull($tabel);

        // Operator menghapus isi baris CONTOH (baris ke-2 tabel).
        foreach ($tabel->getRows()[1]->getCells() as $cell) {
            $this->kosongkanSelWord($cell);
        }

        $barisData = [
            [
                'nama' => 'Rizky Pratama',
                'jenis_kelamin' => 'L',
                'asal_daerah' => 'Kabupaten Bandung',
                'agama' => 'Islam',
                'umur' => 17,
                'angkatan' => 'Angkatan 2026',
                'email' => 'rizky.word@example.test',
            ],
            [
                'nama' => 'Nadia Putri',
                'jenis_kelamin' => 'P',
                'asal_daerah' => 'Sumedang',
                'agama' => 'Islam',
                'umur' => 16,
                'angkatan' => 'Angkatan 2026',
                'email' => 'nadia.word@example.test',
            ],
        ];

        foreach ($barisData as $offset => $nilai) {
            $row = $tabel->getRows()[2 + $offset];

            foreach ($nilai as $kunci => $isi) {
                $row->getCells()[$this->kolomKe($kunci) - 1]->addText((string) $isi);
            }
        }

        $tujuan = $this->folderUji().DIRECTORY_SEPARATOR.'round-trip.docx';
        WordIOFactory::createWriter($phpWord, 'Word2007')->save($tujuan);

        $service = new ImportService;
        $preview = $service->parseAndValidate($tujuan, 'docx', ImportService::MODE_BINAAN);

        $this->assertSame([], $preview['errors']);
        $this->assertCount(2, $preview['valid']);
        $this->assertSame('Rizky Pratama', $preview['valid'][0]['name']);
        $this->assertSame('Nadia Putri', $preview['valid'][1]['name']);

        $ringkasan = $service->importData($preview['valid'], [], null, null, ImportService::MODE_BINAAN);

        $this->assertSame(2, $ringkasan['anak_baru']);
        $this->assertSame(2, $ringkasan['akun_dibuat']);
        $this->assertSame(2, User::query()->whereNotNull('anak_id')->count());
    }

    public function test_baris_contoh_word_yang_lupa_dihapus_ditolak(): void
    {
        $path = $this->layananTemplate()->buatWord();

        $preview = (new ImportService)->parseAndValidate($path, 'docx', ImportService::MODE_BINAAN);

        $this->assertSame([], $preview['valid']);
        $this->assertStringContainsString(
            SpesifikasiKolomImport::PENANDA_CONTOH,
            implode(' | ', array_column($preview['errors'], 'msg'))
        );
    }

    public function test_template_word_lama_tetap_bisa_diimpor(): void
    {
        $phpWord = new PhpWord;
        $tabel = $phpWord->addSection()->addTable(['borderSize' => 6, 'borderColor' => '000000']);

        $header = ['Nama*', 'Email*', 'Jenis Kelamin*', 'Asal Daerah', 'Angkatan'];
        $tabel->addRow();
        foreach ($header as $teks) {
            $tabel->addCell(1800)->addText($teks, ['bold' => true]);
        }

        $tabel->addRow();
        foreach (['Siti Aminah', 'siti.word.lama@example.test', 'P', 'Bandung', 'Angkatan III'] as $teks) {
            $tabel->addCell(1800)->addText($teks);
        }

        $path = $this->folderUji().DIRECTORY_SEPARATOR.'template-lama.docx';
        WordIOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $preview = (new ImportService)->parseAndValidate($path, 'docx', ImportService::MODE_KLIEN);

        $this->assertSame([], $preview['errors']);
        $this->assertCount(1, $preview['valid']);

        (new ImportService)->importData($preview['valid'], [], null, 'Rahasia#2026', ImportService::MODE_KLIEN);

        $this->assertTrue(User::query()->where('email', 'siti.word.lama@example.test')->exists());
    }
}
