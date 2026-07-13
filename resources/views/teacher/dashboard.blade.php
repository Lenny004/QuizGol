@extends('layouts.quizgol')

@section('title', 'Dashboard — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Hola, {{ auth()->user()->name }}</h1>
            <p class="page-subtitle">Prepara secciones y preguntas para tus partidos.</p>
        </div>
        <a class="btn btn-gold" href="{{ route('sections.create') }}">Nueva sección</a>
    </div>

    <div class="stat-row">
        <div class="stat">
            <span class="stat-label">Tus secciones</span>
            <span class="stat-value">{{ $sectionsCount }}</span>
        </div>
        <div class="stat">
            <span class="stat-label">Salas activas</span>
            <span class="stat-value">{{ $activeRooms->count() }}</span>
        </div>
    </div>

    @if ($activeRooms->isNotEmpty())
        <section class="panel" style="margin-bottom: 1.5rem;">
            <div class="panel-header">
                <h2>Salas en vivo</h2>
            </div>
            @foreach ($activeRooms as $room)
                <div class="list-row">
                    <div>
                        <strong>{{ $room->code }}</strong>
                        <span class="muted"> · {{ $room->section->title }} · {{ $room->status }}</span>
                    </div>
                    <a class="btn btn-gold btn-sm" href="{{ route('rooms.host', $room) }}">Abrir proyector</a>
                </div>
            @endforeach
        </section>
    @endif

    <section class="panel">
        <div class="panel-header">
            <h2>Secciones recientes</h2>
            <a href="{{ route('sections.index') }}">Ver todas</a>
        </div>

        @forelse ($recentSections as $section)
            <div class="list-row">
                <div>
                    <strong>{{ $section->title }}</strong>
                    <span class="muted"> · {{ $section->subject->name }}</span>
                    @if ($section->grade)
                        <span class="muted"> · Grado {{ $section->grade }}</span>
                    @endif
                </div>
                <a class="btn btn-primary btn-sm" href="{{ route('sections.questions.index', $section) }}">Preguntas</a>
            </div>
        @empty
            <p class="empty">Aún no tienes secciones. Crea la primera para empezar.</p>
        @endforelse
    </section>
@endsection
