<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>@yield('title') - {{ config('app.name', 'Sirkelta') }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#4f46e5">

    <!-- Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />

    @vite(['resources/css/app.css'])

    <style>
        :root {
            --primary: oklch(0.52 0.22 254);
            --primary-foreground: oklch(0.99 0 0);
            --background: oklch(0.99 0.002 250);
            --foreground: oklch(0.145 0 0);
            --card: oklch(1 0 0);
            --card-foreground: oklch(0.145 0 0);
            --border: oklch(0.91 0.005 250);
            --muted: oklch(0.96 0.005 250);
            --radius: 0.75rem;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            touch-action: pan-x pan-y;
            -webkit-text-size-adjust: 100%;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--background);
            color: var(--foreground);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow-x: hidden;
        }

        /* Subtle Background Gradient Effect */
        .bg-glow {
            position: absolute;
            width: 480px;
            height: 480px;
            border-radius: 50%;
            background: radial-gradient(circle, color-mix(in srgb, var(--primary) 10%, transparent) 0%, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            position: relative;
            z-index: 1;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }

        .brand-header {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 2rem;
            text-decoration: none;
        }

        .brand-logo {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            object-fit: cover;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .brand-name {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--foreground);
            letter-spacing: -0.025em;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 1.5rem;
            padding: 2.25rem 1.75rem;
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.04), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .icon-circle {
            width: 76px;
            height: 76px;
            border-radius: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            position: relative;
        }

        .icon-circle svg {
            width: 38px;
            height: 38px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
        }

        .error-code {
            font-size: 2.75rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.03em;
            color: var(--foreground);
            margin-bottom: 0.5rem;
        }

        .error-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--foreground);
            margin-bottom: 0.5rem;
        }

        .error-message {
            font-size: 0.9rem;
            line-height: 1.55;
            color: #64748b;
            margin-bottom: 1.5rem;
            max-width: 380px;
        }

        .countdown-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            background-color: color-mix(in srgb, var(--primary) 10%, transparent);
            color: var(--primary);
            margin-bottom: 1.5rem;
            border: 1px solid color-mix(in srgb, var(--primary) 20%, transparent);
        }

        .countdown-pill svg {
            width: 14px;
            height: 14px;
            animation: spin-slow 3s linear infinite;
        }

        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .action-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            width: 100%;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            width: 100%;
            border: 1px solid transparent;
            min-height: 44px;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--primary-foreground);
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background-color: var(--background);
            color: var(--foreground);
            border-color: var(--border);
        }

        .btn-secondary:hover {
            background-color: var(--muted);
            color: var(--foreground);
        }

        .btn svg {
            width: 18px;
            height: 18px;
        }

        .footer-note {
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #94a3b8;
        }

        @media (min-width: 640px) {
            .card {
                padding: 2.75rem 2.25rem;
            }
            .action-group {
                flex-direction: row;
            }
        }
    </style>
</head>
<body>
    <div class="bg-glow"></div>

    <div class="container">
        <!-- Brand Header -->
        <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="brand-header">
            <img src="/favicon.png" alt="Sirkelta Logo" class="brand-logo" />
            <span class="brand-name">{{ config('app.name', 'Sirkelta') }}</span>
        </a>

        <!-- Error Card -->
        <div class="card">
            @yield('content')

            <div class="action-group">
                <button type="button" onclick="smartGoBack()" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    <span id="btn-back-label">Kembali ke Halaman Sebelumnya</span>
                </button>

                <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="btn btn-secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    {{ auth()->check() ? 'Ke Dashboard' : 'Ke Beranda' }}
                </a>
            </div>
        </div>

        <p class="footer-note">
            &copy; {{ date('Y') }} {{ config('app.name', 'Sirkelta') }}. Semua Hak Dilindungi.
        </p>
    </div>

    <script>
        function smartGoBack() {
            // Jika ada riwayat sebelumnya di domain ini, kembali ke halaman sebelumnya
            if (window.history.length > 1 && document.referrer && document.referrer !== window.location.href) {
                window.history.back();
            } else {
                // Fallback jika dibuka langsung / tab baru
                window.location.href = "{{ auth()->check() ? route('dashboard') : url('/') }}";
            }
        }
    </script>
    @yield('scripts')
</body>
</html>

