<section class="card card--form">
    <div class="panel__header">
        <div>
            <h2 class="panel__title">Eliminar cuenta</h2>
            <p class="text--muted">Esto borra tu usuario y no se puede deshacer. Escribe tu contraseña para confirmar.</p>
        </div>
    </div>

    <form method="post" action="{{ route('profile.destroy') }}" class="form">
        @csrf
        @method('delete')

        <label class="form__field">
            <span>Contraseña</span>
            <input class="form__input" id="password" type="password" name="password" autocomplete="current-password">
        </label>

        <div class="form__actions">
            <button type="submit" class="btn btn--danger">Eliminar cuenta</button>
        </div>
    </form>
</section>
