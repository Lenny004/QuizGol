{{-- Formulario público para unirse a una sala. --}}
@extends('layouts.quizgol')

@section('title', 'Unirse a sala — QuizGol')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Unirse a una sala</h1>
            <p class="page-header__subtitle">Escanea el QR del proyector o escribe el código. Sin cuenta, solo tu apodo.</p>
        </div>
    </div>

    <div class="card card--form" id="join-app" data-lookup-url="{{ route('play.join.lookup') }}">
        <div class="join-mode" id="join-mode-banner" hidden>
            <span class="join-mode__pill" id="join-mode-pill"></span>
            <span class="text--muted" id="join-mode-hint"></span>
        </div>

        <form method="POST" action="{{ route('play.join.store') }}" class="form" id="join-form">
            @csrf

            <label class="form__field">
                <span>Código de sala</span>
                <input
                    class="form__input form__input--code"
                    type="text"
                    name="code"
                    id="join-code"
                    value="{{ old('code', $prefillCode) }}"
                    required
                    maxlength="8"
                    placeholder="Ej. GOL4"
                    autocomplete="off"
                    autocapitalize="characters"
                >
            </label>

            <label class="form__field">
                <span>Tu apodo</span>
                <input class="form__input" type="text" name="nickname" value="{{ old('nickname') }}" required maxlength="40" placeholder="Cómo te ven en el marcador">
            </label>

            <div class="form__field" id="join-team-field" hidden>
                <span>Elige tu equipo</span>
                <div class="team-picker">
                    <label class="team-picker__option team-picker__option--home">
                        <input type="radio" name="team" value="home" @checked(old('team') === 'home')>
                        <span>Local</span>
                    </label>
                    <label class="team-picker__option team-picker__option--away">
                        <input type="radio" name="team" value="away" @checked(old('team') === 'away')>
                        <span>Visitante</span>
                    </label>
                </div>
                <p class="text--muted" id="join-team-help">Obligatorio en partido de 2 equipos.</p>
            </div>

            <div class="form__actions">
                <button type="submit" class="btn btn--gold">Entrar al partido</button>
            </div>
        </form>
    </div>

    <script src="{{ asset('js/join.js') }}" defer></script>
@endsection
