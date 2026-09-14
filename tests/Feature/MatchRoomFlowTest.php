<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Team;
use App\Services\MatchGameService;
use App\Services\QuizRoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesQuizContent;
use Tests\TestCase;

class MatchRoomFlowTest extends TestCase
{
    use CreatesQuizContent;
    use RefreshDatabase;

    public function test_match_room_awards_goal_on_correct_answer(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection(1);
        $matchService = app(MatchGameService::class);
        $quizService = app(QuizRoomService::class);

        $room = $matchService->createRoom($teacher, $section);
        $this->assertSame(Room::MODE_MATCH, $room->mode);
        $this->assertSame(2, $room->teams()->count());

        $quizService->start($room);
        $player = $matchService->createPlayer($room->fresh(), 'Golero', Team::SIDE_HOME);
        $correct = $room->fresh()->currentQuestion->answers()->where('is_correct', true)->first();

        $quizService->submitAnswer($player, $room->fresh(), $correct->id);

        $home = $room->teams()->where('side', Team::SIDE_HOME)->first();
        $this->assertSame(1, (int) $home->fresh()->goals);
    }

    public function test_join_requires_team_in_match_mode(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection(1);
        $matchService = app(MatchGameService::class);
        $room = $matchService->createRoom($teacher, $section);

        try {
            $matchService->createPlayer($room, 'SinEquipo', null, null);
            $this->fail('Se esperaba ValidationException sin equipo');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $player = $matchService->createPlayer($room->fresh(), 'ConEquipo', Team::SIDE_AWAY);
        $this->assertSame(Team::SIDE_AWAY, $player->team->side);
    }
}
