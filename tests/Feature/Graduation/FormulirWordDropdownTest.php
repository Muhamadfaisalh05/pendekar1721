<?php

namespace Tests\Feature\Graduation;

use App\Models\MasterCity;
use App\Models\MasterEducationDegree;
use App\Models\MasterReligion;
use App\Models\MasterTraining;
use App\Services\ImportService;
use App\Support\Import\SpesifikasiKolomImport;
use App\Support\Import\TemplateFormulirWord;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\Import\ImportTestCase;
use ZipArchive;

/**
 * Dropdown CONTENT CONTROL (`w:sdt`) pada formulir Word per anak.
 *
 * Yang dikunci:
 * - setiap blok memuat 7 dropdown + 1 pemilih tanggal (valid, well-formed),
 * - isi dropdown berasal dari master/enum (bukan daftar cadangan),
 * - master kosong -> placeholder + catatan, bukan nilai karangan,
 * - file yang diisi lewat XML (simulasi pilihan dropdown) terbaca parser,
 * - placeholder "Pilih..." tidak pernah dianggap data,
 * - tombol "Download Template Word" sudah dihapus dari halaman Import Data.
 */
class FormulirWordDropdownTest extends ImportTestCase
{
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    protected function setUp(): void
    {
        parent::setUp();

        config(['pendekar.instansi_nama' => 'Pemerintah Provinsi Jawa Barat']);

        MasterReligion::create(['title' => 'Islam']);
        MasterReligion::create(['title' => 'Kristen']);
        MasterCity::create(['title' => 'Kota Bandung']);
        MasterCity::create(['title' => 'Kabupaten Bandung']);
        MasterEducationDegree::create(['title' => 'SMA/SMK']);
        MasterEducationDegree::create(['title' => 'SMP']);
        MasterTraining::create(['title' => 'Menjahit']);
        MasterTraining::create(['title' => 'Tata Boga']);
    }

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/import-templates/template-formulir-remaja-binaan-*.docx')) ?: [] as $berkas) {
            @unlink($berkas);
        }

        parent::tearDown();
    }

    /**
     * Baca satu entri XML dari .docx.
     */
    private function entriZip(string $path, string $nama): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Berkas .docx harus dapat dibuka.');
        $xml = $zip->getFromName($nama);
        $zip->close();

        $this->assertIsString($xml, "Entri {$nama} harus ada pada .docx.");

        return $xml;
    }

    /**
     * Muat document.xml menjadi DOM (sekaligus membuktikan well-formed).
     */
    private function dokumen(string $path): DOMDocument
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;

        $this->assertTrue(
            @$dom->loadXML($this->entriZip($path, 'word/document.xml')) === true,
            'word/document.xml harus well-formed.'
        );

        return $dom;
    }

    private function xpath(DOMDocument|DOMElement $simpul): DOMXPath
    {
        $xpath = new DOMXPath($simpul instanceof DOMDocument ? $simpul : $simpul->ownerDocument);
        $xpath->registerNamespace('w', self::NS_W);

        return $xpath;
    }

    /**
     * Gabungan seluruh teks `w:t` di dalam satu simpul.
     */
    private function teks(DOMNode $simpul): string
    {
        $teks = '';

        foreach ($this->xpath($simpul)->query('.//w:t', $simpul) ?: [] as $node) {
            $teks .= $node->textContent;
        }

        return trim($teks);
    }

    /**
     * Seluruh tabel BLOK formulir (baris pertama `Label | Isian`).
     *
     * @return array<int, DOMElement>
     */
    private function tabelBlok(DOMDocument $dom): array
    {
        $xpath = $this->xpath($dom);
        $tabel = [];

        foreach ($xpath->query('//w:tbl') ?: [] as $tbl) {
            if (! $tbl instanceof DOMElement) {
                continue;
            }

            $baris = $xpath->query('./w:tr[1]', $tbl)?->item(0);
            $sel = $baris === null ? null : $xpath->query('./w:tc', $baris);

            if ($sel === null || $sel->length !== 2) {
                continue;
            }

            if ($this->teks($sel->item(0)) !== 'Label' || $this->teks($sel->item(1)) !== 'Isian') {
                continue;
            }

            $tabel[] = $tbl;
        }

        return $tabel;

    }

    /**
     * Satu content control berdasarkan tag-nya.
     */
    private function sdt(DOMElement $blok, string $tag): DOMElement
    {
        $simpul = $this->xpath($blok)
            ->query('.//w:sdt[w:sdtPr/w:tag/@w:val="'.$tag.'"]', $blok)
            ?->item(0);

        $this->assertInstanceOf(DOMElement::class, $simpul, "Kontrol {$tag} harus ada pada blok.");

        return $simpul;
    }

    /**
     * Display text seluruh `w:listItem` pada satu dropdown.
     *
     * @return array<int, string>
     */
    private function daftarItem(DOMElement $sdt): array
    {
        $hasil = [];

        foreach ($this->xpath($sdt)->query('.//w:dropDownList/w:listItem', $sdt) ?: [] as $item) {
            $hasil[] = $item->getAttributeNS(self::NS_W, 'displayText');
        }

        return $hasil;
    }

    /**
     * Value seluruh `w:listItem` pada satu dropdown.
     *
     * @return array<int, string>
     */
    private function nilaiItem(DOMElement $sdt): array
    {
        $hasil = [];

        foreach ($this->xpath($sdt)->query('.//w:dropDownList/w:listItem', $sdt) ?: [] as $item) {
            $hasil[] = $item->getAttributeNS(self::NS_W, 'value');
        }

        return $hasil;
    }

    /**
     * Isi `w:sdtContent` seperti Word saat operator memilih nilai.
     */
    private function isiSdt(DOMElement $sdt, string $nilai): void
    {
        $xpath = $this->xpath($sdt);
        $isi = $xpath->query('./w:sdtContent', $sdt)?->item(0);
        $this->assertInstanceOf(DOMElement::class, $isi);

        while ($isi->firstChild !== null) {
            $isi->removeChild($isi->firstChild);
        }

        $dom = $sdt->ownerDocument;
        $run = $dom->createElementNS(self::NS_W, 'w:r');
        $teks = $dom->createElementNS(self::NS_W, 'w:t');
        $teks->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $teks->appendChild($dom->createTextNode($nilai));
        $run->appendChild($teks);
        $isi->appendChild($run);

        $pr = $xpath->query('./w:sdtPr', $sdt)?->item(0);
        $placeholder = $pr === null ? null : $this->xpath($sdt)->query('./w:showingPlcHdr', $pr)?->item(0);

        if ($pr instanceof DOMElement && $placeholder !== null) {
            $pr->removeChild($placeholder);
        }
    }

    /**
     * Isi satu sel biasa (bukan content control) pada tabel blok.
     */
    private function isiSel(DOMElement $blok, string $label, string $nilai): void
    {
        $xpath = $this->xpath($blok);

        foreach ($xpath->query('./w:tr', $blok) ?: [] as $baris) {
            $sel = $xpath->query('./w:tc', $baris);

            if ($sel === null || $sel->length !== 2) {
                continue;
            }

            $teksLabel = strtolower(trim(str_replace('*', '', $this->teks($sel->item(0)))));

            if ($teksLabel !== $label) {
                continue;
            }

            $tc = $sel->item(1);
            $this->assertInstanceOf(DOMElement::class, $tc);

            while ($tc->firstChild !== null) {
                $tc->removeChild($tc->firstChild);
            }

            $dom = $tc->ownerDocument;
            $paragraf = $dom->createElementNS(self::NS_W, 'w:p');
            $run = $dom->createElementNS(self::NS_W, 'w:r');
            $teks = $dom->createElementNS(self::NS_W, 'w:t');
            $teks->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $teks->appendChild($dom->createTextNode($nilai));
            $run->appendChild($teks);
            $paragraf->appendChild($run);
            $tc->appendChild($paragraf);

            return;
        }

        $this->fail("Label {$label} tidak ditemukan pada blok.");
    }

    /**
     * Simpan salinan .docx dengan document.xml yang sudah diubah.
     */
    private function simpanDokumen(string $asal, DOMDocument $dom, string $nama): string
    {
        $tujuan = $this->folderUji().DIRECTORY_SEPARATOR.$nama;
        $this->assertTrue(copy($asal, $tujuan));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tujuan) === true);
        $zip->addFromString('word/document.xml', $dom->saveXML());
        $zip->close();

        return $tujuan;
    }

    public function test_setiap_blok_punya_tujuh_dropdown_dan_satu_pemilih_tanggal(): void
    {
        $path = $this->layananTemplate()->buatFormulirWord();
        $dom = $this->dokumen($path);
        $blok = $this->tabelBlok($dom);

        $this->assertCount(
            TemplateFormulirWord::JUMLAH_BLOK_KOSONG + 1,
            $blok,
            'Formulir harus memuat 1 blok CONTOH + 5 blok kosong.'
        );

        foreach ($blok as $index => $tabel) {
            $nomor = $index + 1;
            $xpath = $this->xpath($tabel);

            $this->assertSame(8, $xpath->query('.//w:sdt', $tabel)->length, "Blok {$nomor} harus punya 8 content control.");
            $this->assertSame(7, $xpath->query('.//w:dropDownList', $tabel)->length, "Blok {$nomor} harus punya 7 dropdown.");
            $this->assertSame(1, $xpath->query('.//w:date', $tabel)->length, "Blok {$nomor} harus punya 1 pemilih tanggal.");
            $this->assertSame(
                0,
                $xpath->query('.//w:sdt[not(ancestor::w:tc)]', $tabel)->length,
                'Content control harus berada di dalam sel tabel.'
            );

            foreach (TemplateFormulirWord::KOLOM_DROPDOWN as $kunci) {
                $sdt = $this->sdt($tabel, 'pendekar_b'.$nomor.'_'.$kunci);
                $pr = $this->xpath($sdt);

                $this->assertSame(1, $pr->query('./w:sdtPr/w:dropDownList', $sdt)->length, "{$kunci} harus dropdown.");
                $this->assertSame(1, $pr->query('./w:sdtPr/w:alias', $sdt)->length, "{$kunci} harus punya alias.");
                $this->assertSame(1, $pr->query('./w:sdtPr/w:tag', $sdt)->length, "{$kunci} harus punya tag.");
            }

            $tanggal = $this->sdt($tabel, 'pendekar_b'.$nomor.'_tanggal_lahir');
            $format = $this->xpath($tanggal)->query('./w:sdtPr/w:date', $tanggal)?->item(0);

            $this->assertInstanceOf(DOMElement::class, $format, 'Tanggal lahir harus memakai pemilih tanggal.');
            $this->assertSame(
                'dd/MM/yyyy',
                $format->getElementsByTagNameNS(self::NS_W, 'dateFormat')->item(0)?->getAttributeNS(self::NS_W, 'val')
            );
        }

        $settings = $this->entriZip($path, 'word/settings.xml');
        $domSettings = new DOMDocument;
        $this->assertTrue(@$domSettings->loadXML($settings) === true, 'word/settings.xml harus well-formed.');
        $this->assertMatchesRegularExpression(
            '/w:name="compatibilityMode"[^>]*w:val="15"/',
            $settings,
            'Compatibility mode 15 diperlukan agar dropdown tampil di Word.'
        );
    }

    public function test_dropdown_memakai_nilai_master_dan_enum(): void
    {
        $path = $this->layananTemplate()->buatFormulirWord();
        $blok = $this->tabelBlok($this->dokumen($path));
        $contoh = $blok[1];

        $jenisKelamin = $this->sdt($contoh, 'pendekar_b2_jenis_kelamin');
        $this->assertSame(['L - Laki-laki', 'P - Perempuan'], $this->daftarItem($jenisKelamin));
        $this->assertSame(['L', 'P'], $this->nilaiItem($jenisKelamin));

        $this->assertSame(
            MasterReligion::query()->orderBy('title')->pluck('title')->all(),
            $this->daftarItem($this->sdt($contoh, 'pendekar_b2_agama')),
            'Agama harus berasal dari master agama.'
        );

        $this->assertSame(
            MasterCity::query()->orderBy('title')->pluck('title')->all(),
            $this->daftarItem($this->sdt($contoh, 'pendekar_b2_kota_ktp')),
            'Kota KTP harus berasal dari master kota.'
        );

        $this->assertSame(
            MasterCity::query()->orderBy('title')->pluck('title')->all(),
            $this->daftarItem($this->sdt($contoh, 'pendekar_b2_penempatan_wilayah')),
            'Penempatan wilayah harus berasal dari master kota.'
        );

        $this->assertSame(
            MasterEducationDegree::query()->orderBy('title')->pluck('title')->all(),
            $this->daftarItem($this->sdt($contoh, 'pendekar_b2_pendidikan')),
            'Pendidikan harus berasal dari master pendidikan.'
        );

        $this->assertSame(
            MasterTraining::query()->orderBy('title')->pluck('title')->all(),
            $this->daftarItem($this->sdt($contoh, 'pendekar_b2_pelatihan')),
            'Pelatihan harus berasal dari master pelatihan.'
        );

        $this->assertSame(['Belum Bekerja', 'Sedang Bekerja'], $this->daftarItem($this->sdt($contoh, 'pendekar_b2_status_pekerjaan')));
    }

    public function test_petunjuk_dan_lampiran_menampilkan_nilai_yang_diterima(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            MasterCity::create(['title' => sprintf('Kota Uji %02d', $i)]);
        }

        $path = $this->layananTemplate()->buatFormulirWord();
        $xml = $this->entriZip($path, 'word/document.xml');
        $totalKota = MasterCity::query()->count();

        $this->assertGreaterThan(10, $totalKota);
        $this->assertStringContainsString('NILAI YANG DITERIMA', $xml);
        $this->assertStringContainsString('LAMPIRAN DAFTAR PILIHAN', $xml);
        $this->assertStringContainsString('dan '.($totalKota - 10).' nilai lainnya', $xml);
        $this->assertStringContainsString('Kota Uji 12', $xml, 'Lampiran memuat daftar lengkap.');
    }

    public function test_pilihan_dropdown_dan_placeholder_terbaca_parser(): void
    {
        $path = $this->layananTemplate()->buatFormulirWord();
        $dom = $this->dokumen($path);
        $blok = $this->tabelBlok($dom);
        $target = $blok[1];

        // Operator menghapus seluruh blok CONTOH sebelum mengisi formulir.
        $blok[0]->parentNode?->removeChild($blok[0]);

        $this->isiSel($target, 'nama', 'Budi Dropdown');
        $this->isiSel($target, 'asal daerah', 'Kabupaten Bandung');
        $this->isiSel($target, 'umur', '17');
        $this->isiSel($target, 'angkatan', 'Angkatan 2026');
        $this->isiSel($target, 'email', 'budi.dropdown@example.test');

        foreach ([
            'jenis_kelamin' => 'L - Laki-laki',
            'agama' => 'Islam',
            'pendidikan' => 'SMA/SMK',
            'pelatihan' => 'Menjahit, Tata Boga',
            'penempatan_wilayah' => 'Kota Bandung',
            'kota_ktp' => 'Kabupaten Bandung',
            'status_pekerjaan' => 'Sedang Bekerja',
            'tanggal_lahir' => '17/08/2008',
        ] as $kunci => $nilai) {
            $this->isiSdt($this->sdt($target, 'pendekar_b2_'.$kunci), $nilai);
        }

        $berkas = $this->simpanDokumen($path, $dom, 'formulir-dropdown.docx');
        $preview = (new ImportService)->parseAndValidate($berkas, 'docx', ImportService::MODE_BINAAN);

        $this->assertSame([], $preview['errors']);
        $this->assertCount(1, $preview['valid']);

        $baris = $preview['valid'][0];
        $this->assertSame('Budi Dropdown', $baris['name']);
        $this->assertSame('L', $baris['gender'], 'Nilai "L - Laki-laki" harus dinormalisasi menjadi L.');
        $this->assertSame('Islam', $baris['agama']);
        $this->assertSame('2008-08-17', $baris['birth_date']);
        $this->assertSame(17, $baris['umur']);
        $this->assertEquals(MasterCity::query()->where('title', 'Kabupaten Bandung')->value('id'), $baris['ktp_city_id']);
        $this->assertEquals(MasterEducationDegree::query()->where('title', 'SMA/SMK')->value('id'), $baris['education_degree_id']);
        $this->assertTrue($baris['is_current_job']);
        $this->assertEquals(
            [MasterCity::query()->where('title', 'Kota Bandung')->value('id')],
            $baris['work_locations']
        );
        $this->assertCount(2, $baris['trainings']);
    }

    public function test_placeholder_pilih_tidak_dianggap_data(): void
    {
        $path = $this->layananTemplate()->buatFormulirWord();
        $dom = $this->dokumen($path);
        $blok = $this->tabelBlok($dom);

        $this->assertStringStartsWith('Pilih', $this->teks($this->sdt($blok[1], 'pendekar_b2_agama')));

        $preview = (new ImportService)->parseAndValidate($path, 'docx', ImportService::MODE_BINAAN);

        $this->assertSame([], $preview['valid'], 'Blok kosong/placeholder tidak boleh jadi baris valid.');
        $this->assertSame([], $preview['errors'], 'Blok CONTOH bukan error; ia dilewati sebagai informasi.');
        $this->assertCount(1, $preview['dilewati']);
        $this->assertSame(1, $preview['dilewati'][0]['blok']);
    }

    public function test_master_kosong_memberi_catatan_bukan_daftar_cadangan(): void
    {
        DB::connection('semarak')->table('anak')->delete();
        MasterReligion::query()->delete();
        MasterCity::query()->delete();
        MasterEducationDegree::query()->delete();
        MasterTraining::query()->delete();

        $path = $this->layananTemplate()->buatFormulirWord();
        $dom = $this->dokumen($path);
        $blok = $this->tabelBlok($dom);
        $xml = $this->entriZip($path, 'word/document.xml');

        $agama = $this->sdt($blok[1], 'pendekar_b2_agama');
        $this->assertSame([], $this->daftarItem($agama), 'Tanpa master tidak boleh ada pilihan karangan.');
        $this->assertSame(1, $this->xpath($agama)->query('./w:sdtPr/w:showingPlcHdr', $agama)->length);
        $this->assertStringStartsWith('Pilih', $this->teks($agama));

        // Enum tetap tersedia walau master kosong.
        $this->assertSame(['L - Laki-laki', 'P - Perempuan'], $this->daftarItem($this->sdt($blok[1], 'pendekar_b2_jenis_kelamin')));
        $this->assertSame(['Belum Bekerja', 'Sedang Bekerja'], $this->daftarItem($this->sdt($blok[1], 'pendekar_b2_status_pekerjaan')));

        $this->assertStringContainsString('belum tersedia di server', $xml);
        $this->assertStringContainsString('Belum tersedia (master kosong)', $xml);
        $this->assertStringContainsString('Agama', $xml);
        $this->assertStringContainsString('Kota KTP', $xml);
    }

    public function test_halaman_import_data_tanpa_tombol_template_word(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/import-data')
            ->assertOk()
            ->assertDontSee('Download Template Word')
            ->assertSee('Download Formulir Word (per anak)');
    }

    public function test_template_excel_punya_dropdown_kota_ktp_dan_status_pekerjaan(): void
    {
        $path = $this->layananTemplate()->buatExcel();
        $spreadsheet = IOFactory::load($path);
        $daftar = $spreadsheet->getSheetByName('DAFTAR');

        $this->assertNotNull($daftar, 'Sheet DAFTAR harus ada.');
        $this->assertSame([
            'AGAMA',
            'JENIS KELAMIN',
            'PENDIDIKAN',
            'PELATIHAN',
            'WILAYAH',
            'KOTA KTP',
            'STATUS PEKERJAAN',
            'STATUS PROFIL',
        ], $daftar->rangeToArray('A1:H1')[0]);

        $this->assertSame(
            ['Belum Bekerja', 'Sedang Bekerja'],
            array_values(array_filter(array_column($daftar->rangeToArray('G2:G3'), 0)))
        );

        $kolom = array_column(SpesifikasiKolomImport::kolom(), 'kunci');
        $huruf = fn (string $kunci): string => Coordinate::stringFromColumnIndex(
            (int) array_search($kunci, $kolom, true) + 1
        );

        $data = $spreadsheet->getSheetByName('DATA');
        $this->assertNotNull($data);

        foreach (['kota_ktp', 'status_pekerjaan', 'status_profil'] as $kunci) {
            $validasi = $data->getDataValidation($huruf($kunci).'5');

            $this->assertSame(DataValidation::TYPE_LIST, $validasi->getType(), "{$kunci} harus dropdown.");
            $this->assertStringStartsWith('DAF_', (string) $validasi->getFormula1(), "{$kunci} harus menunjuk named range DAF_*.");
            $this->assertTrue($validasi->getShowDropDown(), "{$kunci} harus menampilkan panah dropdown.");
        }
    }
}
