<?php

namespace App\Filament\Pages;

use App\Filament\Support\PeringatanMigrasi;
use App\Services\ImportService;
use App\Services\ImportTemplateService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

class ImportData extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Import Data';

    protected static ?string $title = 'Import Data';

    protected static ?string $slug = 'import-data';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationGroup = 'Manajemen Klien';

    protected static string $view = 'filament.pages.import-data';

    public $file;

    public $previewData = null;

    /**
     * Ringkasan hasil import terakhir (dipakai untuk melaporkan baris gagal).
     */
    public $hasilImport = null;

    public $isProcessing = false;

    /**
     * Mode import (FASE 3): `binaan` â†’ akun Draft, `klien` â†’ akun langsung aktif.
     *
     * Dideklarasikan sebagai properti publik karena form halaman ini memakai
     * state path kosong (setiap field = properti komponen, seperti `$file`).
     */
    public $mode;

    /**
     * Foto profil hasil unggah (opsional).
     *
     * Properti ini WAJIB ada: form halaman memakai state path kosong, sehingga
     * tanpa properti publik di sini Filament melempar
     * "No property found for validation: [photos]" saat getState().
     */
    public $photos = [];

    /** Kata sandi awal opsional untuk peserta (mode klien). */
    public $import_password;

    /**
     * Opsi penautan akun binaan ke `semarak.anak` (FASE 3, revisi review).
     *
     * Default MATI: tidak ada penautan otomatis berdasarkan nama. Operator
     * harus mencentang sendiri setelah memeriksa daftar pada langkah Preview.
     */
    public $tautkan_anak = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->user_type === 'admin';
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Jenis Data')
                ->description('Pilih jenis akun yang dibuat. Pengaturan ini berlaku untuk seluruh baris pada file.')
                ->schema([
                    Forms\Components\Radio::make('mode')
                        ->label('Mode Import')
                        ->options(ImportService::pilihanMode())
                        ->default(ImportService::MODE_BINAAN)
                        ->required()
                        // live(): checkbox penautan di bawah hanya muncul untuk
                        // mode binaan, jadi section perlu re-render saat mode
                        // diganti.
                        ->live()
                        ->helperText('"Remaja Binaan" membuat data anak baru (status Aktif) sekaligus akun Draft tanpa '
                            .'kata sandi: akun belum bisa login sampai admin menjalankan aksi "Luluskan & Aktifkan Akun Klien" '
                            .'di menu Anak Binaan.'),

                    Forms\Components\Checkbox::make('tautkan_anak')
                        ->label('Tautkan ke anak yang sudah ada (jangan buat anak baru)')
                        ->default(false)
                        ->visible(fn (Forms\Get $get): bool => $get('mode') === ImportService::MODE_BINAAN)
                        ->helperText('Opsional dan hanya berlaku untuk mode "Remaja Binaan". Bila dicentang, baris yang '
                            .'namanya cocok dengan SATU data anak yang belum punya akun hanya dibuatkan akun Draft yang '
                            .'ditautkan (tanpa anak baru); baris lain tetap membuat anak baru. Nama kembar tidak pernah '
                            .'ditautkan. Centang sebelum menekan "Preview Data" agar rencana per baris ikut diperbarui.'),
                ]),

            Forms\Components\Section::make('Upload File Import & Foto')
                ->description('Upload data klien melalui file Excel/Word. Anda juga dapat mengupload banyak foto sekaligus untuk dipasangkan dengan data klien.')
                ->schema([
                    Forms\Components\FileUpload::make('file')
                        ->label('File Data (Excel/Word)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
                            'application/vnd.ms-excel', // xls
                            'text/csv', // csv
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
                        ])
                        ->required()
                        ->maxSize(5120)
                        // SECURITY (FASE 3): file mentah berisi PII disimpan di
                        // disk "local" (storage/app/imports) yang tidak diekspos
                        // lewat public/storage, dan nama filenya diacak.
                        ->disk('local')
                        ->directory('imports')
                        ->visibility('private'),

                    Forms\Components\FileUpload::make('photos')
                        ->label('Foto Profil (Opsional - Bisa pilih banyak file)')
                        ->helperText('Pastikan nama file foto sama dengan yang Anda tulis di kolom "Nama File Foto" pada Excel/Word (Contoh: budi.jpg)')
                        ->image()
                        ->multiple()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                        ->maxSize(2048)
                        ->disk('public')
                        ->directory('profile_pictures')
                        ->preserveFilenames(),
                ]),

            Forms\Components\Section::make('Kata Sandi Awal Peserta')
                ->description('Opsional. Jika diisi, seluruh peserta hasil import memakai kata sandi ini untuk login pertama (harus cocok dengan IMPORT_PASSWORD_HASH di server). Jika dikosongkan, peserta diberi kata sandi acak dan wajib melakukan reset password.')
                ->schema([
                    Forms\Components\TextInput::make('import_password')
                        ->label('Kata Sandi Awal')
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                ]),
        ];
    }

    public function mount()
    {
        // Peringatan sekali per halaman bila migrasi Fase 2 belum dijalankan.
        // Impor tetap bisa dipakai; hanya penautan ke data anak yang tidak
        // tersedia sampai kolomnya ada.
        PeringatanMigrasi::tampilkanSekali();

        // FASE 3: pilihan awal = "Remaja Binaan" (akun Draft) supaya akun hasil
        // impor tidak langsung aktif sebelum binaan dinyatakan lulus.
        // Opsi penautan ke data anak juga eksplisit MATI: tidak ada penautan
        // otomatis berdasarkan nama.
        $this->form->fill([
            'mode' => ImportService::MODE_BINAAN,
            'tautkan_anak' => false,
        ]);
    }

    /**
     * Unduh template Excel resmi (sheet PETUNJUK, DATA, dan DAFTAR).
     */
    public function downloadExcelTemplate()
    {
        $layanan = new ImportTemplateService;
        $path = $layanan->buatExcel();

        // File disimpan di storage/app/import-templates (bukan app/public) agar
        // tidak pernah bisa diunduh langsung dari web; dihapus setelah dikirim.
        return response()
            ->download($path, $layanan->namaBerkas('xlsx'))
            ->deleteFileAfterSend(true);
    }

    /**
     * Unduh template Word resmi (.docx).
     */
    public function downloadWordTemplate()
    {
        $layanan = new ImportTemplateService;
        $path = $layanan->buatWord();

        return response()
            ->download($path, $layanan->namaBerkas('docx'))
            ->deleteFileAfterSend(true);
    }

    public function processPreview()
    {
        $data = $this->form->getState();

        if (empty($data['file'])) {
            Notification::make()->title('Silakan upload file terlebih dahulu.')->danger()->send();

            return;
        }

        $this->isProcessing = true;

        try {
            // SECURITY (FASE 3): file disimpan pada disk "local"
            // (storage/app/imports) dan dibaca lewat path disk tersebut,
            // bukan lewat storage_path('app/public/...').
            $disk = Storage::disk('local');

            if (! is_string($data['file']) || ! $disk->exists($data['file'])) {
                throw new \Exception('File import tidak ditemukan, silakan unggah ulang.');
            }

            $filePath = $disk->path($data['file']);
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            // Mode menentukan kolom wajib (mode binaan: asal daerah/agama/umur/
            // angkatan wajib). Checkbox penautan menentukan rencana per baris
            // yang ditampilkan, jadi nilainya ikut dibaca di sini.
            $mode = $data['mode'] ?? ImportService::MODE_BINAAN;
            $tautkanAnak = $mode === ImportService::MODE_BINAAN && (bool) ($data['tautkan_anak'] ?? false);

            $this->hasilImport = null;

            $service = new ImportService;
            $this->previewData = $service->parseAndValidate($filePath, $extension, $mode, $tautkanAnak);

            Notification::make()->title('File berhasil diproses untuk preview.')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Gagal memproses file: '.$e->getMessage())->danger()->send();
            $this->previewData = null;
        }

        $this->isProcessing = false;
    }

    public function confirmImport()
    {
        if (empty($this->previewData) || count($this->previewData['errors']) > 0) {
            Notification::make()->title('Perbaiki error terlebih dahulu sebelum import.')->danger()->send();

            return;
        }

        if (count($this->previewData['valid']) === 0) {
            Notification::make()->title('Tidak ada data valid untuk diimport.')->warning()->send();

            return;
        }

        try {
            $data = $this->form->getState();
            $uploadedPhotos = $data['photos'] ?? [];
            $importPassword = $data['import_password'] ?? null;
            $mode = $data['mode'] ?? ImportService::MODE_BINAAN;

            // Penautan ke data anak hanya berlaku untuk mode binaan dan hanya
            // bila operator mencentang opsinya. Nilai ini menentukan apakah
            // akun hasil import dikaitkan ke `semarak.anak`.
            $tautkanAnak = $mode === ImportService::MODE_BINAAN && (bool) ($data['tautkan_anak'] ?? false);

            // SECURITY (FASE 3): kata sandi awal harus cocok dengan hash
            // yang tersimpan di server (IMPORT_PASSWORD_HASH). Mode binaan
            // tidak memakai kata sandi sama sekali, jadi tidak perlu dicek.
            if ($mode !== ImportService::MODE_BINAAN && ! ImportService::initialPasswordMatches($importPassword)) {
                Notification::make()
                    ->title('Kata sandi awal tidak valid atau belum dikonfigurasi di server.')
                    ->danger()
                    ->send();

                return;
            }

            $service = new ImportService;
            $ringkasan = $service->importData(
                $this->previewData['valid'],
                $uploadedPhotos,
                auth()->id(),
                $mode === ImportService::MODE_BINAAN ? null : $importPassword,
                $mode,
                $tautkanAnak,
            );

            $this->hasilImport = $ringkasan;

            $totalDuplikat = count($this->previewData['duplicates']);

            $pesan = $mode === ImportService::MODE_BINAAN
                ? "{$ringkasan['akun_dibuat']} akun Draft dibuat: {$ringkasan['anak_baru']} anak baru, "
                    ."{$ringkasan['ditautkan']} akun ditautkan ke anak yang sudah ada, {$ringkasan['gagal']} baris gagal."
                : "{$ringkasan['akun_dibuat']} akun klien aktif dibuat.";

            Notification::make()
                ->title($ringkasan['gagal'] > 0 ? 'Import Selesai Dengan Catatan' : 'Import Berhasil')
                ->body($pesan." {$totalDuplikat} data dilewati karena duplikat.")
                ->status($ringkasan['gagal'] > 0 ? 'warning' : 'success')
                ->send();

            // Reset form (mode kembali ke pilihan awal "Remaja Binaan" dan
            // opsi penautan kembali MATI).
            $this->form->fill([
                'mode' => ImportService::MODE_BINAAN,
                'tautkan_anak' => false,
            ]);
            $this->previewData = null;

        } catch (\Exception $e) {
            Notification::make()->title('Terjadi kesalahan saat menyimpan data: '.$e->getMessage())->danger()->send();
        }
    }
}
