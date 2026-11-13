<?php

namespace App\Support\Import;

use App\Services\ImportService;

/**
 * Sumber tunggal definisi kolom template import (Excel dan Word).
 *
 * SATU daftar dipakai oleh:
 * - `App\Services\ImportTemplateService` (pembuat template Excel/Word),
 * - `App\Services\ImportService` (parser + validasi + rencana tautan).
 *
 * Dengan begitu header yang diunduh operator dijamin sama dengan header yang
 * dikenali parser. Aturan penamaan header:
 * - teks header ditulis apa adanya (mis. "Jenis Kelamin"),
 * - tanda bintang (`*`) menandai kolom wajib untuk mode Remaja Binaan dan
 *   DIBUANG parser sebelum pemetaan (`str_replace('*', '', ...)`),
 * - kunci internal memakai huruf kecil tanpa bintang, sehingga template lama
 *   yang headernya "Nama*", "Email*", dst. tetap terbaca.
 */
final class SpesifikasiKolomImport
{
    /**
     * Penanda baris contoh. Parser menolak file yang masih memuat teks ini
     * supaya data fiktif tidak pernah masuk ke database.
     */
    public const PENANDA_CONTOH = 'CONTOH - HAPUS SEBELUM IMPORT';

    /** Kolom nomor urut otomatis (diabaikan parser). */
    public const KUNCI_NOMOR = 'no';

