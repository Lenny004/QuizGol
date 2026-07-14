@extends('layouts.quizgol')

@section('title', 'Sala '.$room->code.' — QuizGol')

@section('content')
    <div
        id="host-app"
        class="host-app"
        data-room-id="{{ $room->id }}"
        data-mode="{{ $room->mode }}"
        data-state-url="{{ route('rooms.state', $room) }}"
        data-start-url="{{ route('rooms.start', $room) }}"
        data-next-url="{{ route('rooms.next', $room) }}"
        data-finish-url="{{ route('rooms.finish', $room) }}"
        data-csrf="{{ csrf_token() }}"
    >
        <div class="host-top">
            <div>
                <p class="muted">Código de sala</p>
                <div class="room-code" id="host-code">{{ $room->code }}</div>
                <p class="page-subtitle">
                    {{ $room->section->title }}
                    @if ($room->section->subject)
                        · {{ $room->section->subject->name }}
                    @endif
                    @if ($room->section->gradeLabel())
                        · {{ $room->section->gradeLabel() }}
                    @endif
                    · {{ $room->mode === 'match' ? 'Partido 2 equipos' : 'Quiz individual' }}
                </p>
            </div>
            <div class="host-controls" id="host-controls">
                <button type="button" class="btn btn-gold" id="btn-start" hidden>Iniciar</button>
                <button type="button" class="btn btn-primary" id="btn-next" hidden>Siguiente pregunta</button>
                <button type="button" class="btn btn-danger" id="btn-finish" hidden>Finalizar</button>
            </div>
        </div>

        @if ($room->mode === 'match')
            <div class="match-scoreboard" id="host-match-score" aria-live="polite">
                <div class="match-side match-home">
                    <span class="match-name" id="match-home-name">Local</span>
                    <span class="match-goals" id="match-home-goals">0</span>
                </div>
                <div class="match-vs">–</div>
                <div class="match-side match-away">
                    <span class="match-goals" id="match-away-goals">0</span>
                    <span class="match-name" id="match-away-name">Visitante</span>
                </div>
            </div>
            <p class="match-winner muted" id="host-match-winner" hidden></p>
        @endif

        <div class="host-grid">
            <section class="card host-main">
                <div id="host-lobby">
                    <h2>Lobby</h2>
                    <p class="muted">Esperando jugadores… Comparte el código en grande.</p>
                    <ul class="player-list" id="host-player-list"></ul>
                </div>

                <div id="host-question" hidden>
                    <div class="question-meta">
                        <span id="host-q-progress"></span>
                        <span class="countdown" id="host-countdown">–</span>
                    </div>
                    <h2 class="question-prompt" id="host-prompt"></h2>
                    <div class="host-answers" id="host-answers"></div>
                    <p class="muted" id="host-answered">0 / 0 respondieron</p>
                </div>

                <div id="host-finished" hidden>
                    <h2 class="goal-burst">¡Partido terminado!</h2>
                    <p class="muted">Resultados finales abajo.</p>
                </div>
            </section>

            <aside class="card host-scoreboard">
                <h2>{{ $room->mode === 'match' ? 'Puntos individuales' : 'Marcador' }}</h2>
                <ol class="scoreboard" id="host-scoreboard"></ol>
            </aside>
        </div>
    </div>

    <script src="{{ asset('js/host.js') }}" defer></script>
@endsection
