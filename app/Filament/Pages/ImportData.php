<?php

namespace App\Filament\Pages;

use App\Models\MasterCity;
use App\Models\MasterEducationDegree;
use App\Models\MasterTraining;
use App\Services\ImportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class ImportData extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Import Data';

    protected static ?string $title = 'Import Data Klien';

    protected static ?string $slug = 'import-data';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationGroup = 'Manajemen Klien';

    protected static string $view = 'filament.pages.import-data';

    public $file;

    public $previewData = null;

    public $isProcessing = false;

    /**
     * Mode import (FASE 3): `binaan` → akun Draft, `klien` → akun langsung aktif.
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
                        ->helperText('"Remaja Binaan" membuat akun Draft tanpa kata sandi: belum bisa login sampai '
                            .'admin menjalankan aksi "Luluskan & Aktifkan Akun Klien" di menu Anak Binaan.'),

                    Forms\Components\Checkbox::make('tautkan_anak')
                        ->label('Tautkan ke data anak yang namanya sama')
                        ->default(false)
                        ->visible(fn (Forms\Get $get): bool => $get('mode') === ImportService::MODE_BINAAN)
                        ->helperText('Opsional dan hanya berlaku untuk mode "Remaja Binaan". Bila dicentang, akun '
                            .'hasil import ditautkan ke data anak dengan nama yang SAMA, dengan syarat nama itu unik '
                            .'dan anak tersebut belum punya akun. Daftar yang akan ditautkan ditampilkan di langkah '
                            .'Preview untuk Anda periksa sebelum import.'),
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
        // FASE 3: pilihan awal = "Remaja Binaan" (akun Draft) supaya akun hasil
        // impor tidak langsung aktif sebelum binaan dinyatakan lulus.
        // Opsi penautan ke data anak juga eksplisit MATI: tidak ada penautan
        // otomatis berdasarkan nama.
        $this->form->fill([
            'mode' => ImportService::MODE_BINAAN,
            'tautkan_anak' => false,
        ]);
    }

    public function downloadExcelTemplate()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Nama*',
            'Email*',
            'Jenis Kelamin*',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Alamat KTP',
            'Kota KTP',
            'Pendidikan',
            'Pelatihan',
            'Penempatan Wilayah',
            'Status Pekerjaan',
            'Deskripsi',
            'Status Profil',
            'Nama File Foto',
            // Opsional: hanya dipakai untuk deteksi "kemungkinan duplikat"
            // nama + asal daerah + angkatan pada langkah Preview.
            'Asal Daerah',
            'Angkatan',
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'1', $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        // Contoh Data
        $sheet->setCellValue('A2', 'Budi Santoso');
        $sheet->setCellValue('B2', 'budi@example.com');
        $sheet->setCellValue('C2', 'L');
        $sheet->setCellValue('D2', 'Bandung');
        $sheet->setCellValue('E2', '1995-08-17');
        $sheet->setCellValue('F2', 'Jl. Merdeka No. 1');

        // Ambil data referensi
        $city = MasterCity::first()?->title ?? 'Bandung';
        $edu = MasterEducationDegree::first()?->title ?? 'SMA';
        $training = MasterTraining::first()?->title ?? 'Menjahit';

        $sheet->setCellValue('G2', $city);
        $sheet->setCellValue('H2', $edu);
        $sheet->setCellValue('I2', $training);
        $sheet->setCellValue('J2', $city);
        $sheet->setCellValue('K2', 'Sedang Bekerja');
        $sheet->setCellValue('L2', 'Klien rajin dan disiplin');
        $sheet->setCellValue('M2', 'Tersedia');
        $sheet->setCellValue('N2', 'budi.jpg');
        $sheet->setCellValue('O2', 'Bandung');
        $sheet->setCellValue('P2', 'Angkatan III');

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Template_Import_Klien.xlsx';
        // File template sementara disimpan di storage/app (bukan app/public)
        // agar tidak pernah bisa diunduh langsung dari web.
        $tempPath = storage_path('app/'.$fileName);
        $writer->save($tempPath);

        return response()->download($tempPath)->deleteFileAfterSend(true);
    }

    public function downloadWordTemplate()
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Template Import Data Klien', ['bold' => true, 'size' => 16]);
        $section->addText('Isi data pada tabel di bawah ini. Baris pertama (header) JANGAN dihapus atau diubah.');

        $tableStyle = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 50];
        $phpWord->addTableStyle('Template Table', $tableStyle);
        $table = $section->addTable('Template Table');

        $headers = [
            'Nama*',
            'Email*',
            'Jenis Kelamin*',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Alamat KTP',
            'Kota KTP',
            'Pendidikan',
            'Pelatihan',
            'Penempatan Wilayah',
            'Status Pekerjaan',
            'Deskripsi',
            'Status Profil',
            'Nama File Foto',
            // Opsional: hanya dipakai untuk deteksi "kemungkinan duplikat"
            // nama + asal daerah + angkatan pada langkah Preview.
            'Asal Daerah',
            'Angkatan',
        ];

        $table->addRow();
        foreach ($headers as $header) {
            $table->addCell(1500)->addText($header, ['bold' => true]);
        }

        $city = MasterCity::first()?->title ?? 'Bandung';
        $edu = MasterEducationDegree::first()?->title ?? 'SMA';
        $training = MasterTraining::first()?->title ?? 'Menjahit';

        $table->addRow();
        $table->addCell(1500)->addText('Siti Aminah');
        $table->addCell(1500)->addText('siti@example.com');
        $table->addCell(1500)->addText('P');
        $table->addCell(1500)->addText('Jakarta');
        $table->addCell(1500)->addText('1998-12-01');
        $table->addCell(1500)->addText('Jl. Sudirman');
        $table->addCell(1500)->addText($city);
        $table->addCell(1500)->addText($edu);
        $table->addCell(1500)->addText($training);
        $table->addCell(1500)->addText($city);
        $table->addCell(1500)->addText('Belum Bekerja');
        $table->addCell(1500)->addText('Teks deskripsi');
        $table->addCell(1500)->addText('Tersedia');
        $table->addCell(1500)->addText('siti.jpg');
        $table->addCell(1500)->addText('Jakarta');
        $table->addCell(1500)->addText('Angkatan III');

        $fileName = 'Template_Import_Klien.docx';
        // File template sementara disimpan di storage/app (bukan app/public)
        // agar tidak pernah bisa diunduh langsung dari web.
        $tempPath = storage_path('app/'.$fileName);
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempPath);

        return response()->download($tempPath)->deleteFileAfterSend(true);
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

            $service = new ImportService;
            $this->previewData = $service->parseAndValidate($filePath, $extension);

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
            $service->importData(
                $this->previewData['valid'],
                $uploadedPhotos,
                auth()->id(),
                $mode === ImportService::MODE_BINAAN ? null : $importPassword,
                $mode,
                $tautkanAnak,
            );

            $totalValid = count($this->previewData['valid']);
            $totalDuplikat = count($this->previewData['duplicates']);
            $totalTautan = $tautkanAnak ? count($this->previewData['tautan']['akan'] ?? []) : 0;
            $labelMode = ImportService::pilihanMode()[$mode] ?? $mode;

            Notification::make()
                ->title('Import Berhasil')
                ->body("{$totalValid} akun dibuat dengan mode {$labelMode}. {$totalDuplikat} data dilewati karena duplikat."
                    .($tautkanAnak ? " {$totalTautan} akun ditautkan ke data anak." : ' Tidak ada penautan ke data anak.'))
                ->success()
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
