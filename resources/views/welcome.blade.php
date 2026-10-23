@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
    <section class="hero-section" id="top">
        <img class="hero-video" src="{{ asset('images/hero-ppsgbr.png') }}" alt="" aria-hidden="true" />
        <div class="hero-overlay"></div>

        <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="hero-inner">
                <div class="hero-copy">
                    <span class="eyebrow">Institutional Profile</span>
                    <h1>PENDEKAR1721</h1>
                    <h2>UPTD Pusat Pelayanan Sosial Griya Bina Remaja</h2>
                    <p>
                        Direktori profil tenaga kerja terampil yang telah mendapatkan pelatihan keterampilan dari UPTD
                        PPSGBR
                        Dinas Sosial Pemerintah Provinsi Jawa Barat, dengan fokus pada peningkatan kualitas sumber daya
                        manusia,
                        penempatan kerja, dan penguatan kapasitas komunitas.
                    </p>
                    <div class="hero-actions">
                        <a href="#about" class="button button-light">Lihat Profil</a>
                        <a href="#data" class="button button-primary">Jelajahi Data</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="about-section" id="about">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="about-grid">
                <div class="about-media">
                    <img src="{{ asset('images/ppsgbr.png') }}" alt="PENDEKAR1721" />
                </div>
                <div class="about-copy">
                    <span class="section-kicker">Tentang Kami</span>
                    <h3>PENDEKAR1721 hadir sebagai pusat pelayanan, pembinaan, dan pengembangan sumber daya manusia.</h3>
                    <p>
                        PENDEKAR1721 merupakan wadah penguatan kapasitas tenaga kerja terampil berbasis pelatihan,
                        pendampingan,
                        dan penempatan kerja yang berorientasi pada kesejahteraan sosial dan pemberdayaan masyarakat.
                    </p>
                    <p>
                        Kami mengelola data profil, pelatihan, serta program yang relevan untuk mendukung akses kerja,
                        pengetahuan,
                        dan kesempatan yang lebih luas bagi masyarakat yang membutuhkan.
                    </p>
                    <div class="about-points">
                        <div>
                            <strong>1</strong>
                            <span>Pelatihan dan pembinaan</span>
                        </div>
                        <div>
                            <strong>2</strong>
                            <span>Data dan profil terstruktur</span>
                        </div>
                        <div>
                            <strong>3</strong>
                            <span>Penguatan kapasitas sosial</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="stats-section" id="statistik">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="section-heading">
                <span class="section-kicker">Statistik</span>
                <h3>Data yang menunjukkan komitmen kami</h3>
            </div>
            <div class="stats-grid">
                <article class="stat-card">
                    <div class="stat-number">{{ $totalClients }}</div>
                    <div class="stat-label">Total Klien</div>
                </article>
                <article class="stat-card">
                    <div class="stat-number">{{ $totalClientsWorking }}</div>
                    <div class="stat-label">Sedang Bekerja</div>
                </article>
                <article class="stat-card">
                    <div class="stat-number">{{ $totalTrainings }}</div>
                    <div class="stat-label">Program & Pelatihan</div>
                </article>
            </div>
        </div>
    </section>

    <section class="program-section" id="program">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="section-heading align-left">
                <span class="section-kicker">Program & Pelatihan</span>
                <h3>Pelayanan yang terus berkembang</h3>
            </div>

            <div class="program-grid">
                @forelse($trainings->reject(fn ($training) => \Illuminate\Support\Str::lower($training->title ?? '') === 'montir mobil')->take(6) as $training)
                    @php
                        $isSewingProgram = \Illuminate\Support\Str::lower($training->title ?? '') === 'menjahit';
                        $isSocialGuidanceProgram = \Illuminate\Support\Str::lower($training->title ?? '') === 'bimbingan sosial';
                        $programSlug = match (\Illuminate\Support\Str::lower($training->title ?? '')) {
                            'montir motor' => 'montir-motor',
                            'tata boga' => 'tata-boga',
                            default => null,
                        };
                        $isDetailProgram = $isSewingProgram || $isSocialGuidanceProgram || $programSlug !== null;
                    @endphp
                    <article class="program-card">
                        @if($isDetailProgram)
                            @php
                                $programUrl = match (true) {
                                    $isSewingProgram => route('front.program.sewing'),
                                    $isSocialGuidanceProgram => route('front.program.social-guidance'),
                                    default => route('front.program.detail', ['program' => $programSlug]),
                                };
                                $programIllustration = match (true) {
                                    $isSewingProgram => '/images/sewing-workshop.png',
                                    $isSocialGuidanceProgram => '/images/bimbingan-sosial.png',
                                    $programSlug === 'montir-motor' => '/images/motorcycle-workshop.png',
                                    $programSlug === 'tata-boga' => '/images/culinary-workshop.png',
                                    default => '/images/tata-boga.svg',
                                };
                                $programIllustrationAlt = match (true) {
                                    $isSewingProgram => 'Peserta berlatih menjahit menggunakan mesin di ruang praktik',
                                    $isSocialGuidanceProgram => 'Peserta mengikuti kegiatan Bimbingan Sosial di dalam ruangan',
                                    $programSlug === 'montir-motor' => 'Peserta berlatih memeriksa dan memperbaiki sepeda motor di ruang praktik bengkel',
                                    $programSlug === 'tata-boga' => 'Peserta praktik tata boga menyiapkan dan menimbang bahan makanan bersama',
                                    default => 'Ilustrasi program pelatihan',
                                };
                            @endphp
                            <a class="program-image-link" href="{{ $programUrl }}"
                                aria-label="Lihat detail program {{ $training->title }}">
                                <img class="program-image" src="{{ $programIllustration }}"
                                    alt="{{ $programIllustrationAlt }}" loading="lazy" />
                                <span class="program-image-action">
                                    Lihat program
                                    <span aria-hidden="true">&rarr;</span>
                                </span>
                            </a>
                        @else
                            <div class="program-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                        @endif
                        <h4>
                            @if($isSewingProgram || $isSocialGuidanceProgram)
                                <a href="{{ $programUrl }}">{{ $training->title }}</a>
                            @else
                                {{ $training->title }}
                            @endif
                        </h4>
                        @if($isSewingProgram)
                            <p>Peserta berlatih menggunakan mesin jahit, mengolah kain, dan merapikan hasil jahitan melalui praktik langsung.</p>
                        @elseif($isSocialGuidanceProgram)
                            <p>Pendampingan untuk mengembangkan kemandirian, kepercayaan diri, dan keterampilan sosial peserta.</p>
                        @elseif($programSlug === 'montir-motor')
                            <p>Peserta berlatih mengenali komponen, memeriksa kondisi motor, dan melakukan perawatan dasar di ruang praktik bengkel.</p>
                        @elseif($programSlug === 'tata-boga')
                            <p>Peserta praktik menyiapkan bahan, menimbang takaran, dan bekerja sama mengolah makanan dengan menjaga kebersihan pangan.</p>
                        @else
                            <p>{{ $training->description ?? 'Program pembinaan dan penguatan kapasitas yang relevan untuk kebutuhan masyarakat.' }}</p>
                        @endif
                        @if($isDetailProgram)
                            <a class="program-detail-link" href="{{ $programUrl }}">
                                Pelajari tentang {{ $training->title }}
                                <span aria-hidden="true">&rarr;</span>
                            </a>
                        @endif
                    </article>
                @empty
                    <div class="empty-box">Belum ada program yang tersedia.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="clients-section" id="data">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="section-heading align-left">
                <span class="section-kicker">Data Klien</span>
                <h3>Profil profesional yang siap dibangun</h3>
            </div>

            <form class="client-search" method="GET" action="{{ route('home') }}#data" role="search">
                <label for="student-search">Cari nama siswa</label>
                <div class="client-search-controls">
                    <input id="student-search" type="search" name="search" value="{{ $search }}"
                        placeholder="Masukkan nama siswa" autocomplete="off" />
                    <button class="button button-primary" type="submit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="10.8" cy="10.8" r="6.8" />
                            <path d="m16 16 4.5 4.5" />
                        </svg>
                        <span>Cari</span>
                    </button>
                    @if($search !== '')
                        <a class="client-search-clear" href="{{ route('home') }}#data">Hapus</a>
                    @endif
                </div>
            </form>

            @if($users->count() > 0)
                <div class="client-grid">
                    @foreach($users as $user)
                        @php
                            $slug = sprintf('%s-%s', \Illuminate\Support\Str::slug($user->name), $user->id);
                            $profileUrl = route('front.user.detail', ['slug' => $slug]);
                            $profilePicture = $user->userProfile?->profile_picture_path;
                            $statusLabel = $user->has_current_employment ? 'Bekerja' : 'Belum bekerja';
                            $statusClass = $user->has_current_employment ? 'status active' : 'status';
                        @endphp

                        <article class="client-card">
                            <div class="client-image-wrap">
                                @if($profilePicture)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($profilePicture) }}"
                                        alt="{{ $user->name }}" />
                                @else
                                    <div class="client-fallback">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">
                                            <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 8a8 8 0 0 1 16 0" />
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            <div class="client-body">
                                <div class="client-head">
                                    <h4>{{ $user->name }}</h4>
                                    <span class="{{ $statusClass }}">{{ $statusLabel }}</span>
                                </div>
                                <p>
                                    {{ $user->userProfile?->description ? \Illuminate\Support\Str::limit($user->userProfile->description, 110) : 'Profil tenaga kerja terampil yang siap mengikuti kebutuhan program dan penempatan kerja.' }}
                                </p>
                                <a href="{{ $profileUrl }}" class="inline-link">Lihat Profil</a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="pagination-wrap">
                    {{ $users->links() }}
                </div>
            @else
                <div class="empty-box">
                    {{ $search !== '' ? 'Siswa dengan nama tersebut tidak ditemukan.' : 'Belum ada data klien yang tersedia.' }}
                </div>
            @endif
        </div>
    </section>

    <section class="cta-section">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="cta-box">
                <div>
                    <span class="section-kicker light">Jelajahi lebih lanjut</span>
                    <h3>Siap melihat profil serta data PENDEKAR1721 secara komprehensif?</h3><br/>
                </div>
                <div class="cta-actions">
                    <a href="#data" class="button button-light">Lihat Data</a>
                </div>
            </div>
        </div>
    </section>
@endsection