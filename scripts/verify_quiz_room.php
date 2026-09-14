<?php

/**
 * Verificación E2E del modo quiz (timeout, reveal, sin spoiler).
 * docker compose exec app php scripts/verify_quiz_room.php
 */

use App\Models\Room;
use App\Models\Section;
use App\Models\User;
use App\Services\QuizRoomService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

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

$playerA = $service->createPlayer($room, 'AnaTest');
$playerB = $service->createPlayer($room->fresh(), 'BetoTest');
echo "OK players joined\n";

$service->start($room->fresh());
$room->refresh();
echo "OK started phase={$room->question_phase} question={$room->current_question_id}\n";

$hostAsking = $service->buildHostState($room->fresh());
foreach ($hostAsking['question']['answers'] as $answer) {
    if ($answer['is_correct'] !== null) {
        fwrite(STDERR, "FAIL: host spoilea is_correct en asking\n");
        exit(1);
    }
}
echo "OK host no spoilea en asking\n";

$question = $room->currentQuestion()->with('answers')->first();
$correct = $question->answers->firstWhere('is_correct', true);

$pa = $service->submitAnswer($playerA, $room->fresh(), $correct->id);
echo "OK Ana answered points={$pa->points_awarded}\n";

$waiting = $service->buildPlayerState($room->fresh(), $playerA->fresh());
if ($waiting['phase'] !== 'waiting') {
    fwrite(STDERR, "FAIL: esperaba phase waiting, got {$waiting['phase']}\n");
    exit(1);
}
echo "OK Ana en waiting\n";

// Timeout: rechaza respuesta tarde.
$room->update(['question_started_at' => now()->subSeconds(120)]);
try {
    $service->submitAnswer($playerB, $room->fresh(), $correct->id);
    fwrite(STDERR, "FAIL: debió rechazar por timeout\n");
    exit(1);
} catch (ValidationException $e) {
    echo "OK rejected timeout answer\n";
}

// Restaurar tiempo y revelar manualmente.
$room->update([
    'question_started_at' => now(),
    'question_phase' => Room::PHASE_ASKING,
    'reveal_started_at' => null,
]);

$service->revealQuestion($room->fresh());
$hostReveal = $service->buildHostState($room->fresh());
if (! $hostReveal['reveal']) {
    fwrite(STDERR, "FAIL: host reveal esperado\n");
    exit(1);
}
echo "OK reveal sin spoiler previo\n";

$service->finish($room->fresh());
echo "OK finished\n";
echo "ALL QUIZ CHECKS PASSED\n";
