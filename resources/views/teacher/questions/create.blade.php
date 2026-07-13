@extends('layouts.quizgol')

@section('title', 'Nueva pregunta — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Nueva pregunta</h1>
            <p class="page-subtitle">Sección: {{ $section->title }}</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('sections.questions.index', $section) }}">Volver</a>
    </div>

    <div class="card card-form">
        <form method="POST" action="{{ route('sections.questions.store', $section) }}" class="form">
            @csrf
            @include('teacher.questions._form')
            <div class="form-actions">
                <button type="submit" class="btn btn-gold">Crear pregunta</button>
            </div>
        </form>
    </div>
@endsection
