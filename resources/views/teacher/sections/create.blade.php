@extends('layouts.quizgol')

@section('title', 'Nueva sección — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Nueva sección</h1>
            <p class="page-subtitle">Elige materia y título para tu quiz.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('sections.index') }}">Volver</a>
    </div>

    <div class="card card-form">
        <form method="POST" action="{{ route('sections.store') }}" class="form">
            @csrf

            <label class="field">
                <span>Título</span>
                <input class="input" type="text" name="title" value="{{ old('title') }}" required maxlength="255">
            </label>

            <label class="field">
                <span>Materia</span>
                <select class="input" name="subject_id" required>
                    <option value="">Selecciona una materia</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>
                            {{ $subject->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Grado (opcional)</span>
                <input class="input" type="text" name="grade" value="{{ old('grade') }}" maxlength="50" placeholder="Ej. 5to">
            </label>

            <div class="form-actions">
                <button type="submit" class="btn btn-gold">Crear sección</button>
            </div>
        </form>
    </div>
@endsection
