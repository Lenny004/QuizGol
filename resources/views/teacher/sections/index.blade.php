{{-- Listado de secciones del maestro con filtros por materia/grado. --}}
@extends('layouts.quizgol')

@section('title', 'Secciones — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Secciones</h1>
            <p class="page-header__subtitle">Organiza tus quizzes por materia y grado.</p>
        </div>
        <a class="btn btn--gold" href="{{ route('sections.create') }}">Nueva sección</a>
    </div>

    <form method="GET" action="{{ route('sections.index') }}" class="card card--form card--filters">
        <div class="form__grid">
            <label class="form__field">
                <span>Materia</span>
                <select class="form__input" name="subject_id" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected($subjectId == $subject->id)>
                            {{ $subject->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="form__field">
                <span>Grado</span>
                <select class="form__input" name="grade_id" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach ($grades as $grade)
                        <option value="{{ $grade->id }}" @selected($gradeId == $grade->id)>
                            {{ $grade->name }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>
        @if ($subjectId || $gradeId)
            <div class="form__actions">
                <a class="btn btn--ghost btn--sm" href="{{ route('sections.index') }}">Limpiar filtros</a>
            </div>
        @endif
    </form>

    <div class="card">
        @if ($sections->isEmpty())
            <p class="text--empty">No hay secciones todavía.</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Materia</th>
                        <th>Grado</th>
                        <th>Preguntas</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sections as $section)
                        <tr>
                            <td>{{ $section->title }}</td>
                            <td>{{ $section->subject->name }}</td>
                            <td>{{ $section->gradeLabel() ?: '—' }}</td>
                            <td>{{ $section->questions_count }}</td>
                            <td class="table__actions">
                                <form method="POST" action="{{ route('rooms.store') }}" class="form form--inline">
                                    @csrf
                                    <input type="hidden" name="section_id" value="{{ $section->id }}">
                                    <input type="hidden" name="mode" value="quiz">
                                    <button type="submit" class="btn btn--gold btn--sm" @disabled($section->questions_count < 1) title="Cada alumno suma puntos por su cuenta">Quiz individual</button>
                                </form>
                                <form method="POST" action="{{ route('rooms.store') }}" class="form form--inline">
                                    @csrf
                                    <input type="hidden" name="section_id" value="{{ $section->id }}">
                                    <input type="hidden" name="mode" value="match">
                                    <button type="submit" class="btn btn--primary btn--sm" @disabled($section->questions_count < 1) title="Dos equipos: Local vs Visitante">Partido 2 equipos</button>
                                </form>
                                <a class="btn btn--ghost btn--sm" href="{{ route('sections.questions.index', $section) }}">Preguntas</a>
                                <a class="btn btn--ghost btn--sm" href="{{ route('sections.edit', $section) }}">Editar</a>
                                <form method="POST" action="{{ route('sections.destroy', $section) }}" onsubmit="return confirm('¿Eliminar esta sección?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn--danger btn--sm">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
