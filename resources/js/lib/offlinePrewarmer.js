/**
 * Sirkelta PWA Auto-Prewarmer
 * Otomatis mengunduh & menyimpan data semua halaman utama ke memori offline
 * sehingga saat internet putus, SEMUA halaman langsung bisa dibuka tanpa harus dikunjungi manual satu per satu.
 */

const CORE_PUBLIC_ROUTES = [
    '/',
    '/login',
    '/register',
    '/faq',
    '/tentang-kami',
    '/hubungi-kami',
    '/kebijakan-privasi',
    '/syarat-ketentuan',
    '/kebijakan-pengembalian-dana'
];

const CORE_APP_ROUTES = [
    '/portal',
    '/dashboard',
    '/profile',
    '/keuangan/dashboard',
    '/keuangan/wallets',
    '/keuangan/transactions',
    '/keuangan/radar-utang-piutang',
    '/keuangan/laporan',
    '/wasender/dashboard',
    '/wasender/contacts',
    '/wasender/templates'
];

let isWarming = false;

async function fetchRoute(url) {
    if (!navigator.onLine) return;

    try {
        // 1. Warm Inertia JSON data
        await fetch(url, {
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': '',
                'Accept': 'text/html, application/xhtml+xml',
            },
            credentials: 'same-origin',
        });

        // 2. Warm Full HTML document shell
        await fetch(url, {
            headers: {
                'Accept': 'text/html',
            },
            credentials: 'same-origin',
        });
    } catch (e) {
        // Ignore network errors during background pre-warming
    }
}

export async function warmAllPages(isAuthenticated = false) {
    if (isWarming || !navigator.onLine || typeof window === 'undefined') return;

    const sessionKey = isAuthenticated ? 'sirkelta_prewarmed_auth_v2' : 'sirkelta_prewarmed_guest_v2';
    try {
        if (sessionStorage.getItem(sessionKey) === 'true') {
            return;
        }
    } catch (e) {}

    isWarming = true;

    const routes = isAuthenticated 
        ? [...CORE_APP_ROUTES, ...CORE_PUBLIC_ROUTES]
        : CORE_PUBLIC_ROUTES;

    // Gunakan requestIdleCallback atau setTimeout bertahap agar tidak membebani main thread
    const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    for (const route of routes) {
        if (!navigator.onLine) break;
        await fetchRoute(route);
        await delay(250); // Jeda 250ms antar halaman
    }

    try {
        sessionStorage.setItem(sessionKey, 'true');
    } catch (e) {}

    isWarming = false;
}

export function initOfflinePrewarmer() {
    if (typeof window === 'undefined') return;

    // Tunggu 2 detik setelah halaman awal selesai termuat
    window.addEventListener('load', () => {
        setTimeout(() => {
            const hasAuth = !!document.querySelector('meta[name="user-authenticated"]') || 
                            document.cookie.includes('sirkelta_session') ||
                            window.location.pathname.startsWith('/portal') ||
                            window.location.pathname.startsWith('/dashboard') ||
                            window.location.pathname.startsWith('/keuangan') ||
                            window.location.pathname.startsWith('/wasender') ||
                            window.location.pathname.startsWith('/admin');

            warmAllPages(hasAuth);
        }, 2000);
    });

    // Jalankan kembali prewarming jika koneksi pulih kembali
    window.addEventListener('online', () => {
        setTimeout(() => {
            const hasAuth = window.location.pathname.startsWith('/portal') ||
                            window.location.pathname.startsWith('/dashboard') ||
                            window.location.pathname.startsWith('/keuangan') ||
                            window.location.pathname.startsWith('/wasender') ||
                            window.location.pathname.startsWith('/admin');
            warmAllPages(hasAuth);
        }, 3000);
    });
}

