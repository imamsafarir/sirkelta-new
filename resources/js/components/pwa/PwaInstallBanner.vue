<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { canInstallPwa, isPwaInstalled, promptPwaInstall } from '@/lib/pwaManager';
import { 
    countPendingTransactions, 
    getPendingTransactions, 
    removeOfflineTransaction, 
    clearAllOfflineTransactions, 
    syncPendingTransactions 
} from '@/lib/offlineStorage';
import { 
    Download, 
    WifiOff, 
    X, 
    CheckCircle2, 
    RefreshCw, 
    Layers, 
    Trash2, 
    ChevronRight,
    ArrowDownLeft,
    ArrowUpRight,
    ArrowLeftRight,
    Clock
} from 'lucide-vue-next';

const isDismissed = ref(false);
const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true);
const pendingCount = ref(0);
const isSyncing = ref(false);
const showSyncSuccess = ref(false);
const lastSyncedCount = ref(0);

// Modal Antrean State
const isQueueOpen = ref(false);
const pendingList = ref([]);

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(val || 0);
};

const formatTime = (isoString) => {
    if (!isoString) return '';
    try {
        const d = new Date(isoString);
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ', ' + d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    } catch (e) {
        return '';
    }
};

const updateOnlineStatus = async () => {
    isOnline.value = navigator.onLine;
    await checkPending();
    if (isOnline.value && pendingCount.value > 0) {
        handleManualSync();
    }
};

const checkPending = async () => {
    pendingCount.value = await countPendingTransactions();
    if (isQueueOpen.value) {
        pendingList.value = await getPendingTransactions();
    }
};

const openQueueModal = async () => {
    pendingList.value = await getPendingTransactions();
    isQueueOpen.value = true;
};

const closeQueueModal = () => {
    isQueueOpen.value = false;
};

const handleDeletePending = async (offlineId) => {
    await removeOfflineTransaction(offlineId);
    await checkPending();
};

const handleClearAll = async () => {
    if (confirm('Batalkan dan hapus seluruh antrean offline di perangkat ini?')) {
        await clearAllOfflineTransactions();
        await checkPending();
    }
};

const handleManualSync = async () => {
    if (!isOnline.value || isSyncing.value || pendingCount.value === 0) return;
    isSyncing.value = true;
    try {
        const result = await syncPendingTransactions();
        if (result.synced > 0) {
            lastSyncedCount.value = result.synced;
            showSyncSuccess.value = true;
            setTimeout(() => {
                showSyncSuccess.value = false;
            }, 3500);
            try {
                router.reload({ preserveScroll: true });
            } catch (e) {}
        }
    } finally {
        isSyncing.value = false;
        await checkPending();
    }
};

const handleInstall = async () => {
    await promptPwaInstall();
};

const dismissBanner = () => {
    isDismissed.value = true;
    try {
        localStorage.setItem('sirkelta_pwa_banner_dismissed', 'true');
    } catch (e) {}
};

onMounted(async () => {
    if (typeof window !== 'undefined') {
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        window.addEventListener('offline-sync-completed', checkPending);
        window.addEventListener('offline-queue-updated', checkPending);

        try {
            isDismissed.value = localStorage.getItem('sirkelta_pwa_banner_dismissed') === 'true';
        } catch (e) {}

        await checkPending();
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('online', updateOnlineStatus);
        window.removeEventListener('offline', updateOnlineStatus);
        window.removeEventListener('offline-sync-completed', checkPending);
        window.removeEventListener('offline-queue-updated', checkPending);
    }
});
</script>

