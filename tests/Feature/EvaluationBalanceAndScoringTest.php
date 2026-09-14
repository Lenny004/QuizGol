<?php

namespace Tests\Feature;

use App\Services\EvaluationBalanceService;
use App\Services\MatchGameService;
use App\Services\QuizRoomService;
use App\Support\QuestionScoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesQuizContent;
use Tests\TestCase;

class EvaluationBalanceAndScoringTest extends TestCase
{
    use CreatesQuizContent;
    use RefreshDatabase;

    public function test_points_scale_with_difficulty_and_speed(): void
    {
        $this->assertSame(500, QuestionScoring::basePoints('easy'));
        $this->assertSame(1000, QuestionScoring::basePoints('medium'));
        $this->assertSame(2000, QuestionScoring::basePoints('hard'));

        $this->assertSame(500, QuestionScoring::award(500, 30, 0));
        $this->assertSame(2000, QuestionScoring::award(2000, 30, 0));
        $this->assertSame(375, QuestionScoring::award(500, 30, 15));
        $this->assertSame(1500, QuestionScoring::award(2000, 30, 15));
        $this->assertSame(250, QuestionScoring::award(500, 30, 30));
    }

    public function test_balanced_section_is_ready_to_play(): void
    {
        ['section' => $section] = $this->createTeacherWithSection(3);
        $report = app(EvaluationBalanceService::class)->analyze($section);

        $this->assertTrue($report['balanced']);
        $this->assertSame(1, $report['counts']['easy']);
        $this->assertSame(1, $report['counts']['medium']);
        $this->assertSame(1, $report['counts']['hard']);
        $this->assertSame(3500, $report['max_score']);
    }

    public function test_unbalanced_section_cannot_create_quiz_or_match_room(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection(3, 30, ['easy', 'easy', 'easy']);

        $report = app(EvaluationBalanceService::class)->analyze($section);
        $this->assertFalse($report['balanced']);
        $this->assertNotEmpty($report['issues']);

        try {
            app(QuizRoomService::class)->createRoom($teacher, $section);
            $this->fail('Se esperaba ValidationException por evaluación desequilibrada');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('section_id', $exception->errors());
        }

        try {
            app(MatchGameService::class)->createRoom($teacher, $section);
            $this->fail('Se esperaba ValidationException en modo partido');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('section_id', $exception->errors());
        }
    }

    public function test_http_store_room_rejects_unbalanced_section(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection(2, 30, ['easy', 'medium']);

        $this->actingAs($teacher)
            ->from(route('sections.index'))
            ->post(route('rooms.store'), [
                'section_id' => $section->id,
                'mode' => 'quiz',
            ])
            ->assertRedirect(route('sections.index'))
            ->assertSessionHasErrors('section_id');
    }

    public function test_http_store_room_allows_balanced_section(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection();

        $this->actingAs($teacher)
            ->post(route('rooms.store'), [
                'section_id' => $section->id,
                'mode' => 'quiz',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rooms', [
            'section_id' => $section->id,
            'host_id' => $teacher->id,
        ]);
    }

    public function test_creating_a_question_assigns_points_from_difficulty(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection(0);

        $this->actingAs($teacher)
            ->post(route('sections.questions.store', $section), [
                'prompt' => '¿Cuánto es 7 + 5?',
                'difficulty' => 'hard',
                'time_limit' => 40,
                'points' => 100,
                'answers' => ['12', '11', '13', ''],
                'correct_index' => 0,
            ])
            ->assertRedirect(route('sections.questions.index', $section));

        $this->assertDatabaseHas('questions', [
            'section_id' => $section->id,
            'prompt' => '¿Cuánto es 7 + 5?',
            'difficulty' => 'hard',
            'points' => 2000,
        ]);
    }

    public function test_questions_index_shows_balance_status(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection();

        $this->actingAs($teacher)
            ->get(route('sections.questions.index', $section))
            ->assertOk()
            ->assertSee('Equilibrio de la evaluación')
            ->assertSee('Lista para jugar');
    }

    public function test_hard_correct_answer_awards_more_than_easy(): void
    {
        ['teacher' => $teacher, 'section' => $section] = $this->createTeacherWithSection();
        $service = app(QuizRoomService::class);
        $room = $service->createRoom($teacher, $section);
        $service->start($room);

        $this->assertSame('easy', $room->fresh()->currentQuestion->difficulty);

        $service->revealQuestion($room->fresh());
        $service->nextQuestion($room->fresh());
        $service->revealQuestion($room->fresh());
        $service->nextQuestion($room->fresh());

        $hardQuestion = $room->fresh()->currentQuestion;
        $this->assertSame('hard', $hardQuestion->difficulty);

        $player = $service->createPlayer($room->fresh(), 'Dificil');
        $hardCorrect = $hardQuestion->answers()->where('is_correct', true)->first();
        $hardAnswer = $service->submitAnswer($player, $room->fresh(), $hardCorrect->id);

        $this->assertSame(2000, $hardAnswer->points_awarded);
    }
}
