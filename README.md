# QuizGol

Quizzes en vivo con temática de fútbol: modo **quiz individual** o **partido de 2 equipos**.

## Requisitos

Solo necesitas [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado y en ejecución.

PHP, Composer, PostgreSQL y Node **no** se instalan en el host: todo corre en contenedores (PHP 8.4 + PostgreSQL 16).

## Arrancar

Desde la raíz del proyecto:

```bash
docker compose up -d --build
```

Abre la app en [http://localhost](http://localhost).

La primera vez, las migraciones y el seeder se ejecutan al levantar el contenedor `app` (si `RUN_MIGRATIONS=true`). Si hace falta sembrar de nuevo:

```bash
docker compose exec app php artisan migrate --force --seed
```

## Credenciales de demo

| Rol     | Email                | Contraseña |
|---------|----------------------|------------|
| Admin   | admin@quizgol.test   | password   |
| Maestro | maestro@quizgol.test | password   |

## Cómo jugar

### Quiz individual

1. Entra como maestro → **Secciones**.
2. En una sección con preguntas, pulsa **Quiz individual**.
3. Proyecta el código de sala.
4. Los alumnos van a `/join`, ponen código + apodo (sin equipo).
5. El maestro pulsa **Iniciar**, avanza con **Siguiente pregunta** y **Finalizar**.
6. Gana quien acumule más puntos personales.

### Partido 2 equipos

1. En **Secciones**, pulsa **Partido 2 equipos**.
2. Se crean equipos Local y Visitante.
3. Al unirse (`/join`), el alumno elige equipo (Local o Visitante).
4. Mismo flujo de preguntas: cada acierto suma **1 gol** al equipo y puntos personales.
5. Al finalizar, gana el equipo con más goles (o empate).

## Correo (Mailpit)

Los correos de desarrollo se capturan en Mailpit:

- Interfaz web: [http://localhost:8025](http://localhost:8025)
- SMTP interno del compose: host `mailpit`, puerto `1025`

## Artisan vía Docker

```bash
docker compose exec app php artisan migrate --force --seed
docker compose exec app php artisan route:list
docker compose exec app php artisan tinker
```

Verificación rápida de salas:

```bash
docker compose exec app php scripts/verify_quiz_room.php
docker compose exec app php scripts/verify_match_room.php
```

## Servicios Docker

| Servicio   | Contenedor         | Puerto(s)        | Notas                          |
|------------|--------------------|------------------|--------------------------------|
| App (PHP 8.4) | quizgol_app     | 9000 (interno)   | Laravel + PHP-FPM              |
| Nginx      | quizgol_nginx      | 80 → http://localhost | Front HTTP                |
| PostgreSQL 16 | quizgol_postgres | 5432           | Usuario/DB: `quizgol`          |
| Mailpit    | quizgol_mailpit    | 8025, 1025       | UI en :8025                    |

## Animaciones (Lottie)

Las animaciones de gol/fallo usan CSS (`goal-burst` / `miss-shake`) como placeholder. La carpeta `public/lottie/` está reservada por si más adelante se añaden archivos Lottie reales.

## Variables de entorno

Copia `.env.example` a `.env` dentro del flujo Docker (o deja que el contenedor lo genere). Valores clave:

- `DB_HOST=postgres`, `DB_DATABASE=quizgol`, `DB_USERNAME=quizgol`, `DB_PASSWORD=secret`
- `MAIL_HOST=mailpit`, `MAIL_PORT=1025`
- `APP_URL=http://localhost`
