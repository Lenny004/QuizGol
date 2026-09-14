{{-- Confirmación de contraseña (zona segura). --}}
@extends('layouts.quizgol')

@section('title', 'Confirmar contraseña — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Confirmar contraseña</h1>
            <p class="page-header__subtitle">Zona segura: confirma tu contraseña para continuar.</p>
        </div>
    </div>

    <div class="card card--form">
        <form method="POST" action="{{ route('password.confirm') }}" class="form">
            @csrf

            <label class="form__field">
                <span>Contraseña</span>
                <input class="form__input" id="password" type="password" name="password" required autocomplete="current-password">
            </label>

            <div class="form__actions">
                <button type="submit" class="btn btn--gold">Confirmar</button>
            </div>
        </form>
    </div>
@endsection