    /**
     * Definisi seluruh kolom sheet DATA / tabel DATA Word.
     *
     * `wajib` berisi mode import tempat kolom itu wajib diisi. Kolom yang tidak
     * wajib di suatu mode tetap boleh diisi (data opsional).
     *
     * @return array<int, array{
     *     kunci: string,
     *     header: string,
     *     wajib: array<int, string>,
     *     format: string,
     *     contoh: string,
     *     keterangan: string,
     *     lebar: int
     * }>
     */
    public static function kolom(): array
    {
        return [
            [
                'kunci' => self::KUNCI_NOMOR,
                'header' => 'No',
                'wajib' => [],
                'format' => 'Otomatis',
                'contoh' => '1',
                'keterangan' => 'Diisi rumus otomatis. Jangan diubah; kolom ini diabaikan saat import.',
                'lebar' => 6,
            ],
            [
                'kunci' => 'nama',
                'header' => 'Nama',
                'wajib' => [ImportService::MODE_BINAAN, ImportService::MODE_KLIEN],
                'format' => 'Teks, 2-150 karakter',
                'contoh' => 'Ahmad Fauzi',
                'keterangan' => 'Nama lengkap remaja binaan sesuai dokumen resmi.',
                'lebar' => 26,
            ],
            [
                'kunci' => 'jenis_kelamin',
                'header' => 'Jenis Kelamin',
                'wajib' => [ImportService::MODE_BINAAN, ImportService::MODE_KLIEN],
                'format' => 'L / P',
                'contoh' => 'L',
                'keterangan' => 'L = laki-laki, P = perempuan.',
                'lebar' => 12,
            ],
            [
                'kunci' => 'asal_daerah',
                'header' => 'Asal Daerah',
                'wajib' => [ImportService::MODE_BINAAN],
                'format' => 'Teks, maks 100 karakter',
                'contoh' => 'Kabupaten Bandung',
                'keterangan' => 'Daerah asal peserta. Dipakai juga untuk memeriksa kemungkinan duplikat.',
                'lebar' => 20,
            ],
            [
                'kunci' => 'agama',
                'header' => 'Agama',
                'wajib' => [ImportService::MODE_BINAAN],
                'format' => 'Pilih dari daftar',
                'contoh' => 'Islam',
                'keterangan' => 'Agama peserta (lihat sheet DAFTAR).',
                'lebar' => 12,
            ],
            [
                'kunci' => 'umur',
                'header' => 'Umur',
                'wajib' => [ImportService::MODE_BINAAN],
                'format' => 'Bilangan bulat 5-30',
                'contoh' => '17',
                'keterangan' => 'Boleh dikosongkan bila kolom Tanggal Lahir diisi; umur dihitung otomatis.',
                'lebar' => 8,
            ],
            [
                'kunci' => 'tanggal_lahir',
                'header' => 'Tanggal Lahir',
                'wajib' => [],
                'format' => 'DD/MM/YYYY',
                'contoh' => '17/08/2008',
                'keterangan' => 'Dipakai menghitung umur bila kolom Umur kosong.',
                'lebar' => 14,
            ],
            [
                'kunci' => 'angkatan',
                'header' => 'Angkatan',
                'wajib' => [ImportService::MODE_BINAAN],
                'format' => 'Teks, maks 50 karakter',
                'contoh' => 'Angkatan 2026',
                'keterangan' => 'Angkatan pembinaan peserta.',
                'lebar' => 16,
            ],
            [
                'kunci' => 'email',
                'header' => 'Email',
                'wajib' => [ImportService::MODE_KLIEN],
                'format' => 'Alamat email valid',
                'contoh' => 'contoh@example.test',
                'keterangan' => 'Opsional pada mode Remaja Binaan (akun Draft belum bisa login); wajib pada mode Klien Umum.',
                'lebar' => 24,
            ],
            [
                'kunci' => 'tempat_lahir',
                'header' => 'Tempat Lahir',
                'wajib' => [],
                'format' => 'Teks',
                'contoh' => 'Cimahi',
                'keterangan' => 'Opsional.',
                'lebar' => 16,
            ],
            [
                'kunci' => 'alamat_ktp',
                'header' => 'Alamat KTP',
                'wajib' => [],
                'format' => 'Teks',
                'contoh' => 'Jl. Contoh No. 1',
                'keterangan' => 'Opsional, alamat sesuai KTP.',
                'lebar' => 24,
            ],
            [
                'kunci' => 'kota_ktp',
                'header' => 'Kota KTP',
                'wajib' => [],
                'format' => 'Pilih dari daftar',
                'contoh' => 'Kota Bandung',
                'keterangan' => 'Opsional. Harus terdaftar pada master wilayah sistem.',
                'lebar' => 18,
            ],
            [
                'kunci' => 'pendidikan',
                'header' => 'Pendidikan',
                'wajib' => [],
                'format' => 'Pilih dari daftar',
                'contoh' => 'SMA/SMK',
                'keterangan' => 'Opsional. Harus terdaftar pada master pendidikan.',
                'lebar' => 16,
            ],
            [
                'kunci' => 'pelatihan',
                'header' => 'Pelatihan',
                'wajib' => [],
                'format' => 'Daftar, pisahkan dengan koma',
                'contoh' => 'Menjahit',
                'keterangan' => 'Opsional, boleh lebih dari satu (contoh: Menjahit, Tata Boga).',
                'lebar' => 22,
            ],
            [
                'kunci' => 'penempatan_wilayah',
                'header' => 'Penempatan Wilayah',
                'wajib' => [],
                'format' => 'Daftar, pisahkan dengan koma',
                'contoh' => 'Kota Bandung',
                'keterangan' => 'Opsional, boleh lebih dari satu.',
                'lebar' => 22,
            ],
            [
                'kunci' => 'status_pekerjaan',
                'header' => 'Status Pekerjaan',
                'wajib' => [],
                'format' => 'Teks',
                'contoh' => 'Belum Bekerja',
                'keterangan' => 'Opsional. Isi "Sedang Bekerja" bila peserta sudah bekerja.',
                'lebar' => 18,
            ],
            [
                'kunci' => 'deskripsi',
                'header' => 'Deskripsi',
                'wajib' => [],
                'format' => 'Teks',
                'contoh' => 'Contoh keterangan fiktif.',
                'keterangan' => 'Opsional.',
                'lebar' => 26,
            ],
            [
                'kunci' => 'status_profil',
                'header' => 'Status Profil',
                'wajib' => [],
                'format' => 'Tersedia / Belum',
                'contoh' => 'Tersedia',
                'keterangan' => 'Opsional. "Tersedia" menandai profil siap ditampilkan.',
                'lebar' => 14,
            ],
            [
                'kunci' => 'nama_file_foto',
                'header' => 'Nama File Foto',
                'wajib' => [],
                'format' => 'Nama file, mis. budi.jpg',
                'contoh' => 'contoh-foto.jpg',
                'keterangan' => 'Opsional. Harus sama dengan nama file foto yang diunggah pada menu Import Data.',
                'lebar' => 20,
            ],
        ];
    }

