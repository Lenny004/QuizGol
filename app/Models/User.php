<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuario autenticado del sistema (maestro o admin).
 *
 * role: "teacher" | "admin"
 * Los alumnos/jugadores NO son Users; usan RoomPlayer + cookie.
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLE_TEACHER = 'teacher';

    public const ROLE_ADMIN = 'admin';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Atributos que se castean automáticamente.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Secciones (bancos de preguntas) creadas por este usuario. */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /** Salas donde este usuario es el anfitrión. */
    public function hostedRooms(): HasMany
    {
        return $this->hasMany(Room::class, 'host_id');
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Puede entrar al área de maestro (teacher o admin). */
    public function canAccessTeacherArea(): bool
    {
        return $this->isTeacher() || $this->isAdmin();
    }
}
