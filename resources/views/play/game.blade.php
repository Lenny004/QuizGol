{{-- Pantalla del jugador. Monta #play-app + public/js/play.js (polling + respuestas). --}}
@extends('layouts.quizgol')

@section('title', 'Jugando '.$room->code.' — QuizGol')

@section('content')
    <div
        id="play-app"
        class="play"
        data-code="{{ $room->code }}"
        data-mode="{{ $room->mode }}"
        data-state-url="{{ route('play.state', $room->code) }}"
        data-answer-url="{{ route('play.answer', $room->code) }}"
        data-csrf="{{ csrf_token() }}"
        data-nickname="{{ $player->nickname }}"
    >
        <div class="play__header">
            <span id="play-nick">{{ $player->nickname }}</span>
            <span class="team__badge" id="play-team-badge" hidden></span>
            <span class="play__score" id="play-score">0 pts</span>
        </div>

        @if ($room->mode === 'match')
            <div class="match match--compact" id="play-match-score" aria-live="polite">
                <div class="match__side">
                    <span class="match__name" id="play-home-name">Local</span>
                    <span class="match__goals" id="play-home-goals">0</span>
                </div>
                <div class="match__vs">–</div>
                <div class="match__side">
                    <span class="match__goals" id="play-away-goals">0</span>
                    <span class="match__name" id="play-away-name">Visitante</span>
                </div>
            </div>
        @endif

        <section class="card play__stage" id="play-lobby">
            <div class="play__ball" aria-hidden="true">⚽</div>
            <h1 class="page-header__title">En el vestuario…</h1>
            <p class="page-header__subtitle">Espera a que el maestro inicie el partido.</p>
            <p class="text--muted">Jugadores: <span id="lobby-count">0</span></p>
        </section>

        <section class="card play__stage" id="play-question" hidden>
            <div class="question__meta">
                <span class="question__countdown" id="play-countdown">–</span>
            </div>
            <h2 class="question__prompt" id="play-prompt"></h2>
            <div class="answer-grid" id="play-answers"></div>
        </section>

        <section class="card play__stage" id="play-reveal" hidden>
            <div id="reveal-content"></div>
        </section>

        <section class="card play__stage" id="play-finished" hidden>
            <h1 class="page-header__title">Fin del partido</h1>
            <p class="page-header__subtitle" id="final-match-result" hidden></p>
            <p class="page-header__subtitle">Tu puntaje: <strong id="final-score">0</strong></p>
            <ol class="scoreboard" id="play-scoreboard"></ol>
        </section>
    </div>

    <script src="{{ asset('js/play.js') }}" defer></script>
@endsection
