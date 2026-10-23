@extends('layouts.app')

@section('title', 'Program Menjahit')

@section('content')
    <section class="program-detail-section">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="profile-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Beranda</a>
                <span>/</span>
                <a href="{{ route('home') }}#program">Program &amp; Pelatihan</a>
                <span>/</span>
                <span>Menjahit</span>
            </nav>

            <header class="program-detail-hero">
                <span class="section-kicker">Program &amp; Pelatihan</span>
                <h1>Menjahit</h1>
                <p>
                    Di ruang praktik, peserta berlatih menggunakan mesin jahit dan mengolah kain secara bertahap
                    untuk menghasilkan jahitan yang rapi.
                </p>
            </header>

            <div class="program-detail-grid">
                <article class="program-detail-card">
                    <span class="section-kicker">Tentang Program</span>
                    <h2>Tentang Menjahit</h2>
                    <figure class="program-detail-figure">
                        <img src="{{ asset('images/sewing-workshop.png') }}"
                            alt="Peserta berlatih menjahit menggunakan mesin di ruang praktik" loading="lazy" />
                        <figcaption>Praktik menjahit menggunakan mesin di ruang pelatihan.</figcaption>
                    </figure>
                    <p>
                        Peserta belajar mengenal dan menggunakan mesin jahit dengan aman, menyiapkan kain, mengukur
                        bahan, memahami pola sederhana, lalu berlatih menyatukan potongan kain dan merapikan hasilnya.
                    </p>
                    <p>
                        Praktik ini melatih ketelitian, kesabaran, dan keterampilan kerja. Kemampuan menjahit dapat
                        digunakan untuk membuat atau memperbaiki pakaian, serta dikembangkan menjadi peluang kerja
                        maupun usaha.
                    </p>
                </article>

                <article class="program-detail-card">
                    <span class="section-kicker">Perkembangan Keterampilan</span>
                    <h2>Sejarah Menjahit</h2>
                    <ol class="sewing-history">
                        <li>
                            <h3>Berawal dari kebutuhan dasar</h3>
                            <p>
                                Menjahit berkembang sebagai cara menyambung dan memperbaiki bahan pakaian agar dapat
                                digunakan. Keterampilan ini diwariskan dan dipraktikkan dalam kehidupan sehari-hari.
                            </p>
                        </li>
                        <li>
                            <h3>Alat dan teknik semakin berkembang</h3>
                            <p>
                                Perkembangan alat jahit, termasuk mesin jahit, membantu pekerjaan dilakukan lebih
                                cepat dan konsisten. Pola serta teknik potong-jahit pun semakin beragam.
                            </p>
                        </li>
                        <li>
                            <h3>Menjadi keterampilan kerja</h3>
                            <p>
                                Saat ini, keterampilan menjahit dipelajari untuk membuat dan memperbaiki pakaian,
                                menghasilkan produk tekstil, serta mendukung peluang kerja dan usaha mandiri.
                            </p>
                        </li>
                    </ol>
                    <p class="program-history-note">
                        Sejarah di atas merupakan gambaran umum perkembangan keterampilan menjahit, bukan catatan
                        khusus tentang tahun atau sejarah penyelenggaraan pelatihan di lembaga ini.
                    </p>
                </article>
            </div>

            <a href="{{ route('home') }}#program" class="button button-primary program-back-link">
                Kembali ke Program &amp; Pelatihan
            </a>
        </div>
    </section>
@endsection
