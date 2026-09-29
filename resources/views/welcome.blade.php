@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
    <section class="hero-section" id="top">
        <video class="hero-video" autoplay muted loop playsinline
            poster="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1600&q=80">
            <source src="https://videos.pexels.com/video-files/3195394/3195394-hd_1920_1080.mp4" type="video/mp4" />
        </video>
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

    <section class="quick-access-section" id="informasi">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="quick-grid">
                <a href="#about" class="quick-card">
                    <span class="quick-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 8a8 8 0 0 1 16 0" />
                        </svg>
                    </span>
                    <span class="quick-label">Profil</span>
                </a>
                <a href="#program" class="quick-card">
                    <span class="quick-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path
                                d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v9A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-9Zm4 0h8M8 12h8M8 15h5" />
                        </svg>
                    </span>
                    <span class="quick-label">Program</span>
                </a>
                <a href="#data" class="quick-card">
                    <span class="quick-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 18V6m6 12V9m6 9v-6m4 6V4" />
                        </svg>
                    </span>
                    <span class="quick-label">Data</span>
                </a>
                <a href="#statistik" class="quick-card">
                    <span class="quick-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M5 18h14M7 15l3-4 3 2 5-7" />
                        </svg>
                    </span>
                    <span class="quick-label">Statistik</span>
                </a>
                <a href="#informasi" class="quick-card">
                    <span class="quick-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M12 7.5h.01M12 12h.01M12 16.5h.01M4.5 12a7.5 7.5 0 1 1 15 0 7.5 7.5 0 0 1-15 0Z" />
                        </svg>
                    </span>
                    <span class="quick-label">Informasi</span>
                </a>
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
                @forelse($trainings->take(6) as $training)
                    <article class="program-card">
                        <div class="program-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <h4>{{ $training->title }}</h4>
                        <p>{{ $training->description ?? 'Program pembinaan dan penguatan kapasitas yang relevan untuk kebutuhan masyarakat.' }}
                        </p>
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
                    <h3>Siap melihat profil serta data PENDEKAR1721 secara komprehensif?</h3>
                </div>
                <div class="cta-actions">
                    <a href="#data" class="button button-light">Lihat Data</a>
                    <a href="{{ route('filament.client.auth.login') }}" class="button button-primary">Masuk Klien</a>
                </div>
            </div>
        </div>
    </section>
@endsection