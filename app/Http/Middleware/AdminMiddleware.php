<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // User must be authenticated.
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Only role_id 1 (Admin) can continue.
        if ((int) $user->role_id !== 1) {
            return response()->json([
                'message' => 'Admin access required.',
            ], 403);
        }

        return $next($request);
    }
}