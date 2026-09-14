{{-- Solicitud de enlace para restablecer contraseña. --}}
@extends('layouts.quizgol')

@section('title', 'Recuperar contraseña — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Recuperar contraseña</h1>
            <p class="page-header__subtitle">Escribe tu correo y te enviamos un enlace para elegir una nueva.</p>
        </div>
    </div>

    <div class="card card--form">
        <form method="POST" action="{{ route('password.email') }}" class="form">
            @csrf

            <label class="form__field">
                <span>Correo</span>
                <input class="form__input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </label>

            <div class="form__actions">
                <button type="submit" class="btn btn--gold">Enviar enlace</button>
                <a href="{{ route('login') }}">Volver al login</a>
            </div>
        </form>
    </div>
@endsection
