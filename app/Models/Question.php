<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pregunta de una sección (MVP: solo opción múltiple).
 *
 * - prompt: texto de la pregunta
 * - time_limit: segundos para responder (default 30)
 * - points: puntos base al acertar (default 1000)
 * - sort_order: orden de aparición en el juego
 * - difficulty: easy|medium|hard (opcional)
 */
class Question extends Model
{
    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    /** Etiquetas en español para mostrar en la UI del maestro. */
    public const DIFFICULTIES = [
        'easy' => 'Fácil',
        'medium' => 'Media',
        'hard' => 'Difícil',
    ];

    protected $fillable = [
        'section_id',
        'prompt',
        'image_path',
        'type',
        'difficulty',
        'time_limit',
        'points',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_limit' => 'integer',
            'points' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** Sección a la que pertenece la pregunta. */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** Opciones de respuesta (2 a 4 en el MVP). */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    /** Devuelve "Fácil", "Media" o "Difícil", o null si no hay dificultad. */
    public function difficultyLabel(): ?string
    {
        if (! $this->difficulty) {
            return null;
        }

        return self::DIFFICULTIES[$this->difficulty] ?? $this->difficulty;
    }
}
