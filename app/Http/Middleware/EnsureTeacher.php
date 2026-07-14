<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe el área de maestro a usuarios con rol teacher o admin.
 *
 * Alias registrado en bootstrap/app.php como "teacher".
 */
class EnsureTeacher
{
    /**
     * Continúa la petición solo si el usuario puede acceder al área de maestro.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canAccessTeacherArea()) {
            abort(403, 'Solo maestros y administradores pueden entrar aquí.');
        }

        return $next($request);
    }
}
