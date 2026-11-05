<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotentRequest
{
    /**
     * Handle an incoming request with atomic lock & idempotency check.
     * Mencegah duplikasi transaksi dan race conditions akibat double submit / koneksi tidak stabil.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $method = strtoupper($request->method());

        // Hanya terapkan pada operasi mutasi (POST, PUT, PATCH, DELETE)
        if (! in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        // Pengecualian webhook pihak ketiga (sudah memiliki mekanisme verifikasi sendiri)
        if ($request->is('api/midtrans/*') || $request->is('webhooks/*')) {
            return $next($request);
        }

        $user = $request->user();
        $scope = $user ? "user_{$user->id}" : 'guest_'.hash('xxh128', $request->ip() ?? 'unknown');

        $headerKey = $request->header('X-Idempotency-Key');
        $isExplicitKey = ! empty($headerKey) && is_string($headerKey);

        if ($isExplicitKey) {
            $idempotencyKey = substr($headerKey, 0, 64);
            $cacheKey = "idempotency_res:{$scope}:{$idempotencyKey}";
            $lockKey = "idempotency_lock:{$scope}:{$idempotencyKey}";
        } else {
            // Buat signature lock dari method, path, dan payload untuk mencegah race condition bersamaan
            $payload = $request->except(['_token', 'remember']);
            array_walk_recursive($payload, function (&$value): void {
                if ($value instanceof UploadedFile) {
                    $value = $value->getClientOriginalName().':'.$value->getSize();
                }
            });

            $idempotencyKey = hash('xxh128', $method.'|'.$request->path().'|'.json_encode($payload));
            $cacheKey = null; // Jangan simpan cache jangka panjang tanpa token eksplisit
            $lockKey = "idempotency_lock:{$scope}:{$idempotencyKey}";
        }

        // 1. Jika terdapat explicit key, periksa apakah respon transaksi identik ini sudah selesai sebelumnya
        if ($isExplicitKey && $cacheKey) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $this->reconstructResponse($cached);
            }
        }

        // 2. Kunci atomic lock (tunggu hingga 4 detik jika ada request identik yang sedang berjalan)
        $lock = Cache::lock($lockKey, 10);

        try {
            return $lock->block(4, function () use ($request, $next, $cacheKey, $isExplicitKey): Response {
                // Periksa ulang di dalam lock untuk memastikan request yang baru saja selesai tidak dieksekusi ulang
                if ($isExplicitKey && $cacheKey) {
                    $cached = Cache::get($cacheKey);
                    if (is_array($cached)) {
                        return $this->reconstructResponse($cached);
                    }
                }

                $response = $next($request);

                // Periksa apakah terdapat validation error dalam sesi
                $hasErrors = $request->hasSession() && $request->session()->has('errors');

                // Hanya simpan respon yang benar-benar berhasil jika menggunakan explicit key
                if ($isExplicitKey && $cacheKey && ($response->isSuccessful() || $response->isRedirection()) && ! $hasErrors && $response->getStatusCode() < 400) {
                    $isRedirect = $response->isRedirection();
                    $cachedData = [
                        'is_redirect' => $isRedirect,
                        'status' => $response->getStatusCode(),
                        'target' => $isRedirect && method_exists($response, 'getTargetUrl') ? $response->getTargetUrl() : null,
                        'content' => ! $isRedirect ? $response->getContent() : '',
                        'headers' => [
                            'Content-Type' => $response->headers->get('Content-Type'),
                            'X-Inertia' => $response->headers->get('X-Inertia'),
                        ],
                    ];

                    Cache::put($cacheKey, $cachedData, 120);
                }

                return $response;
            });
        } catch (LockTimeoutException) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Permintaan serupa sedang diproses oleh server. Mohon tunggu sejenak.',
                ], 429);
            }

            return back()->with('error', 'Permintaan serupa sedang diproses oleh server. Mohon tunggu sejenak.');
        }
    }

    /**
     * Membangun kembali respon HTTP dari data cache yang tersimpan.
     *
     * @param  array{is_redirect: bool, status: int, target: ?string, content: string, headers: array<string, ?string>}  $cached
     */
    protected function reconstructResponse(array $cached): Response
    {
        if (! empty($cached['is_redirect']) && ! empty($cached['target'])) {
            return redirect()->to($cached['target'], $cached['status'] ?? 302)
                ->withHeaders(['X-Idempotent-Replay' => 'true']);
        }

        $response = response($cached['content'] ?? '', $cached['status'] ?? 200);

        if (! empty($cached['headers']) && is_array($cached['headers'])) {
            foreach ($cached['headers'] as $name => $value) {
                if ($value !== null) {
                    $response->headers->set($name, $value);
                }
            }
        }

        $response->headers->set('X-Idempotent-Replay', 'true');

        return $response;
    }
}
