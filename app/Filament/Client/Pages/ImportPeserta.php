<?php

namespace App\Filament\Client\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use App\Services\ImportService;
use App\Models\MasterCity;
use App\Models\MasterEducationDegree;
use App\Models\MasterTraining;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportPeserta extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'Import Data';
    protected static ?string $title = 'Import Data Peserta';
    protected static ?string $slug = 'import-peserta';
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.client.pages.import-peserta';

    public $file;
    public $previewData = null;
    public $isProcessing = false;

    public static function canAccess(): bool
    {
        return auth()->user()->user_type === 'client';
    }

    protected function getFormSchema(): array
    {
        return [
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
                        ->directory('imports')
                        ->preserveFilenames(),
                        
                    Forms\Components\FileUpload::make('photos')
                        ->label('Foto Profil (Opsional - Bisa pilih banyak file)')
                        ->helperText('Pastikan nama file foto sama dengan yang Anda tulis di kolom "Nama File Foto" pada Excel/Word (Contoh: budi.jpg)')
                        ->image()
                        ->multiple()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                        ->maxSize(2048)
                        ->directory('profile_pictures')
                        ->preserveFilenames(),
                ]),
        ];
    }

    public function mount()
    {
        $this->form->fill();
    }

    public function downloadExcelTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $headers = [
            'Nama*', 'Email*', 'Jenis Kelamin*', 'Tempat Lahir', 'Tanggal Lahir', 
            'Alamat KTP', 'Kota KTP', 'Pendidikan', 'Pelatihan', 'Penempatan Wilayah', 
            'Status Pekerjaan', 'Deskripsi', 'Status Profil', 'Nama File Foto'
        ];
        
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
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

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Template_Import_Klien.xlsx';
        $tempPath = storage_path('app/public/' . $fileName);
        $writer->save($tempPath);

        return response()->download($tempPath)->deleteFileAfterSend(true);
    }

    public function downloadWordTemplate()
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Template Import Data Klien', ['bold' => true, 'size' => 16]);
        $section->addText('Isi data pada tabel di bawah ini. Baris pertama (header) JANGAN dihapus atau diubah.');

        $tableStyle = array('borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 50);
        $phpWord->addTableStyle('Template Table', $tableStyle);
        $table = $section->addTable('Template Table');

        $headers = [
            'Nama*', 'Email*', 'Jenis Kelamin*', 'Tempat Lahir', 'Tanggal Lahir', 
            'Alamat KTP', 'Kota KTP', 'Pendidikan', 'Pelatihan', 'Penempatan Wilayah', 
            'Status Pekerjaan', 'Deskripsi', 'Status Profil', 'Nama File Foto'
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

        $fileName = 'Template_Import_Klien.docx';
        $tempPath = storage_path('app/public/' . $fileName);
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
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
            $filePath = storage_path('app/public/' . $data['file']);
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            
            $service = new ImportService();
            $this->previewData = $service->parseAndValidate($filePath, $extension);
            
            Notification::make()->title('File berhasil diproses untuk preview.')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Gagal memproses file: ' . $e->getMessage())->danger()->send();
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

            $service = new ImportService();
            $service->importData($this->previewData['valid'], $uploadedPhotos);
            
            $totalValid = count($this->previewData['valid']);
            $totalDuplikat = count($this->previewData['duplicates']);
            
            Notification::make()
                ->title('Import Berhasil')
                ->body("{$totalValid} data berhasil ditambahkan. {$totalDuplikat} data dilewati karena duplikat.")
                ->success()
                ->send();

            // Reset form
            $this->form->fill();
            $this->previewData = null;
            
        } catch (\Exception $e) {
            Notification::make()->title('Terjadi kesalahan saat menyimpan data: ' . $e->getMessage())->danger()->send();
        }
    }
}
