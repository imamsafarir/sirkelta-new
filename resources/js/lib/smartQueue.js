import { reactive } from 'vue';

/**
 * Smart Queue & Concurrency Controller
 * Mencegah duplikasi request (spam click/double submit) dan mengatur antrean proses
 * serta menginjeksi X-Idempotency-Key agar server kebal duplikasi akibat koneksi tidak stabil.
 */

const DUPLICATE_WINDOW_MS = 1200;
const WATCHDOG_TIMEOUT_MS = 15000;

export const queueState = reactive({
    isLoading: false,
    activeCount: 0,
    queueCount: 0,
    statusText: 'Memproses permintaan...',
    showWatchdog: false,
    uploadProgress: null,
});

let watchdogTimer = null;
const recentRequests = new Map();
const activeVisits = new Set();
const manualTasks = new Set();
const activeIdempotencyKeys = new Map();

function generateUUID() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

function serializePayload(data) {
    if (!data) return '';
    try {
        if (data instanceof FormData) {
            const entries = [];
            for (const [k, v] of data.entries()) {
                entries.push(`${k}:${typeof v === 'object' && v ? v.name || 'blob' : v}`);
            }
            return entries.sort().join('&');
        }
        if (typeof data === 'object') {
            return JSON.stringify(data);
        }
    } catch {
        return '';
    }
    return String(data);
}

function cleanOldRequests() {
    const now = Date.now();
    for (const [key, timestamp] of recentRequests.entries()) {
        if (now - timestamp > DUPLICATE_WINDOW_MS) {
            recentRequests.delete(key);
        }
    }
}

function updateQueueState() {
    const total = activeVisits.size + manualTasks.size;
    queueState.activeCount = total;

    if (total > 0) {
        queueState.isLoading = true;
        startWatchdog();
    } else {
        queueState.isLoading = false;
        queueState.statusText = 'Memproses permintaan...';
        queueState.uploadProgress = null;
        stopWatchdog();
    }
}

function startWatchdog() {
    if (watchdogTimer) return;
    watchdogTimer = setTimeout(() => {
        if (activeVisits.size + manualTasks.size > 0) {
            queueState.showWatchdog = true;
        }
    }, WATCHDOG_TIMEOUT_MS);
}

function stopWatchdog() {
    clearTimeout(watchdogTimer);
    watchdogTimer = null;
    queueState.showWatchdog = false;
}

/**
 * Menghasilkan atau menggunakan kembali token idempotensi untuk mutasi yang sama.
 * Jika koneksi pengguna putus dan mengirim ulang formulir yang sama, token ini identik
 * sehingga server tahu bahwa itu bukan transaksi baru.
 */
export function getOrCreateIdempotencyKey(method = 'get', url = '', data = null) {
    const normalizedMethod = (method || 'get').toLowerCase();
    if (!['post', 'put', 'patch', 'delete'].includes(normalizedMethod)) {
        return null;
    }

    const signature = `${normalizedMethod}:${url}:${serializePayload(data)}`;
    let key = activeIdempotencyKeys.get(signature);

    if (!key) {
        key = generateUUID();
        activeIdempotencyKeys.set(signature, key);

        // Pertahankan selama 5 detik untuk mengakomodasi potensi retry akibat koneksi tidak stabil
        setTimeout(() => {
            activeIdempotencyKeys.delete(signature);
        }, 5000);
    }

    return key;
}

/**
 * Mengecek apakah request mutasi ini diizinkan atau duplikat (spam klik).
 */
export function isRequestAllowed(method = 'get', url = '', data = null) {
    cleanOldRequests();

    const normalizedMethod = (method || 'get').toLowerCase();
    const isMutation = ['post', 'put', 'patch', 'delete'].includes(normalizedMethod);

    if (isMutation && url) {
        const serializedData = serializePayload(data);
        const requestKey = `${normalizedMethod}:${url}:${serializedData}`;
        const lastTimestamp = recentRequests.get(requestKey);
        const now = Date.now();

        if (lastTimestamp && now - lastTimestamp < DUPLICATE_WINDOW_MS) {
            console.warn('[SmartQueue] Permintaan duplikat dicegah agar tidak membebani server:', requestKey);
            return false;
        }

        recentRequests.set(requestKey, now);
    }

    return true;
}

export function resetQueue() {
    activeVisits.clear();
    manualTasks.clear();
    recentRequests.clear();
    activeIdempotencyKeys.clear();
    updateQueueState();
}

/**
 * Hubungkan router Inertia dengan smartQueue
 */
export function initInertiaInterceptor(router) {
    if (!router || typeof router.on !== 'function') return;

    router.on('before', (event) => {
        const visit = event.detail?.visit || {};

        // Abaikan background / silent requests
        if (visit.headers?.['X-Silent'] || visit.headers?.['x-silent'] || visit.silent) {
            return;
        }

        const method = (visit.method || 'get').toLowerCase();
        const url = visit.url ? visit.url.toString() : '';
        const data = visit.data || null;

        // Injeksi X-Idempotency-Key untuk setiap operasi mutasi
        if (['post', 'put', 'patch', 'delete'].includes(method)) {
            const idempotencyKey = getOrCreateIdempotencyKey(method, url, data);
            if (idempotencyKey) {
                visit.headers = visit.headers || {};
                visit.headers['X-Idempotency-Key'] = idempotencyKey;
            }
        }

        const allowed = isRequestAllowed(method, url, data);
        if (!allowed) {
            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
        }
    });

    router.on('start', (event) => {
        const visit = event.detail?.visit;
        if (!visit) return;

        // Abaikan silent request
        if (visit.headers?.['X-Silent'] || visit.headers?.['x-silent'] || visit.silent) {
            return;
        }

        const method = (visit.method || 'get').toLowerCase();
        const isMutation = ['post', 'put', 'patch', 'delete'].includes(method);

        if (isMutation) {
            queueState.statusText = 'Menyimpan dan mengamankan data...';
        } else {
            queueState.statusText = 'Memuat halaman...';
        }

        activeVisits.add(visit);
        updateQueueState();
    });

    router.on('progress', (event) => {
        const progress = event.detail?.progress;
        if (progress?.percentage !== undefined) {
            queueState.uploadProgress = Math.round(progress.percentage);
            queueState.statusText = `Mengunggah berkas (${queueState.uploadProgress}%)...`;
        }
    });

    router.on('finish', (event) => {
        const visit = event.detail?.visit;
        if (visit) {
            activeVisits.delete(visit);
        } else {
            activeVisits.clear();
        }
        updateQueueState();
    });

    router.on('cancel', (event) => {
        const visit = event.detail?.visit;
        if (visit) {
            activeVisits.delete(visit);
        }
        updateQueueState();
    });

    router.on('error', (event) => {
        const visit = event.detail?.visit;
        if (visit) {
            activeVisits.delete(visit);
        }
        updateQueueState();
    });
}

/**
 * Eksekusi async function sembarang (misal: fetch API manual) dengan proteksi smartQueue
 */
export async function runWithSmartQueue(
    fn,
    { method = 'post', url = '', data = null, statusText = 'Memproses permintaan...' } = {}
) {
    const allowed = isRequestAllowed(method, url, data);
    if (!allowed) {
        return null;
    }

    const taskId = Symbol('manualTask');
    manualTasks.add(taskId);
    if (statusText) {
        queueState.statusText = statusText;
    }
    updateQueueState();

    try {
        return await fn();
    } finally {
        manualTasks.delete(taskId);
        updateQueueState();
    }
}
