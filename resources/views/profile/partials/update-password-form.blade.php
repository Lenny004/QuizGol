<section class="card card--form">
    <div class="panel__header">
        <div>
            <h2 class="panel__title">Cambiar contraseña</h2>
            <p class="text--muted">Usa una contraseña larga y difícil de adivinar.</p>
        </div>
    </div>

    <form method="post" action="{{ route('password.update') }}" class="form">
        @csrf
        @method('put')

        <label class="form__field">
            <span>Contraseña actual</span>
            <input class="form__input" id="update_password_current_password" type="password" name="current_password" autocomplete="current-password">
        </label>

        <label class="form__field">
            <span>Nueva contraseña</span>
            <input class="form__input" id="update_password_password" type="password" name="password" autocomplete="new-password">
        </label>

        <label class="form__field">
            <span>Confirmar nueva contraseña</span>
            <input class="form__input" id="update_password_password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
        </label>

        <div class="form__actions">
            <button type="submit" class="btn btn--gold">Guardar contraseña</button>
        </div>
    </form>
</section>