    /**
     * Kunci kolom yang benar-benar berisi data (tanpa kolom nomor urut).
     *
     * Dipakai parser untuk memutuskan sebuah baris "kosong" atau tidak: baris
     * yang hanya berisi rumus nomor urut (200 baris pra-format) TIDAK dianggap
     * data.
     *
     * @return array<int, string>
     */
    public static function kunciData(): array
    {
        return array_values(array_filter(
            array_column(self::kolom(), 'kunci'),
            fn (string $kunci): bool => $kunci !== self::KUNCI_NOMOR
        ));
    }

    /**
     * Header tampilan (dengan tanda `*` pada kolom wajib mode yang diminta).
     *
     * @return array<int, string>
     */
    public static function header(string $mode = ImportService::MODE_BINAAN): array
    {
        return array_map(
            fn (array $kolom): string => $kolom['header'].(in_array($mode, $kolom['wajib'], true) ? '*' : ''),
            self::kolom()
        );
    }

    /**
     * Kunci kolom yang wajib pada mode tertentu.
     *
     * @return array<int, string>
     */
    public static function wajib(string $mode): array
    {
        $hasil = [];

        foreach (self::kolom() as $kolom) {
            if (in_array($mode, $kolom['wajib'], true)) {
                $hasil[] = $kolom['kunci'];
            }
        }

        return $hasil;
    }

    /**
     * Nilai satu baris CONTOH (fiktif). Nilai referensi (kota/pendidikan/
     * pelatihan/wilayah) memakai data master yang tersedia saat template dibuat.
     *
     * @param  array<string, string>  $referensi
     * @return array<int, string>
     */
    public static function barisContoh(array $referensi = []): array
    {
        $nilai = [];

        foreach (self::kolom() as $kolom) {
            $nilai[] = match ($kolom['kunci']) {
                self::KUNCI_NOMOR => '1',
                'nama' => self::PENANDA_CONTOH,
                'kota_ktp', 'penempatan_wilayah' => $referensi['wilayah'] ?? $kolom['contoh'],
                'pendidikan' => $referensi['pendidikan'] ?? $kolom['contoh'],
                'pelatihan' => $referensi['pelatihan'] ?? $kolom['contoh'],
                'agama' => $referensi['agama'] ?? $kolom['contoh'],
                default => $kolom['contoh'],
            };
        }

        return $nilai;
    }

    /**
     * Langkah pengisian (maksimal 8 butir, dipakai Excel dan Word).
     *
     * @return array<int, string>
     */
    public static function langkahPengisian(): array
    {
        return [
            'Unduh template ini, lalu buka pada aplikasi pengolah angka/dokumen Anda (Excel atau Word).',
            'Isi data pada sheet/tabel DATA mulai baris kosong pertama setelah baris CONTOH.',
            'Hapus baris CONTOH berwarna kuning sebelum file diunggah.',
            'Isi seluruh kolom bertanda (*) karena wajib; kolom tanpa tanda boleh dikosongkan.',
            'Tulis tanggal lahir dengan format DD/MM/YYYY (contoh 17/08/2008), atau isi kolom Umur dengan angka 5-30.',
            'Tulis jenis kelamin dengan kode L (laki-laki) atau P (perempuan).',
            'Bila lebih dari satu pelatihan/wilayah, pisahkan dengan koma (contoh: Menjahit, Tata Boga).',
            'Simpan file, unggah pada menu Import Data, pilih mode Remaja Binaan, lalu periksa Preview sebelum menekan Import.',
        ];
    }

