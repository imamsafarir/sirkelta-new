<script setup>
import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { canInstallPwa, isPwaInstalled, promptPwaInstall, requestNotificationPermission, notificationPermission } from '@/lib/pwaManager';
import { countPendingTransactions, syncPendingTransactions } from '@/lib/offlineStorage';
import { Download, WifiOff, Bell, X, CheckCircle2, RefreshCw } from 'lucide-vue-next';

const isDismissed = ref(false);
const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true);
const pendingCount = ref(0);
const isSyncing = ref(false);

const updateOnlineStatus = async () => {
    isOnline.value = navigator.onLine;
    if (isOnline.value) {
        checkPending();
    }
};

const checkPending = async () => {
    pendingCount.value = await countPendingTransactions();
};

const handleManualSync = async () => {
    if (!isOnline.value || isSyncing.value) return;
    isSyncing.value = true;
    try {
        await syncPendingTransactions();
        await checkPending();
    } finally {
        isSyncing.value = false;
    }
};

const handleInstall = async () => {
    await promptPwaInstall();
};

const handleEnableNotifications = async () => {
    await requestNotificationPermission();
};

const dismissBanner = () => {
    isDismissed.value = true;
    try {
        sessionStorage.setItem('sirkelta_pwa_banner_dismissed', 'true');
    } catch (e) {}
};

onMounted(async () => {
    if (typeof window !== 'undefined') {
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        window.addEventListener('offline-sync-completed', checkPending);
        
        try {
            isDismissed.value = sessionStorage.getItem('sirkelta_pwa_banner_dismissed') === 'true';
        } catch (e) {}

        await checkPending();
    }
});
</script>

<template>
    <div class="fixed bottom-4 left-4 right-4 z-50 flex flex-col gap-2 max-w-md mx-auto pointer-events-none">
        <!-- 1. Offline Indicator Banner -->
        <div 
            v-if="!isOnline" 
            class="pointer-events-auto bg-amber-500 text-white px-4 py-2.5 rounded-xl shadow-lg flex items-center justify-between text-xs sm:text-sm font-medium animate-in slide-in-from-bottom-2 duration-200"
        >
            <div class="flex items-center gap-2">
                <WifiOff class="w-4 h-4 shrink-0" />
                <span>Mode Offline: Pencatatan tetap berjalan & tersimpan lokal.</span>
            </div>
            <span v-if="pendingCount > 0" class="bg-amber-700/60 text-white px-2 py-0.5 rounded-full text-[11px] font-bold">
                {{ pendingCount }} pending
            </span>
        </div>

        <!-- 2. Pending Sync Notification (When back online) -->
        <div 
            v-else-if="pendingCount > 0" 
            class="pointer-events-auto bg-emerald-600 text-white px-4 py-2.5 rounded-xl shadow-lg flex items-center justify-between text-xs sm:text-sm font-medium animate-in slide-in-from-bottom-2 duration-200"
        >
            <div class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 shrink-0" />
                <span>{{ pendingCount }} catatan siap disinkronkan.</span>
            </div>
            <Button 
                size="sm"
                variant="secondary"
                @click="handleManualSync" 
                :disabled="isSyncing"
                class="h-7 px-2.5 font-semibold gap-1.5 text-xs bg-white text-emerald-700 hover:bg-emerald-50 dark:bg-white dark:text-emerald-700 shadow-2xs"
            >
                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncing }" />
                {{ isSyncing ? 'Menyinkronkan...' : 'Sinkronkan' }}
            </Button>
        </div>

        <!-- 3. Install PWA Prompt (Only if not installed and not dismissed) -->
        <div 
            v-if="!isPwaInstalled && canInstallPwa && !isDismissed" 
            class="pointer-events-auto bg-card text-card-foreground border shadow-2xl p-3.5 rounded-2xl flex items-center gap-3 animate-in slide-in-from-bottom-4 duration-300 backdrop-blur-md"
        >
            <img src="/icons/icon-192x192.png" alt="Sirkelta" class="w-11 h-11 rounded-xl shadow-xs shrink-0 object-cover" />
            <div class="flex-1 min-w-0">
                <h4 class="text-xs sm:text-sm font-bold truncate">Install Aplikasi Sirkelta</h4>
                <p class="text-[11px] sm:text-xs text-muted-foreground truncate">Akses cepat & catat transaksi saat offline</p>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <Button 
                    size="sm"
                    @click="handleInstall"
                    class="h-8 px-3 font-semibold gap-1 text-xs shadow-xs"
                >
                    <Download class="w-3.5 h-3.5" />
                    <span>Install</span>
                </Button>
                <button 
                    @click="dismissBanner"
                    class="p-1.5 text-muted-foreground hover:text-foreground rounded-lg hover:bg-muted transition"
                    title="Tutup"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>
        </div>
    </div>
</template>

