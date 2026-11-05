/**
 * Sirkelta PWA Offline Storage & Sync Engine (IndexedDB)
 */

const DB_NAME = 'sirkelta_pwa_db';
const DB_VERSION = 1;
const STORE_NAME = 'offline_transactions';

function openDatabase() {
    return new Promise((resolve, reject) => {
        if (!('indexedDB' in window)) {
            reject(new Error('Browser ini tidak mendukung IndexedDB.'));
            return;
        }

        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                const store = db.createObjectStore(STORE_NAME, { keyPath: 'offline_id' });
                store.createIndex('status', 'status', { unique: false });
                store.createIndex('created_at', 'created_at', { unique: false });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

/**
 * Menyimpan catatan transaksi baru saat koneksi offline
 */
export async function saveOfflineTransaction(payload) {
    const db = await openDatabase();
    const offlineId = 'offline_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
    const record = {
        offline_id: offlineId,
        payload: { ...payload },
        status: 'pending_sync',
        created_at: new Date().toISOString(),
    };

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        const req = store.add(record);

        req.onsuccess = () => resolve(record);
        req.onerror = () => reject(req.error);
    });
}

/**
 * Mengambil semua transaksi yang tertunda sinkronisasi
 */
export async function getPendingTransactions() {
    try {
        const db = await openDatabase();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_NAME, 'readonly');
            const store = tx.objectStore(STORE_NAME);
            const req = store.getAll();

            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    } catch (e) {
        return [];
    }
}

/**
 * Menghapus transaksi yang sudah berhasil disinkronkan ke server
 */
export async function removeOfflineTransaction(offlineId) {
    const db = await openDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        const req = store.delete(offlineId);

        req.onsuccess = () => resolve(true);
        req.onerror = () => reject(req.error);
    });
}

/**
 * Menghitung jumlah catatan transaksi offline yang belum disinkronkan
 */
export async function countPendingTransactions() {
    try {
        const db = await openDatabase();
        return new Promise((resolve) => {
            const tx = db.transaction(STORE_NAME, 'readonly');
            const store = tx.objectStore(STORE_NAME);
            const req = store.count();

            req.onsuccess = () => resolve(req.result || 0);
            req.onerror = () => resolve(0);
        });
    } catch (e) {
        return 0;
    }
}

/**
 * Proses sinkronisasi otomatis transaksi offline ke server Laravel saat online
 */
let isSyncing = false;

export async function syncPendingTransactions(onProgress = null) {
    if (isSyncing || !navigator.onLine) {
        return { synced: 0, failed: 0 };
    }

    const pending = await getPendingTransactions();
    if (pending.length === 0) {
        return { synced: 0, failed: 0 };
    }

    isSyncing = true;
    let syncedCount = 0;
    let failedCount = 0;

    // Ambil CSRF token
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';

    for (const item of pending) {
        try {
            const response = await fetch('/keuangan/transactions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(item.payload),
            });

            if (response.ok || response.status === 200 || response.status === 201 || response.status === 302) {
                await removeOfflineTransaction(item.offline_id);
                syncedCount++;
                if (onProgress) onProgress(syncedCount, pending.length);
            } else {
                failedCount++;
            }
        } catch (err) {
            console.error('Gagal menyinkronkan transaksi offline:', err);
            failedCount++;
            break; // Hentikan loop jika koneksi masih putus
        }
    }

    isSyncing = false;

    if (syncedCount > 0) {
        // Tampilkan notifikasi native jika diizinkan
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                navigator.serviceWorker.ready.then((reg) => {
                    reg.showNotification('Sinkronisasi Sukses', {
                        body: `${syncedCount} catatan transaksi offline berhasil disimpan ke server.`,
                        icon: '/icons/icon-192x192.png',
                        badge: '/icons/badge-72x72.png',
                        vibrate: [100, 50, 100],
                        tag: 'sync-complete',
                    });
                });
            } catch (e) {
                // Ignore fallback
            }
        }

        window.dispatchEvent(new CustomEvent('offline-sync-completed', {
            detail: { synced: syncedCount, remaining: pending.length - syncedCount }
        }));
    }

    return { synced: syncedCount, failed: failedCount };
}

/**
 * Inisialisasi pendengar event online untuk auto-sync
 */
export function initOfflineSyncListener() {
    if (typeof window === 'undefined') return;

    window.addEventListener('online', () => {
        syncPendingTransactions();
    });

    // Dengarkan pesan dari Service Worker (Background Sync)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'TRIGGER_OFFLINE_SYNC') {
                syncPendingTransactions();
            }
        });
    }

    // Periksa antrean saat aplikasi baru dibuka
    if (navigator.onLine) {
        setTimeout(() => {
            syncPendingTransactions();
        }, 2000);
    }
}

