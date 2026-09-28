@extends('layouts.app')

@section('title', $user->name)

    @section('content')
        <div class="profile-page-shell">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">
                <nav class="profile-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('home') }}">Beranda</a>
                    <span>/</span>
                    <span>{{ $user->name }}</span>
                </nav>

                <section class="profile-hero">
                    <div class="profile-photo-wrap">
                        @if($user->userProfile->profile_picture_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($user->userProfile->profile_picture_path) }}"
                                alt="Foto profil {{ $user->name }}" />
                        @else
                            <div class="profile-placeholder">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">
                                    <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 8a8 8 0 0 1 16 0" />
                                </svg>
                            </div>
                        @endif
                    </div>

                    <div class="profile-copy">
                        <span class="section-kicker">Profil Klien</span>
                        <h1>{{ $user->name }}</h1>
                        <div class="profile-badges">
                            <x-current-job-badge :show="$user->has_current_job" />
                            @if($user->userProfile->educationDegree)
                                <x-profile-badge :label="$user->userProfile->educationDegree->title" variant="primary" />
                            @endif
                            @if($user->userProfile->gender)
                                <x-profile-badge :label="$user->userProfile->gender === 'L' ? 'Laki-Laki' : 'Perempuan'" />
                            @endif
                        </div>

                        @if($user->userProfile->description)
                            <p class="profile-description">{{ $user->userProfile->description }}</p>
                        @endif

                        <div class="quick-metrics">
                            @if($user->userProfile->age)
                                <div>
                                    <strong>{{ $user->userProfile->age }}</strong>
                                    <span>Tahun</span>
                                </div>
                            @endif
                            @if($user->userProfile->body_height)
                                <div>
                                    <strong>{{ $user->userProfile->body_height }}</strong>
                                    <span>cm</span>
                                </div>
                            @endif
                            @if($user->userProfile->body_weight)
                                <div>
                                    <strong>{{ $user->userProfile->body_weight }}</strong>
                                    <span>kg</span>
                                </div>
                            @endif
                        </div>

                        <a href="{{ route('home') }}" class="button button-primary compact">Kembali ke Beranda</a>
                    </div>
                </section>

                <div class="profile-detail-grid">
                    <section class="info-card">
                        <div class="card-title">Informasi Pribadi</div>
                        <dl class="detail-list">
                            @isset($user->userProfile->age)
                                <div>
                                    <dt>Umur</dt>
                                    <dd>{{ $user->userProfile->age }} Tahun</dd>
                                </div>
                            @endisset
                            @isset($user->userProfile->body_height)
                                <div>
                                    <dt>Tinggi Badan</dt>
                                    <dd>{{ $user->userProfile->body_height }} cm</dd>
                                </div>
                            @endisset
                            @isset($user->userProfile->body_weight)
                                <div>
                                    <dt>Berat Badan</dt>
                                    <dd>{{ $user->userProfile->body_weight }} kg</dd>
                                </div>
                            @endisset
                            @isset($user->userProfile->religion)
                                <div>
                                    <dt>Agama</dt>
                                    <dd>{{ $user->userProfile->religion->title }}</dd>
                                </div>
                            @endisset
                            @isset($user->userProfile->ethnicGroup)
                                <div>
                                    <dt>Suku</dt>
                                    <dd>{{ $user->userProfile->ethnicGroup->title }}</dd>
                                </div>
                            @endisset
                            @isset($user->userProfile->educationDegree)
                                <div>
                                    <dt>Pendidikan</dt>
                                    <dd>{{ $user->userProfile->educationDegree->title }}</dd>
                                </div>
                            @endisset
                        </dl>
                    </section>

                    <section class="info-card">
                        <div class="card-title">Dokumen & Sertifikat</div>
                        <div class="tiny-list">
                            @if($user->userProfile->skck_status === true)
                                <div class="tiny-item success">SKCK</div>
                            @endif
                            @if($user->userProfile->surat_kesehatan_status === true)
                                <div class="tiny-item success">Surat Kesehatan</div>
                            @endif
                            @if(!($user->userProfile->skck_status === true || $user->userProfile->surat_kesehatan_status === true))
                                <div class="tiny-item">Belum ada dokumen lengkap</div>
                            @endif
                        </div>
                    </section>
                </div>

                <section class="profile-full-card">
                    <div class="card-title">Penempatan Wilayah Kerja</div>
                    @if($user->userWorkLocations->count() > 0)
                        <div class="chip-list">
                            @foreach($user->userWorkLocations as $userWorkLocation)
                                <x-profile-badge :label="$userWorkLocation->title" variant="primary" />
                            @endforeach
                        </div>
                    @else
                        <div class="empty-box">Belum ada data penempatan wilayah kerja.</div>
                    @endif
                </section>

                <section class="profile-full-card">
                    <div class="card-title">Pelatihan Keterampilan</div>
                    @if($user->userTrainings->count() > 0)
                        <div class="stack-list">
                            @foreach($user->userTrainings as $userTraining)
                                <div class="stack-item">
                                    <div class="stack-icon">✓</div>
                                    <span>{{ $userTraining->title }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-box">Belum ada data pelatihan keterampilan.</div>
                    @endif
                </section>

                <section class="profile-full-card">
                    <div class="card-title">Pengalaman Bekerja</div>
                    @if($user->userExperiences->count() > 0)
                        <div class="experience-list">
                            @foreach($user->userExperiences->sortBy('sort_order') as $userExperience)
                                <div class="experience-item">
                                    <div class="experience-mark {{ $userExperience->is_current_job ? 'active' : '' }}"></div>
                                    <div class="experience-copy">
                                        <div class="experience-topline">
                                            <h4>{{ $userExperience->experience_job_name }}</h4>
                                            @if($userExperience->is_current_job == 1)
                                                <x-current-job-badge :show="true" />
                                            @endif
                                        </div>
                                        <p>{{ $userExperience->experience_description }}</p>
                                        @if($userExperience->experience_year)
                                            <small>{{ $userExperience->experience_year }}</small>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-box">Belum ada data pengalaman bekerja.</div>
                    @endif
                </section>
            </div>
        </div>
    @endsection
    </section>

    {{-- ======= KETERAMPILAN / SKILLS ======= --}}
    @if($user->userSkills->count() > 0)
        <section class="mb-10">
            <x-section-heading title="Keterampilan" />
            <div class="flex flex-wrap gap-2">
                @foreach($user->userSkills as $userSkill)
                    <x-profile-badge :label="$userSkill->title" variant="amber" />
                @endforeach
            </div>
        </section>
    @endif

    </div>
    </div>
@endsection