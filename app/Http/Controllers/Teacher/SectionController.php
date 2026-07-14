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

/**
 * CRUD de secciones (bancos de preguntas) del maestro autenticado.
 */
class SectionController extends Controller
{
    /**
     * Lista las secciones del maestro, con filtros opcionales por materia/grado.
     */
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

        return view('teacher.sections.index', compact(
            'sections',
            'subjects',
            'grades',
            'subjectId',
            'gradeId'
        ));
    }

    /**
     * Formulario para crear una sección nueva.
     */
    public function create(): View
    {
        return view('teacher.sections.create', $this->formCatalog());
    }

    /**
     * Guarda la sección y redirige al listado de preguntas.
     */
    public function store(Request $request): RedirectResponse
    {
        $validatedData = $this->validateSection($request);

        $section = auth()->user()->sections()->create($validatedData);

        return redirect()
            ->route('sections.questions.index', $section)
            ->with('success', 'Sección creada. Ahora puedes agregar preguntas.');
    }

    /**
     * Formulario de edición (solo el dueño).
     */
    public function edit(Section $section): View
    {
        $this->authorizeOwner($section);

        return view('teacher.sections.edit', array_merge(
            ['section' => $section],
            $this->formCatalog()
        ));
    }

    /**
     * Actualiza título, materia y grado de la sección.
     */
    public function update(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeOwner($section);

        $section->update($this->validateSection($request));

        return redirect()
            ->route('sections.index')
            ->with('success', 'Sección actualizada.');
    }

    /**
     * Elimina la sección (cascade borra preguntas vía FK).
     */
    public function destroy(Section $section): RedirectResponse
    {
        $this->authorizeOwner($section);

        $section->delete();

        return redirect()
            ->route('sections.index')
            ->with('success', 'Sección eliminada.');
    }

    /**
     * Solo el dueño de la sección puede editarla o borrarla.
     */
    private function authorizeOwner(Section $section): void
    {
        abort_unless($section->user_id === auth()->id(), 403);
    }

    /**
     * Materias y grados activos para los selects del formulario.
     *
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
     * Valida el formulario y comprueba que la materia ofrezca el grado elegido.
     *
     * @return array{title: string, subject_id: int, grade_id: int|null}
     */
    private function validateSection(Request $request): array
    {
        $validatedData = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'grade_id' => ['nullable', 'exists:grades,id'],
        ]);

        $gradeId = $validatedData['grade_id'] ?? null;

        if ($gradeId) {
            $subjectAllowsGrade = Subject::query()
                ->whereKey($validatedData['subject_id'])
                ->whereHas('grades', fn ($query) => $query->where('grades.id', $gradeId))
                ->exists();

            if (! $subjectAllowsGrade) {
                throw ValidationException::withMessages([
                    'grade_id' => 'Esa materia no está disponible para el grado seleccionado.',
                ]);
            }
        }

        return [
            'title' => $validatedData['title'],
            'subject_id' => (int) $validatedData['subject_id'],
            'grade_id' => $gradeId ? (int) $gradeId : null,
        ];
    }
}
