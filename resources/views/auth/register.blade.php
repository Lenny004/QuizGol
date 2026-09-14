{{-- Registro de maestros. --}}
@extends('layouts.quizgol')

@section('title', 'Crear cuenta — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Crear cuenta</h1>
            <p class="page-header__subtitle">Regístrate para armar secciones y lanzar quizzes en vivo.</p>
        </div>
    </div>

    <div class="card card--form">
        <form method="POST" action="{{ route('register') }}" class="form">
            @csrf

            <label class="form__field">
                <span>Nombre</span>
                <input class="form__input" id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            </label>

            <label class="form__field">
                <span>Correo</span>
                <input class="form__input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
            </label>

            <label class="form__field">
                <span>Contraseña</span>
                <input class="form__input" id="password" type="password" name="password" required autocomplete="new-password">
            </label>

            <label class="form__field">
                <span>Confirmar contraseña</span>
                <input class="form__input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </label>

            <div class="form__actions">
                <button type="submit" class="btn btn--gold">Registrarme</button>
                <a href="{{ route('login') }}">Ya tengo cuenta</a>
            </div>
        </form>
    </div>
@endsection
