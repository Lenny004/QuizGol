<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function index(): View
    {
        $sections = auth()->user()
            ->sections()
            ->with('subject')
            ->withCount('questions')
            ->latest()
            ->get();

        return view('teacher.sections.index', compact('sections'));
    }

    public function create(): View
    {
        $subjects = Subject::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('teacher.sections.create', compact('subjects'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'grade' => ['nullable', 'string', 'max:50'],
        ]);

        $section = auth()->user()->sections()->create($data);

        return redirect()
            ->route('sections.questions.index', $section)
            ->with('success', 'Sección creada. Ahora puedes agregar preguntas.');
    }

    public function edit(Section $section): View
    {
        $this->authorizeOwner($section);

        $subjects = Subject::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('teacher.sections.edit', compact('section', 'subjects'));
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeOwner($section);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'grade' => ['nullable', 'string', 'max:50'],
        ]);

        $section->update($data);

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
}
