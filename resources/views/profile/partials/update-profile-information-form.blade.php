<section class="card card--form">
    <div class="panel__header">
        <div>
            <h2 class="panel__title">Tus datos</h2>
            <p class="text--muted">Nombre y correo de la cuenta de maestro.</p>
        </div>
    </div>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="form">
        @csrf
        @method('patch')

        <label class="form__field">
            <span>Nombre</span>
            <input class="form__input" id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
        </label>

        <label class="form__field">
            <span>Correo</span>
            <input class="form__input" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
        </label>

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <p class="text--muted">
                Tu correo no está verificado.
                <button form="send-verification" class="btn btn--ghost btn--sm">Reenviar verificación</button>
            </p>
        @endif

        <div class="form__actions">
            <button type="submit" class="btn btn--gold">Guardar</button>
        </div>
    </form>
</section>
