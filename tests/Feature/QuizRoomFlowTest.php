<?php

namespace Tests\Feature;

use App\Models\PlayerAnswer;
use App\Models\Room;
use App\Services\QuizRoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesQuizContent;
use Tests\TestCase;

class QuizRoomFlowTest extends TestCase
{
    use CreatesQuizContent;
    use RefreshDatabase;

    public function test_teacher_can_create_quiz_room_and_start(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection();
        $service = app(QuizRoomService::class);

        $room = $service->createRoom($teacher, $section);

        $this->assertSame(Room::MODE_QUIZ, $room->mode);
        $this->assertSame(Room::STATUS_LOBBY, $room->status);

        $service->start($room);
        $room->refresh();

        $this->assertTrue($room->isActive());
        $this->assertTrue($room->isAsking());

        $state = $service->buildHostState($room);
        $this->assertSame(Room::STATUS_ACTIVE, $state['status']);
        $this->assertSame(Room::PHASE_ASKING, $state['question_phase']);
        $this->assertFalse($state['reveal']);
    }

    public function test_host_state_does_not_spoil_correct_answers_while_asking(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection();
        $service = app(QuizRoomService::class);
        $room = $service->createRoom($teacher, $section);
        $service->start($room);

        $state = $service->buildHostState($room->fresh());

        $this->assertFalse($state['reveal']);
        foreach ($state['question']['answers'] as $answer) {
            $this->assertNull($answer['is_correct']);
        }
    }

    public function test_late_answers_are_rejected_after_timeout(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection(3, 5);
        $service = app(QuizRoomService::class);
        $room = $service->createRoom($teacher, $section);
        $service->start($room);
        $room->refresh();

        $player = $service->createPlayer($room, 'Ana');
        $correct = $room->currentQuestion->answers()->where('is_correct', true)->first();

        $room->update(['question_started_at' => now()->subSeconds(10)]);

        try {
            $service->submitAnswer($player, $room->fresh(), $correct->id);
            $this->fail('Se esperaba ValidationException por timeout');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('answer_id', $exception->errors());
        }

        $this->assertSame(0, PlayerAnswer::query()->count());
    }

    public function test_reveal_shows_correct_answers_and_rejects_new_submissions(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection();
        $service = app(QuizRoomService::class);
        $room = $service->createRoom($teacher, $section);
        $service->start($room);
        $player = $service->createPlayer($room->fresh(), 'Ana');
        $correct = $room->fresh()->currentQuestion->answers()->where('is_correct', true)->first();

        $service->revealQuestion($room->fresh());

        $hostState = $service->buildHostState($room->fresh());
        $this->assertTrue($hostState['reveal']);
        $this->assertTrue(collect($hostState['question']['answers'])->contains(
            fn ($answer) => $answer['is_correct'] === true
        ));

        try {
            $service->submitAnswer($player, $room->fresh(), $correct->id);
            $this->fail('Se esperaba ValidationException tras revelar');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('answer_id', $exception->errors());
        }
    }

    public function test_player_can_join_and_answer_during_asking(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection();
        $service = app(QuizRoomService::class);
        $room = $service->createRoom($teacher, $section);
        $service->start($room);

        $player = $service->createPlayer($room->fresh(), 'Luis');
        $correct = $room->fresh()->currentQuestion->answers()->where('is_correct', true)->first();

        $answer = $service->submitAnswer($player, $room->fresh(), $correct->id);

        $this->assertTrue($answer->is_correct);
        $this->assertSame(500, $answer->points_awarded);
        $this->assertDatabaseHas('player_answers', [
            'room_player_id' => $player->id,
            'is_correct' => true,
            'points_awarded' => 500,
        ]);
    }

    public function test_join_lookup_reports_mode(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection();
        $room = app(QuizRoomService::class)->createRoom($teacher, $section);

        $this->getJson(route('play.join.lookup', ['code' => $room->code]))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('is_match', false)
            ->assertJsonPath('mode', 'quiz');
    }

    public function test_results_page_is_available_for_host(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection();
        $service = app(QuizRoomService::class);
        $room = $service->createRoom($teacher, $section);
        $service->start($room);
        $service->finish($room->fresh());

        $this->actingAs($teacher)
            ->get(route('rooms.results', $room))
            ->assertOk()
            ->assertSee('Aciertos por pregunta')
            ->assertSee($room->code);
    }
}
