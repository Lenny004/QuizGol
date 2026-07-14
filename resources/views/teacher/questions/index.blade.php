{{-- Lista de preguntas de una sección + acciones crear/editar/borrar. --}}
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
                <button type="submit" class="btn btn--primary" @disabled($questions->isEmpty())>Crear sala</button>
            </form>
            <a class="btn btn--gold" href="{{ route('sections.questions.create', $section) }}">Nueva pregunta</a>
        </div>
    </div>

    <div class="card">
        @forelse ($questions as $question)
            <div class="list__row list__row--stack">
                <div>
                    <strong>{{ $loop->iteration }}. {{ $question->prompt }}</strong>
                    <p class="text--muted">
                        {{ $question->time_limit }}s · {{ $question->points }} pts ·
                        {{ $question->answers->count() }} respuestas
                        @if ($question->difficultyLabel())
                            · {{ $question->difficultyLabel() }}
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
