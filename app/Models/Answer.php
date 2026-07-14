<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opción de respuesta de una pregunta.
 *
 * Solo una debería tener is_correct = true por pregunta (MVP).
 * El jugador nunca recibe is_correct en el JSON hasta después de responder.
 */
class Answer extends Model
{
    protected $fillable = [
        'question_id',
        'text',
        'image_path',
        'is_correct',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Pregunta a la que pertenece esta opción. */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
