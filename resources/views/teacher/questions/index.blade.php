{{-- Lista de preguntas de una sección + equilibrio de la evaluación. --}}
@extends('layouts.quizgol')

@section('title', 'Preguntas — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">{{ $section->title }}</h1>
            <p class="page-header__subtitle">
                {{ $section->subject->name }}
                @if ($section->gradeLabel())
                    · {{ $section->gradeLabel() }}
                @endif
            </p>
        </div>
        <div class="page-header__actions">
            <a class="btn btn--ghost" href="{{ route('sections.index') }}">Secciones</a>
            <form method="POST" action="{{ route('rooms.store') }}">
                @csrf
                <input type="hidden" name="section_id" value="{{ $section->id }}">
                <button
                    type="submit"
                    class="btn btn--primary"
                    @disabled(! $balance['balanced'])
                    title="{{ $balance['balanced'] ? 'Crear sala de quiz' : 'Equilibra la evaluación para poder crear una sala' }}"
                >Crear sala</button>
            </form>
            <a class="btn btn--gold" href="{{ route('sections.questions.create', $section) }}">Nueva pregunta</a>
        </div>
    </div>

    <section class="balance {{ $balance['balanced'] ? 'balance--ok' : 'balance--warn' }}">
        <div class="balance__header">
            <h2 class="balance__title">Equilibrio de la evaluación</h2>
            <span class="badge {{ $balance['balanced'] ? 'badge--ok' : 'badge--low' }}">
                {{ $balance['balanced'] ? 'Lista para jugar' : 'Pendiente' }}
            </span>
        </div>
        <p class="balance__lead">
            Una sesión válida mezcla las tres dificultades y ninguna puede superar el 50% del banco.
            Puntaje máximo si se acierta todo al instante: <strong>{{ $balance['max_score'] }}</strong> pts.
        </p>
        <div class="balance__stats">
            @foreach (\App\Models\Question::DIFFICULTIES as $value => $label)
                <div class="balance__stat">
                    <span class="badge badge--{{ $value }}">{{ $label }}</span>
                    <strong>{{ $balance['counts'][$value] }}</strong>
                    <span class="text--muted">{{ $balance['shares'][$value] }}%</span>
                </div>
            @endforeach
        </div>
        @if (! $balance['balanced'])
            <ul class="balance__issues">
                @foreach ($balance['issues'] as $issue)
                    <li>{{ $issue }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="card">
        @forelse ($questions as $question)
            <div class="list__row list__row--stack">
                <div>
                    <strong>{{ $loop->iteration }}. {{ $question->prompt }}</strong>
                    <p class="text--muted">
                        {{ $question->time_limit }}s · {{ $question->points }} pts ·
                        {{ $question->answers->count() }} respuestas
                        @if ($question->difficultyLabel())
                            · <span class="badge badge--{{ $question->difficulty }}">{{ $question->difficultyLabel() }}</span>
                        @else
                            · <span class="badge badge--low">Sin dificultad</span>
                        @endif
                    </p>
                    <ul class="list__answers">
                        @foreach ($question->answers as $answer)
                            <li class="list__answer {{ $answer->is_correct ? 'list__answer--correct' : '' }}">
                                {{ $answer->text }}
                                @if ($answer->is_correct)
                                    <span class="badge">Correcta</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="table__actions">
                    <a class="btn btn--ghost btn--sm" href="{{ route('questions.edit', $question) }}">Editar</a>
                    <form method="POST" action="{{ route('questions.destroy', $question) }}" onsubmit="return confirm('¿Eliminar esta pregunta?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--danger btn--sm">Eliminar</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text--empty">Esta sección aún no tiene preguntas.</p>
        @endforelse
    </div>
@endsection
