/**
 * Sirkelta PWA Manager (Service Worker, Push Notifications, Install Prompt)
 */

import { ref } from 'vue';

export const deferredInstallPrompt = ref(null);
export const canInstallPwa = ref(false);
export const isPwaInstalled = ref(false);
export const notificationPermission = ref(
    typeof window !== 'undefined' && 'Notification' in window ? Notification.permission : 'default'
);

/**
 * Cek apakah aplikasi sedang dibuka dalam mode standalone (terpasang di HP)
 */
export function checkIsStandalone() {
    if (typeof window === 'undefined') return false;
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true ||
        document.referrer.includes('android-app://')
    );
}

/**
 * Inisialisasi Service Worker & Event Install
 */
export function initPwa() {
    if (typeof window === 'undefined') return;

    isPwaInstalled.value = checkIsStandalone();

    // 1. Registrasi Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker
                .register('/sw.js')
                .then((registration) => {
                    // Cek pembaruan instan
                    registration.update();

                    // Update check
                    registration.onupdatefound = () => {
                        const installingWorker = registration.installing;
                        if (installingWorker) {
                            installingWorker.onstatechange = () => {
                                if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // Konten baru tersedia
                                    window.dispatchEvent(new CustomEvent('pwa-updated'));
                                }
                            };
                        }
                    };
                })
                .catch((error) => {
                    console.warn('Gagal mendaftarkan Service Worker:', error);
                });
        });
    }

    // 2. Tangkap event install prompt dari browser
    window.addEventListener('beforeinstallprompt', (e) => {
        // Mencegah mini-infobar default browser agar bisa dikontrol via UI
        e.preventDefault();
        deferredInstallPrompt.value = e;
        canInstallPwa.value = true;
    });

    // 3. Deteksi ketika aplikasi berhasil terpasang
    window.addEventListener('appinstalled', () => {
        canInstallPwa.value = false;
        deferredInstallPrompt.value = null;
        isPwaInstalled.value = true;
    });
}

/**
 * Trigger prompt installasi PWA dari halaman manapun
 */
export async function promptPwaInstall() {
    if (!deferredInstallPrompt.value) {
        // Jika browser tidak menyediakan prompt (misal iOS Safari), beri petunjuk manual
        if (/iPhone|iPad|iPod/.test(navigator.userAgent)) {
            alert('Untuk menginstall di iPhone/iPad: Tekan tombol "Share" (ikon panah kotak di bawah), lalu pilih "Add to Home Screen" (Tambah ke Layar Utama).');
        } else {
            alert('Aplikasi sudah terpasang atau browser Anda dapat menginstallnya melalui menu titik tiga > "Install Aplikasi".');
        }
        return false;
    }

    // Tampilkan prompt bawaan OS
    const promptEvent = deferredInstallPrompt.value;
    promptEvent.prompt();

    const { outcome } = await promptEvent.userChoice;
    deferredInstallPrompt.value = null;
    canInstallPwa.value = false;

    return outcome === 'accepted';
}

/**
 * Meminta izin Push Notification kepada pengguna
 */
export async function requestNotificationPermission() {
    if (!('Notification' in window)) {
        alert('Perangkat Anda belum mendukung fitur Notifikasi Web.');
        return 'denied';
    }

    try {
        const permission = await Notification.requestPermission();
        notificationPermission.value = permission;

        if (permission === 'granted') {
            // Tampilkan notifikasi sambutan untuk menguji tampilan resmi HP
            showSmartphoneNotification('Notifikasi Sirkelta Aktif', {
                body: 'Anda akan menerima pemberitahuan penting dan pengingat transaksi secara langsung di HP.',
                url: '/dashboard',
            });
        }

        return permission;
    } catch (error) {
        console.error('Error saat meminta izin notifikasi:', error);
        return 'denied';
    }
}

/**
 * Menampilkan notifikasi lokal dengan style smartphone resmi
 */
export async function showSmartphoneNotification(title, options = {}) {
    if (!('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }

    const defaultOptions = {
        icon: '/icons/icon-192x192.png',
        badge: '/icons/badge-72x72.png',
        vibrate: [200, 100, 200],
        tag: options.tag || 'sirkelta-alert',
        renotify: true,
        data: {
            url: options.url || '/dashboard',
        },
        ...options,
    };

    if ('serviceWorker' in navigator) {
        const registration = await navigator.serviceWorker.ready;
        registration.showNotification(title, defaultOptions);
    } else {
        new Notification(title, defaultOptions);
    }
}

