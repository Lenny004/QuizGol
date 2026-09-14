{{-- Aviso de verificación de correo. --}}
@extends('layouts.quizgol')

@section('title', 'Verifica tu correo — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Verifica tu correo</h1>
            <p class="page-header__subtitle">Revisa tu bandeja y pulsa el enlace que te enviamos. Si no llegó, te mandamos otro.</p>
        </div>
    </div>

    <div class="card card--form">
        <div class="form__actions">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn--gold">Reenviar correo</button>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn--ghost">Salir</button>
            </form>
        </div>
    </div>
@endsection
