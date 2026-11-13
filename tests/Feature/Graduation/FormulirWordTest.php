<?php

namespace Tests\Feature\Graduation;

use App\Enums\UserStatus;
use App\Models\MasterCity;
use App\Models\MasterEducationDegree;
use App\Models\MasterReligion;
use App\Models\MasterTraining;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use App\Services\ImportService;
use App\Support\Import\SpesifikasiKolomImport;
use App\Support\Import\TemplateFormulirWord;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\Support\Import\ImportTestCase;

/**
 * Formulir Word per anak (A4 portrait, satu blok per halaman).
 *
 * Yang dikunci oleh tes ini:
 * - struktur formulir: tabel `Label | Isian`, blok CONTOH + 5 blok kosong,
 *   header/footer, petunjuk halaman 1, font isian Arial 10;
 * - parser mengenali DUA format (formulir baru & tabel horizontal lama);
 * - blok kosong diabaikan, blok CONTOH dan label wajib kosong ditolak dengan
 *   nomor blok yang benar;
 * - mode Klien Umum tidak berubah.
 */
class FormulirWordTest extends ImportTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['pendekar.instansi_nama' => 'Pemerintah Provinsi Jawa Barat']);

        MasterReligion::create(['title' => 'Islam']);
        MasterCity::create(['title' => 'Kota Bandung']);
        MasterEducationDegree::create(['title' => 'SMA/SMK']);
        MasterTraining::create(['title' => 'Menjahit']);
    }

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/import-templates/template-formulir-remaja-binaan-*.docx')) ?: [] as $berkas) {
            @unlink($berkas);
        }

        parent::tearDown();
    }

    /**
     * Semua tabel dua kolom (satu tabel = satu blok) pada dokumen.
     *
     * @return array<int, Table>
     */
    private function tabelFormulir(PhpWord $phpWord): array
    {
        $tabel = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (! $element instanceof Table) {
                    continue;
                }

                if (count($element->getRows()[0]->getCells()) === 2) {
                    $tabel[] = $element;
                }
            }
        }

        return $tabel;
    }

    /**
     * Teks satu sel Word (Text/TextRun).
     */
    private function teksSel(Cell $sel): string
    {
        $teks = '';

        foreach ($sel->getElements() as $element) {
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

        return trim($teks);
    }

    /**
     * Kosongkan seluruh kolom Isian pada satu blok.
     */
    private function kosongkanBlok(Table $tabel): void
    {
        foreach ($tabel->getRows() as $baris) {
            $sel = $baris->getCells()[1] ?? null;

            if ($sel === null) {
                continue;
            }

            foreach ($sel->getElements() as $element) {
                $sel->removeElement($element);
            }
        }
    }

    /**
     * Isi kolom Isian pada baris dengan label tertentu.
     *
     * @param  array<string, string>  $nilai  Kunci = label tanpa `*` (huruf kecil).
     */
    private function isiBlok(Table $tabel, array $nilai): void
    {
        foreach ($tabel->getRows() as $baris) {
            $sel = $baris->getCells();

            if (count($sel) !== 2) {
                continue;
            }

            $label = strtolower(trim(str_replace('*', '', $this->teksSel($sel[0]))));

            if (! array_key_exists($label, $nilai)) {
                continue;
            }

            foreach ($sel[1]->getElements() as $element) {
                $sel[1]->removeElement($element);
            }

            $sel[1]->addText((string) $nilai[$label], ['name' => 'Arial', 'size' => 10]);
        }
    }

    /**
     * Teks isian pada baris dengan label tertentu.
     */
    private function isiUntuk(Table $tabel, string $label): string
    {
        $label = strtolower($label);

        foreach ($tabel->getRows() as $baris) {
            $sel = $baris->getCells();

            if (count($sel) !== 2) {
                continue;
            }

            $teksLabel = strtolower(trim(str_replace('*', '', $this->teksSel($sel[0]))));

            if ($teksLabel === $label) {
                return $this->teksSel($sel[1]);
            }
        }

        return '';
    }

    /**
     * Data dua remaja binaan (fiktif) untuk round-trip.
     *
     * @return array<int, array<string, string>>
     */
    private function duaBinaan(): array
    {
        return [
            [
                'nama' => 'Rizky Formulir',
                'jenis kelamin' => 'L',
                'asal daerah' => 'Kabupaten Bandung',
                'agama' => 'Islam',
                'umur' => '17',
                'angkatan' => 'Angkatan 2026',
                'email' => 'rizky.formulir@example.test',
            ],
            [
                'nama' => 'Nadia Formulir',
                'jenis kelamin' => 'P',
                'asal daerah' => 'Sumedang',
                'agama' => 'Islam',
                'umur' => '16',
                'angkatan' => 'Angkatan 2026',
                'email' => 'nadia.formulir@example.test',
            ],
        ];
    }

    /**
     * Buat formulir lalu kosongkan blok CONTOH (blok 1), sehingga file siap isi.
     *
     * @return array{0: string, 1: PhpWord, 2: array<int, Table>}
     */
    private function formulirSiapIsi(): array
    {
        $path = $this->layananTemplate()->buatFormulirWord();
        $phpWord = WordIOFactory::load($path);
        $tabel = $this->tabelFormulir($phpWord);

        $this->kosongkanBlok($tabel[0]);

        return [$path, $phpWord, $tabel];
    }

    /**
     * Simpan salinan dokumen ke folder uji.
     */
    private function simpanSalinan(PhpWord $phpWord, string $nama): string
    {
        $tujuan = $this->folderUji().DIRECTORY_SEPARATOR.$nama;
        WordIOFactory::createWriter($phpWord, 'Word2007')->save($tujuan);

        return $tujuan;
    }

    public function test_formulir_word_punya_dua_kolom_lima_blok_kosong_dan_blok_contoh(): void
    {
        $path = $this->layananTemplate()->buatFormulirWord();

        $this->assertSame(
            'template-formulir-remaja-binaan-'.now()->format('Ymd').'.docx',
            basename($path),
            'Nama unduhan harus mengikuti pola template-formulir-remaja-binaan-YYYYMMDD.docx.'
        );

        $phpWord = WordIOFactory::load($path);
        $this->assertSame(SpesifikasiKolomImport::judulFormulir(), $phpWord->getDocInfo()->getTitle());
        $this->assertSame('Pendekar 1721', $phpWord->getDocInfo()->getCreator());

        $tabel = $this->tabelFormulir($phpWord);

        $this->assertCount(
            TemplateFormulirWord::JUMLAH_BLOK_KOSONG + 1,
            $tabel,
            'Formulir harus memuat 1 blok CONTOH + 5 blok kosong.'
        );

        foreach ($tabel as $blok) {
            foreach ($blok->getRows() as $baris) {
                $this->assertCount(2, $baris->getCells(), 'Setiap baris blok harus dua kolom (Label | Isian).');
            }
        }

        // Header tabel dan penanda CONTOH.
        $this->assertSame(['Label', 'Isian'], array_map(
            fn (Cell $sel): string => $this->teksSel($sel),
            $tabel[0]->getRows()[0]->getCells()
        ));
        $this->assertStringContainsString(SpesifikasiKolomImport::PENANDA_CONTOH, $this->isiUntuk($tabel[0], 'Nama'));

        // Lima blok berikutnya benar-benar kosong.
        foreach (array_slice($tabel, 1) as $index => $blok) {
            $this->assertSame('', $this->isiUntuk($blok, 'Nama'), 'Blok kosong ke-'.($index + 1).' harus kosong.');
        }

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $document = (string) $zip->getFromName('word/document.xml');
        $headerXml = (string) $zip->getFromName('word/header1.xml');
        $footerXml = (string) $zip->getFromName('word/footer1.xml');
        $zip->close();

        // A4 portrait (PhpWord menulis ukuran dalam twips desimal).
        $this->assertMatchesRegularExpression('/<w:pgSz[^>]*w:orient="portrait"[^>]*w:w="11905/', $document);
        $this->assertStringNotContainsString('w:orient="landscape"', $document);

        // Petunjuk halaman 1 + header instansi + footer nomor halaman.
        $this->assertStringContainsString('PETUNJUK PENGISIAN', $document);
        $this->assertStringContainsString('BLOK 1 - CONTOH', $document);
        $this->assertStringContainsString('Pemerintah Provinsi Jawa Barat', $headerXml);
        $this->assertStringContainsString('PAGE', $footerXml);
        $this->assertStringContainsString('NUMPAGES', $footerXml);

        // Font isian seragam: Arial 10pt (w:sz 20 = setengah poin).
        $this->assertStringContainsString('w:ascii="Arial"', $document);
        $this->assertStringContainsString('w:sz w:val="20"', $document);
    }

    public function test_round_trip_formulir_dua_blok_menghasilkan_dua_anak_dan_dua_akun(): void
    {
        [$path, $phpWord, $tabel] = $this->formulirSiapIsi();

        $this->isiBlok($tabel[1], $this->duaBinaan()[0]);
        $this->isiBlok($tabel[2], $this->duaBinaan()[1]);

        $tujuan = $this->simpanSalinan($phpWord, 'formulir-dua-blok.docx');

        $service = new ImportService;
        $preview = $service->parseAndValidate($tujuan, 'docx', ImportService::MODE_BINAAN);

        $this->assertSame([], $preview['errors']);
        $this->assertCount(2, $preview['valid']);
        $this->assertSame('Rizky Formulir', $preview['valid'][0]['name']);
        $this->assertSame('Nadia Formulir', $preview['valid'][1]['name']);
        $this->assertSame(2, $preview['valid'][0]['_blok'], 'Blok 1 kosong diabaikan; blok 2 = baris pertama.');
        $this->assertSame(3, $preview['valid'][1]['_blok']);

        $ringkasan = $service->importData($preview['valid'], [], null, null, ImportService::MODE_BINAAN);

        $this->assertSame(2, $ringkasan['anak_baru']);
        $this->assertSame(2, $ringkasan['akun_dibuat']);
        $this->assertSame(0, $ringkasan['gagal']);
        $this->assertSame(
            2,
            Anak::query()->whereIn('nama', ['Rizky Formulir', 'Nadia Formulir'])->count()
        );
        $this->assertSame(2, User::query()->whereNotNull('anak_id')->count());
    }

    public function test_blok_contoh_formulir_ditolak_dan_blok_kosong_diabaikan(): void
    {
        $path = $this->layananTemplate()->buatFormulirWord();

        $preview = (new ImportService)->parseAndValidate($path, 'docx', ImportService::MODE_BINAAN);

        $this->assertSame([], $preview['valid']);
        $this->assertCount(1, $preview['errors'], 'Hanya blok CONTOH yang dilaporkan; 5 blok kosong diabaikan.');
        $this->assertSame(1, $preview['errors'][0]['blok'], 'Pesan galat harus menunjuk Blok 1.');
        $this->assertStringContainsString(SpesifikasiKolomImport::PENANDA_CONTOH, $preview['errors'][0]['msg']);
    }

    public function test_blok_dengan_label_wajib_kosong_ditolak_dengan_nomor_blok(): void
    {
        [$path, $phpWord, $tabel] = $this->formulirSiapIsi();

        // Blok 3 diisi sebagian: Nama dikosongkan, kolom lain terisi.
        $this->isiBlok($tabel[2], [
            'jenis kelamin' => 'P',
            'asal daerah' => 'Sumedang',
            'agama' => 'Islam',
            'umur' => '16',
            'angkatan' => 'Angkatan 2026',
        ]);

        $tujuan = $this->simpanSalinan($phpWord, 'formulir-label-wajib-kosong.docx');

        $preview = (new ImportService)->parseAndValidate($tujuan, 'docx', ImportService::MODE_BINAAN);

        $this->assertSame([], $preview['valid']);
        $this->assertNotEmpty($preview['errors']);
        $this->assertSame(3, $preview['errors'][0]['blok']);
        $this->assertSame('Nama', $preview['errors'][0]['col']);
        $this->assertSame('Nama wajib diisi', $preview['errors'][0]['msg']);
    }

    public function test_template_word_lama_dua_kolom_tetap_terbaca_sebagai_tabel_horizontal(): void
    {
        $phpWord = new PhpWord;
        $tabel = $phpWord->addSection()->addTable(['borderSize' => 6, 'borderColor' => '000000']);

        $tabel->addRow();
        foreach (['Nama*', 'Email*'] as $teks) {
            $tabel->addCell(3000)->addText($teks, ['bold' => true]);
        }

        $tabel->addRow();
        foreach (['Siti Lama', 'siti.lama@example.test'] as $teks) {
            $tabel->addCell(3000)->addText($teks);
        }

        $path = $this->simpanSalinan($phpWord, 'template-lama-dua-kolom.docx');

        $preview = (new ImportService)->parseAndValidate($path, 'docx', ImportService::MODE_KLIEN);

        $this->assertSame([], $preview['errors'], 'Tabel horizontal lama tidak boleh salah dikenali sebagai formulir.');
        $this->assertCount(1, $preview['valid']);
        $this->assertSame('Siti Lama', $preview['valid'][0]['name']);
    }

    public function test_mode_klien_umum_tidak_berubah(): void
    {
        [$path, $phpWord, $tabel] = $this->formulirSiapIsi();

        $baris = $this->duaBinaan();
        $tanpaEmail = $baris[0];
        unset($tanpaEmail['email']);

        $this->isiBlok($tabel[1], $tanpaEmail);
        $this->isiBlok($tabel[2], $baris[1]);

        $service = new ImportService;
        $berkas = $this->simpanSalinan($phpWord, 'formulir-mode-klien.docx');
        $preview = $service->parseAndValidate($berkas, 'docx', ImportService::MODE_KLIEN);

        // Aturan mode Klien Umum tetap: email wajib, tanpa syarat data anak.
        $this->assertNotEmpty($preview['errors']);
        $this->assertSame('Email', $preview['errors'][0]['col']);
        $this->assertSame('Email wajib diisi', $preview['errors'][0]['msg']);
        $this->assertSame(2, $preview['errors'][0]['blok'], 'Galat email menunjuk Blok 2.');
        $this->assertCount(1, $preview['valid'], 'Baris tanpa email tidak valid; baris lain tetap diproses.');

        // Setelah email diisi, import mode klien membuat akun Published tanpa anak.
        $this->isiBlok($tabel[1], $tanpaEmail + ['email' => 'rizky.klien@example.test']);
        $lengkap = $this->simpanSalinan($phpWord, 'formulir-mode-klien-lengkap.docx');
        $preview = $service->parseAndValidate($lengkap, 'docx', ImportService::MODE_KLIEN);

        $this->assertSame([], $preview['errors']);
        $this->assertCount(2, $preview['valid']);

        $jumlahAnakSebelum = Anak::query()->count();

        $ringkasan = $service->importData($preview['valid'], [], null, 'Rahasia#2026', ImportService::MODE_KLIEN);

        $this->assertSame(2, $ringkasan['akun_dibuat']);
        $this->assertSame(0, $ringkasan['anak_baru']);
        $this->assertSame($jumlahAnakSebelum, Anak::query()->count(), 'Mode Klien Umum tidak boleh membuat data anak.');

        $user = User::query()->where('email', 'rizky.klien@example.test')->firstOrFail();
        $this->assertSame(UserStatus::Published, $user->status);
        $this->assertSame(User::ROLE_CLIENT, $user->user_type);
    }
}
