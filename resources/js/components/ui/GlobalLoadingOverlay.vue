<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="queueState.isLoading"
                class="fixed inset-0 z-[99999] flex items-center justify-center bg-slate-950/40 dark:bg-black/60 backdrop-blur-md select-none pointer-events-auto p-4 transition-all"
                aria-live="polite"
                aria-busy="true"
                role="status"
            >
                <!-- Center Card -->
                <div
                    class="relative flex flex-col items-center justify-center max-w-sm w-full bg-card/90 dark:bg-zinc-900/90 backdrop-blur-xl border border-border/80 shadow-2xl shadow-primary/5 rounded-3xl p-6 sm:p-8 text-center space-y-4 animate-in fade-in zoom-in-95 duration-200"
                >
                    <!-- Circular Spinner with Glow Ring -->
                    <div class="relative w-16 h-16 flex items-center justify-center">
                        <!-- Pulsing outer glow -->
                        <div class="absolute inset-0 rounded-full bg-primary/20 dark:bg-primary/30 animate-ping"></div>

                        <!-- Track background -->
                        <div class="w-16 h-16 rounded-full border-4 border-muted/50 dark:border-zinc-800"></div>

                        <!-- Spinning Circle Arc -->
                        <div
                            class="absolute inset-0 w-16 h-16 rounded-full border-4 border-transparent border-t-primary border-r-primary animate-spin"
                            style="animation-duration: 0.85s;"
                        ></div>

                        <!-- Inner Glowing Accent Dot -->
                        <div class="absolute w-3.5 h-3.5 rounded-full bg-primary shadow-sm shadow-primary animate-pulse"></div>
                    </div>

                    <!-- Status Description -->
                    <div class="space-y-1.5 w-full">
                        <h3 class="text-base font-semibold text-foreground tracking-tight">
                            {{ queueState.statusText || 'Memproses permintaan...' }}
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Mohon tunggu sejenak, sistem sedang memastikan kestabilan data.
                        </p>
                    </div>

                    <!-- Upload Progress (if any) -->
                    <div v-if="queueState.uploadProgress !== null" class="w-full space-y-1">
                        <div class="w-full bg-muted rounded-full h-1.5 overflow-hidden">
                            <div
                                class="bg-primary h-full transition-all duration-150 rounded-full"
                                :style="{ width: `${queueState.uploadProgress}%` }"
                            ></div>
                        </div>
                        <span class="text-[11px] font-mono text-muted-foreground">
                            {{ queueState.uploadProgress }}%
                        </span>
                    </div>

                    <!-- Smart Queue Badge -->
                    <div
                        v-if="queueState.activeCount > 1 || queueState.queueCount > 0"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs font-medium"
                    >
                        <ShieldCheck class="w-3.5 h-3.5" />
                        <span>Antrean cerdas aktif ({{ queueState.activeCount }} proses)</span>
                    </div>

                    <!-- Watchdog / Timeout Recovery (If taking > 15s) -->
                    <div
                        v-if="queueState.showWatchdog"
                        class="w-full pt-2 border-t border-border/60 flex flex-col items-center gap-2 text-xs text-amber-600 dark:text-amber-400"
                    >
                        <div class="flex items-center gap-1.5">
                            <AlertCircle class="w-4 h-4 shrink-0" />
                            <span>Proses membutuhkan waktu lebih lama dari biasanya.</span>
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <button
                                type="button"
                                @click="handleCancel"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium bg-muted hover:bg-muted/80 text-foreground transition"
                            >
                                Tutup Loading
                            </button>
                            <button
                                type="button"
                                @click="handleReload"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium bg-primary hover:bg-primary/90 text-primary-foreground transition flex items-center gap-1"
                            >
                                <RefreshCw class="w-3 h-3" />
                                Muat Ulang
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { queueState, resetQueue } from '@/lib/smartQueue';
import { AlertCircle, RefreshCw, ShieldCheck } from 'lucide-vue-next';

const handleCancel = () => {
    resetQueue();
};

const handleReload = () => {
    resetQueue();
    window.location.reload();
};
</script>