    /**
     * Aturan umum pengisian (dipakai Excel dan Word).
     *
     * @return array<int, string>
     */
    public static function aturanUmum(): array
    {
        return [
            'Jangan mengubah, menghapus, atau menambah baris header; urutan kolom harus tetap.',
            'Jangan menghapus kolom, meskipun kolom itu opsional dan tidak diisi.',
            'Jangan menggabungkan sel (merge) di dalam tabel data dan jangan menaruh teks lain pada tabel data.',
            'Jumlah baris maksimum per file mengikuti batas server ('.(int) config('pendekar.import.max_rows', 2000).' baris).',
            'Gunakan satu file untuk satu kali import. Simpan berkas asli sebagai arsip bila diperlukan.',
        ];
    }

    /**
     * Langkah pengisian FORMULIR (satu blok = satu remaja binaan).
     *
     * Dipakai halaman PETUNJUK pada template formulir Word; berbeda dari
     * {@see langkahPengisian()} yang berbicara tentang sheet/tabel DATA.
     *
     * @return array<int, string>
     */
    public static function langkahPengisianFormulir(): array
    {
        return [
            'Unduh formulir ini, lalu buka pada aplikasi pengolah dokumen (Microsoft Word atau LibreOffice Writer).',
            'Setiap halaman blok adalah SATU remaja binaan: isi kolom Isian di sebelah kanan label.',
            'Kosongkan atau hapus seluruh halaman BLOK 1 (CONTOH) sebelum mengunggah; jangan mengosongkan sebagian saja.',
            'Label bertanda (*) wajib diisi; label tanpa tanda boleh dikosongkan.',
            'Tulis tanggal lahir dengan format DD/MM/YYYY (contoh 17/08/2008), atau isi Umur dengan angka 5-30.',
            'Tulis jenis kelamin dengan kode L (laki-laki) atau P (perempuan).',
            'Bila lebih dari satu pelatihan/wilayah, pisahkan dengan koma (contoh: Menjahit, Tata Boga).',
            'Blok yang seluruh isiannya kosong diabaikan. Simpan sebagai .docx, lalu unggah pada menu Import Data.',
        ];
    }

    /**
     * Aturan umum khusus formulir per anak.
     *
     * @return array<int, string>
     */
    public static function aturanUmumFormulir(): array
    {
        return [
            'Jangan mengubah teks label di kolom kiri; parser mengenali label tersebut.',
            'Jangan menggabungkan sel, menambah baris label baru, atau memindahkan tabel ke halaman lain.',
            'Satu blok = satu anak; jangan menaruh dua anak dalam satu tabel.',
            'Judul "BLOK n" dipakai sebagai nomor blok pada pesan galat Preview; jangan menghapusnya.',
            'Jumlah blok maksimum per file mengikuti batas server ('.(int) config('pendekar.import.max_rows', 2000).' blok).',
            'Gunakan satu file untuk satu kali import. Simpan berkas asli sebagai arsip bila diperlukan.',
        ];
    }

    /**
     * Judul resmi formulir per anak.
     */
    public static function judulFormulir(): string
    {
        return 'FORMULIR DATA REMAJA BINAAN';
    }

    /**
     * Subjudul formulir per anak.
     */
    public static function subjudulFormulir(): string
    {
        return 'Satu blok untuk satu remaja binaan';
    }

    /**
     * Catatan privasi yang wajib tampil di kedua template.
     */
    public static function catatanPrivasi(): string
    {
        return 'Dokumen ini memuat data pribadi anak. Simpan dan kirim secara aman; hapus setelah import.';
    }

    /**
     * Nama instansi dari konfigurasi (boleh kosong; template memakai placeholder).
     */
    public static function namaInstansi(): string
    {
        $nama = trim((string) config('pendekar.instansi_nama', ''));

        return $nama === '' ? 'Nama Instansi' : $nama;
    }

    /**
     * Judul resmi template.
     */
    public static function judulTemplate(): string
    {
        return 'TEMPLATE IMPORT DATA REMAJA BINAAN';
    }

    /**
     * Subjudul template.
     */
    public static function subjudulTemplate(): string
    {
        return 'Program Pendekar 1721';
    }
}
