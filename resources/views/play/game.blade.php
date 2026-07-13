@extends('layouts.quizgol')

@section('title', 'Jugando '.$room->code.' — QuizGol')

@section('content')
    <div
        id="play-app"
        class="play-app"
        data-code="{{ $room->code }}"
        data-mode="{{ $room->mode }}"
        data-state-url="{{ route('play.state', $room->code) }}"
        data-answer-url="{{ route('play.answer', $room->code) }}"
        data-csrf="{{ csrf_token() }}"
        data-nickname="{{ $player->nickname }}"
    >
        <div class="play-header">
            <span class="play-nick" id="play-nick">{{ $player->nickname }}</span>
            <span class="team-badge" id="play-team-badge" hidden></span>
            <span class="play-score" id="play-score">0 pts</span>
        </div>

        @if ($room->mode === 'match')
            <div class="match-scoreboard match-scoreboard-compact" id="play-match-score" aria-live="polite">
                <div class="match-side match-home">
                    <span class="match-name" id="play-home-name">Local</span>
                    <span class="match-goals" id="play-home-goals">0</span>
                </div>
                <div class="match-vs">–</div>
                <div class="match-side match-away">
                    <span class="match-goals" id="play-away-goals">0</span>
                    <span class="match-name" id="play-away-name">Visitante</span>
                </div>
            </div>
        @endif

        <section class="card play-stage" id="play-lobby">
            <div class="ball-emoji" aria-hidden="true">⚽</div>
            <h1 class="page-title">En el vestuario…</h1>
            <p class="page-subtitle">Espera a que el maestro inicie el partido.</p>
            <p class="muted">Jugadores: <span id="lobby-count">0</span></p>
        </section>

        <section class="card play-stage" id="play-question" hidden>
            <div class="question-meta">
                <span class="countdown" id="play-countdown">–</span>
            </div>
            <h2 class="question-prompt" id="play-prompt"></h2>
            <div class="answer-grid" id="play-answers"></div>
        </section>

        <section class="card play-stage play-feedback" id="play-reveal" hidden>
            <div id="reveal-content"></div>
        </section>

        <section class="card play-stage" id="play-finished" hidden>
            <h1 class="page-title">Fin del partido</h1>
            <p class="page-subtitle" id="final-match-result" hidden></p>
            <p class="page-subtitle">Tu puntaje: <strong id="final-score">0</strong></p>
            <ol class="scoreboard" id="play-scoreboard"></ol>
        </section>
    </div>

    <script src="{{ asset('js/play.js') }}" defer></script>
@endsection
