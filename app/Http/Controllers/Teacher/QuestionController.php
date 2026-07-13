<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function index(Section $section): View
    {
        $this->authorizeSection($section);

        $questions = $section->questions()
            ->with('answers')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('teacher.questions.index', compact('section', 'questions'));
    }

    public function create(Section $section): View
    {
        $this->authorizeSection($section);

        return view('teacher.questions.create', compact('section'));
    }

    public function store(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($section);

        $data = $this->validateQuestion($request);

        DB::transaction(function () use ($section, $data) {
            $question = $section->questions()->create([
                'prompt' => $data['prompt'],
                'time_limit' => $data['time_limit'],
                'points' => $data['points'],
                'sort_order' => $section->questions()->count(),
            ]);

            $this->syncAnswers($question, $data['answers'], (int) $data['correct_index']);
        });

        return redirect()
            ->route('sections.questions.index', $section)
            ->with('success', 'Pregunta creada.');
    }

    public function edit(Question $question): View
    {
        $question->load(['section', 'answers']);
        $this->authorizeSection($question->section);

        return view('teacher.questions.edit', [
            'section' => $question->section,
            'question' => $question,
        ]);
    }

    public function update(Request $request, Question $question): RedirectResponse
    {
        $question->load('section');
        $this->authorizeSection($question->section);

        $data = $this->validateQuestion($request);

        DB::transaction(function () use ($question, $data) {
            $question->update([
                'prompt' => $data['prompt'],
                'time_limit' => $data['time_limit'],
                'points' => $data['points'],
            ]);

            $question->answers()->delete();
            $this->syncAnswers($question, $data['answers'], (int) $data['correct_index']);
        });

        return redirect()
            ->route('sections.questions.index', $question->section)
            ->with('success', 'Pregunta actualizada.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $question->load('section');
        $this->authorizeSection($question->section);

        $section = $question->section;
        $question->delete();

        return redirect()
            ->route('sections.questions.index', $section)
            ->with('success', 'Pregunta eliminada.');
    }

    private function authorizeSection(Section $section): void
    {
        abort_unless($section->user_id === auth()->id(), 403);
    }

    /**
     * @return array{prompt: string, time_limit: int, points: int, answers: array<int, string>, correct_index: int}
     */
    private function validateQuestion(Request $request): array
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'time_limit' => ['required', 'integer', 'min:5', 'max:120'],
            'points' => ['required', 'integer', 'min:100', 'max:5000'],
            'answers' => ['required', 'array', 'min:2', 'max:4'],
            'answers.*' => ['nullable', 'string', 'max:500'],
            'correct_index' => ['required', 'integer', 'min:0', 'max:3'],
        ]);

        $answers = [];
        foreach ($data['answers'] as $index => $text) {
            $trimmed = trim((string) $text);
            if ($trimmed !== '') {
                $answers[(int) $index] = $trimmed;
            }
        }

        if (count($answers) < 2) {
            throw ValidationException::withMessages([
                'answers' => 'Agrega al menos 2 respuestas.',
            ]);
        }

        $correctIndex = (int) $data['correct_index'];

        if (! array_key_exists($correctIndex, $answers)) {
            throw ValidationException::withMessages([
                'correct_index' => 'Marca como correcta una respuesta que no esté vacía.',
            ]);
        }

        // Reindex so sort_order is 0..n and correct_index matches the new list.
        $reindexed = array_values($answers);
        $newCorrect = array_search($answers[$correctIndex], $reindexed, true);

        return [
            'prompt' => $data['prompt'],
            'time_limit' => $data['time_limit'],
            'points' => $data['points'],
            'answers' => $reindexed,
            'correct_index' => (int) $newCorrect,
        ];
    }

    /**
     * @param  array<int, string>  $answers
     */
    private function syncAnswers(Question $question, array $answers, int $correctIndex): void
    {
        foreach (array_values($answers) as $index => $text) {
            $question->answers()->create([
                'text' => $text,
                'is_correct' => $index === $correctIndex,
                'sort_order' => $index,
            ]);
        }
    }
}
