<?php

namespace App\Services;

use App\Models\MasterCity;
use App\Models\MasterEducationDegree;
use App\Models\MasterTraining;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserExperience;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

class ImportService
{
    protected $cities;
    protected $educations;
    protected $trainings;

    public function __construct()
    {
        $this->cities = MasterCity::pluck('id', 'title')->mapWithKeys(function ($item, $key) {
            return [strtolower(trim($key)) => $item];
        })->toArray();

        $this->educations = MasterEducationDegree::pluck('id', 'title')->mapWithKeys(function ($item, $key) {
            return [strtolower(trim($key)) => $item];
        })->toArray();

        $this->trainings = MasterTraining::pluck('id', 'title')->mapWithKeys(function ($item, $key) {
            return [strtolower(trim($key)) => $item];
        })->toArray();
    }

    public function parseAndValidate($filePath, $extension)
    {
        $rows = [];
        if (in_array($extension, ['xlsx', 'xls', 'csv'])) {
            $rows = $this->parseExcel($filePath);
        } elseif ($extension === 'docx') {
            $rows = $this->parseWord($filePath);
        } else {
            throw new \Exception("Format file tidak didukung: {$extension}");
        }

        return $this->validateRows($rows);
    }

    protected function parseExcel($filePath)
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = [];
        $header = [];

        foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getCalculatedValue();
            }

            if ($rowIndex === 1) {
                // Read header and sanitize
                $header = array_map(function($h) {
                    return strtolower(trim(str_replace('*', '', $h)));
                }, $rowData);
            } else {
                // Map to header
                $mapped = [];
                foreach ($header as $index => $colName) {
                    if ($colName) {
                        $mapped[$colName] = $rowData[$index] ?? null;
                    }
                }
                if (count(array_filter($mapped)) > 0) { // skip empty rows
                    $mapped['_row_number'] = $rowIndex;
                    $rows[] = $mapped;
                }
            }
        }
        return $rows;
    }

    protected function parseWord($filePath)
    {
        $phpWord = WordIOFactory::load($filePath);
        $rows = [];
        $header = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                    $rowIndex = 1;
                    foreach ($element->getRows() as $row) {
                        $rowData = [];
                        foreach ($row->getCells() as $cell) {
                            $text = '';
                            foreach ($cell->getElements() as $cellElement) {
                                if ($cellElement instanceof \PhpOffice\PhpWord\Element\TextRun) {
                                    foreach ($cellElement->getElements() as $textElement) {
                                        if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                            $text .= $textElement->getText();
                                        }
                                    }
                                }
                            }
                            $rowData[] = trim($text);
                        }

                        if ($rowIndex === 1) {
                            $header = array_map(function($h) {
                                return strtolower(trim(str_replace('*', '', $h)));
                            }, $rowData);
                        } else {
                            $mapped = [];
                            foreach ($header as $index => $colName) {
                                if ($colName) {
                                    $mapped[$colName] = $rowData[$index] ?? null;
                                }
                            }
                            if (count(array_filter($mapped)) > 0) {
                                $mapped['_row_number'] = $rowIndex;
                                $rows[] = $mapped;
                            }
                        }
                        $rowIndex++;
                    }
                    break; // Only read the first table found
                }
            }
        }
        return $rows;
    }

    protected function validateRows(array $rows)
    {
        $results = [
            'valid' => [],
            'errors' => [],
            'duplicates' => [],
            'total' => count($rows)
        ];

        // Fetch all existing emails to fast-check duplicates
        $existingEmails = User::pluck('email')->map(fn($e) => strtolower($e))->toArray();

        foreach ($rows as $row) {
            $rowNum = $row['_row_number'];
            $errors = [];
            $parsedData = [];

            // 1. Nama (Required)
            $nama = $row['nama'] ?? null;
            if (empty($nama)) {
                $errors[] = ['col' => 'Nama', 'val' => '', 'msg' => 'Nama wajib diisi'];
            } else {
                $parsedData['name'] = $nama;
            }

            // 2. Email (Required & Unique)
            $email = $row['email'] ?? null;
            if (empty($email)) {
                $errors[] = ['col' => 'Email', 'val' => '', 'msg' => 'Email wajib diisi'];
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['col' => 'Email', 'val' => $email, 'msg' => 'Format email tidak valid'];
            } else {
                if (in_array(strtolower($email), $existingEmails)) {
                    $results['duplicates'][] = [
                        'row' => $rowNum,
                        'name' => $nama,
                        'email' => $email
                    ];
                    continue; // Skip further validation for duplicates
                }
                $parsedData['email'] = $email;
            }

            // 3. Jenis Kelamin (L/P)
            $jk = strtoupper(trim($row['jenis kelamin'] ?? ''));
            if (!empty($jk) && !in_array($jk, ['L', 'P'])) {
                $errors[] = ['col' => 'Jenis Kelamin', 'val' => $jk, 'msg' => 'Harus L atau P'];
            }
            $parsedData['gender'] = in_array($jk, ['L', 'P']) ? $jk : null;

            // 4. Tempat Lahir
            $parsedData['birth_place'] = $row['tempat lahir'] ?? null;

            // 5. Tanggal Lahir
            $tgl = $row['tanggal lahir'] ?? null;
            if (!empty($tgl)) {
                if (is_numeric($tgl)) {
                    // Excel serialized date
                    $unixDate = ($tgl - 25569) * 86400;
                    $parsedData['birth_date'] = gmdate("Y-m-d", $unixDate);
                } else {
                    $parsedData['birth_date'] = date('Y-m-d', strtotime($tgl));
                }
            }

            // 6. Alamat KTP
            $parsedData['ktp_address'] = $row['alamat ktp'] ?? null;

            // 7. Kota KTP
            $kotaKtp = trim($row['kota ktp'] ?? '');
            if (!empty($kotaKtp)) {
                $kotaKey = strtolower($kotaKtp);
                if (isset($this->cities[$kotaKey])) {
                    $parsedData['ktp_city_id'] = $this->cities[$kotaKey];
                } else {
                    $errors[] = ['col' => 'Kota KTP', 'val' => $kotaKtp, 'msg' => 'Data kota tidak ditemukan di sistem'];
                }
            }

            // 8. Pendidikan
            $pendidikan = trim($row['pendidikan'] ?? '');
            if (!empty($pendidikan)) {
                $pendidikanKey = strtolower($pendidikan);
                if (isset($this->educations[$pendidikanKey])) {
                    $parsedData['education_degree_id'] = $this->educations[$pendidikanKey];
                } else {
                    $errors[] = ['col' => 'Pendidikan', 'val' => $pendidikan, 'msg' => 'Data pendidikan tidak ditemukan di sistem'];
                }
            }

            // 9. Pelatihan (Multiple)
            $parsedData['trainings'] = [];
            $pelatihan = trim($row['pelatihan'] ?? '');
            if (!empty($pelatihan)) {
                $items = array_map('trim', explode(',', $pelatihan));
                foreach ($items as $item) {
                    $itemKey = strtolower($item);
                    if (isset($this->trainings[$itemKey])) {
                        $parsedData['trainings'][] = $this->trainings[$itemKey];
                    } else {
                        $errors[] = ['col' => 'Pelatihan', 'val' => $item, 'msg' => 'Pelatihan tidak ditemukan di sistem'];
                    }
                }
            }

            // 10. Penempatan Wilayah (Multiple)
            $parsedData['work_locations'] = [];
            $wilayah = trim($row['penempatan wilayah'] ?? '');
            if (!empty($wilayah)) {
                $items = array_map('trim', explode(',', $wilayah));
                foreach ($items as $item) {
                    $itemKey = strtolower($item);
                    if (isset($this->cities[$itemKey])) {
                        $parsedData['work_locations'][] = $this->cities[$itemKey];
                    } else {
                        $errors[] = ['col' => 'Penempatan Wilayah', 'val' => $item, 'msg' => 'Wilayah tidak ditemukan di sistem'];
                    }
                }
            }

            // 11. Status Pekerjaan
            $statusKerja = trim(strtolower($row['status pekerjaan'] ?? ''));
            $parsedData['is_current_job'] = ($statusKerja === 'sedang bekerja' || $statusKerja === '1');

            // 12. Deskripsi
            $parsedData['description'] = $row['deskripsi'] ?? null;

            // 13. Status Profil
            $statusProfil = trim(strtolower($row['status profil'] ?? ''));
            $parsedData['hire_status'] = ($statusProfil === 'tersedia' || $statusProfil === '1') ? '1' : '0';

            // 14. Nama File Foto
            $parsedData['photo_filename'] = trim($row['nama file foto'] ?? '');

            // Compile errors or valid data
            if (count($errors) > 0) {
                foreach ($errors as $err) {
                    $results['errors'][] = [
                        'row' => $rowNum,
                        'name' => $nama,
                        'col' => $err['col'],
                        'val' => $err['val'],
                        'msg' => $err['msg']
                    ];
                }
            } else {
                $parsedData['_row_number'] = $rowNum;
                $results['valid'][] = $parsedData;
            }
        }

        return $results;
    }

    public function importData(array $validData, array $uploadedPhotos = [])
    {
        DB::beginTransaction();
        try {
            foreach ($validData as $data) {
                // 1. Create User
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make('password123'),
                    'user_type' => 'client',
                    'status' => 'published',
                ]);

                // Match Photo
                $photoPath = null;
                if (!empty($data['photo_filename']) && count($uploadedPhotos) > 0) {
                    $targetFilename = strtolower(trim($data['photo_filename']));
                    foreach ($uploadedPhotos as $uploadedPhoto) {
                        // $uploadedPhoto is usually a path like "profile_pictures/budi.jpg"
                        $uploadedBasename = strtolower(basename($uploadedPhoto));
                        if ($uploadedBasename === $targetFilename) {
                            $photoPath = $uploadedPhoto;
                            break;
                        }
                    }
                }

                // 2. Create UserProfile
                UserProfile::create([
                    'user_id' => $user->id,
                    'gender' => $data['gender'] ?? null,
                    'birth_place' => $data['birth_place'] ?? null,
                    'birth_date' => $data['birth_date'] ?? null,
                    'ktp_address' => $data['ktp_address'] ?? null,
                    'ktp_city_id' => $data['ktp_city_id'] ?? null,
                    'education_degree_id' => $data['education_degree_id'] ?? null,
                    'description' => $data['description'] ?? null,
                    'hire_status' => $data['hire_status'] ?? '0',
                    'profile_picture_path' => $photoPath,
                ]);

                // 3. Attach Trainings
                if (!empty($data['trainings'])) {
                    $user->userTrainings()->attach($data['trainings']);
                }

                // 4. Attach Work Locations
                if (!empty($data['work_locations'])) {
                    $user->userWorkLocations()->attach($data['work_locations']);
                }

                // 5. Create current job experience if applicable
                if (!empty($data['is_current_job']) && $data['is_current_job'] === true) {
                    UserExperience::create([
                        'user_id' => $user->id,
                        'experience_job_name' => 'Bekerja Saat Ini',
                        'experience_description' => 'Di-import dari sistem',
                        'is_current_job' => true,
                        'sort_order' => 1
                    ]);
                }
            }
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Import Error: " . $e->getMessage());
            throw $e;
        }
    }
}
