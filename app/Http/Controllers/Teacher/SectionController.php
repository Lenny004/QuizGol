<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function index(Request $request): View
    {
        $subjectId = $request->integer('subject_id') ?: null;
        $gradeId = $request->integer('grade_id') ?: null;

        $sections = auth()->user()
            ->sections()
            ->with(['subject', 'grade'])
            ->withCount('questions')
            ->forSubject($subjectId)
            ->forGrade($gradeId)
            ->latest()
            ->get();

        $subjects = Subject::query()->active()->orderBy('name')->get();
        $grades = Grade::query()->active()->ordered()->get();

        return view('teacher.sections.index', compact('sections', 'subjects', 'grades', 'subjectId', 'gradeId'));
    }

    public function create(): View
    {
        return view('teacher.sections.create', $this->formCatalog());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateSection($request);

        $section = auth()->user()->sections()->create($data);

        return redirect()
            ->route('sections.questions.index', $section)
            ->with('success', 'Sección creada. Ahora puedes agregar preguntas.');
    }

    public function edit(Section $section): View
    {
        $this->authorizeOwner($section);

        return view('teacher.sections.edit', array_merge(
            ['section' => $section],
            $this->formCatalog()
        ));
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeOwner($section);

        $section->update($this->validateSection($request));

        return redirect()
            ->route('sections.index')
            ->with('success', 'Sección actualizada.');
    }

    public function destroy(Section $section): RedirectResponse
    {
        $this->authorizeOwner($section);

        $section->delete();

        return redirect()
            ->route('sections.index')
            ->with('success', 'Sección eliminada.');
    }

    private function authorizeOwner(Section $section): void
    {
        abort_unless($section->user_id === auth()->id(), 403);
    }

    /**
     * @return array{subjects: \Illuminate\Support\Collection, grades: \Illuminate\Support\Collection}
     */
    private function formCatalog(): array
    {
        return [
            'subjects' => Subject::query()->active()->orderBy('name')->get(),
            'grades' => Grade::query()->active()->ordered()->get(),
        ];
    }

    /**
     * @return array{title: string, subject_id: int, grade_id: int|null}
     */
    private function validateSection(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'grade_id' => ['nullable', 'exists:grades,id'],
        ]);

        $gradeId = $data['grade_id'] ?? null;

        if ($gradeId) {
            $allowed = Subject::query()
                ->whereKey($data['subject_id'])
                ->whereHas('grades', fn ($q) => $q->where('grades.id', $gradeId))
                ->exists();

            if (! $allowed) {
                throw ValidationException::withMessages([
                    'grade_id' => 'Esa materia no está disponible para el grado seleccionado.',
                ]);
            }
        }

        return [
            'title' => $data['title'],
            'subject_id' => (int) $data['subject_id'],
            'grade_id' => $gradeId ? (int) $gradeId : null,
        ];
    }
}
