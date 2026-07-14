{{-- Lista de preguntas de una sección + acciones crear/editar/borrar. --}}
@extends('layouts.quizgol')

@section('title', 'Preguntas — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $section->title }}</h1>
            <p class="page-subtitle">
                {{ $section->subject->name }}
                @if ($section->gradeLabel())
                    · {{ $section->gradeLabel() }}
                @endif
            </p>
        </div>
        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('sections.index') }}">Secciones</a>
            <form method="POST" action="{{ route('rooms.store') }}">
                @csrf
                <input type="hidden" name="section_id" value="{{ $section->id }}">
                <button type="submit" class="btn btn-primary" @disabled($questions->isEmpty())>Crear sala</button>
            </form>
            <a class="btn btn-gold" href="{{ route('sections.questions.create', $section) }}">Nueva pregunta</a>
        </div>
    </div>

    <div class="card">
        @forelse ($questions as $question)
            <div class="list-row list-row-stack">
                <div>
                    <strong>{{ $loop->iteration }}. {{ $question->prompt }}</strong>
                    <p class="muted">
                        {{ $question->time_limit }}s · {{ $question->points }} pts ·
                        {{ $question->answers->count() }} respuestas
                        @if ($question->difficultyLabel())
                            · {{ $question->difficultyLabel() }}
                        @endif
                    </p>
                    <ul class="answer-preview">
                        @foreach ($question->answers as $answer)
                            <li class="{{ $answer->is_correct ? 'is-correct' : '' }}">
                                {{ $answer->text }}
                                @if ($answer->is_correct)
                                    <span class="badge">Correcta</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="table-actions">
                    <a class="btn btn-ghost btn-sm" href="{{ route('questions.edit', $question) }}">Editar</a>
                    <form method="POST" action="{{ route('questions.destroy', $question) }}" onsubmit="return confirm('¿Eliminar esta pregunta?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="empty">Esta sección aún no tiene preguntas.</p>
        @endforelse
    </div>
@endsection
