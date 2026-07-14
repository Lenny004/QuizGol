{{-- Formulario para editar una sección existente. --}}
@extends('layouts.quizgol')

@section('title', 'Editar sección — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Editar sección</h1>
            <p class="page-header__subtitle">{{ $section->title }}</p>
        </div>
        <a class="btn btn--ghost" href="{{ route('sections.index') }}">Volver</a>
    </div>

    <div class="card card--form">
        <form method="POST" action="{{ route('sections.update', $section) }}" class="form">
            @csrf
            @method('PUT')

            <label class="form__field">
                <span>Título</span>
                <input class="form__input" type="text" name="title" value="{{ old('title', $section->title) }}" required maxlength="255">
                @error('title') <span class="form__error">{{ $message }}</span> @enderror
            </label>

            <label class="form__field">
                <span>Materia</span>
                <select class="form__input" name="subject_id" required>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected(old('subject_id', $section->subject_id) == $subject->id)>
                            {{ $subject->name }}
                        </option>
                    @endforeach
                </select>
                @error('subject_id') <span class="form__error">{{ $message }}</span> @enderror
            </label>

            <label class="form__field">
                <span>Grado</span>
                <select class="form__input" name="grade_id">
                    <option value="">Sin grado</option>
                    @foreach ($grades as $grade)
                        <option value="{{ $grade->id }}" @selected(old('grade_id', $section->grade_id) == $grade->id)>
                            {{ $grade->name }}
                        </option>
                    @endforeach
                </select>
                @error('grade_id') <span class="form__error">{{ $message }}</span> @enderror
            </label>

            <div class="form__actions">
                <button type="submit" class="btn btn--gold">Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection
