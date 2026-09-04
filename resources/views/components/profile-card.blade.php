@props(['user'])

@php
    $slug = sprintf('%s-%s', \Illuminate\Support\Str::slug($user->name), $user->id);
    $profileUrl = route('front.user.detail', ['slug' => $slug]);
    $isHirable = $user->userProfile->hire_status == 1;
    $profilePicture = $user->userProfile->profile_picture_path;
@endphp

<div class="bg-white rounded-xl border border-gray-100 shadow-sm card-hover overflow-hidden">
    {{-- Profile Image --}}
    <div class="relative">
        <div class="aspect-[3/4] w-full bg-gray-100 overflow-hidden">
            @if($profilePicture)
                <img
                    class="w-full h-full object-cover"
                    src="{{ \Illuminate\Support\Facades\Storage::url($profilePicture) }}"
                    alt="Foto profil {{ $user->name }}"
                    loading="lazy"
                />
            @else
                <div class="profile-img-fallback w-full h-full">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </div>
            @endif
        </div>

        {{-- Status Badge --}}
        @if($user->has_current_job)
            <div class="absolute top-3 right-3">
                <span class="inline-flex items-center gap-1 rounded-full bg-green-500 px-2.5 py-1 text-xs font-semibold text-white shadow-sm">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                    Bekerja
                </span>
            </div>
        @endif
    </div>

    {{-- Card Content --}}
    <div class="p-4">
        {{-- Name --}}
        <h3 class="font-bold text-gray-900 text-base leading-snug mb-1 line-clamp-2">
            @if($isHirable)
                <a href="{{ $profileUrl }}" class="hover:text-primary-700 transition-colors duration-200">
                    {{ $user->name }}
                </a>
            @else
                {{ $user->name }}
            @endif
        </h3>

        {{-- Description --}}
        @if($user->userProfile->description)
            <p class="text-sm text-gray-500 line-clamp-3 mb-4 leading-relaxed">
                {{ \Illuminate\Support\Str::limit($user->userProfile->description, 120) }}
            </p>
        @else
            <p class="text-sm text-gray-400 italic mb-4">Belum ada deskripsi</p>
        @endif

        {{-- Action Button --}}
        @if($isHirable)
            <a href="{{ $profileUrl }}"
               class="flex items-center justify-center w-full gap-2 bg-primary-700 hover:bg-primary-800 text-white text-sm font-semibold py-2.5 px-4 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                <span>Lihat Profil</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>
        @else
            <div class="flex items-center justify-center w-full gap-2 bg-gray-100 text-gray-400 text-sm font-semibold py-2.5 px-4 rounded-lg cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                <span>Tidak Tersedia</span>
            </div>
        @endif
    </div>
</div>
