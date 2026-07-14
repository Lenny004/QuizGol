{{-- Formulario público para unirse a una sala con código + apodo (+ equipo si es partido). --}}
@extends('layouts.quizgol')

@section('title', 'Unirse a sala — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Unirse a una sala</h1>
            <p class="page-header__subtitle">Ingresa el código del partido y tu apodo. Si es un partido de 2 equipos, elige Local o Visitante.</p>
        </div>
    </div>

    <div class="card card--form">
        <form method="POST" action="{{ route('play.join.store') }}" class="form">
            @csrf

            <label class="form__field">
                <span>Código de sala</span>
                <input class="form__input" type="text" name="code" value="{{ old('code') }}" required maxlength="8" placeholder="Ej. GOL4" autocomplete="off">
            </label>

            <label class="form__field">
                <span>Apodo</span>
                <input class="form__input" type="text" name="nickname" value="{{ old('nickname') }}" required maxlength="40" placeholder="Tu nombre en el partido">
            </label>

            <label class="form__field">
                <span>Equipo <span class="text--muted">(obligatorio solo en partido 2 equipos)</span></span>
                <select class="form__input" name="team">
                    <option value="">— No aplica / quiz —</option>
                    <option value="home" @selected(old('team') === 'home')>Local</option>
                    <option value="away" @selected(old('team') === 'away')>Visitante</option>
                </select>
            </label>

            <div class="form__actions">
                <button type="submit" class="btn btn--gold">Entrar</button>
            </div>
        </form>
    </div>
@endsection
