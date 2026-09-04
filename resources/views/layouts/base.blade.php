<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#083668" />
        <meta name="description" content="PENDEKAR1721 — Direktori Profil Tenaga Kerja Terampil, UPTD Pusat Pelayanan Sosial Griya Bina Remaja, Dinas Sosial Pemerintah Provinsi Jawa Barat">

        @hasSection('title')
            <title>@yield('title') - PENDEKAR1721</title>
        @else
            <title>PENDEKAR1721 — Direktori Profil Tenaga Kerja Terampil</title>
        @endif

        <!-- Favicon -->
		<link rel="shortcut icon" href="{{ url(asset('favicon.ico')) }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://rsms.me">
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css">

        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
        @livewireStyles
        @livewireScripts

        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">
    </head>

    <body class="bg-gray-50 font-sans text-gray-800 min-h-screen flex flex-col">
        @yield('body')
    </body>
</html>
