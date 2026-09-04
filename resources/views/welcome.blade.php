@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
    {{-- ============================================ --}}
    {{-- HERO SECTION --}}
    {{-- ============================================ --}}
    <section class="hero-gradient relative overflow-hidden">
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" viewBox="0 0 1200 400" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="100" cy="300" r="200" fill="white" opacity="0.05" />
                <circle cx="1100" cy="100" r="300" fill="white" opacity="0.03" />
                <circle cx="600" cy="350" r="150" fill="white" opacity="0.04" />
            </svg>
        </div>
        <div class="container mx-auto px-4 sm:px-6 relative">
            <div class="flex flex-col lg:flex-row items-center gap-8 py-12 lg:py-20">
                {{-- Hero Text --}}
                <div class="flex-1 text-center lg:text-left">
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white leading-tight tracking-tight">
                        PENDEKAR1721
                    </h1>
                    <p class="text-primary-200 text-lg sm:text-xl mt-3 font-light leading-relaxed">
                        Direktori Profil Tenaga Kerja Terampil
                    </p>
                    <p class="text-primary-300 text-sm sm:text-base mt-2 leading-relaxed max-w-xl mx-auto lg:mx-0">
                        UPTD Pusat Pelayanan Sosial Griya Bina Remaja — Dinas Sosial Pemerintah Provinsi Jawa Barat
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                        <a href="#direktori"
                            class="inline-flex items-center justify-center gap-2 bg-white text-primary-700 font-semibold py-3 px-6 rounded-lg hover:bg-gray-100 transition-colors duration-200 shadow-lg focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-primary-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            Cari Tenaga Kerja
                        </a>
                    </div>
                </div>
                {{-- Hero Image --}}
                <div class="flex-shrink-0 hidden md:block">
                    <img src="{{ asset('images/ppsgbr.png') }}"
                        class="h-48 lg:h-56 w-auto rounded-2xl shadow-2xl opacity-90" alt="Logo PENDEKAR1721" />
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================ --}}
    {{-- STATISTICS SECTION --}}
    {{-- ============================================ --}}
    <section class="container mx-auto px-4 sm:px-6 -mt-8 relative z-10">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6">
            <x-stat-card icon="users" :value="number_format($totalClients)" label="Total Klien Terdaftar" variant="blue" />
            <x-stat-card icon="briefcase" :value="number_format($totalClientsWorking)" label="Sedang Bekerja"
                variant="green" />
            <x-stat-card icon="academic" :value="number_format($totalTrainings)" label="Program Pelatihan"
                variant="amber" />
        </div>
    </section>

    {{-- ============================================ --}}
    {{-- SEARCH & FILTER SECTION --}}
    {{-- ============================================ --}}
    <section id="direktori" class="container mx-auto px-4 sm:px-6 mt-10 scroll-mt-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 lg:p-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Pencarian & Filter</h2>
                    <p class="text-sm text-gray-500">Temukan tenaga kerja sesuai kebutuhan Anda</p>
                </div>
            </div>

            <form method="GET" action="{{ route('home') }}">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-5">
                    {{-- Status Pekerjaan --}}
                    <div>
                        <label for="has_current_job" class="block text-sm font-semibold text-gray-700 mb-1.5">Status
                            Pekerjaan</label>
                        <select name="has_current_job" id="has_current_job" class="filter-select">
                            <option value="">Semua Status</option>
                            <option value="1" @selected(request('has_current_job') == '1')>Sedang Bekerja</option>
                            <option value="0" @selected(request('has_current_job') == '0')>Belum Bekerja</option>
                        </select>
                    </div>

                    {{-- Jenis Kelamin --}}
                    <div>
                        <label for="gender" class="block text-sm font-semibold text-gray-700 mb-1.5">Jenis Kelamin</label>
                        <select name="gender" id="gender" class="filter-select">
                            <option value="">Semua</option>
                            <option value="L" @selected(request('gender') == 'L')>Laki-Laki</option>
                            <option value="P" @selected(request('gender') == 'P')>Perempuan</option>
                        </select>
                    </div>

                    {{-- Penempatan Wilayah Kerja --}}
                    <div>
                        <label for="city_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Wilayah Kerja</label>
                        <select name="city_id" id="city_id" class="filter-select">
                            <option value="">Semua Wilayah</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city->id }}" @selected(request('city_id') == $city->id)>{{ $city->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Jenjang Pendidikan --}}
                    <div>
                        <label for="education_degree_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Jenjang
                            Pendidikan</label>
                        <select name="education_degree_id" id="education_degree_id" class="filter-select">
                            <option value="">Semua Jenjang</option>
                            @foreach ($educationDegrees as $degree)
                                <option value="{{ $degree->id }}" @selected(request('education_degree_id') == $degree->id)>
                                    {{ $degree->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Keterampilan / Pelatihan --}}
                    <div>
                        <label for="training_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Pelatihan
                            Keterampilan</label>
                        <select name="training_id" id="training_id" class="filter-select">
                            <option value="">Semua Keterampilan</option>
                            @foreach ($trainings as $training)
                                <option value="{{ $training->id }}" @selected(request('training_id') == $training->id)>
                                    {{ $training->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Buttons --}}
                    <div class="flex items-end gap-3">
                        <button type="submit"
                            class="flex-1 inline-flex items-center justify-center gap-2 bg-primary-700 hover:bg-primary-800 text-white text-sm font-semibold py-2.5 px-6 rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            Cari
                        </button>
                        <a href="{{ route('home') }}"
                            class="inline-flex items-center justify-center gap-2 border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm font-medium py-2.5 px-4 rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                            </svg>
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    {{-- ============================================ --}}
    {{-- TRAINING TABS / PILLS --}}
    {{-- ============================================ --}}
    <section class="container mx-auto px-4 sm:px-6 mt-8">
        <div class="bg-primary-700 rounded-xl p-4 lg:p-5">
            <div class="flex items-center gap-3 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-200" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                </svg>
                <h3 class="text-sm font-semibold text-primary-100">Jurusan Pelatihan Keterampilan</h3>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('home') }}"
                    class="training-pill {{ request('training_id') == '' ? 'training-pill-active' : 'training-pill-inactive' }}">
                    Semua
                </a>
                @foreach ($trainings as $training)
                    <a href="{{ route('home', ['training_id' => $training->id]) }}"
                        class="training-pill {{ request('training_id') == $training->id ? 'training-pill-active' : 'training-pill-inactive' }}">
                        {{ $training->title }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================================ --}}
    {{-- PROFILE CARDS GRID --}}
    {{-- ============================================ --}}
    <section class="container mx-auto px-4 sm:px-6 mt-8 mb-12">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Direktori Tenaga Kerja</h2>
                <p class="text-sm text-gray-500 mt-0.5">Menampilkan {{ $users->count() }} dari {{ $users->total() }} data
                </p>
            </div>
        </div>

        @if($users->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 lg:gap-6">
                @foreach ($users as $user)
                    <x-profile-card :user="$user" />
                @endforeach
            </div>
        @else
            <x-empty-state message="Tidak ada tenaga kerja yang sesuai dengan filter Anda." icon="search" />
        @endif

        {{-- Pagination --}}
        @if($users->hasPages())
            <div class="mt-8 flex justify-center">
                {{ $users->withQueryString()->links() }}
            </div>
        @endif
    </section>
@endsection