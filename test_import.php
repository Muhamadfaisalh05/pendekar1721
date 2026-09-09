<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$page = new \App\Filament\Pages\ImportData();
$page->downloadExcelTemplate();
echo "Excel created.\n";

$page->downloadWordTemplate();
echo "Word created.\n";

// test parsing
$svc = new \App\Services\ImportService();
$res1 = $svc->parseAndValidate(storage_path('app/public/Template_Import_Klien.xlsx'), 'xlsx');
echo "Excel valid rows: " . count($res1['valid']) . "\n";
echo "Excel error rows: " . count($res1['errors']) . "\n";

$res2 = $svc->parseAndValidate(storage_path('app/public/Template_Import_Klien.docx'), 'docx');
echo "Word valid rows: " . count($res2['valid']) . "\n";
echo "Word error rows: " . count($res2['errors']) . "\n";
