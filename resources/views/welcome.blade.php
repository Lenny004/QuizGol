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
    <style>
        .landing {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            text-align: center;
        }
        .brand {
            font-family: var(--font-display);
            font-size: clamp(3rem, 12vw, 5.5rem);
            letter-spacing: 0.02em;
            color: var(--color-gold);
            text-shadow: 0 4px 0 rgba(0, 0, 0, 0.35);
        }
        .tagline {
            margin-top: 0.75rem;
            max-width: 28rem;
            font-size: 1.125rem;
            color: var(--color-cream);
            opacity: 0.92;
        }
        .cta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        .btn {
            display: inline-block;
            padding: 0.85rem 1.6rem;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
            transition: transform 0.15s ease, background 0.15s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-primary {
            background: var(--color-gold);
            color: var(--color-navy);
        }
        .btn-secondary {
            background: transparent;
            color: var(--color-cream);
            border: 2px solid var(--color-gold);
        }
    </style>
</head>
<body>
    <main class="landing">
        <h1 class="brand">QuizGol</h1>
        <p class="tagline">Quizzes en vivo con ritmo de partido. Enseña, compite y celebra cada acierto.</p>
        <div class="cta">
            <a class="btn btn-primary" href="/login">Iniciar sesión</a>
            <a class="btn btn-secondary" href="/join">Unirse a sala</a>
        </div>
    </main>
</body>
</html>

