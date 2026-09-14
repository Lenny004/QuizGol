# QuizGol

Quizzes en vivo con temática de fútbol: modo **quiz individual** o **partido de 2 equipos**.

Flujo de pregunta estilo Kahoot: **preguntar → revelar → siguiente** (timeout real en servidor, auto-revelación y auto-avance).

---

## Flujo para correr el proyecto (Windows)

### 0. Requisitos

1. Instala [Docker Desktop](https://www.docker.com/products/docker-desktop/) y **ábrelo** (icono de ballena en la bandeja; debe decir *Running*).
2. Abre una terminal en la carpeta del proyecto:

```powershell
cd D:\Lenny\Projects\QuizGol
```

> En este PC a veces funciona `docker-compose` y no `docker compose`. Usa el que te responda.

### 1. Arrancar contenedores

```powershell
docker-compose up -d --build
```

Si tu Docker acepta el plugin nuevo:

```powershell
docker compose up -d --build
```

Espera 30–60 s la primera vez (Composer + Postgres + seed).

Comprueba que estén arriba:

```powershell
docker-compose ps
```

Debes ver `quizgol_app`, `quizgol_nginx`, `quizgol_postgres`, `quizgol_mailpit` en estado **Up**.

### 2. Abrir la app

En el navegador: [http://localhost](http://localhost)

- Login maestro: [http://localhost/login](http://localhost/login)
- Unirse (alumno): [http://localhost/join](http://localhost/join)

### 3. Si la web no carga o da error de BD

Puerto 80 ocupado, BD vieja o contenedores a medias:

```powershell
docker-compose down
docker-compose up -d --build
docker-compose exec app php artisan migrate:fresh --force --seed
```

Luego vuelve a [http://localhost](http://localhost).

### 4. Credenciales demo

| Rol     | Email                | Contraseña | Tras login |
|---------|----------------------|------------|------------|
| Maestro | `maestro@quizgol.test` | `password` | Dashboard → Secciones |
| Admin   | `admin@quizgol.test`   | `password` | Igual (área teacher) |

El seeder deja una sección **Sumas y restas — Demo** (Matemáticas · 3° Primaria) con 4 preguntas.

---

## Flujo rápido: jugar una partida de prueba

Hazlo en **dos ventanas** del navegador (una = maestro / proyector, otra = alumno).

### A) Maestro

1. Entra a [http://localhost/login](http://localhost/login) con `maestro@quizgol.test` / `password`.
2. Ve a **Secciones** ([http://localhost/sections](http://localhost/sections)).
3. En la sección demo pulsa **Quiz individual** (o **Partido 2 equipos**).
4. Se abre el **proyector** con el código grande y el **QR**.
5. Deja esa pestaña abierta.

### B) Alumno

1. Abre una ventana de incógnito (o otro dispositivo en la misma red).
2. Escanea el QR **o** entra a [http://localhost/join](http://localhost/join) y escribe el código.
3. Pon un apodo.
   - En **quiz**: no pide equipo.
   - En **partido**: elige Local o Visitante.
4. Pulsa **Entrar al partido**.

### C) Jugar

1. En el proyector, pulsa **Iniciar**.
2. El alumno responde en su pantalla.
3. Al acabar el tiempo (o cuando todos respondan), se **revela** la correcta.
4. Tras ~8 s (o **Siguiente pregunta**) avanza.
5. Al terminar: **Ver reporte** (aciertos por pregunta + ranking).

---

## Comandos útiles

```powershell
# Ver logs si algo falla
docker-compose logs -f app

# Reiniciar solo la app
docker-compose restart app nginx

# Reset total de BD (borra datos y vuelve a sembrar)
docker-compose exec app php artisan migrate:fresh --force --seed

# Tests
docker-compose exec app php artisan test

# Smoke scripts
docker-compose exec app php scripts/verify_quiz_room.php
docker-compose exec app php scripts/verify_match_room.php

# Apagar
docker-compose down
```

---

## Cómo jugar (detalle)

### Quiz individual

1. Maestro → **Secciones** → **Quiz individual**.
2. Proyecta código + QR.
3. Alumnos en `/join` (solo apodo).
4. **Iniciar** → responder → **Revelar** / auto → **Siguiente**.
5. Gana quien tenga más puntos.

### Partido 2 equipos

1. **Partido 2 equipos** crea Local y Visitante.
2. Al unirse, el formulario detecta el modo y pide equipo.
3. Cada acierto suma **1 gol** al equipo + puntos personales.
4. Al final gana el equipo con más goles (o empate).

### Unirse (alumnos)

- `/join` o `/join?code=ABCD` (QR).
- Cookie `quizgol_player` (HttpOnly, SameSite=Lax, Secure en HTTPS, 7 días) para rejoin.

## Reporte pedagógico

Dashboard → **Reportes recientes**, o `/rooms/{id}/results` (solo anfitrión):

- Ranking
- % de aciertos por pregunta
- Desglose por opción
- Marcador del partido (si aplica)

## Visual / CSS (BEM)

Estilos en `public/css/app.css` (BEM). Tema fútbol: verde + crema + dorado, QR en lobby, respuestas tipo Kahoot. Auth/perfil Breeze usan Tailwind.

| Block | Uso |
|-------|-----|
| `host` / `join-panel` | Proyector + QR |
| `join-mode` / `team-picker` | Formulario de unión |
| `play` / `answer-grid` / `feedback` | Jugador |
| `match` / `scoreboard` | Marcadores |
| `report-question` | Reporte |

JS: `public/js/host.js`, `play.js`, `join.js`. QR: `public/js/qrcode.min.js`.

## Correo (Mailpit)

- UI: [http://localhost:8025](http://localhost:8025)
- SMTP interno: `mailpit:1025`

## Servicios Docker

| Servicio | Contenedor | Puerto | Notas |
|----------|------------|--------|-------|
| App PHP 8.4 | `quizgol_app` | 9000 (interno) | Laravel + FPM |
| Nginx | `quizgol_nginx` | **80** → localhost | Front |
| PostgreSQL 16 | `quizgol_postgres` | 5432 | user/db `quizgol` |
| Mailpit | `quizgol_mailpit` | 8025 / 1025 | Correo dev |

## Variables de entorno

Claves usadas por Docker (también en `.env` si existe):

- `DB_HOST=postgres`, `DB_DATABASE=quizgol`, `DB_USERNAME=quizgol`, `DB_PASSWORD=secret`
- `MAIL_HOST=mailpit`, `MAIL_PORT=1025`
- `APP_URL=http://localhost`

## Problemas frecuentes

| Síntoma | Qué hacer |
|---------|-----------|
| `docker compose` no existe | Usa `docker-compose` (con guion) |
| `localhost` no carga | ¿Docker Desktop corriendo? ¿Algo usa el puerto 80? |
| Error 500 / BD | `docker-compose exec app php artisan migrate:fresh --force --seed` |
| Login no entra | Credenciales de la tabla de arriba; si fallan, vuelve a sembrar |
| Contenedores “Restarting” | `docker-compose logs app` y revisa el error |

## Animaciones (Lottie)

Gol/fallo usan CSS (`feedback--goal` / `feedback--miss`). `public/lottie/` está reservado para Lottie reales más adelante.
