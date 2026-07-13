<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeacher
{
    /**
     * Allow only teachers and admins into the teacher area.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['teacher', 'admin'], true)) {
            abort(403, 'Solo maestros y administradores pueden entrar aquí.');
        }

        return $next($request);
    }
}
