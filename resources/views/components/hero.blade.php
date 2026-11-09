@php
/**
 * Hero component for PENDEKAR1721 homepage.
 * Expects optional variables:
 *  - $title (string) default "PENDEKAR1721"
 *  - $subtitle (string) default "UPTD Pusat Pelayanan Sosial Griya Bina Remaja"
 *  - $description (string) optional additional description.
 */
@endphp
<section class="relative w-full h-screen overflow-hidden">
    <video autoplay muted loop playsinline class="object-cover w-full h-full">
        <source src="{{ asset('videos/hero.mp4') }}" type="video/mp4">
        Your browser does not support the video tag.
    </video>
    <div class="absolute inset-0 bg-primary-700 bg-opacity-50 flex flex-col items-center justify-center text-center px-4">
        <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-4">{{ $title ?? 'PENDEKAR1721' }}</h1>
        <p class="text-lg md:text-xl text-primary-200 mb-6">{{ $subtitle ?? 'UPTD Pusat Pelayanan Sosial Griya Bina Remaja' }}</p>
        @isset($description)
            <p class="text-base md:text-lg text-primary-100 mb-8">{{ $description }}</p>
        @endisset
        <div class="flex flex-col sm:flex-row gap-4">
            <a href="{{ route('home') }}" class="inline-flex items-center justify-center gap-2 bg-white text-primary-700 font-semibold py-3 px-6 rounded-lg hover:bg-primary-50 transition-colors">
                Lihat Profil
            </a>
            <a href="{{ route('home') }}#direktori" class="inline-flex items-center justify-center gap-2 bg-primary-700 text-white font-semibold py-3 px-6 rounded-lg hover:bg-primary-800 transition-colors">
                Jelajahi Data
            </a>
        </div>
    </div>
</section>
