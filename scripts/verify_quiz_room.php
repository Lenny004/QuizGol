<?php

/**
 * Script de verificación E2E del MVP de salas (quiz).
 * Ejecutar: php artisan tinker < scripts/verify_quiz_room.php
 * o: php scripts/verify_quiz_room.php (vía docker)
 */

use App\Models\Answer;
use App\Models\Question;
use App\Models\Room;
use App\Models\Section;
use App\Models\User;
use App\Services\QuizRoomService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$service = app(QuizRoomService::class);

$teacher = User::query()->where('email', 'maestro@quizgol.test')->firstOrFail();
$section = Section::query()->where('user_id', $teacher->id)->with('questions.answers')->firstOrFail();

if ($section->questions->count() < 1) {
    fwrite(STDERR, "FAIL: la sección no tiene preguntas\n");
    exit(1);
}

echo "OK teacher={$teacher->email} section={$section->id} questions={$section->questions->count()}\n";

$room = $service->createRoom($teacher, $section);
echo "OK room created code={$room->code} status={$room->status}\n";

$player = $service->createPlayer($room, 'AnaTest');
echo "OK player joined nickname={$player->nickname} token={$player->session_token}\n";

$service->start($room->fresh());
$room->refresh();
echo "OK started current_question={$room->current_question_id} status={$room->status}\n";

$question = $room->currentQuestion()->with('answers')->first();
$correct = $question->answers->firstWhere('is_correct', true);
$wrong = $question->answers->firstWhere('is_correct', false);

if (! $correct) {
    fwrite(STDERR, "FAIL: no hay respuesta correcta\n");
    exit(1);
}

$pa = $service->submitAnswer($player, $room, $correct->id);
$player->refresh();
echo "OK answer correct points={$pa->points_awarded} score={$player->score} is_correct=".($pa->is_correct ? '1' : '0')."\n";

// Segunda respuesta debe fallar
try {
    $service->submitAnswer($player, $room, $correct->id);
    fwrite(STDERR, "FAIL: debió rechazar doble respuesta\n");
    exit(1);
} catch (\Illuminate\Validation\ValidationException $e) {
    echo "OK rejected duplicate answer\n";
}

$playerState = $service->buildPlayerState($room->fresh(), $player->fresh());
$hostState = $service->buildHostState($room->fresh());

if ($playerState['phase'] !== 'reveal' || empty($playerState['my_answer'])) {
    fwrite(STDERR, "FAIL: player phase esperado reveal\n");
    exit(1);
}
if (! isset($hostState['answered_count']) || $hostState['answered_count'] < 1) {
    fwrite(STDERR, "FAIL: host answered_count\n");
    exit(1);
}
// Jugador no debe ver is_correct en opciones
foreach ($playerState['question']['answers'] as $a) {
    if (array_key_exists('is_correct', $a)) {
        fwrite(STDERR, "FAIL: player state expone is_correct\n");
        exit(1);
    }
}
echo "OK player/host state payloads\n";

$service->nextQuestion($room->fresh());
$room->refresh();

// Con 1 pregunta, next debe finalizar
if ($section->questions->count() === 1) {
    if ($room->status !== 'finished') {
        fwrite(STDERR, "FAIL: esperado finished tras última pregunta\n");
        exit(1);
    }
    echo "OK finished after last question status={$room->status}\n";
} else {
    echo "OK advanced to next question id={$room->current_question_id}\n";
    $service->finish($room);
    $room->refresh();
    echo "OK force finish status={$room->status}\n";
}

$final = $service->buildPlayerState($room->fresh(), $player->fresh());
echo "OK final phase={$final['phase']} scoreboard=".json_encode($final['scoreboard'])."\n";
echo "PASS quiz room MVP verification\n";