<template>
    <!-- TOP FLOATING STATUS PILL (Non-intrusive, clear of bottom navigation & buttons) -->
    <div class="fixed top-[calc(env(safe-area-inset-top,0px)+4.25rem)] md:top-3 left-1/2 -translate-x-1/2 z-[60] pointer-events-none transition-all duration-300">
        
        <!-- State 1: Offline Mode Pill -->
        <div 
            v-if="!isOnline" 
            class="pointer-events-auto bg-amber-500/95 dark:bg-amber-600/95 text-white backdrop-blur-md px-3.5 py-1.5 rounded-full shadow-lg border border-amber-400/30 flex items-center gap-2.5 text-xs font-semibold animate-in fade-in slide-in-from-top-2 duration-200"
        >
            <span class="flex h-2 w-2 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-200 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
            </span>
            <div class="flex items-center gap-1.5">
                <WifiOff class="w-3.5 h-3.5 shrink-0" />
                <span>Mode Offline</span>
                <span class="hidden sm:inline font-normal opacity-85">• Data tersimpan</span>
            </div>

            <!-- Antrean Badge Button -->
            <button 
                v-if="pendingCount > 0"
                @click="openQueueModal"
                class="ml-1 bg-amber-700/80 hover:bg-amber-800 text-white px-2 py-0.5 rounded-full text-[11px] font-bold flex items-center gap-1 transition shadow-2xs"
                title="Buka daftar antrean transaksi offline"
            >
                <Layers class="w-3 h-3" />
                <span>Antrean: {{ pendingCount }}</span>
                <ChevronRight class="w-3 h-3 opacity-80" />
            </button>
        </div>

        <!-- State 2: Syncing In Progress Pill -->
        <div 
            v-else-if="isOnline && isSyncing" 
            class="pointer-events-auto bg-indigo-600/95 text-white backdrop-blur-md px-3.5 py-1.5 rounded-full shadow-lg border border-indigo-400/30 flex items-center gap-2 text-xs font-semibold animate-in fade-in slide-in-from-top-2 duration-200"
        >
            <RefreshCw class="w-3.5 h-3.5 animate-spin" />
            <span>Menyinkronkan {{ pendingCount }} transaksi...</span>
        </div>

        <!-- State 3: Back Online with Pending Items Pill -->
        <div 
            v-else-if="isOnline && pendingCount > 0" 
            class="pointer-events-auto bg-emerald-600/95 text-white backdrop-blur-md px-3.5 py-1.5 rounded-full shadow-lg border border-emerald-400/30 flex items-center gap-2.5 text-xs font-semibold animate-in fade-in slide-in-from-top-2 duration-200"
        >
            <div class="flex items-center gap-1.5">
                <CheckCircle2 class="w-3.5 h-3.5 shrink-0" />
                <span>{{ pendingCount }} antrean siap sinkron</span>
            </div>
            <button 
                @click="openQueueModal"
                class="bg-emerald-700/80 hover:bg-emerald-800 text-white px-2 py-0.5 rounded-full text-[11px] font-bold flex items-center gap-1 transition"
            >
                <span>Lihat</span>
                <ChevronRight class="w-3 h-3 opacity-80" />
            </button>
            <Button 
                size="sm"
                variant="secondary"
                @click="handleManualSync" 
                class="h-6 px-2 text-[11px] font-bold bg-white text-emerald-700 hover:bg-emerald-50 rounded-full shadow-2xs"
            >
                Sinkronkan
            </Button>
        </div>

        <!-- State 4: Sync Succeeded Toast Pill -->
        <div 
            v-else-if="showSyncSuccess" 
            class="pointer-events-auto bg-emerald-600 text-white backdrop-blur-md px-4 py-1.5 rounded-full shadow-lg border border-emerald-400/30 flex items-center gap-2 text-xs font-semibold animate-in fade-in slide-in-from-top-2 duration-200"
        >
            <CheckCircle2 class="w-3.5 h-3.5" />
            <span>{{ lastSyncedCount }} transaksi berhasil disinkronkan ke server!</span>
        </div>
    </div>

    <!-- MODAL / DIALOG ANTREAN SINKRONISASI OFFLINE -->
    <div 
        v-if="isQueueOpen" 
        class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-4 bg-background/80 backdrop-blur-xs select-none animate-in fade-in duration-200"
        @click.self="closeQueueModal"
    >
        <div class="bg-card text-card-foreground border shadow-2xl rounded-2xl w-full max-w-lg flex flex-col overflow-hidden max-h-[85vh] animate-in zoom-in-95 duration-200">
            <!-- Modal Header -->
            <div class="p-4 sm:p-5 border-b flex items-center justify-between bg-muted/40">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                        <Layers class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-bold text-sm sm:text-base leading-tight">Antrean Sinkronisasi Offline</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            {{ pendingList.length }} transaksi tersimpan lokal di perangkat ini
                        </p>
                    </div>
                </div>
                <button 
                    @click="closeQueueModal" 
                    class="p-2 text-muted-foreground hover:text-foreground rounded-lg hover:bg-muted transition"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Modal Body: Queue List -->
            <div class="p-4 sm:p-5 flex-1 overflow-y-auto space-y-3">
                <div v-if="pendingList.length === 0" class="text-center py-10">
                    <CheckCircle2 class="w-12 h-12 text-emerald-500 mx-auto mb-3 opacity-90" />
                    <h4 class="font-bold text-sm">Tidak Ada Antrean Pending</h4>
                    <p class="text-xs text-muted-foreground mt-1 max-w-xs mx-auto">
                        Semua catatan transaksi Anda telah aman tersinkronisasi ke server.
                    </p>
                </div>

                <div 
                    v-for="item in pendingList" 
                    :key="item.offline_id"
                    class="p-3.5 rounded-xl border bg-card hover:bg-muted/30 transition flex items-center justify-between gap-3 shadow-2xs"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <div 
                            class="h-9 w-9 rounded-lg flex items-center justify-center shrink-0"
                            :class="{
                                'bg-emerald-500/10 text-emerald-600': item.payload?.type === 'income',
                                'bg-rose-500/10 text-rose-600': item.payload?.type === 'expense',
                                'bg-blue-500/10 text-blue-600': item.payload?.type === 'transfer',
                            }"
                        >
                            <ArrowDownLeft v-if="item.payload?.type === 'income'" class="w-4 h-4" />
                            <ArrowUpRight v-else-if="item.payload?.type === 'expense'" class="w-4 h-4" />
                            <ArrowLeftRight v-else class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-xs sm:text-sm">
                                    {{ formatCurrency(item.payload?.amount) }}
                                </span>
                                <span 
                                    class="text-[10px] font-bold px-1.5 py-0.5 rounded-md uppercase"
                                    :class="{
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300': item.payload?.type === 'income',
                                        'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300': item.payload?.type === 'expense',
                                        'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300': item.payload?.type === 'transfer',
                                    }"
                                >
                                    {{ item.payload?.type === 'income' ? 'Pemasukan' : (item.payload?.type === 'expense' ? 'Pengeluaran' : 'Transfer') }}
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground truncate mt-0.5">
                                {{ item.payload?.description || item.payload?.category || 'Tanpa keterangan' }}
                                <span v-if="item.payload?.party_name">• {{ item.payload.party_name }}</span>
                            </p>
                            <div class="flex items-center gap-1.5 text-[10px] text-muted-foreground mt-1">
                                <Clock class="w-3 h-3 opacity-70" />
                                <span>{{ formatTime(item.created_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <button 
                        @click="handleDeletePending(item.offline_id)"
                        class="p-2 text-muted-foreground hover:text-destructive rounded-lg hover:bg-destructive/10 transition shrink-0"
                        title="Hapus antrean ini"
                    >
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-4 sm:p-5 border-t bg-muted/20 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="text-[11px] text-muted-foreground text-center sm:text-left">
                    <span v-if="!isOnline" class="text-amber-600 dark:text-amber-400 font-medium">
                        ⚠️ Terputus: Antrean akan otomatis dikirim saat internet terhubung.
                    </span>
                    <span v-else class="text-emerald-600 dark:text-emerald-400 font-medium">
                        ✓ Terhubung: Klik tombol sinkronkan untuk memproses antrean.
                    </span>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <button 
                        v-if="pendingList.length > 0"
                        @click="handleClearAll"
                        class="px-3 py-1.5 text-xs text-muted-foreground hover:text-destructive font-medium transition"
                    >
                        Kosongkan
                    </button>
                    <Button 
                        variant="outline" 
                        size="sm" 
                        @click="closeQueueModal"
                        class="h-8 px-3 text-xs"
                    >
                        Tutup
                    </Button>
                    <Button 
                        v-if="pendingList.length > 0"
                        size="sm" 
                        @click="handleManualSync"
                        :disabled="!isOnline || isSyncing"
                        class="h-8 px-4 text-xs font-semibold gap-1.5"
                    >
                        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncing }" />
                        {{ isSyncing ? 'Menyinkronkan...' : 'Sinkronkan Sekarang' }}
                    </Button>
                </div>
            </div>
        </div>
    </div>

    <!-- PWA INSTALL PROMPT CARD (Centered at bottom with safe area) -->
    <div 
        v-if="!isPwaInstalled && canInstallPwa && !isDismissed" 
        class="fixed bottom-[calc(5rem+env(safe-area-inset-bottom,0px))] md:bottom-8 left-1/2 -translate-x-1/2 z-40 w-[calc(100vw-2rem)] max-w-md pointer-events-none animate-in slide-in-from-bottom-4 duration-300"
    >
        <div class="pointer-events-auto bg-card/95 text-card-foreground border shadow-2xl p-3.5 sm:p-4 rounded-2xl flex items-center gap-3 backdrop-blur-md">
            <img src="/icons/icon-192x192.png" alt="Sirkelta" class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl shadow-xs shrink-0 object-cover border" />
            <div class="flex-1 min-w-0">
                <h4 class="text-xs sm:text-sm font-bold truncate">Install Aplikasi Sirkelta</h4>
                <p class="text-[11px] sm:text-xs text-muted-foreground truncate">Akses cepat, hemat kuota & bekerja offline</p>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <Button 
                    size="sm"
                    @click="handleInstall"
                    class="h-8 px-3.5 font-semibold gap-1.5 text-xs shadow-xs rounded-xl"
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
