@extends('layouts.app')

@section('title', $user->name)

@section('content')
    {{-- ============================================ --}}
    {{-- BREADCRUMB                                    --}}
    {{-- ============================================ --}}
    <div class="bg-white border-b border-gray-100">
        <div class="container mx-auto px-4 sm:px-6 py-3">
            <nav class="flex items-center gap-2 text-sm" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="text-gray-500 hover:text-primary-700 transition-colors">Beranda</a>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
                <span class="text-gray-900 font-medium truncate">{{ $user->name }}</span>
            </nav>
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- PROFILE HEADER                                --}}
    {{-- ============================================ --}}
    <section class="bg-white border-b border-gray-100">
        <div class="container mx-auto px-4 sm:px-6 py-8 lg:py-12">
            <div class="flex flex-col lg:flex-row gap-8">
                {{-- Profile Photo --}}
                <div class="flex-shrink-0 mx-auto lg:mx-0">
                    <div class="w-56 h-72 sm:w-64 sm:h-80 rounded-2xl overflow-hidden shadow-lg border-4 border-white ring-1 ring-gray-200 bg-gray-100">
                        @if($user->userProfile->profile_picture_path)
                            <img class="w-full h-full object-cover"
                                 src="{{ \Illuminate\Support\Facades\Storage::url($user->userProfile->profile_picture_path) }}"
                                 alt="Foto profil {{ $user->name }}" />
                        @else
                            <div class="profile-img-fallback w-full h-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-20 h-20 text-primary-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="0.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Profile Info --}}
                <div class="flex-1 text-center lg:text-left">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">{{ $user->name }}</h1>

                    <div class="flex flex-wrap items-center gap-2 mt-3 justify-center lg:justify-start">
                        <x-current-job-badge :show="$user->has_current_job" />

                        @if($user->userProfile->educationDegree)
                            <x-profile-badge :label="$user->userProfile->educationDegree->title" variant="primary" />
                        @endif

                        @if($user->userProfile->gender)
                            <x-profile-badge :label="$user->userProfile->gender === 'L' ? 'Laki-Laki' : 'Perempuan'" />
                        @endif
                    </div>

                    @if($user->userProfile->description)
                        <p class="text-gray-600 mt-4 leading-relaxed max-w-2xl mx-auto lg:mx-0">{{ $user->userProfile->description }}</p>
                    @endif

                    {{-- Quick stats --}}
                    <div class="flex flex-wrap gap-6 mt-6 justify-center lg:justify-start">
                        @if($user->userProfile->age)
                            <div class="text-center lg:text-left">
                                <p class="text-lg font-bold text-gray-900">{{ $user->userProfile->age }}</p>
                                <p class="text-xs text-gray-500">Tahun</p>
                            </div>
                        @endif
                        @if($user->userProfile->body_height)
                            <div class="text-center lg:text-left">
                                <p class="text-lg font-bold text-gray-900">{{ $user->userProfile->body_height }} <span class="text-sm font-normal text-gray-500">cm</span></p>
                                <p class="text-xs text-gray-500">Tinggi Badan</p>
                            </div>
                        @endif
                        @if($user->userProfile->body_weight)
                            <div class="text-center lg:text-left">
                                <p class="text-lg font-bold text-gray-900">{{ $user->userProfile->body_weight }} <span class="text-sm font-normal text-gray-500">kg</span></p>
                                <p class="text-xs text-gray-500">Berat Badan</p>
                            </div>
                        @endif
                    </div>

                    {{-- Back button --}}
                    <div class="mt-6">
                        <a href="{{ route('home') }}"
                           class="inline-flex items-center gap-2 text-sm text-primary-700 hover:text-primary-800 font-medium transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                            </svg>
                            Kembali ke Beranda
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================ --}}
    {{-- DETAIL SECTIONS                               --}}
    {{-- ============================================ --}}
    <div class="container mx-auto px-4 sm:px-6 py-8 lg:py-12">
        <div class="max-w-4xl">

            {{-- ======= INFORMASI PRIBADI ======= --}}
            <section class="mb-10">
                <x-section-heading title="Informasi Pribadi" />
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="divide-y divide-gray-100">
                        @isset($user->userProfile->age)
                            <div class="flex flex-col sm:flex-row px-5 py-3.5">
                                <dt class="text-sm font-medium text-gray-500 sm:w-48 flex-shrink-0">Umur</dt>
                                <dd class="text-sm text-gray-900 mt-1 sm:mt-0">{{ $user->userProfile->age }} Tahun</dd>
                            </div>
                        @endisset

                        @isset($user->userProfile->body_height)
                            <div class="flex flex-col sm:flex-row px-5 py-3.5">
                                <dt class="text-sm font-medium text-gray-500 sm:w-48 flex-shrink-0">Tinggi Badan</dt>
                                <dd class="text-sm text-gray-900 mt-1 sm:mt-0">{{ $user->userProfile->body_height }} cm</dd>
                            </div>
                        @endisset

                        @isset($user->userProfile->body_weight)
                            <div class="flex flex-col sm:flex-row px-5 py-3.5">
                                <dt class="text-sm font-medium text-gray-500 sm:w-48 flex-shrink-0">Berat Badan</dt>
                                <dd class="text-sm text-gray-900 mt-1 sm:mt-0">{{ $user->userProfile->body_weight }} kg</dd>
                            </div>
                        @endisset

                        @isset($user->userProfile->religion)
                            <div class="flex flex-col sm:flex-row px-5 py-3.5">
                                <dt class="text-sm font-medium text-gray-500 sm:w-48 flex-shrink-0">Agama</dt>
                                <dd class="text-sm text-gray-900 mt-1 sm:mt-0">{{ $user->userProfile->religion->title }}</dd>
                            </div>
                        @endisset

                        @isset($user->userProfile->ethnicGroup)
                            <div class="flex flex-col sm:flex-row px-5 py-3.5">
                                <dt class="text-sm font-medium text-gray-500 sm:w-48 flex-shrink-0">Suku</dt>
                                <dd class="text-sm text-gray-900 mt-1 sm:mt-0">{{ $user->userProfile->ethnicGroup->title }}</dd>
                            </div>
                        @endisset

                        @isset($user->userProfile->educationDegree)
                            <div class="flex flex-col sm:flex-row px-5 py-3.5">
                                <dt class="text-sm font-medium text-gray-500 sm:w-48 flex-shrink-0">Pendidikan</dt>
                                <dd class="text-sm text-gray-900 mt-1 sm:mt-0">{{ $user->userProfile->educationDegree->title }}</dd>
                            </div>
                        @endisset
                    </div>
                </div>
            </section>

            {{-- ======= DOKUMEN & SERTIFIKAT ======= --}}
            @if($user->userProfile->skck_status || $user->userProfile->surat_kesehatan_status)
                <section class="mb-10">
                    <x-section-heading title="Dokumen & Sertifikat" />
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @if($user->userProfile->skck_status === true)
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-start gap-4">
                                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">SKCK</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Memiliki Surat Keterangan Catatan Kepolisian</p>
                                </div>
                            </div>
                        @endif
                        @if($user->userProfile->surat_kesehatan_status === true)
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-start gap-4">
                                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Surat Kesehatan</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Memiliki Surat Keterangan Sehat dari Instansi Kesehatan</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            {{-- ======= PENEMPATAN WILAYAH KERJA ======= --}}
            <section class="mb-10">
                <x-section-heading title="Penempatan Wilayah Kerja" />
                @if($user->userWorkLocations->count() > 0)
                    <div class="flex flex-wrap gap-2">
                        @foreach($user->userWorkLocations as $userWorkLocation)
                            <x-profile-badge :label="$userWorkLocation->title" variant="primary" />
                        @endforeach
                    </div>
                @else
                    <x-empty-state message="Belum ada data penempatan wilayah kerja." />
                @endif
            </section>

            {{-- ======= PELATIHAN KETERAMPILAN ======= --}}
            <section class="mb-10">
                <x-section-heading title="Pelatihan Keterampilan" />
                @if($user->userTrainings->count() > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($user->userTrainings as $userTraining)
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-start gap-4 card-hover">
                                <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $userTraining->title }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-empty-state message="Belum ada data pelatihan keterampilan." />
                @endif
            </section>

            {{-- ======= PENGALAMAN BEKERJA ======= --}}
            <section class="mb-10">
                <x-section-heading title="Pengalaman Bekerja" />
                @if($user->userExperiences->count() > 0)
                    <div class="space-y-4">
                        @foreach($user->userExperiences->sortBy('sort_order') as $userExperience)
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-start gap-4 card-hover">
                                <div class="w-10 h-10 rounded-lg {{ $userExperience->is_current_job ? 'bg-green-100' : 'bg-gray-100' }} flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 {{ $userExperience->is_current_job ? 'text-green-600' : 'text-gray-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">{{ $userExperience->experience_job_name }}</p>
                                            <p class="text-sm text-gray-600 mt-0.5">{{ $userExperience->experience_description }}</p>
                                            @if($userExperience->experience_year)
                                                <p class="text-xs text-gray-400 mt-1">{{ $userExperience->experience_year }}</p>
                                            @endif
                                        </div>
                                        @if($userExperience->is_current_job == 1)
                                            <x-current-job-badge :show="true" />
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-empty-state message="Belum ada data pengalaman bekerja." />
                @endif
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
