<?php

namespace Modules\WaSender\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\WaSender\Models\ExtensionSession;
use Symfony\Component\HttpFoundation\Response;

class EnsureExtensionToken
{
    /**
     * Handle an incoming request from Chrome Extension.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token sesi tidak ditemukan. Silakan login kembali.',
            ], 401);
        }

        $tokenHash = hash('sha256', $token);

        $session = ExtensionSession::with(['user'])
            ->where('session_token_hash', $tokenHash)
            ->where('status', 'active')
            ->first();

        if (! $session) {
            return response()->json([
                'status' => 'error',
                'code' => 'SESSION_INVALID',
                'message' => 'Sesi Anda tidak valid atau telah dicabut dari perangkat/browser lain.',
            ], 401);
        }

        if (! $session->isCurrentlyActive(heartbeatGraceMinutes: 5)) {
            $session->update(['status' => 'expired']);

            return response()->json([
                'status' => 'error',
                'code' => 'SESSION_EXPIRED',
                'message' => 'Sesi ekstensi telah kedaluwarsa. Silakan login kembali.',
            ], 401);
        }

        $user = $session->user;

        if (! $user || (isset($user->is_active) && ! $user->is_active)) {
            $session->revoke();

            return response()->json([
                'status' => 'error',
                'code' => 'ACCOUNT_SUSPENDED',
                'message' => 'Akun Anda dinonaktifkan oleh administrator.',
            ], 403);
        }

        // Bind user and session to request
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('extension_session', $session);

        return $next($request);
    }
}
