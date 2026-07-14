{{-- Editar pregunta y sus respuestas. --}}
@extends('layouts.quizgol')

@section('title', 'Editar pregunta — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Editar pregunta</h1>
            <p class="page-header__subtitle">Sección: {{ $section->title }}</p>
        </div>
        <a class="btn btn--ghost" href="{{ route('sections.questions.index', $section) }}">Volver</a>
    </div>

    <div class="card card--form">
        <form method="POST" action="{{ route('questions.update', $question) }}" class="form">
            @csrf
            @method('PUT')
            @include('teacher.questions._form')
            <div class="form__actions">
                <button type="submit" class="btn btn--gold">Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection
