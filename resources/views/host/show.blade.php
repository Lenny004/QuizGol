{{-- Pantalla del anfitrión (proyector). Monta #host-app + public/js/host.js --}}
@extends('layouts.quizgol')

@section('title', 'Sala '.$room->code.' — QuizGol')

@section('content')
    <div
        id="host-app"
        class="host"
        data-room-id="{{ $room->id }}"
        data-mode="{{ $room->mode }}"
        data-join-url="{{ url('/join?code='.$room->code) }}"
        data-state-url="{{ route('rooms.state', $room) }}"
        data-start-url="{{ route('rooms.start', $room) }}"
        data-reveal-url="{{ route('rooms.reveal', $room) }}"
        data-next-url="{{ route('rooms.next', $room) }}"
        data-finish-url="{{ route('rooms.finish', $room) }}"
        data-results-url="{{ route('rooms.results', $room) }}"
        data-csrf="{{ csrf_token() }}"
    >
        <div class="host__top">
            <div>
                <p class="text--muted">Código de sala</p>
                <div class="host__code" id="host-code">{{ $room->code }}</div>
                <p class="page-header__subtitle">
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
            <div class="host__controls" id="host-controls">
                <button type="button" class="btn btn--gold" id="btn-start" hidden>Iniciar</button>
                <button type="button" class="btn btn--primary" id="btn-reveal" hidden>Revelar respuestas</button>
                <button type="button" class="btn btn--gold" id="btn-next" hidden>Siguiente pregunta</button>
                <a class="btn btn--ghost" id="btn-results" href="{{ route('rooms.results', $room) }}" hidden>Ver reporte</a>
                <button type="button" class="btn btn--danger" id="btn-finish" hidden>Finalizar</button>
            </div>
        </div>

        @if ($room->mode === 'match')
            <div class="match" id="host-match-score" aria-live="polite">
                <div class="match__side">
                    <span class="match__name" id="match-home-name">Local</span>
                    <span class="match__goals" id="match-home-goals">0</span>
                </div>
                <div class="match__vs">–</div>
                <div class="match__side">
                    <span class="match__goals" id="match-away-goals">0</span>
                    <span class="match__name" id="match-away-name">Visitante</span>
                </div>
            </div>
            <p class="match__winner text--muted" id="host-match-winner" hidden></p>
        @endif

        <div class="host__grid">
            <section class="card">
                <div id="host-lobby">
                    <div class="join-panel">
                        <div class="join-panel__qr-wrap">
                            <div id="host-qr" class="join-panel__qr" aria-label="Código QR para unirse"></div>
                            <p class="join-panel__scan-hint">Escanea con la cámara del celular</p>
                        </div>
                        <div class="join-panel__steps">
                            <h2 class="join-panel__title">¡Entra al partido!</h2>
                            <ol class="join-panel__list">
                                <li><strong>Abre la cámara</strong> de tu celular</li>
                                <li><strong>Escanea el QR</strong> o ve a <code>{{ url('/join') }}</code></li>
                                <li>Escribe el código <strong class="join-panel__code">{{ $room->code }}</strong> y tu apodo</li>
                                @if ($room->mode === 'match')
                                    <li>Elige equipo: <strong>Local</strong> o <strong>Visitante</strong></li>
                                @endif
                            </ol>
                            <p class="join-panel__url text--muted" id="host-join-url">{{ url('/join?code='.$room->code) }}</p>
                        </div>
                    </div>
                    <h3 class="join-panel__players-title">Jugadores en lobby</h3>
                    <ul class="scoreboard" id="host-player-list"></ul>
                </div>

                <div id="host-question" hidden>
                    <div class="question__meta">
                        <span id="host-q-progress"></span>
                        <span class="question__countdown" id="host-countdown">–</span>
                        <span class="question__phase-badge" id="host-phase-badge" hidden></span>
                    </div>
                    <h2 class="question__prompt" id="host-prompt"></h2>
                    <div class="host__answers" id="host-answers"></div>
                    <p class="text--muted" id="host-answered">0 / 0 respondieron</p>
                </div>

                <div id="host-finished" hidden>
                    <h2 class="feedback--goal">¡Partido terminado!</h2>
                    <p class="text--muted">Revisa el reporte pedagógico con aciertos por pregunta.</p>
                    <a class="btn btn--gold" href="{{ route('rooms.results', $room) }}">Abrir reporte</a>
                </div>
            </section>

            <aside class="card">
                <h2>{{ $room->mode === 'match' ? 'Puntos individuales' : 'Marcador' }}</h2>
                <ol class="scoreboard" id="host-scoreboard"></ol>
            </aside>
        </div>
    </div>

    <script src="{{ asset('js/qrcode.min.js') }}" defer></script>
    <script src="{{ asset('js/host.js') }}" defer></script>
@endsection
