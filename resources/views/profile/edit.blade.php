{{-- Perfil del maestro: datos, contraseña y baja. --}}
@extends('layouts.quizgol')

@section('title', 'Perfil — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Perfil</h1>
            <p class="page-header__subtitle">Actualiza tus datos o cambia la contraseña.</p>
        </div>
    </div>

    <div class="stack">
        @include('profile.partials.update-profile-information-form')
        @include('profile.partials.update-password-form')
        @include('profile.partials.delete-user-form')
    </div>
@endsection
