<?php

namespace App\Support\Import;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Pemasang dan pembuka CONTENT CONTROL (`w:sdt`) pada dokumen Word (.docx).
 *
 * PhpWord tidak mendukung content control, sedangkan formulir per anak butuh
 * dropdown yang benar-benar tampil di Microsoft Word desktop. Solusinya:
 * - `TemplateFormulirWord` menulis token `[[SDT:blok:kunci]]` di kolom Isian,
 *   lalu kelas ini mengganti paragraf token tersebut dengan `w:sdt` yang valid
 *   (di DALAM `w:tc`, sebagai sdt tingkat run di `w:p`),
 * - saat file diunggah kembali, {@see ratakan()} membuka `w:sdtContent` agar
 *   PhpWord dapat membaca nilai yang dipilih operator.
 *
 * Struktur yang ditulis mengikuti skema OOXML:
 * `w:sdt > w:sdtPr (w:alias, w:tag, [w:showingPlcHdr], w:dropDownList|w:date)
 *  > w:sdtContent > w:r > w:t`.
 */
final class KontrolKontenWord
{
    /** Awal token penanda yang ditulis PhpWord. */
    public const AWALAN_TOKEN = '[[SDT:';

    /** Akhir token penanda. */
    public const AKHIR_TOKEN = ']]';

    /** Namespace utama WordprocessingML. */
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Namespace XML (`xml:space`). */
    private const NS_XML = 'http://www.w3.org/XML/1998/namespace';

    /**
     * Token unik satu kontrol pada satu blok, mis. `[[SDT:2:agama]]`.
     */
    public static function token(int $blok, string $kunci): string
    {
        return self::AWALAN_TOKEN.$blok.':'.$kunci.self::AKHIR_TOKEN;
    }

