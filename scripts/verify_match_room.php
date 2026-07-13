<?php

/**
 * Verificación E2E del modo partido (2 equipos).
 * Ejecutar en Docker:
 *   docker compose exec app php scripts/verify_match_room.php
 */

use App\Models\Section;
use App\Models\User;
use App\Services\MatchGameService;
use App\Services\QuizRoomService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$matchService = app(MatchGameService::class);
$quizService = app(QuizRoomService::class);

$teacher = User::query()->where('email', 'maestro@quizgol.test')->firstOrFail();
$section = Section::query()->where('user_id', $teacher->id)->with('questions.answers')->firstOrFail();

if ($section->questions->count() < 1) {
    fwrite(STDERR, "FAIL: la sección no tiene preguntas\n");
    exit(1);
}

echo "OK teacher={$teacher->email} section={$section->id} questions={$section->questions->count()}\n";

$room = $matchService->createRoom($teacher, $section);
$room->load(['teams', 'matchGame']);

if ($room->mode !== 'match') {
    fwrite(STDERR, "FAIL: mode esperado match\n");
    exit(1);
}
if ($room->teams->count() !== 2 || ! $room->matchGame) {
    fwrite(STDERR, "FAIL: se esperaban 2 teams + MatchGame\n");
    exit(1);
}

$home = $room->teams->firstWhere('side', 'home');
$away = $room->teams->firstWhere('side', 'away');
echo "OK room match code={$room->code} home={$home->name} away={$away->name}\n";

$pHome = $matchService->createPlayer($room, 'LocalTest', 'home');
$pAway = $matchService->createPlayer($room, 'AwayTest', 'away');

if ((int) $pHome->team_id !== (int) $home->id || (int) $pAway->team_id !== (int) $away->id) {
    fwrite(STDERR, "FAIL: team_id incorrecto al unirse\n");
    exit(1);
}
echo "OK players joined teams home/away\n";

// Sin team debe fallar
try {
    $matchService->createPlayer($room, 'SinEquipo', null);
    fwrite(STDERR, "FAIL: debió exigir equipo\n");
    exit(1);
} catch (\Illuminate\Validation\ValidationException $e) {
    echo "OK rejected join without team\n";
}

$quizService->start($room->fresh());
$room->refresh();
$room->load('matchGame');
echo "OK started status={$room->status} match_status={$room->matchGame->status}\n";

$question = $room->currentQuestion()->with('answers')->first();
$correct = $question->answers->firstWhere('is_correct', true);

if (! $correct) {
    fwrite(STDERR, "FAIL: no hay respuesta correcta\n");
    exit(1);
}

$quizService->submitAnswer($pHome, $room, $correct->id);
$quizService->submitAnswer($pAway, $room, $correct->id);

$home->refresh();
$away->refresh();
$pHome->refresh();
$pAway->refresh();

echo "OK goals home={$home->goals} away={$away->goals} scores={$pHome->score}/{$pAway->score}\n";

if ((int) $home->goals !== 1 || (int) $away->goals !== 1) {
    fwrite(STDERR, "FAIL: esperado marcador 1-1 tras dos aciertos\n");
    exit(1);
}

$hostState = $quizService->buildHostState($room->fresh());
$playerState = $quizService->buildPlayerState($room->fresh(), $pHome->fresh());

if (! isset($hostState['match']['home']['goals'], $hostState['match']['away']['goals'])) {
    fwrite(STDERR, "FAIL: host state sin match payload\n");
    exit(1);
}
if ((int) $hostState['match']['home']['goals'] !== 1 || (int) $hostState['match']['away']['goals'] !== 1) {
    fwrite(STDERR, "FAIL: host match goals incorrectos\n");
    exit(1);
}
if (($playerState['my_team']['side'] ?? null) !== 'home') {
    fwrite(STDERR, "FAIL: player my_team esperado home\n");
    exit(1);
}
echo "OK match payloads host/player\n";

$quizService->finish($room->fresh());
$room->refresh();
$final = $quizService->buildHostState($room);

if ($final['match']['winner'] !== 'draw') {
    fwrite(STDERR, "FAIL: winner esperado draw, got ".json_encode($final['match']['winner'])."\n");
    exit(1);
}
if ($room->status !== 'finished') {
    fwrite(STDERR, "FAIL: room no finished\n");
    exit(1);
}

echo "OK finished winner={$final['match']['winner']} goals={$final['match']['home']['goals']}-{$final['match']['away']['goals']}\n";
echo "PASS match room MVP verification\n";
