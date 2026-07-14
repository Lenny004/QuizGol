{{-- Dashboard del maestro: contadores, salas activas y secciones recientes. --}}
@extends('layouts.quizgol')

@section('title', 'Dashboard — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Hola, {{ auth()->user()->name }}</h1>
            <p class="page-header__subtitle">Prepara secciones y preguntas para tus partidos.</p>
        </div>
        <a class="btn btn--gold" href="{{ route('sections.create') }}">Nueva sección</a>
    </div>

    <div class="stats">
        <div class="stats__item">
            <span class="stats__label">Tus secciones</span>
            <span class="stats__value">{{ $sectionsCount }}</span>
        </div>
        <div class="stats__item">
            <span class="stats__label">Salas activas</span>
            <span class="stats__value">{{ $activeRooms->count() }}</span>
        </div>
    </div>

    @if ($activeRooms->isNotEmpty())
        <section class="panel panel--spaced">
            <div class="panel__header">
                <h2 class="panel__title">Salas en vivo</h2>
            </div>
            @foreach ($activeRooms as $room)
                <div class="list__row">
                    <div>
                        <strong>{{ $room->code }}</strong>
                        <span class="text--muted"> · {{ $room->section->title }} · {{ $room->status }}</span>
                    </div>
                    <a class="btn btn--gold btn--sm" href="{{ route('rooms.host', $room) }}">Abrir proyector</a>
                </div>
            @endforeach
        </section>
    @endif

    <section class="panel">
        <div class="panel__header">
            <h2 class="panel__title">Secciones recientes</h2>
            <a class="panel__link" href="{{ route('sections.index') }}">Ver todas</a>
        </div>

        @forelse ($recentSections as $section)
            <div class="list__row">
                <div>
                    <strong>{{ $section->title }}</strong>
                    <span class="text--muted"> · {{ $section->subject->name }}</span>
                    @if ($section->gradeLabel())
                        <span class="text--muted"> · {{ $section->gradeLabel() }}</span>
                    @endif
                </div>
                <a class="btn btn--primary btn--sm" href="{{ route('sections.questions.index', $section) }}">Preguntas</a>
            </div>
        @empty
            <p class="text--empty">Aún no tienes secciones. Crea la primera para empezar.</p>
        @endforelse
    </section>
@endsection
