<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { 
    ArrowLeft, 
    Home, 
    FileQuestion, 
    ShieldAlert, 
    Clock, 
    ServerCrash, 
    Wrench,
    ZapOff
} from 'lucide-vue-next';

const props = defineProps({
    status: {
        type: [Number, String],
        default: 404,
    },
    auth: {
        type: Object,
        default: () => ({ user: null }),
    },
});

const statusCode = computed(() => Number(props.status) || 404);
const countdown = ref(5);
let timer = null;

const errorDetails = computed(() => {
    switch (statusCode.value) {
        case 404:
            return {
                title: 'Halaman Tidak Ditemukan',
                badge: 'Error 404',
                message: 'Maaf, tautan yang Anda tuju tidak tersedia, salah ketik, atau telah dipindahkan ke alamat baru.',
                icon: FileQuestion,
                bgClass: 'bg-primary/10 text-primary',
                badgeClass: 'bg-primary/10 text-primary border border-primary/20',
            };
        case 403:
            return {
                title: 'Akses Ditolak',
                badge: 'Izin Ditolak',
                message: 'Halaman atau menu ini dibatasi khusus untuk tingkatan akun atau role tertentu. Silakan hubungi pengelola jika Anda memerlukan akses.',
                icon: ShieldAlert,
                bgClass: 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400',
                badgeClass: 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300',
            };
        case 419:
            return {
                title: 'Sesi Halaman Kedaluwarsa',
                badge: 'Sesi Berakhir',
                message: 'Sesi keamanan Anda telah berakhir karena tidak ada aktivitas dalam beberapa waktu. Silakan muat ulang halaman.',
                icon: Clock,
                bgClass: 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300',
                badgeClass: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300',
            };
        case 429:
            return {
                title: 'Terlalu Banyak Permintaan',
                badge: 'Batas Frekuensi',
                message: 'Sistem mendeteksi terlalu banyak permintaan dalam waktu singkat. Mohon tunggu beberapa detik sebelum mencoba kembali.',
                icon: ZapOff,
                bgClass: 'bg-orange-50 dark:bg-orange-950/40 text-orange-600 dark:text-orange-400',
                badgeClass: 'bg-orange-50 dark:bg-orange-950/60 text-orange-700 dark:text-orange-300',
            };
        case 503:
            return {
                title: 'Sistem Sedang Diperbarui',
                badge: 'Pemeliharaan',
                message: 'Layanan sedang dalam proses pemeliharaan rutin atau pembaruan fitur demi kenyamanan Anda. Kami akan segera kembali.',
                icon: Wrench,
                bgClass: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400',
                badgeClass: 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300',
            };
        case 500:
        default:
            return {
                title: 'Terjadi Kesalahan pada Server',
                badge: 'Gangguan Server',
                message: 'Terjadi kendala internal pada sistem saat memproses permintaan Anda. Tim kami telah mencatat kendala ini dan sedang memperbaikinya.',
                icon: ServerCrash,
                bgClass: 'bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400',
                badgeClass: 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300',
            };
    }
});

const goBack = () => {
    if (timer) clearInterval(timer);
    if (typeof window !== 'undefined' && window.history.length > 1 && document.referrer && document.referrer !== window.location.href) {
        window.history.back();
    } else {
        router.visit(props.auth?.user ? '/dashboard' : '/');
    }
};

onMounted(() => {
    if (statusCode.value === 404) {
        timer = setInterval(() => {
            countdown.value--;
            if (countdown.value <= 0) {
                clearInterval(timer);
                goBack();
            }
        }, 1000);
    }
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <Head :title="`${statusCode} - ${errorDetails.title}`" />

    <div class="min-h-screen bg-slate-50 dark:bg-background text-foreground flex flex-col items-center justify-center p-4 sm:p-6 relative overflow-hidden select-none">
        <!-- Subtle Glow Effect -->
        <div class="absolute w-96 h-96 bg-primary/10 rounded-full blur-3xl pointer-events-none top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"></div>

        <div class="relative z-10 w-full max-w-md text-center">
            <!-- Brand Header -->
            <Link :href="auth?.user ? '/dashboard' : '/'" class="inline-flex items-center gap-2.5 mb-8 hover:opacity-90 transition">
                <img src="/favicon.png" alt="Sirkelta Logo" class="w-9 h-9 rounded-xl shadow-xs object-cover" />
                <span class="font-extrabold text-xl tracking-tight text-foreground">Sirkelta</span>
            </Link>

            <!-- Error Card -->
            <div class="bg-card text-card-foreground border rounded-3xl p-6 sm:p-8 shadow-xl shadow-slate-200/50 dark:shadow-none flex flex-col items-center">
                <!-- Icon Circle -->
                <div 
                    class="w-18 h-18 rounded-2xl flex items-center justify-center mb-5 shadow-2xs transition-transform hover:scale-105 duration-200"
                    :class="errorDetails.bgClass"
                >
                    <component :is="errorDetails.icon" class="w-9 h-9 stroke-[2.2]" />
                </div>

                <!-- Badge Status -->
                <span 
                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-2"
                    :class="errorDetails.badgeClass"
                >
                    {{ errorDetails.badge }}
                </span>

                <!-- Error Code & Title -->
                <h1 class="text-4xl sm:text-5xl font-black tracking-tight text-foreground mb-1">
                    {{ statusCode }}
                </h1>
                <h2 class="text-lg sm:text-xl font-bold text-foreground mb-2">
                    {{ errorDetails.title }}
                </h2>

                <!-- Error Message -->
                <p class="text-xs sm:text-sm text-muted-foreground leading-relaxed mb-4 max-w-xs sm:max-w-sm">
                    {{ errorDetails.message }}
                </p>

                <!-- 5 Seconds Countdown Indicator (For 404 Not Found) -->
                <div 
                    v-if="statusCode === 404"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1 bg-primary/10 text-primary rounded-full text-xs font-semibold mb-6 border border-primary/20"
                >
                    <Clock class="w-3.5 h-3.5 animate-spin" style="animation-duration: 4s;" />
                    <span>Otomatis kembali dalam <strong class="font-bold">{{ countdown }}</strong> detik</span>
                </div>

                <!-- Action Buttons (Identical to Welcome.vue style) -->
                <div class="w-full flex flex-col sm:flex-row items-center justify-center gap-3">
                    <Button 
                        type="button" 
                        size="lg"
                        @click="goBack"
                        class="w-full sm:w-auto h-11 px-6 font-bold gap-2 shadow-xs cursor-pointer"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        <span>{{ statusCode === 404 ? `Kembali ke Halaman Sebelumnya (${countdown}s)` : 'Kembali ke Halaman Sebelumnya' }}</span>
                    </Button>

                    <Link :href="auth?.user ? '/dashboard' : '/'" class="w-full sm:w-auto">
                        <Button 
                            size="lg" 
                            variant="outline" 
                            class="w-full sm:w-auto h-11 px-6 font-semibold bg-background/60 backdrop-blur-md cursor-pointer"
                        >
                            <Home class="h-4 w-4" />
                            <span>{{ auth?.user ? 'Ke Dashboard' : 'Ke Beranda' }}</span>
                        </Button>
                    </Link>
                </div>
            </div>

            <p class="mt-6 text-xs text-muted-foreground">
                &copy; {{ new Date().getFullYear() }} Sirkelta. Semua Hak Dilindungi.
            </p>
        </div>
    </div>
</template>

