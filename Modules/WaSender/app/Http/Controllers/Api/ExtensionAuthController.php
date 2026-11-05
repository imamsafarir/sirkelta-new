<?php

namespace Modules\WaSender\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\WaSender\Models\ExtensionSession;

class ExtensionAuthController extends Controller
{
    public function syncWebSession(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'status' => 'unauthenticated',
                'authenticated' => false,
                'message' => 'Belum ada sesi login web aktif di browser.',
            ], 401);
        }

        if (isset($user->is_active) && ! $user->is_active) {
            return response()->json([
                'status' => 'error',
                'authenticated' => false,
                'code' => 'ACCOUNT_SUSPENDED',
                'message' => 'Akun Anda dinonaktifkan oleh administrator.',
            ], 403);
        }

        $deviceId = $request->input('device_id') ?? $request->header('X-Device-Id');
        $deviceName = $request->input('device_name') ?? $request->header('X-Device-Name') ?? 'Google Chrome';
        $forceTakeover = filter_var($request->input('force_takeover', true), FILTER_VALIDATE_BOOLEAN);

        if (! $user->hasAnyRole(['superadmin', 'admin_wasender', 'user_wasender'])) {
            $user->assignRole('user_wasender');
        }

        Auth::guard('web')->login($user, remember: true);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return DB::transaction(function () use ($user, $request, $deviceId, $deviceName, $forceTakeover) {
            User::where('id', $user->id)->lockForUpdate()->first();

            $existingSession = ExtensionSession::where('user_id', $user->id)
                ->where('status', 'active')
                ->latest()
                ->first();

            if ($existingSession && $existingSession->isCurrentlyActive(heartbeatGraceMinutes: 3)) {
                if ($deviceId && $existingSession->device_id === $deviceId) {
                    $plainToken = Str::random(64);
                    $existingSession->update([
                        'session_token_hash' => hash('sha256', $plainToken),
                        'device_name' => $deviceName,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'last_heartbeat_at' => Carbon::now(),
                        'expires_at' => Carbon::now()->addHours(24),
                        'status' => 'active',
                    ]);

                    return response()->json([
                        'status' => 'success',
                        'authenticated' => true,
                        'message' => 'Sesi ekstensi aktif berhasil disinkronkan.',
                        'data' => $this->formatAuthPayload($user, $plainToken, $existingSession),
                    ]);
                }

                if (! $forceTakeover) {
                    return response()->json([
                        'status' => 'locked',
                        'authenticated' => true,
                        'code' => 'SESSION_LOCKED',
                        'message' => "Akun ini sedang aktif di browser lain ({$existingSession->device_name}).",
                        'active_session' => [
                            'device_name' => $existingSession->device_name,
                            'ip_address' => $existingSession->ip_address,
                            'last_heartbeat' => $existingSession->last_heartbeat_at?->diffForHumans() ?? 'Baru saja',
                        ],
                    ], 409);
                }
            }

            ExtensionSession::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'revoked']);

            $plainToken = Str::random(64);
            $newSession = ExtensionSession::create([
                'user_id' => $user->id,
                'session_token_hash' => hash('sha256', $plainToken),
                'device_name' => $deviceName,
                'device_id' => $deviceId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'active',
                'last_heartbeat_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addHours(24),
            ]);

            return response()->json([
                'status' => 'success',
                'authenticated' => true,
                'message' => 'Sesi ekstensi berhasil diambil alih dan diaktifkan.',
                'data' => $this->formatAuthPayload($user, $plainToken, $newSession),
            ]);
        });
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'force_takeover' => ['nullable', 'boolean'],
        ]);

        $loginInput = $validated['login'] ?? $validated['email'] ?? '';

        if (empty($loginInput)) {
            return response()->json([
                'status' => 'error',
                'code' => 'INVALID_INPUT',
                'message' => 'Silakan masukkan username atau email.',
            ], 422);
        }

        $user = User::where('email', $loginInput)
            ->orWhere('username', $loginInput)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Username/email atau kata sandi yang Anda masukkan salah.',
            ], 401);
        }

        if (isset($user->is_active) && ! $user->is_active) {
            return response()->json([
                'status' => 'error',
                'code' => 'ACCOUNT_SUSPENDED',
                'message' => 'Akun Anda dinonaktifkan oleh administrator.',
            ], 403);
        }

        if (! $user->hasAnyRole(['superadmin', 'admin_wasender', 'user_wasender'])) {
            $user->assignRole('user_wasender');
        }

        Auth::guard('web')->login($user, remember: true);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return DB::transaction(function () use ($user, $request, $validated) {
            User::where('id', $user->id)->lockForUpdate()->first();

            $existingSession = ExtensionSession::where('user_id', $user->id)
                ->where('status', 'active')
                ->latest()
                ->first();

            $deviceId = $validated['device_id'] ?? null;
            $deviceName = $validated['device_name'] ?? 'Google Chrome';
            $forceTakeover = filter_var($request->input('force_takeover', false), FILTER_VALIDATE_BOOLEAN);

            if ($existingSession && $existingSession->isCurrentlyActive(heartbeatGraceMinutes: 3)) {
                if ($deviceId && $existingSession->device_id === $deviceId) {
                    $plainToken = Str::random(64);
                    $existingSession->update([
                        'session_token_hash' => hash('sha256', $plainToken),
                        'device_name' => $deviceName,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'last_heartbeat_at' => Carbon::now(),
                        'expires_at' => Carbon::now()->addHours(24),
                        'status' => 'active',
                    ]);

                    $payload = $this->formatAuthPayload($user, $plainToken, $existingSession);

                    return response()->json([
                        'status' => 'success',
                        'token' => $plainToken,
                        'user' => $payload['user'],
                        'message' => 'Sesi perangkat Anda berhasil diperbarui.',
                        'data' => $payload,
                    ]);
                }

                if (! $forceTakeover) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 'SESSION_LOCKED',
                        'message' => "Akun ini sedang aktif di browser lain ({$existingSession->device_name}). Anda dapat mengambil alih sesi untuk menggunakannya di sini.",
                        'active_session' => [
                            'device_name' => $existingSession->device_name,
                            'ip_address' => $existingSession->ip_address,
                            'last_heartbeat' => $existingSession->last_heartbeat_at?->diffForHumans() ?? 'Baru saja',
                        ],
                    ], 409);
                }
            }

            ExtensionSession::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            $plainToken = Str::random(64);
            $newSession = ExtensionSession::create([
                'user_id' => $user->id,
                'session_token_hash' => hash('sha256', $plainToken),
                'device_name' => $deviceName,
                'device_id' => $deviceId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'active',
                'last_heartbeat_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addHours(24),
            ]);

            $payload = $this->formatAuthPayload($user, $plainToken, $newSession);

            return response()->json([
                'status' => 'success',
                'token' => $plainToken,
                'user' => $payload['user'],
                'message' => 'Login ekstensi berhasil.',
                'data' => $payload,
            ]);
        });
    }

    public function heartbeat(Request $request): JsonResponse
    {
        /** @var ExtensionSession $session */
        $session = $request->attributes->get('extension_session');
        $session->touchHeartbeat(extendHours: 24);

        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'message' => 'Heartbeat tersinkronisasi.',
            'quota' => $this->getQuotaPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var ExtensionSession $session */
        $session = $request->attributes->get('extension_session');
        if ($session) {
            $session->revoke();
        }

        $user = $request->user();
        if ($user) {
            ExtensionSession::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'revoked']);
        }

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil logout dari ekstensi dan website. Kunci sesi dilepaskan.',
        ]);
    }

    public function unifiedLogin(Request $request): JsonResponse
    {
        return $this->login($request);
    }

    public function unifiedLogout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if ($token) {
            $tokenHash = hash('sha256', $token);
            ExtensionSession::where('session_token_hash', $tokenHash)->update(['status' => 'revoked']);
        }

        $user = $request->user();
        if ($user) {
            ExtensionSession::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'revoked']);
        }

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil logout dari ekstensi dan website.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        /** @var ExtensionSession $session */
        $session = $request->attributes->get('extension_session');

        return response()->json([
            'status' => 'success',
            'data' => $this->formatAuthPayload($user, null, $session),
        ]);
    }

    public function validateBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1'],
            'files_count' => ['nullable', 'integer', 'min:0'],
        ]);

        $user = $request->user();
        $isPro = $user->isPro('wasender');
        $usage = $user->getTodayUsage();

        $maxMsgs = $isPro ? -1 : 20;
        $maxFiles = $isPro ? -1 : 20;

        $requestedCount = $validated['count'];
        $requestedFiles = $validated['files_count'] ?? 0;

        if ($requestedFiles > 10) {
            return response()->json([
                'status' => 'error',
                'code' => 'MAX_ATTACHMENTS_EXCEEDED',
                'message' => 'Maksimal lampiran yang dapat dikirim adalah 10 file per broadcast.',
                'quota' => $this->getQuotaPayload($user),
            ], 422);
        }

        if ($maxMsgs !== -1) {
            $remainingMsgs = max(0, $maxMsgs - $usage->messages_sent);

            if ($usage->messages_sent + $requestedCount > $maxMsgs) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'QUOTA_EXCEEDED',
                    'message' => "Pengiriman melebihi kuota harian paket Free. Sisa kuota hari ini: {$remainingMsgs} pesan (Target: {$requestedCount} pesan). Silakan upgrade ke Pro untuk pengiriman tanpa batas.",
                    'quota' => $this->getQuotaPayload($user),
                ], 422);
            }
        }

        return response()->json([
            'status' => 'success',
            'allowed' => true,
            'quota' => $this->getQuotaPayload($user),
        ]);
    }

    public function recordSent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'success' => ['required', 'boolean'],
            'has_attachment' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        if ($validated['success']) {
            $usage = $user->getTodayUsage();
            $usage->increment('messages_sent');

            if (! empty($validated['has_attachment'])) {
                $usage->increment('files_attached');
            }
        }

        return response()->json([
            'status' => 'success',
            'quota' => $this->getQuotaPayload($user),
        ]);
    }

    private function getQuotaPayload(User $user): array
    {
        $isPro = $user->isPro('wasender');
        $usage = $user->getTodayUsage();

        $maxMsgs = $isPro ? -1 : 20;
        $maxFiles = $isPro ? -1 : 20;

        return [
            'messages_sent' => $usage->messages_sent,
            'max_daily_messages' => $maxMsgs,
            'is_unlimited_messages' => $maxMsgs === -1,
            'remaining_messages' => $maxMsgs === -1 ? 'Unlimited' : max(0, $maxMsgs - $usage->messages_sent),
            'files_attached' => $usage->files_attached,
            'max_daily_attachments' => $maxFiles,
            'is_unlimited_attachments' => $maxFiles === -1,
            'remaining_attachments' => $maxFiles === -1 ? 'Unlimited' : max(0, $maxFiles - $usage->files_attached),
        ];
    }

    private function formatAuthPayload(User $user, ?string $token, ExtensionSession $session): array
    {
        $isPro = $user->isPro('wasender');
        $sub = $user->getActiveProSubscription('wasender');

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->hasRole('superadmin') ? 'superadmin' : ($user->hasRole('admin_wasender') ? 'admin' : 'user'),
                'roles' => $user->roles->pluck('name')->toArray(),
                'phone' => $user->phone ?? null,
                'referral_code' => $user->referral_code,
            ],
            'session' => [
                'id' => $session->id,
                'device_name' => $session->device_name,
                'device_id' => $session->device_id,
                'ip_address' => $session->ip_address,
                'last_heartbeat_at' => $session->last_heartbeat_at?->toIso8601String(),
                'expires_at' => $session->expires_at?->toIso8601String(),
            ],
            'plan' => [
                'name' => $isPro ? 'Pro' : 'Free',
                'slug' => $isPro ? 'pro' : 'free',
                'is_free' => ! $isPro,
                'ends_at' => $sub?->expires_at?->format('d M Y H:i'),
            ],
            'quota' => $this->getQuotaPayload($user),
        ];
    }
}
