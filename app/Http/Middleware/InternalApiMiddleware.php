<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for internal API endpoints used by the AI agent.
 *
 * - Only allows requests from localhost (127.0.0.1, ::1)
 * - Authenticates user via X-User-Id header (trusted internal call)
 * - No Bearer token required
 */
class InternalApiMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only allow localhost access
        $clientIp = $request->ip();
        if (!in_array($clientIp, ['127.0.0.1', '::1'])) {
            return response()->json([
                'success' => false,
                'message' => 'Internal API access denied.',
            ], 403);
        }

        // Authenticate via X-User-Id header
        $userId = $request->header('X-User-Id');
        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                Auth::setUser($user);
            }
        }

        // Mark channel for audit log (internal API = WhatsApp AI)
        $request->attributes->set('audit_channel', 'whatsapp');

        return $next($request);
    }
}
