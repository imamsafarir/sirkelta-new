<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Download,
    Laptop,
    Radio,
    ShieldCheck,
    LogOut,
    ExternalLink,
    Users,
    FileText,
    Send,
    ArrowRight,
    Sparkles,
    CheckCircle2,
    FolderCheck,
    Terminal,
    AlertCircle,
    Info
} from 'lucide-vue-next';

const props = defineProps({
    activeSession: {
        type: Object,
        default: null,
    },
    stats: {
        type: Object,
        required: true,
    },
    downloadUrl: {
        type: String,
        default: '/wasender/download/extension',
    },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user);
const isRevoking = ref(false);

function revokeSession() {
    if (confirm('Putuskan sesi ekstensi aktif saat ini? Ekstensi di browser harus masuk kembali.')) {
        isRevoking.value = true;
        router.post('/wasender/dashboard/extension-session/revoke', {}, {
            preserveScroll: true,
            onFinish: () => {
                isRevoking.value = false;
            },
        });
    }
}
</script>

<template>
    <DashboardLayout :user="authUser" active-module="wasender" title="Dashboard WaSender">
        <Head title="Dashboard WaSender - Broadcast WhatsApp" />

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Welcome & Download Hero Banner -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-teal-700 to-slate-900 p-6 sm:p-8 text-white shadow-lg">
                <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div class="space-y-2 max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-emerald-200 text-xs font-semibold border border-white/10">
                            <Sparkles class="w-3.5 h-3.5" />
                            <span>Arsitektur Chrome Extension MV3</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                            Kirim Pesan WhatsApp Massal Lebih Aman & Terkontrol
                        </h1>
                        <p class="text-xs sm:text-sm text-emerald-100/90 leading-relaxed">
                            Unduh ekstensi Google Chrome resmi WaSender Sirkelta, pasang secara Developer Mode, dan kelola buku kontak serta draf template Anda langsung dari panel ini.
                        </p>
                    </div>

                    <div class="shrink-0 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <a
                            :href="downloadUrl"
                            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-white text-emerald-800 hover:bg-emerald-50 font-bold text-xs shadow-md transition transform hover:-translate-y-0.5 active:translate-y-0 cursor-pointer"
                        >
                            <Download class="w-4 h-4 text-emerald-600" />
                            <span>Unduh Ekstensi Chrome (.zip)</span>
                        </a>
                        <a
                            href="https://web.whatsapp.com"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs border border-white/20 transition cursor-pointer"
                        >
                            <span>Buka WhatsApp Web</span>
                            <ExternalLink class="w-3.5 h-3.5 text-emerald-200" />
                        </a>
                    </div>
                </div>

                <!-- Abstract decorative glow -->
                <div class="absolute -right-10 -bottom-10 w-72 h-72 rounded-full bg-emerald-400/20 blur-3xl pointer-events-none"></div>
            </div>

            <!-- Stats Overview 4-Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Kontak -->
                <Card class="shadow-xs hover:border-emerald-300 transition-all">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardDescription class="text-xs font-semibold uppercase tracking-wider">Total Kontak</CardDescription>
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <Users class="w-4 h-4" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-extrabold text-foreground font-mono">
                            {{ stats.contacts_count }}
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-1">
                            Tersimpan di buku kontak
                        </p>
                    </CardContent>
                </Card>

                <!-- Grup -->
                <Card class="shadow-xs hover:border-emerald-300 transition-all">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardDescription class="text-xs font-semibold uppercase tracking-wider">Kategori Grup</CardDescription>
                        <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <FolderCheck class="w-4 h-4" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-extrabold text-foreground font-mono">
                            {{ stats.groups_count }}
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-1">
                            Segmentasi penerima pesan
                        </p>
                    </CardContent>
                </Card>

                <!-- Template -->
                <Card class="shadow-xs hover:border-emerald-300 transition-all">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardDescription class="text-xs font-semibold uppercase tracking-wider">Template Pesan</CardDescription>
                        <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
                            <FileText class="w-4 h-4" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-extrabold text-foreground font-mono">
                            {{ stats.templates_count }}
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-1">
                            Draf personalisasi & anti-banned
                        </p>
                    </CardContent>
                </Card>

                <!-- Pengiriman Hari Ini -->
                <Card class="shadow-xs hover:border-emerald-300 transition-all">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardDescription class="text-xs font-semibold uppercase tracking-wider">Terkirim Hari Ini</CardDescription>
                        <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                            <Send class="w-4 h-4" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-extrabold text-foreground font-mono">
                            {{ stats.sent_today || 0 }}
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-1">
                            Pesan dikirim via ekstensi hari ini
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Extension Session Guard Card -->
            <Card class="shadow-xs border-border">
                <CardHeader class="border-b pb-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                <Laptop class="w-5 h-5" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <CardTitle class="text-base font-bold text-foreground">Sesi Ekstensi Google Chrome</CardTitle>
                                    <Badge
                                        v-if="activeSession && activeSession.is_active"
                                        class="bg-emerald-100 text-emerald-800 border-emerald-200 text-[10px] font-bold"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse mr-1"></span>
                                        Terhubung Aktif
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="secondary"
                                        class="text-[10px] font-semibold text-muted-foreground"
                                    >
                                        Belum Terhubung
                                    </Badge>
                                </div>
                                <CardDescription class="text-xs mt-0.5">
                                    Kontrol sesi otentikasi ekstensi Chrome untuk pengiriman pesan di WhatsApp Web.
                                </CardDescription>
                            </div>
                        </div>

                        <div v-if="activeSession && activeSession.is_active">
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                :disabled="isRevoking"
                                @click="revokeSession"
                                class="text-xs font-semibold gap-1.5 cursor-pointer"
                            >
                                <LogOut class="w-3.5 h-3.5" />
                                <span>{{ isRevoking ? 'Memutuskan...' : 'Putuskan Sesi' }}</span>
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="pt-5">
                    <!-- If Connected: Device Details -->
                    <div v-if="activeSession && activeSession.is_active" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="p-3.5 bg-muted/40 rounded-2xl border border-border flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-background border border-border flex items-center justify-center shrink-0 text-muted-foreground">
                                <Laptop class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <span class="text-muted-foreground text-[10px] uppercase font-bold block">Nama Perangkat</span>
                                <strong class="text-foreground text-xs font-semibold truncate block">{{ activeSession.device_name }}</strong>
                            </div>
                        </div>

                        <div class="p-3.5 bg-muted/40 rounded-2xl border border-border flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-background border border-border flex items-center justify-center shrink-0 text-muted-foreground">
                                <Radio class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <span class="text-muted-foreground text-[10px] uppercase font-bold block">Alamat IP</span>
                                <strong class="text-foreground text-xs font-mono truncate block">{{ activeSession.ip_address || 'Lokal' }}</strong>
                            </div>
                        </div>

                        <div class="p-3.5 bg-muted/40 rounded-2xl border border-border flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-background border border-border flex items-center justify-center shrink-0 text-emerald-600">
                                <ShieldCheck class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <span class="text-muted-foreground text-[10px] uppercase font-bold block">Heartbeat Terakhir</span>
                                <strong class="text-foreground text-xs font-semibold truncate block">{{ activeSession.last_heartbeat_at }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- If Not Connected -->
                    <div v-else class="p-4 rounded-2xl bg-muted/30 border border-border flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <Info class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
                            <div class="space-y-1">
                                <h4 class="text-xs font-bold text-foreground">Ekstensi Belum Terhubung</h4>
                                <p class="text-xs text-muted-foreground leading-relaxed">
                                    Buka browser Google Chrome Anda, klik ikon ekstensi WaSender di toolbar, lalu masuk menggunakan kredensial akun Sirkelta Anda atau buka WhatsApp Web untuk sinkronisasi otomatis.
                                </p>
                            </div>
                        </div>

                        <a
                            href="https://web.whatsapp.com"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs transition shrink-0 cursor-pointer shadow-xs"
                        >
                            <span>Buka WhatsApp Web</span>
                            <ExternalLink class="w-3.5 h-3.5" />
                        </a>
                    </div>
                </CardContent>
            </Card>

            <!-- Developer Mode Installation Guide Card -->
            <Card class="shadow-xs border-border">
                <CardHeader class="pb-3 border-b">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                            <Terminal class="w-4 h-4" />
                        </div>
                        <div>
                            <CardTitle class="text-base font-bold text-foreground">Panduan Pemasangan Ekstensi di Google Chrome (Developer Mode)</CardTitle>
                            <CardDescription class="text-xs">
                                Ikuti langkah mudah berikut untuk memasang ekstensi WaSender secara manual di browser Anda:
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="pt-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Step 1 -->
                        <div class="p-4 rounded-2xl border border-border bg-card flex flex-col justify-between space-y-3">
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="w-6 h-6 rounded-full bg-emerald-600 text-white font-bold text-xs flex items-center justify-center">1</span>
                                    <Download class="w-4 h-4 text-emerald-600" />
                                </div>
                                <h4 class="text-xs font-bold text-foreground">Unduh & Ekstrak Berkas ZIP</h4>
                                <p class="text-[11px] text-muted-foreground leading-relaxed">
                                    Klik tombol unduh di bagian atas untuk mendapatkan berkas <code class="bg-muted px-1 py-0.5 rounded text-[10px] font-mono font-bold">wasender-chrome-extension.zip</code>, lalu ekstrak ke folder komputer Anda.
                                </p>
                            </div>
                            <a :href="downloadUrl" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 inline-flex items-center gap-1 cursor-pointer">
                                <span>Unduh Sekarang</span>
                                <ArrowRight class="w-3 h-3" />
                            </a>
                        </div>

                        <!-- Step 2 -->
                        <div class="p-4 rounded-2xl border border-border bg-card flex flex-col justify-between space-y-3">
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="w-6 h-6 rounded-full bg-teal-600 text-white font-bold text-xs flex items-center justify-center">2</span>
                                    <Laptop class="w-4 h-4 text-teal-600" />
                                </div>
                                <h4 class="text-xs font-bold text-foreground">Buka chrome://extensions</h4>
                                <p class="text-[11px] text-muted-foreground leading-relaxed">
                                    Buka tab baru di browser Google Chrome, ketik <code class="bg-muted px-1.5 py-0.5 rounded text-[10px] font-mono font-bold text-foreground">chrome://extensions</code> di address bar, lalu aktifkan toggle <strong>Developer mode</strong> (Mode Pengembang) di kanan atas.
                                </p>
                            </div>
                            <span class="text-[11px] font-semibold text-muted-foreground">Pojok Kanan Atas Browser</span>
                        </div>

                        <!-- Step 3 -->
                        <div class="p-4 rounded-2xl border border-border bg-card flex flex-col justify-between space-y-3">
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="w-6 h-6 rounded-full bg-sky-600 text-white font-bold text-xs flex items-center justify-center">3</span>
                                    <FolderCheck class="w-4 h-4 text-sky-600" />
                                </div>
                                <h4 class="text-xs font-bold text-foreground">Load Unpacked & Selesai</h4>
                                <p class="text-[11px] text-muted-foreground leading-relaxed">
                                    Klik tombol <strong>Load unpacked</strong> (Muat yang belum dibongkar) di kiri atas, lalu pilih folder hasil ekstrak tadi. Ekstensi siap digunakan dan otomatis tersambung saat membuka WhatsApp Web!
                                </p>
                            </div>
                            <a href="https://web.whatsapp.com" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold text-sky-600 hover:text-sky-700 inline-flex items-center gap-1 cursor-pointer">
                                <span>Buka WhatsApp Web</span>
                                <ExternalLink class="w-3 h-3" />
                            </a>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Quick Navigation Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Link
                    :href="route('wasender.contacts.index')"
                    class="p-5 bg-card rounded-2xl border border-border hover:border-emerald-400 shadow-xs hover:shadow-md transition flex items-center justify-between group"
                >
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                            <Users class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-foreground group-hover:text-emerald-700 transition">Buku Kontak & Grup</h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Kelola nomor WhatsApp, segmentasi grup, dan impor/ekspor CSV.</p>
                        </div>
                    </div>
                    <ArrowRight class="w-5 h-5 text-muted-foreground group-hover:text-emerald-600 transition" />
                </Link>

                <Link
                    :href="route('wasender.contacts.index', { tab: 'templates' })"
                    class="p-5 bg-card rounded-2xl border border-border hover:border-teal-400 shadow-xs hover:shadow-md transition flex items-center justify-between group"
                >
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-700 flex items-center justify-center shrink-0">
                            <FileText class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-foreground group-hover:text-teal-700 transition">Draf Template Pesan</h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Tulis template pesan dengan variabel {nama}, spintax, dan kode acak anti-banned.</p>
                        </div>
                    </div>
                    <ArrowRight class="w-5 h-5 text-muted-foreground group-hover:text-teal-600 transition" />
                </Link>
            </div>
        </div>
    </DashboardLayout>
</template>
