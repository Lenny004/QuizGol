@extends('layouts.quizgol')

@section('title', 'Reporte '.$room->code.' — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Reporte de la sala {{ $report['room']['code'] }}</h1>
            <p class="page-header__subtitle">
                {{ $report['section']['title'] }}
                @if ($report['section']['subject']) · {{ $report['section']['subject'] }} @endif
                @if ($report['section']['grade']) · {{ $report['section']['grade'] }} @endif
                · {{ $report['room']['mode'] === 'match' ? 'Partido' : 'Quiz' }}
                · {{ $report['players_count'] }} jugadores
            </p>
        </div>
        <div class="page-header__actions">
            @if ($room->status !== 'finished')
                <a class="btn btn--ghost" href="{{ route('rooms.host', $room) }}">Volver al proyector</a>
            @endif
            <a class="btn btn--primary" href="{{ route('dashboard') }}">Dashboard</a>
        </div>
    </div>

    @if ($report['match'])
        <div class="match match--compact" style="margin-bottom: 1.25rem;">
            <div class="match__side">
                <span class="match__name">{{ $report['match']['home']['name'] }}</span>
                <span class="match__goals">{{ $report['match']['home']['goals'] }}</span>
            </div>
            <div class="match__vs">–</div>
            <div class="match__side">
                <span class="match__goals">{{ $report['match']['away']['goals'] }}</span>
                <span class="match__name">{{ $report['match']['away']['name'] }}</span>
            </div>
        </div>
        @if ($report['match']['winner'])
            <p class="text--muted" style="margin-bottom: 1rem;">
                @if ($report['match']['winner'] === 'draw')
                    Resultado: empate
                @elseif ($report['match']['winner'] === 'home')
                    Ganador: {{ $report['match']['home']['name'] }}
                @else
                    Ganador: {{ $report['match']['away']['name'] }}
                @endif
            </p>
        @endif
    @endif

    <div class="host__grid">
        <section class="card">
            <h2>Aciertos por pregunta</h2>
            @forelse ($report['questions'] as $index => $question)
                <article class="report-question">
                    <header class="report-question__head">
                        <strong>{{ $index + 1 }}. {{ $question['prompt'] }}</strong>
                        <span class="badge {{ $question['percent_correct'] >= 70 ? 'badge--ok' : ($question['percent_correct'] >= 40 ? 'badge--mid' : 'badge--low') }}">
                            {{ $question['percent_correct'] }}% aciertos
                        </span>
                    </header>
                    <p class="text--muted">
                        {{ $question['correct'] }} / {{ $question['answered'] }} respuestas correctas
                    </p>
                    <ul class="report-question__answers">
                        @foreach ($question['answers'] as $answer)
                            <li class="{{ $answer['is_correct'] ? 'is-correct' : '' }}">
                                <span>{{ $answer['text'] }}</span>
                                <span>{{ $answer['count'] }} ({{ $answer['percent'] }}%)</span>
                            </li>
                        @endforeach
                    </ul>
                </article>
            @empty
                <p class="text--empty">Sin preguntas en esta sección.</p>
            @endforelse
        </section>

        <aside class="card">
            <h2>Ranking</h2>
            <ol class="scoreboard">
                @foreach ($report['players'] as $index => $player)
                    <li>
                        <span>
                            {{ $index + 1 }}. {{ $player['nickname'] }}
                            @if ($player['team'])
                                <span class="text--muted">({{ $player['team'] }})</span>
                            @endif
                        </span>
                        <strong>{{ $player['score'] }}</strong>
                    </li>
                @endforeach
            </ol>
        </aside>
    </div>
@endsection
