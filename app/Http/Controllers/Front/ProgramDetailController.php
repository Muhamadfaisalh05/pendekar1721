<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\MasterTraining;
use Illuminate\View\View;

class ProgramDetailController extends Controller
{
    private const PROGRAMS = [
        'montir-motor' => [
            'title' => 'Montir Motor',
            'description' => 'Peserta berlatih merawat dan memperbaiki sepeda motor secara langsung, mulai dari mengenali komponen hingga melakukan pemeriksaan dasar dengan aman.',
            'about_heading' => 'Tentang Montir Motor',
            'image' => 'images/motorcycle-workshop.png',
            'image_alt' => 'Peserta berlatih memeriksa dan memperbaiki sepeda motor di ruang praktik bengkel',
            'about' => [
                'Dalam praktik bengkel, peserta mengenal komponen utama sepeda motor, menggunakan peralatan kerja, dan menerapkan prosedur keselamatan saat memeriksa kendaraan.',
                'Latihan dapat mencakup pemeriksaan mesin, rem, roda, dan kelistrikan serta perawatan berkala dan penanganan gangguan dasar sesuai arahan instruktur.',
            ],
            'history_heading' => 'Sejarah Montir Motor',
            'history_kicker' => 'Perkembangan Perawatan Sepeda Motor',
            'history' => [
                [
                    'title' => 'Sepeda motor mulai digunakan',
                    'description' => 'Berkembangnya sepeda motor sebagai alat transportasi mendorong kebutuhan untuk merawat mesin dan komponennya.',
                ],
                [
                    'title' => 'Bengkel dan keterampilan mekanik berkembang',
                    'description' => 'Teknisi mempelajari sistem mesin, bahan bakar, rem, dan kelistrikan untuk menjaga kendaraan tetap berfungsi.',
                ],
                [
                    'title' => 'Teknologi motor semakin beragam',
                    'description' => 'Sepeda motor modern memakai teknologi dan sistem kendali yang terus berkembang, sehingga mekanik perlu belajar mendiagnosis dan merawatnya.',
                ],
            ],
            'note' => 'Sejarah ini merupakan gambaran umum perkembangan keterampilan mekanik sepeda motor, bukan catatan khusus tentang penyelenggaraan pelatihan di lembaga ini.',
        ],
        'montir-mobil' => [
            'title' => 'Montir Mobil',
            'description' => 'Pelatihan dasar perawatan dan perbaikan mobil, dengan perhatian pada keselamatan kerja, pemeriksaan komponen, dan diagnosis masalah kendaraan.',
            'about_heading' => 'Tentang Montir Mobil',
            'about' => [
                'Peserta mempelajari fungsi komponen kendaraan, penggunaan peralatan bengkel, prosedur keselamatan, dan langkah pemeriksaan mobil.',
                'Materi dapat meliputi perawatan mesin, sistem rem, roda, kelistrikan, serta pengenalan diagnosis masalah sesuai prosedur dan tingkat keterampilan peserta.',
            ],
            'history_heading' => 'Sejarah Montir Mobil',
            'history_kicker' => 'Perkembangan Perawatan Mobil',
            'history' => [
                [
                    'title' => 'Mobil mendorong kebutuhan perawatan',
                    'description' => 'Seiring mobil digunakan untuk perjalanan dan kegiatan sehari-hari, diperlukan orang yang mampu menjaga kendaraan tetap aman dan berfungsi.',
                ],
                [
                    'title' => 'Keahlian bengkel semakin terspesialisasi',
                    'description' => 'Perkembangan mesin dan komponen kendaraan membentuk berbagai bidang pekerjaan mekanik, dari pemeriksaan rutin hingga perbaikan sistem tertentu.',
                ],
                [
                    'title' => 'Diagnosis mengikuti teknologi kendaraan',
                    'description' => 'Mobil modern memiliki sistem elektronik dan kendali yang lebih kompleks, sehingga teknisi memadukan keterampilan mekanik dengan alat diagnosis.',
                ],
            ],
            'note' => 'Sejarah ini merupakan gambaran umum perkembangan keterampilan mekanik mobil, bukan catatan khusus tentang penyelenggaraan pelatihan di lembaga ini.',
        ],
        'tata-boga' => [
            'title' => 'Tata Boga',
            'description' => 'Peserta berlatih menyiapkan bahan makanan dan bekerja sama mengolah hidangan dalam kegiatan praktik tata boga.',
            'about_heading' => 'Tentang Tata Boga',
            'image' => 'images/culinary-workshop.png',
            'image_alt' => 'Peserta praktik tata boga menyiapkan dan menimbang bahan makanan bersama',
            'about' => [
                'Dalam kegiatan praktik, peserta menyiapkan dan menimbang bahan, mengenal peralatan dapur, serta belajar bekerja sama mengikuti tahapan pengolahan makanan.',
                'Peserta juga mempelajari kebersihan dan keamanan pangan, teknik memasak dasar, serta penyajian hidangan. Keterampilan ini dapat dikembangkan untuk peluang kerja maupun usaha kuliner.',
            ],
            'history_heading' => 'Sejarah Tata Boga',
            'history_kicker' => 'Perkembangan Seni Mengolah Makanan',
            'history' => [
                [
                    'title' => 'Mengolah makanan untuk kebutuhan sehari-hari',
                    'description' => 'Manusia mengembangkan cara menyiapkan dan memasak bahan pangan untuk memenuhi kebutuhan keluarga dan komunitas.',
                ],
                [
                    'title' => 'Teknik memasak dan tradisi kuliner berkembang',
                    'description' => 'Bahan lokal, kebiasaan, dan pertukaran budaya menghasilkan beragam teknik, hidangan, serta tradisi kuliner di berbagai daerah.',
                ],
                [
                    'title' => 'Tata boga menjadi keterampilan profesional',
                    'description' => 'Pengetahuan tentang pengolahan, kebersihan, penyajian, dan pengelolaan dapur kini digunakan dalam layanan makanan dan industri kuliner.',
                ],
            ],
            'note' => 'Sejarah ini merupakan gambaran umum perkembangan tata boga, bukan catatan khusus tentang penyelenggaraan pelatihan di lembaga ini.',
        ],
    ];

    public function __invoke(string $program): View
    {
        abort_unless(isset(self::PROGRAMS[$program]), 404);

        $programData = self::PROGRAMS[$program];

        $training = MasterTraining::query()
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($programData['title'])])
            ->first();

        return view('front.programs.detail', [
            'program' => $programData,
            'training' => $training,
        ]);
    }
}
