{{-- Layout principal de QuizGol (maestro, host y play). Carga public/css/app.css (BEM). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'QuizGol')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Titan+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="app__shell">
        <header class="nav">
            <a class="nav__brand" href="{{ auth()->check() ? route('dashboard') : url('/') }}">QuizGol</a>
            <nav class="nav__links">
                @auth
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <a href="{{ route('sections.index') }}">Secciones</a>
                    <a href="{{ route('profile.edit') }}">Perfil</a>
                    <a href="{{ route('play.join') }}">Unirse</a>
                    <form method="POST" action="{{ route('logout') }}" class="nav__logout">
                        @csrf
                        <button type="submit" class="btn btn--ghost">Salir</button>
                    </form>
                @else
                    <a href="{{ route('play.join') }}">Unirse</a>
                    <a href="{{ route('login') }}">Iniciar sesión</a>
                @endauth
            </nav>
        </header>

        <main class="app__main">
            @if (session('success'))
                <div class="alert alert--success">{{ session('success') }}</div>
            @endif

            @if (session('info'))
                <div class="alert alert--info">{{ session('info') }}</div>
            @endif

            @if (session('status'))
                <div class="alert alert--success">
                    {{ match (session('status')) {
                        'profile-updated' => 'Perfil actualizado.',
                        'password-updated' => 'Contraseña actualizada.',
                        'verification-link-sent' => 'Te enviamos un nuevo enlace de verificación.',
                        default => session('status'),
                    } }}
                </div>
            @endif

            @php
                $errorMessages = collect($errors->getBags())->flatMap->all()->unique();
            @endphp
            @if ($errorMessages->isNotEmpty())
                <div class="alert alert--danger">
                    <ul class="alert__list">
                        @foreach ($errorMessages as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
