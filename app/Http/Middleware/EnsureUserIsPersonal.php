<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsPersonal
{
    /**
     * Solo el personal de la fábrica puede entrar al panel interno.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->esPersonal(), 403);

        return $next($request);
    }
}
