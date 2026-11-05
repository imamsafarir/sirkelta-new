<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // 1. Auth Rate Limiter: Mencegah brute-force dan credential stuffing
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip())->response(function (Request $request, array $headers) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'Terlalu banyak percobaan autentikasi. Demi keamanan, mohon tunggu 1 menit sebelum mencoba kembali.',
                    ], 429, $headers);
                }

                return back()->with('error', 'Terlalu banyak percobaan autentikasi. Demi keamanan, mohon tunggu 1 menit sebelum mencoba kembali.')->withHeaders($headers);
            });
        });

        // 2. Transactions Rate Limiter: Menghindari lonjakan spam mutasi kas
        RateLimiter::for('transactions', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(60)->by($key)->response(function (Request $request, array $headers) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'Aktivitas transaksi terlalu cepat. Sistem sedang menstabilkan antrean, silakan coba beberapa saat lagi.',
                    ], 429, $headers);
                }

                return back()->with('error', 'Aktivitas transaksi terlalu cepat. Sistem sedang menstabilkan antrean, silakan coba beberapa saat lagi.')->withHeaders($headers);
            });
        });

        // 3. Bulk Operations Rate Limiter: Operasi massal seperti bulk delete
        RateLimiter::for('bulk-operations', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(15)->by($key)->response(function (Request $request, array $headers) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'Aktivitas pemrosesan massal sedang padat. Mohon tunggu sejenak.',
                    ], 429, $headers);
                }

                return back()->with('error', 'Aktivitas pemrosesan massal sedang padat. Mohon tunggu sejenak.')->withHeaders($headers);
            });
        });

        // 4. Exports Rate Limiter: Mengunduh CSV/Laporan
        RateLimiter::for('exports', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(10)->by($key)->response(function (Request $request, array $headers) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'Batas pengunduhan laporan tercapai. Silakan coba 1 menit lagi.',
                    ], 429, $headers);
                }

                return back()->with('error', 'Batas pengunduhan laporan tercapai. Silakan coba 1 menit lagi.')->withHeaders($headers);
            });
        });

        // 5. Default API Rate Limiter
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
