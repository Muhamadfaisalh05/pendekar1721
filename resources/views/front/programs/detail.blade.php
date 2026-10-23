@extends('layouts.app')

@section('title', 'Program ' . $program['title'])

@section('content')
    <section class="program-detail-section">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="profile-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Beranda</a>
                <span>/</span>
                <a href="{{ route('home') }}#program">Program &amp; Pelatihan</a>
                <span>/</span>
                <span>{{ $program['title'] }}</span>
            </nav>

            <header class="program-detail-hero">
                <span class="section-kicker">Program &amp; Pelatihan</span>
                <h1>{{ $program['title'] }}</h1>
                <p>{{ $program['description'] }}</p>
            </header>

            <div class="program-detail-grid">
                <article class="program-detail-card">
                    <span class="section-kicker">Tentang Program</span>
                    <h2>{{ $program['about_heading'] }}</h2>
                    @if(isset($program['image']))
                        <figure class="program-detail-figure">
                            <img src="{{ asset($program['image']) }}" alt="{{ $program['image_alt'] }}" loading="lazy" />
                            <figcaption>{{ $program['image_alt'] }}</figcaption>
                        </figure>
                    @endif
                    @foreach($program['about'] as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </article>

                <article class="program-detail-card">
                    <span class="section-kicker">{{ $program['history_kicker'] }}</span>
                    <h2>{{ $program['history_heading'] }}</h2>
                    <ol class="sewing-history">
                        @foreach($program['history'] as $event)
                            <li>
                                <h3>{{ $event['title'] }}</h3>
                                <p>{{ $event['description'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                    <p class="program-history-note">{{ $program['note'] }}</p>
                </article>
            </div>

            <a href="{{ route('home') }}#program" class="button button-primary program-back-link">
                Kembali ke Program &amp; Pelatihan
            </a>
        </div>
    </section>
@endsection
