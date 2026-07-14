{{--
  Partial del formulario de pregunta (create/edit).
  Campos: prompt, difficulty, time_limit, points, 4 respuestas + correct_index.
--}}
@php
    $existingAnswers = old('answers');
    if ($existingAnswers === null && isset($question)) {
        $existingAnswers = $question->answers->pluck('text')->all();
    }
    $existingAnswers = array_values($existingAnswers ?? ['', '', '', '']);
    while (count($existingAnswers) < 4) {
        $existingAnswers[] = '';
    }

    $correctIndex = old('correct_index');
    if ($correctIndex === null && isset($question)) {
        $correctIndex = $question->answers->search(fn ($a) => $a->is_correct);
    }
    $correctIndex = (int) ($correctIndex ?? 0);
@endphp

<label class="form__field">
    <span>Enunciado</span>
    <textarea class="form__input" name="prompt" rows="3" required maxlength="2000">{{ old('prompt', $question->prompt ?? '') }}</textarea>
</label>

<div class="form__grid">
    <label class="form__field">
        <span>Dificultad</span>
        <select class="form__input" name="difficulty">
            <option value="">Sin definir</option>
            @foreach (\App\Models\Question::DIFFICULTIES as $value => $label)
                <option value="{{ $value }}" @selected(old('difficulty', $question->difficulty ?? '') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="form__field">
        <span>Tiempo límite (segundos)</span>
        <input class="form__input" type="number" name="time_limit" min="5" max="120" value="{{ old('time_limit', $question->time_limit ?? 30) }}" required>
    </label>

    <label class="form__field">
        <span>Puntos</span>
        <input class="form__input" type="number" name="points" min="100" max="5000" step="100" value="{{ old('points', $question->points ?? 1000) }}" required>
    </label>
</div>

<fieldset class="form__field form__answers">
    <legend>Respuestas (2 a 4)</legend>
    <p class="text--muted">Marca cuál es la correcta. Deja vacías las que no uses (mínimo 2).</p>

    @for ($i = 0; $i < 4; $i++)
        <div class="form__answer-row">
            <label class="form__radio-correct">
                <input type="radio" name="correct_index" value="{{ $i }}" @checked($correctIndex === $i)>
                <span>Correcta</span>
            </label>
            <input
                class="form__input"
                type="text"
                name="answers[{{ $i }}]"
                value="{{ $existingAnswers[$i] ?? '' }}"
                maxlength="500"
                placeholder="Respuesta {{ $i + 1 }}"
                @if ($i < 2) required @endif
            >
        </div>
    @endfor
</fieldset>
