{{-- Login de maestros (BEM, mismo look que el resto de QuizGol). --}}
@extends('layouts.quizgol')

@section('title', 'Iniciar sesión — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Iniciar sesión</h1>
            <p class="page-header__subtitle">Entra como maestro para crear secciones y dirigir el partido.</p>
        </div>
    </div>

    <div class="stack">
        <div class="card card--form">
            <form method="POST" action="{{ route('login') }}" class="form">
                @csrf

                <label class="form__field">
                    <span>Correo</span>
                    <input
                        class="form__input"
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                    >
                </label>

                <label class="form__field">
                    <span>Contraseña</span>
                    <input
                        class="form__input"
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                    >
                </label>

                <label class="form__check">
                    <input id="remember_me" type="checkbox" name="remember">
                    <span>Recuérdame</span>
                </label>

                <div class="form__actions">
                    <button type="submit" class="btn btn--gold">Entrar</button>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                    @endif
                </div>
            </form>
        </div>

        @if (Route::has('register'))
            <p class="text--muted">
                ¿Aún no tienes cuenta?
                <a class="panel__link" href="{{ route('register') }}">Regístrate</a>
            </p>
        @endif
    </div>
@endsection