    /**
     * Ganti seluruh paragraf token menjadi content control di dalam sel.
     *
     * @param  array<string, array{
     *     jenis: string,
     *     alias: string,
     *     tag: string,
     *     placeholder: string,
     *     nilai: string|null,
     *     items?: array<int, array{display: string, value: string}>
     * }>  $kontrol  Kunci = "blok:kunci" (lihat {@see token()}).
     */
    public static function pasang(string $path, array $kontrol): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Berkas .docx tidak dapat dibuka untuk pemasangan dropdown.');
        }

        try {
            $xml = $zip->getFromName('word/document.xml');

            if (! is_string($xml) || $xml === '') {
                throw new RuntimeException('word/document.xml tidak ditemukan pada berkas .docx.');
            }

            $dom = new DOMDocument;
            $dom->preserveWhiteSpace = true;

            if (@$dom->loadXML($xml) !== true) {
                throw new RuntimeException('word/document.xml tidak well-formed sebelum pemasangan dropdown.');
            }

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', self::NS_W);

            $terpasang = 0;

            /** @var array<int, DOMNode> $simpul */
            $simpul = iterator_to_array($xpath->query('//w:t') ?: []);

            foreach ($simpul as $node) {
                $teks = $node->textContent;

                if (! str_contains($teks, self::AWALAN_TOKEN)) {
                    continue;
                }

                if (preg_match('/\[\[SDT:(\d+):([a-z_]+)\]\]/', $teks, $cocok) !== 1) {
                    continue;
                }

                $id = $cocok[1].':'.$cocok[2];

                if (! isset($kontrol[$id])) {
                    continue;
                }

                $paragraf = self::paragrafInduk($node);

                if (! $paragraf instanceof DOMElement || $paragraf->parentNode === null) {
                    continue;
                }

                $baru = $dom->createElementNS(self::NS_W, 'w:p');
                $baru->appendChild(self::elemenSdt($dom, $kontrol[$id]));
                $paragraf->parentNode->replaceChild($baru, $paragraf);
                $terpasang++;
            }

            if ($terpasang === 0) {
                throw new RuntimeException('Tidak ada token content control yang berhasil diganti.');
            }

            $zip->addFromString('word/document.xml', $dom->saveXML());
        } finally {
            $zip->close();
        }
    }

    /**
     * Salinan sementara dokumen dengan seluruh `w:sdt` dibuka menjadi isinya.
     *
     * Dipakai sebelum PhpWord membaca file: reader PhpWord tidak mengenal
     * `w:sdt`, sehingga nilai pilihan operator akan hilang bila tidak dibuka.
     * Placeholder ("Pilih...") tetap dibaca sebagai teks dan dinormalkan
     * menjadi kosong oleh `ImportService`.
     */
    public static function ratakan(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return $path;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || ! str_contains($xml, 'w:sdt')) {
            return $path;
        }

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;

        if (@$dom->loadXML($xml) !== true) {
            return $path;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NS_W);

        /** @var array<int, DOMNode> $semua */
        $semua = iterator_to_array($xpath->query('//w:sdt') ?: []);

        foreach ($semua as $sdt) {
            $isi = null;

            foreach ($sdt->childNodes as $anak) {
                if ($anak instanceof DOMElement && $anak->localName === 'sdtContent') {
                    $isi = $anak;

                    break;
                }
            }

            $induk = $sdt->parentNode;

            if (! $isi instanceof DOMElement || $induk === null) {
                continue;
            }

            while ($isi->firstChild !== null) {
                $induk->insertBefore($isi->firstChild, $sdt);
            }

            $induk->removeChild($sdt);
        }

        $tujuan = tempnam(sys_get_temp_dir(), 'docx-parser-');

        if ($tujuan === false) {
            return $path;
        }

        $tujuan .= '.docx';

        if (! copy($path, $tujuan)) {
            return $path;
        }

        $zipTujuan = new ZipArchive;

        if ($zipTujuan->open($tujuan) !== true) {
            return $path;
        }

        $zipTujuan->addFromString('word/document.xml', $dom->saveXML());
        $zipTujuan->close();

        return $tujuan;
    }

    /**
     * Bangun satu elemen `w:sdt` lengkap.
     *
     * @param  array{jenis: string, alias: string, tag: string, placeholder: string, nilai: string|null, items?: array<int, array{display: string, value: string}>}  $kontrol
     */
    private static function elemenSdt(DOMDocument $dom, array $kontrol): DOMElement
    {
        $nilai = $kontrol['nilai'] ?? null;

        if (is_string($nilai)) {
            $nilai = trim($nilai);

            if ($nilai === '') {
                $nilai = null;
            }
        }

        $sdt = $dom->createElementNS(self::NS_W, 'w:sdt');
        $pr = $dom->createElementNS(self::NS_W, 'w:sdtPr');

        $pr->appendChild(self::elemenDenganVal($dom, 'w:alias', $kontrol['alias']));
        $pr->appendChild(self::elemenDenganVal($dom, 'w:tag', $kontrol['tag']));

        if ($nilai === null) {
            $pr->appendChild($dom->createElementNS(self::NS_W, 'w:showingPlcHdr'));
        }

        if (($kontrol['jenis'] ?? '') === 'dropdown') {
            $pr->appendChild(self::daftarPilihan($dom, $kontrol['items'] ?? []));
        } else {
            $pr->appendChild(self::pemilihTanggal($dom, $nilai));
        }

        $isi = $dom->createElementNS(self::NS_W, 'w:sdtContent');
        $jalan = $dom->createElementNS(self::NS_W, 'w:r');

        $rPr = $dom->createElementNS(self::NS_W, 'w:rPr');
        $font = $dom->createElementNS(self::NS_W, 'w:rFonts');
        $font->setAttributeNS(self::NS_W, 'w:ascii', 'Arial');
        $font->setAttributeNS(self::NS_W, 'w:hAnsi', 'Arial');
        $rPr->appendChild($font);
        $rPr->appendChild(self::elemenDenganVal($dom, 'w:sz', '20'));
        $rPr->appendChild(self::elemenDenganVal($dom, 'w:szCs', '20'));
        $jalan->appendChild($rPr);

        $teks = $dom->createElementNS(self::NS_W, 'w:t');
        $teks->appendChild($dom->createTextNode($nilai ?? $kontrol['placeholder']));
        $teks->setAttributeNS(self::NS_XML, 'xml:space', 'preserve');
        $jalan->appendChild($teks);

        $isi->appendChild($jalan);
        $sdt->appendChild($pr);
        $sdt->appendChild($isi);

        return $sdt;
    }

    /**
     * `w:dropDownList` berisi `w:listItem` dari master/enum.
     *
     * @param  array<int, array{display: string, value: string}>  $items
     */
    private static function daftarPilihan(DOMDocument $dom, array $items): DOMElement
    {
        $daftar = $dom->createElementNS(self::NS_W, 'w:dropDownList');

        foreach ($items as $item) {
            $display = trim((string) ($item['display'] ?? ''));
            $value = trim((string) ($item['value'] ?? $display));

            if ($display === '') {
                continue;
            }

            $listItem = $dom->createElementNS(self::NS_W, 'w:listItem');
            $listItem->setAttributeNS(self::NS_W, 'w:displayText', $display);
            $listItem->setAttributeNS(self::NS_W, 'w:value', $value === '' ? $display : $value);
            $daftar->appendChild($listItem);
        }

        return $daftar;
    }

    /**
     * `w:date` dengan format Indonesia dd/MM/yyyy.
     */
    private static function pemilihTanggal(DOMDocument $dom, ?string $nilai): DOMElement
    {
        $tanggal = $dom->createElementNS(self::NS_W, 'w:date');
        $penuh = self::tanggalPenuh($nilai);

        if ($penuh !== null) {
            $tanggal->setAttributeNS(self::NS_W, 'w:fullDate', $penuh);
        }

        $tanggal->appendChild(self::elemenDenganVal($dom, 'w:dateFormat', 'dd/MM/yyyy'));
        $tanggal->appendChild(self::elemenDenganVal($dom, 'w:lid', 'id-ID'));
        $tanggal->appendChild(self::elemenDenganVal($dom, 'w:storeMappedDataAs', 'dateTime'));
        $tanggal->appendChild(self::elemenDenganVal($dom, 'w:calendar', 'gregorian'));

        return $tanggal;
    }

    /**
     * Ubah `17/08/2008` menjadi `2008-08-17T00:00:00Z` untuk `w:fullDate`.
     */
    private static function tanggalPenuh(?string $nilai): ?string
    {
        if ($nilai === null || preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $nilai, $cocok) !== 1) {
            return null;
        }

        $hari = (int) $cocok[1];
        $bulan = (int) $cocok[2];
        $tahun = (int) $cocok[3];

        if (! checkdate($bulan, $hari, $tahun)) {
            return null;
        }

        return sprintf('%04d-%02d-%02dT00:00:00Z', $tahun, $bulan, $hari);
    }

    /**
     * Elemen `w:*` dengan atribut `w:val`.
     */
    private static function elemenDenganVal(DOMDocument $dom, string $nama, string $nilai): DOMElement
    {
        $elemen = $dom->createElementNS(self::NS_W, $nama);
        $elemen->setAttributeNS(self::NS_W, 'w:val', $nilai);

        return $elemen;
    }

    /**
     * Paragraf (`w:p`) terdekat yang memuat simpul teks.
     */
    private static function paragrafInduk(DOMNode $node): ?DOMElement
    {
        $kini = $node->parentNode;

        while ($kini !== null) {
            if ($kini instanceof DOMElement && $kini->localName === 'p') {
                return $kini;
            }

            $kini = $kini->parentNode;
        }

        return null;
    }
}
