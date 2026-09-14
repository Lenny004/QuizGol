{{-- Formulario de nueva contraseña (enlace del correo). --}}
@extends('layouts.quizgol')

@section('title', 'Nueva contraseña — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Nueva contraseña</h1>
            <p class="page-header__subtitle">Elige una contraseña nueva para tu cuenta de maestro.</p>
        </div>
    </div>

    <div class="card card--form">
        <form method="POST" action="{{ route('password.store') }}" class="form">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <label class="form__field">
                <span>Correo</span>
                <input class="form__input" id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
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
                <button type="submit" class="btn btn--gold">Guardar contraseña</button>
            </div>
        </form>
    </div>
@endsection
