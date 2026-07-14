<?php

use App\Http\Controllers\Play\JoinController;
use App\Http\Controllers\Play\PlayController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Teacher\DashboardController;
use App\Http\Controllers\Teacher\HostController;
use App\Http\Controllers\Teacher\QuestionController;
use App\Http\Controllers\Teacher\RoomController;
use App\Http\Controllers\Teacher\SectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web de QuizGol
|--------------------------------------------------------------------------
|
| Públicas: landing, unirse y jugar (sin login).
| Maestro: dashboard, secciones, preguntas y control de salas (auth + teacher).
| Perfil: rutas Breeze estándar.
|
*/

Route::get('/', function () {
    return view('welcome');
});

// --- Jugadores (sin autenticación) ---
Route::get('/join', [JoinController::class, 'show'])->name('play.join');
Route::post('/join', [JoinController::class, 'store'])->name('play.join.store');
Route::get('/play/{code}', [PlayController::class, 'show'])->name('play.game');
Route::get('/play/{code}/state', [PlayController::class, 'state'])->name('play.state');
Route::post('/play/{code}/answer', [PlayController::class, 'answer'])->name('play.answer');

// --- Área del maestro (auth + middleware teacher) ---
Route::middleware(['auth', 'teacher'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Secciones = bancos de preguntas del maestro.
    Route::resource('sections', SectionController::class)->except(['show']);

    // Preguntas anidadas; shallow evita /sections/{section}/questions/{question} en edit/update/destroy.
    Route::resource('sections.questions', QuestionController::class)->shallow()->except(['show']);

    // Salas en vivo / pantalla del anfitrión.
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::get('/rooms/{room}/host', [HostController::class, 'show'])->name('rooms.host');
    Route::get('/rooms/{room}/state', [HostController::class, 'state'])->name('rooms.state');
    Route::post('/rooms/{room}/start', [RoomController::class, 'start'])->name('rooms.start');
    Route::post('/rooms/{room}/next', [RoomController::class, 'next'])->name('rooms.next');
    Route::post('/rooms/{room}/finish', [RoomController::class, 'finish'])->name('rooms.finish');
});

// --- Perfil (Breeze) ---
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
