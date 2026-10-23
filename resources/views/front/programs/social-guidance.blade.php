@extends('layouts.app')

@section('title', 'Program Bimbingan Sosial')

@section('content')
    <section class="program-detail-section">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="profile-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Beranda</a>
                <span>/</span>
                <a href="{{ route('home') }}#program">Program &amp; Pelatihan</a>
                <span>/</span>
                <span>Bimbingan Sosial</span>
            </nav>

            <header class="program-detail-hero">
                <span class="section-kicker">Program &amp; Pelatihan</span>
                <h1>Bimbingan Sosial</h1>
                <p>
                    Bimbingan sosial mendampingi peserta mengembangkan kemampuan berinteraksi, membangun kemandirian,
                    dan menyiapkan diri untuk berperan aktif di lingkungan masyarakat.
                </p>
            </header>

            <div class="program-detail-grid">
                <article class="program-detail-card">
                    <span class="section-kicker">Tentang Program</span>
                    <h2>Tentang Bimbingan Sosial</h2>
                    <p>
                        Kegiatan bimbingan sosial dapat mencakup komunikasi, kerja sama, pengenalan diri, pemecahan
                        masalah sehari-hari, disiplin, dan latihan membangun hubungan yang saling menghargai.
                        Materi dan metode disesuaikan dengan kebutuhan peserta.
                    </p>
                    <p>
                        Pendampingan dilakukan secara bertahap agar peserta dapat mengenali potensi dirinya,
                        meningkatkan rasa percaya diri, serta menerapkan keterampilan sosial dalam kehidupan
                        sehari-hari dan persiapan memasuki dunia kerja.
                    </p>
                </article>

                <article class="program-detail-card">
                    <span class="section-kicker">Perkembangan Pendampingan Sosial</span>
                    <h2>Sejarah Bimbingan Sosial</h2>
                    <ol class="sewing-history">
                        <li>
                            <h3>Berawal dari dukungan komunitas</h3>
                            <p>
                                Dukungan sosial telah lama tumbuh melalui keluarga dan masyarakat untuk membantu
                                anggotanya menghadapi tantangan serta berpartisipasi dalam kehidupan bersama.
                            </p>
                        </li>
                        <li>
                            <h3>Berkembang menjadi pendampingan terarah</h3>
                            <p>
                                Layanan sosial kemudian mengembangkan pendekatan pendampingan yang lebih terencana,
                                dengan kegiatan yang membantu seseorang membangun kemampuan, kemandirian, dan relasi
                                sosial yang sehat.
                            </p>
                        </li>
                        <li>
                            <h3>Berfokus pada partisipasi dan kemandirian</h3>
                            <p>
                                Bimbingan sosial masa kini mendorong peserta mengenali potensi, mengakses dukungan yang
                                sesuai, dan berperan aktif di lingkungan sosialnya.
                            </p>
                        </li>
                    </ol>
                    <p class="program-history-note">
                        Sejarah di atas merupakan gambaran umum perkembangan pendampingan sosial, bukan catatan khusus
                        tentang tahun atau sejarah penyelenggaraan program di lembaga ini.
                    </p>
                </article>
            </div>

            <a href="{{ route('home') }}#program" class="button button-primary program-back-link">
                Kembali ke Program &amp; Pelatihan
            </a>
        </div>
    </section>
@endsection
