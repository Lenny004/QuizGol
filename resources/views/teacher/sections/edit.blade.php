@extends('layouts.quizgol')

@section('title', 'Editar sección — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Editar sección</h1>
            <p class="page-subtitle">{{ $section->title }}</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('sections.index') }}">Volver</a>
    </div>

    <div class="card card-form">
        <form method="POST" action="{{ route('sections.update', $section) }}" class="form">
            @csrf
            @method('PUT')

            <label class="field">
                <span>Título</span>
                <input class="input" type="text" name="title" value="{{ old('title', $section->title) }}" required maxlength="255">
            </label>

            <label class="field">
                <span>Materia</span>
                <select class="input" name="subject_id" required>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected(old('subject_id', $section->subject_id) == $subject->id)>
                            {{ $subject->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Grado (opcional)</span>
                <input class="input" type="text" name="grade" value="{{ old('grade', $section->grade) }}" maxlength="50">
            </label>

            <div class="form-actions">
                <button type="submit" class="btn btn-gold">Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection
