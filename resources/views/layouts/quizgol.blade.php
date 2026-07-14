{{-- Layout principal de QuizGol (maestro, host y play). Carga public/css/app.css. --}}
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
    <div class="app-shell">
        <header class="nav">
            <a class="nav-brand" href="{{ auth()->check() ? route('dashboard') : url('/') }}">QuizGol</a>
            <nav class="nav-links">
                @auth
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <a href="{{ route('sections.index') }}">Secciones</a>
                    <a href="{{ route('play.join') }}">Unirse</a>
                    <form method="POST" action="{{ route('logout') }}" class="nav-logout">
                        @csrf
                        <button type="submit" class="btn btn-ghost">Salir</button>
                    </form>
                @else
                    <a href="{{ route('play.join') }}">Unirse</a>
                    <a href="{{ route('login') }}">Iniciar sesión</a>
                @endauth
            </nav>
        </header>

        <main class="container">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if (session('info'))
                <div class="alert alert-info">{{ session('info') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="alert-list">
                        @foreach ($errors->all() as $error)
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
