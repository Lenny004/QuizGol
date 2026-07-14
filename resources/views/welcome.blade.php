{{-- Landing pública: CTA para unirse o iniciar sesión como maestro. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QuizGol</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Titan+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <main class="landing">
        <h1 class="landing__brand">QuizGol</h1>
        <p class="landing__tagline">Quizzes en vivo con ritmo de partido. Enseña, compite y celebra cada acierto.</p>
        <div class="landing__cta">
            <a class="btn btn--gold" href="/login">Iniciar sesión</a>
            <a class="btn btn--secondary" href="/join">Unirse a sala</a>
        </div>
    </main>
</body>
</html>
