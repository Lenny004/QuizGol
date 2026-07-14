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

/**
 * CRUD de preguntas (opción múltiple) dentro de una sección.
 */
class QuestionController extends Controller
{
    /**
     * Lista las preguntas de la sección ordenadas por sort_order.
     */
    public function index(Section $section): View
    {
        $this->authorizeSection($section);

        $questions = $section->questions()
            ->with('answers')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $section->load(['subject', 'grade']);

        return view('teacher.questions.index', compact('section', 'questions'));
    }

    /**
     * Formulario para crear una pregunta nueva.
     */
    public function create(Section $section): View
    {
        $this->authorizeSection($section);

        return view('teacher.questions.create', compact('section'));
    }

    /**
     * Guarda pregunta + respuestas en una transacción.
     */
    public function store(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($section);

        $validatedData = $this->validateQuestion($request);

        DB::transaction(function () use ($section, $validatedData) {
            $question = $section->questions()->create([
                'prompt' => $validatedData['prompt'],
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'difficulty' => $validatedData['difficulty'],
                'time_limit' => $validatedData['time_limit'],
                'points' => $validatedData['points'],
                'sort_order' => $section->questions()->count(),
            ]);

            $this->syncAnswers(
                $question,
                $validatedData['answers'],
                (int) $validatedData['correct_index']
            );
        });

        return redirect()
            ->route('sections.questions.index', $section)
            ->with('success', 'Pregunta creada.');
    }

    /**
     * Formulario de edición de pregunta y sus respuestas.
     */
    public function edit(Question $question): View
    {
        $question->load(['section', 'answers']);
        $this->authorizeSection($question->section);

        return view('teacher.questions.edit', [
            'section' => $question->section,
            'question' => $question,
        ]);
    }

    /**
     * Actualiza pregunta; recrea las respuestas (MVP simple).
     */
    public function update(Request $request, Question $question): RedirectResponse
    {
        $question->load('section');
        $this->authorizeSection($question->section);

        $validatedData = $this->validateQuestion($request);

        DB::transaction(function () use ($question, $validatedData) {
            $question->update([
                'prompt' => $validatedData['prompt'],
                'difficulty' => $validatedData['difficulty'],
                'time_limit' => $validatedData['time_limit'],
                'points' => $validatedData['points'],
            ]);

            // MVP: borrar y volver a crear las opciones (pierde IDs antiguos).
            $question->answers()->delete();
            $this->syncAnswers(
                $question,
                $validatedData['answers'],
                (int) $validatedData['correct_index']
            );
        });

        return redirect()
            ->route('sections.questions.index', $question->section)
            ->with('success', 'Pregunta actualizada.');
    }

    /**
     * Elimina la pregunta de la sección.
     */
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

    /**
     * Solo el dueño de la sección puede gestionar sus preguntas.
     */
    private function authorizeSection(Section $section): void
    {
        abort_unless($section->user_id === auth()->id(), 403);
    }

    /**
     * Valida pregunta y limpia respuestas vacías.
     * Reindexa las respuestas para que correct_index apunte a la lista final.
     *
     * @return array{prompt: string, difficulty: string|null, time_limit: int, points: int, answers: array<int, string>, correct_index: int}
     */
    private function validateQuestion(Request $request): array
    {
        $validatedData = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'time_limit' => ['required', 'integer', 'min:5', 'max:120'],
            'points' => ['required', 'integer', 'min:100', 'max:5000'],
            'answers' => ['required', 'array', 'min:2', 'max:4'],
            'answers.*' => ['nullable', 'string', 'max:500'],
            'correct_index' => ['required', 'integer', 'min:0', 'max:3'],
        ]);

        // Quita opciones vacías pero conserva el índice original para mapear la correcta.
        $filledAnswers = [];
        foreach ($validatedData['answers'] as $index => $text) {
            $trimmedText = trim((string) $text);
            if ($trimmedText !== '') {
                $filledAnswers[(int) $index] = $trimmedText;
            }
        }

        if (count($filledAnswers) < 2) {
            throw ValidationException::withMessages([
                'answers' => 'Agrega al menos 2 respuestas.',
            ]);
        }

        $correctIndex = (int) $validatedData['correct_index'];

        if (! array_key_exists($correctIndex, $filledAnswers)) {
            throw ValidationException::withMessages([
                'correct_index' => 'Marca como correcta una respuesta que no esté vacía.',
            ]);
        }

        // Reindexa 0..n para sort_order y recalcula el índice de la correcta.
        $reindexedAnswers = array_values($filledAnswers);
        $newCorrectIndex = array_search($filledAnswers[$correctIndex], $reindexedAnswers, true);

        return [
            'prompt' => $validatedData['prompt'],
            'difficulty' => $validatedData['difficulty'] ?? null,
            'time_limit' => $validatedData['time_limit'],
            'points' => $validatedData['points'],
            'answers' => $reindexedAnswers,
            'correct_index' => (int) $newCorrectIndex,
        ];
    }

    /**
     * Crea las filas Answer asociadas a la pregunta.
     *
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
