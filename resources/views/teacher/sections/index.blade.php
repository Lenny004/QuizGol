@extends('layouts.quizgol')

@section('title', 'Secciones — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Secciones</h1>
            <p class="page-subtitle">Organiza tus quizzes por materia y grado.</p>
        </div>
        <a class="btn btn-gold" href="{{ route('sections.create') }}">Nueva sección</a>
    </div>

    <div class="card">
        @if ($sections->isEmpty())
            <p class="empty">No hay secciones todavía.</p>
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
                            <td>{{ $section->grade ?: '—' }}</td>
                            <td>{{ $section->questions_count }}</td>
                            <td class="table-actions">
                                <form method="POST" action="{{ route('rooms.store') }}" class="inline-form">
                                    @csrf
                                    <input type="hidden" name="section_id" value="{{ $section->id }}">
                                    <input type="hidden" name="mode" value="quiz">
                                    <button type="submit" class="btn btn-gold btn-sm" @disabled($section->questions_count < 1) title="Cada alumno suma puntos por su cuenta">Quiz individual</button>
                                </form>
                                <form method="POST" action="{{ route('rooms.store') }}" class="inline-form">
                                    @csrf
                                    <input type="hidden" name="section_id" value="{{ $section->id }}">
                                    <input type="hidden" name="mode" value="match">
                                    <button type="submit" class="btn btn-primary btn-sm" @disabled($section->questions_count < 1) title="Dos equipos: Local vs Visitante">Partido 2 equipos</button>
                                </form>
                                <a class="btn btn-ghost btn-sm" href="{{ route('sections.questions.index', $section) }}">Preguntas</a>
                                <a class="btn btn-ghost btn-sm" href="{{ route('sections.edit', $section) }}">Editar</a>
                                <form method="POST" action="{{ route('sections.destroy', $section) }}" onsubmit="return confirm('¿Eliminar esta sección?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
