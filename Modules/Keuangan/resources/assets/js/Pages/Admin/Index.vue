<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Users,
    Search,
    ShieldCheck,
    ChevronLeft,
    ChevronRight,
    RotateCcw,
    Gift,
    ShieldAlert,
    AlertTriangle,
    CheckCircle2,
    Lock,
    Clock,
    Activity,
    Calendar
} from 'lucide-vue-next';

const props = defineProps({
    metrics: {
        type: Object,
        required: true,
    },
    users: {
        type: Object,
        required: true,
    },
    referral_logs: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    user: {
        type: Object,
        required: true,
    },
});

// User Filters
const searchQuery = ref(props.filters.search || '');
const roleFilter = ref(props.filters.role || '');

// Referral Filters
const referralFilter = ref(props.filters.referral_filter || '');
const referralSearch = ref(props.filters.referral_search || '');

const applyUserFilter = () => {
    router.get(
        route('keuangan.admin.index'),
        {
            search: searchQuery.value || undefined,
            role: roleFilter.value || undefined,
            referral_filter: referralFilter.value || undefined,
            referral_search: referralSearch.value || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const resetUserFilter = () => {
    searchQuery.value = '';
    roleFilter.value = '';
    applyUserFilter();
};

const applyReferralFilter = () => {
    router.get(
        route('keuangan.admin.index'),
        {
            search: searchQuery.value || undefined,
            role: roleFilter.value || undefined,
            referral_filter: referralFilter.value || undefined,
            referral_search: referralSearch.value || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const resetReferralFilter = () => {
    referralFilter.value = '';
    referralSearch.value = '';
    applyReferralFilter();
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(val || 0);
};

const getRoleBadge = (role) => {
    switch (role) {
        case 'superadmin':
            return { label: 'Superadmin', class: 'bg-primary/10 text-primary border-primary/20' };
        case 'admin_keuangan':
            return { label: 'Admin Keuangan', class: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' };
        case 'user_keuangan':
            return { label: 'User Keuangan', class: 'bg-sky-500/10 text-sky-600 border-sky-500/20' };
        case 'admin_wasender':
            return { label: 'Admin WaSender', class: 'bg-indigo-500/10 text-indigo-600 border-indigo-500/20' };
        case 'user_wasender':
            return { label: 'User WaSender', class: 'bg-slate-500/10 text-slate-600 border-slate-500/20' };
        default:
            return { label: role, class: 'bg-muted text-foreground' };
    }
};

const getInitials = (name) => {
    return name
        ?.split(' ')
        .map((n) => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase() || 'US';
};

// Anti-Fraud Modal & Actions
const activeModalLog = ref(null);
const isActionModalOpen = ref(false);

const actionForm = useForm({
    status: 'blocked',
    fraud_reason: '',
});

const openActionModal = (log, targetStatus) => {
    activeModalLog.value = log;
    actionForm.status = targetStatus;
    actionForm.fraud_reason = log.fraud_reason || '';
    isActionModalOpen.value = true;
};

const closeActionModal = () => {
    isActionModalOpen.value = false;
    activeModalLog.value = null;
    actionForm.reset();
};

const submitAction = () => {
    if (!activeModalLog.value) return;
    actionForm.patch(route('keuangan.admin.referrals.update-status', activeModalLog.value.id), {
        preserveScroll: true,
        onSuccess: () => closeActionModal(),
    });
};

const quickVerify = (log) => {
    router.patch(route('keuangan.admin.referrals.update-status', log.id), {
        status: 'valid',
        fraud_reason: null,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <DashboardLayout :user="user" active-module="admin" title="Admin Modul Keuangan">
        <div class="space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Admin Keuangan & Anti-Fraud</h1>
                        <Badge variant="outline" class="gap-1 bg-emerald-500/10 text-emerald-600 border-emerald-500/20 font-bold text-xs py-0.5">
                            <ShieldCheck class="h-3 w-3" />
                            <span>Akses Admin</span>
                        </Badge>
                    </div>
                    <p class="text-xs sm:text-sm text-muted-foreground mt-1">
                        Pusat direktori pengguna terdaftar, pengawasan program referral, dan penindakan anti-fraud.
                    </p>
                </div>
            </div>

            <!-- Global Metric KPI Cards (Confidential User Cash Strictly Excluded) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Users -->
                <div class="bg-card p-4 sm:p-5 rounded-2xl border shadow-2xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Pengguna Finansial</span>
                        <div class="h-8 w-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                            <Users class="h-4 w-4" />
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-extrabold tracking-tight tabular-nums">{{ metrics.total_users }} Akun</div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Pengguna aktif modul keuangan</p>
                    </div>
                </div>

                <!-- Total Referrals -->
                <div class="bg-card p-4 sm:p-5 rounded-2xl border shadow-2xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Total Relasi Referral</span>
                        <div class="h-8 w-8 rounded-xl bg-sky-500/10 text-sky-600 flex items-center justify-center">
                            <Gift class="h-4 w-4" />
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-extrabold tracking-tight tabular-nums text-foreground">
                            {{ metrics.total_referrals_count }} Relasi
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Akun terhubung melalui referral</p>
                    </div>
                </div>

                <!-- Referral Liabilities -->
                <div class="bg-card p-4 sm:p-5 rounded-2xl border shadow-2xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Reward Tersedia</span>
                        <div class="h-8 w-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center">
                            <Clock class="h-4 w-4" />
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-extrabold tracking-tight tabular-nums text-amber-600">
                            {{ formatRupiah(metrics.total_referral_rewards_available) }}
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Saldo reward aktif siap pakai</p>
                    </div>
                </div>

                <!-- Anti-Fraud Risk Indicator -->
                <div 
                    class="bg-card p-4 sm:p-5 rounded-2xl border shadow-2xs space-y-2"
                    :class="metrics.flagged_referrals_count > 0 ? 'border-rose-500/30 bg-rose-500/5' : ''"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider" :class="metrics.flagged_referrals_count > 0 ? 'text-rose-700' : 'text-muted-foreground'">
                            Audit Anti-Fraud
                        </span>
                        <div 
                            class="h-8 w-8 rounded-xl flex items-center justify-center"
                            :class="metrics.flagged_referrals_count > 0 ? 'bg-rose-500/10 text-rose-600' : 'bg-emerald-500/10 text-emerald-600'"
                        >
                            <ShieldAlert v-if="metrics.flagged_referrals_count > 0" class="h-4 w-4" />
                            <ShieldCheck v-else class="h-4 w-4" />
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-extrabold tracking-tight tabular-nums" :class="metrics.flagged_referrals_count > 0 ? 'text-rose-600' : 'text-emerald-600'">
                            {{ metrics.flagged_referrals_count }} Terindikasi
                        </div>
                        <p class="text-[11px] mt-0.5" :class="metrics.flagged_referrals_count > 0 ? 'text-rose-600/80 font-medium' : 'text-muted-foreground'">
                            {{ metrics.blocked_referrals_count }} reward dibekukan
                        </p>
                    </div>
                </div>
            </div>

            <!-- BAGIAN 1: Direktori Pengguna (5 Kolom: Pengguna, Peran, Referral, Terdaftar, Aktif Terakhir) -->
            <div class="bg-card rounded-2xl border shadow-2xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <Users class="h-4 w-4 text-primary" />
                            <h2 class="font-bold text-base">Direktori Pengguna</h2>
                            <Badge variant="secondary" class="text-[10px] px-1.5 py-0">{{ users.total }} akun</Badge>
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5">Daftar pengguna terdaftar, peran akses, status referral, dan riwayat keaktifan.</p>
                    </div>

                    <!-- User Filter bar -->
                    <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
                        <div class="relative flex-1 sm:w-64">
                            <Search class="h-3.5 w-3.5 text-muted-foreground absolute left-3 top-1/2 -translate-y-1/2" />
                            <Input
                                v-model="searchQuery"
                                @keyup.enter="applyUserFilter"
                                placeholder="Cari nama, @username, email..."
                                class="pl-8.5 h-9 text-xs rounded-xl"
                            />
                        </div>
                        <select
                            v-model="roleFilter"
                            @change="applyUserFilter"
                            class="h-9 rounded-xl border border-input bg-card px-3 py-1 text-xs font-semibold shadow-2xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="">Semua Peran</option>
                            <option value="superadmin">Superadmin</option>
                            <option value="admin_keuangan">Admin Keuangan</option>
                            <option value="user_keuangan">User Keuangan</option>
                        </select>
                        <Button
                            v-if="searchQuery || roleFilter"
                            type="button"
                            @click="resetUserFilter"
                            variant="ghost"
                            size="sm"
                            class="h-9 px-2 text-xs text-muted-foreground"
                            title="Reset Filter"
                        >
                            <RotateCcw class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>

                <!-- Desktop Table: Exactly the 5 requested columns -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b bg-muted/40 text-muted-foreground uppercase tracking-wider text-[10px]">
                                <th class="py-3 px-4 font-bold">Pengguna</th>
                                <th class="py-3 px-4 font-bold">Peran</th>
                                <th class="py-3 px-4 font-bold">Referral</th>
                                <th class="py-3 px-4 font-bold">Terdaftar</th>
                                <th class="py-3 px-4 font-bold text-right">Aktif Terakhir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="users.data.length === 0">
                                <td colspan="5" class="py-12 text-center text-muted-foreground">
                                    <div class="flex flex-col items-center justify-center">
                                        <Users class="h-8 w-8 text-muted-foreground/40 mb-2" />
                                        <p class="font-medium">Tidak ada pengguna yang sesuai pencarian.</p>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="u in users.data"
                                :key="u.id"
                                class="hover:bg-muted/30 transition-colors"
                            >
                                <!-- 1. Pengguna -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center shrink-0">
                                            {{ getInitials(u.name) }}
                                        </div>
                                        <div class="flex flex-col min-w-0">
                                            <span class="font-bold text-foreground truncate">{{ u.name }}</span>
                                            <span class="text-[11px] text-muted-foreground truncate">@{{ u.username }} • {{ u.email }}</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- 2. Peran -->
                                <td class="py-3.5 px-4">
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="r in u.roles"
                                            :key="r"
                                            variant="outline"
                                            class="text-[10px] font-semibold"
                                            :class="getRoleBadge(r).class"
                                        >
                                            {{ getRoleBadge(r).label }}
                                        </Badge>
                                    </div>
                                </td>

                                <!-- 3. Referral -->
                                <td class="py-3.5 px-4">
                                    <div class="flex flex-col text-[11px] space-y-0.5">
                                        <div class="text-foreground font-medium">
                                            <span v-if="u.referred_by">Diajak @{{ u.referred_by.username }}</span>
                                            <span v-else class="text-muted-foreground italic">Organik (Langsung)</span>
                                        </div>
                                        <div class="text-[10px] text-muted-foreground">
                                            <span>Mengajak <strong class="text-foreground">{{ u.referrals_count }}</strong> rekan</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- 4. Terdaftar Kapan -->
                                <td class="py-3.5 px-4 text-muted-foreground font-medium">
                                    <div class="flex items-center gap-1.5">
                                        <Calendar class="h-3 w-3 text-muted-foreground/70" />
                                        <span>{{ u.created_at }}</span>
                                    </div>
                                </td>

                                <!-- 5. Aktif Terakhir Kapan -->
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="font-semibold text-foreground text-xs">{{ u.last_active_human }}</span>
                                        <span class="text-[10px] text-muted-foreground">{{ u.last_active_date }}</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List -->
                <div class="sm:hidden divide-y">
                    <div v-if="users.data.length === 0" class="p-8 text-center text-muted-foreground text-xs">
                        Tidak ada data pengguna yang sesuai pencarian.
                    </div>
                    <div
                        v-for="u in users.data"
                        :key="u.id"
                        class="p-4 space-y-2.5"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="h-8 w-8 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center shrink-0">
                                    {{ getInitials(u.name) }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-xs">{{ u.name }}</span>
                                    <span class="text-[10px] text-muted-foreground">@{{ u.username }} • {{ u.email }}</span>
                                </div>
                            </div>
                            <div class="text-right text-[10px]">
                                <span class="font-semibold text-foreground block">{{ u.last_active_human }}</span>
                                <span class="text-muted-foreground">{{ u.created_at }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1 text-[11px]">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="r in u.roles"
                                    :key="r"
                                    variant="outline"
                                    class="text-[9px] font-semibold"
                                    :class="getRoleBadge(r).class"
                                >
                                    {{ getRoleBadge(r).label }}
                                </Badge>
                            </div>
                            <span class="text-[10px] text-muted-foreground">
                                {{ u.referred_by ? `Diajak @${u.referred_by.username}` : 'Organik' }} • Mengajak {{ u.referrals_count }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Pagination Footer -->
                <div
                    v-if="users.total > users.per_page"
                    class="p-4 border-t flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted-foreground"
                >
                    <span>
                        Menampilkan {{ users.from || 0 }} - {{ users.to || 0 }} dari {{ users.total }} akun
                    </span>
                    <div class="flex items-center gap-1.5">
                        <Link
                            v-if="users.prev_page_url"
                            :href="users.prev_page_url"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border bg-card hover:bg-muted font-semibold transition-colors"
                        >
                            <ChevronLeft class="h-3.5 w-3.5" />
                            <span>Sebelumnya</span>
                        </Link>
                        <span
                            v-else
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border bg-muted/40 opacity-50 cursor-not-allowed font-semibold"
                        >
                            <ChevronLeft class="h-3.5 w-3.5" />
                            <span>Sebelumnya</span>
                        </span>

                        <Link
                            v-if="users.next_page_url"
                            :href="users.next_page_url"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border bg-card hover:bg-muted font-semibold transition-colors"
                        >
                            <span>Selanjutnya</span>
                            <ChevronRight class="h-3.5 w-3.5" />
                        </Link>
                        <span
                            v-else
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border bg-muted/40 opacity-50 cursor-not-allowed font-semibold"
                        >
                            <span>Selanjutnya</span>
                            <ChevronRight class="h-3.5 w-3.5" />
                        </span>
                    </div>
                </div>
            </div>

            <!-- BAGIAN 2: Pusat Audit & Kontrol Anti-Fraud Referral (Digabung Menjadi Satu Halaman Rapi) -->
            <div class="bg-card rounded-2xl border shadow-2xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <ShieldAlert class="h-4 w-4 text-rose-600" />
                            <h2 class="font-bold text-base">Pengawasan & Kontrol Anti-Fraud Referral</h2>
                            <Badge 
                                v-if="metrics.flagged_referrals_count > 0" 
                                class="text-[10px] px-1.5 py-0 bg-rose-500 text-white font-bold"
                            >
                                {{ metrics.flagged_referrals_count }} terindikasi
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5">Audit log transaksi referral, pencegahan akun kloningan, dan wewenang pembekuan reward.</p>
                    </div>

                    <!-- Anti-Fraud Filter Bar -->
                    <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
                        <div class="relative flex-1 sm:w-56">
                            <Search class="h-3.5 w-3.5 text-muted-foreground absolute left-3 top-1/2 -translate-y-1/2" />
                            <Input
                                v-model="referralSearch"
                                @keyup.enter="applyReferralFilter"
                                placeholder="Cari pemberi / pendaftar..."
                                class="pl-8.5 h-9 text-xs rounded-xl"
                            />
                        </div>
                        <select
                            v-model="referralFilter"
                            @change="applyReferralFilter"
                            class="h-9 rounded-xl border border-input bg-card px-3 py-1 text-xs font-semibold shadow-2xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="">Semua Status</option>
                            <option value="valid">Sah (Valid)</option>
                            <option value="suspect">Mencurigakan (Suspect)</option>
                            <option value="blocked">Diblokir (Fraud)</option>
                            <option value="redeemed">Sudah Digunakan</option>
                            <option value="unredeemed">Belum Digunakan</option>
                        </select>
                        <Button
                            v-if="referralSearch || referralFilter"
                            type="button"
                            @click="resetReferralFilter"
                            variant="ghost"
                            size="sm"
                            class="h-9 px-2 text-xs text-muted-foreground"
                            title="Reset Filter"
                        >
                            <RotateCcw class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>

                <!-- Anti-Fraud Referral Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b bg-muted/40 text-muted-foreground uppercase tracking-wider text-[10px]">
                                <th class="py-3 px-4 font-bold">Pemberi Referral</th>
                                <th class="py-3 px-4 font-bold">Akun Terdaftar</th>
                                <th class="py-3 px-4 font-bold text-center">Reward</th>
                                <th class="py-3 px-4 font-bold">Indikator Integritas</th>
                                <th class="py-3 px-4 font-bold text-center">Status Fraud</th>
                                <th class="py-3 px-4 font-bold text-center">Status Klaim</th>
                                <th class="py-3 px-4 font-bold text-right">Tindakan Admin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="referral_logs.length === 0">
                                <td colspan="7" class="py-10 text-center text-muted-foreground">
                                    <div class="flex flex-col items-center justify-center">
                                        <Gift class="h-7 w-7 text-muted-foreground/40 mb-1.5" />
                                        <p class="font-medium">Tidak ada log referral yang sesuai filter.</p>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="log in referral_logs"
                                :key="log.id"
                                class="hover:bg-muted/30 transition-colors"
                                :class="log.status === 'blocked' ? 'bg-rose-500/5' : ''"
                            >
                                <!-- Referrer -->
                                <td class="py-3 px-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-foreground">{{ log.referrer.name }}</span>
                                        <span class="text-[11px] text-muted-foreground font-mono">@{{ log.referrer.username }}</span>
                                    </div>
                                </td>

                                <!-- Referee -->
                                <td class="py-3 px-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-foreground">{{ log.referee.name }}</span>
                                        <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                            <span>@{{ log.referee.username }}</span>
                                            <span>•</span>
                                            <span>{{ log.referee.joined_at }}</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Reward -->
                                <td class="py-3 px-4 text-center font-bold text-emerald-600 tabular-nums">
                                    + {{ formatRupiah(log.reward_discount) }}
                                </td>

                                <!-- Risk Indicator -->
                                <td class="py-3 px-4">
                                    <div v-if="log.risk_flags.length > 0" class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="flag in log.risk_flags"
                                            :key="flag"
                                            variant="outline"
                                            class="text-[9px] bg-amber-500/10 text-amber-700 border-amber-500/20 font-bold gap-1"
                                        >
                                            <AlertTriangle class="h-2.5 w-2.5" />
                                            <span>{{ flag }}</span>
                                        </Badge>
                                    </div>
                                    <div v-else class="flex items-center gap-1 text-[11px] text-emerald-600 font-semibold">
                                        <CheckCircle2 class="h-3 w-3" />
                                        <span>Sah & Wajar</span>
                                    </div>
                                    <p v-if="log.fraud_reason" class="text-[10px] text-rose-600 mt-0.5 italic">
                                        Alasan: {{ log.fraud_reason }}
                                    </p>
                                </td>

                                <!-- Status Fraud -->
                                <td class="py-3 px-4 text-center">
                                    <Badge
                                        v-if="log.status === 'blocked'"
                                        variant="destructive"
                                        class="text-[10px] font-bold gap-1 py-0.5"
                                    >
                                        <Lock class="h-2.5 w-2.5" />
                                        <span>Dibekukan</span>
                                    </Badge>
                                    <Badge
                                        v-else-if="log.status === 'suspect'"
                                        variant="outline"
                                        class="text-[10px] bg-amber-500/10 text-amber-700 border-amber-500/30 font-bold py-0.5"
                                    >
                                        <AlertTriangle class="h-2.5 w-2.5" />
                                        <span>Mencurigakan</span>
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="outline"
                                        class="text-[10px] bg-emerald-500/10 text-emerald-700 border-emerald-500/30 font-bold py-0.5"
                                    >
                                        <CheckCircle2 class="h-2.5 w-2.5" />
                                        <span>Sah (Valid)</span>
                                    </Badge>
                                </td>

                                <!-- Redemption Status -->
                                <td class="py-3 px-4 text-center">
                                    <Badge
                                        :variant="log.is_redeemed ? 'secondary' : 'outline'"
                                        class="text-[10px] font-medium"
                                    >
                                        {{ log.is_redeemed ? `Klaim: ${log.applied_app || 'Pro'}` : 'Belum Digunakan' }}
                                    </Badge>
                                </td>

                                <!-- Actions -->
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Quick Verify Button -->
                                        <Button
                                            v-if="log.status !== 'valid'"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2 text-[10px] text-emerald-600 border-emerald-500/30 hover:bg-emerald-500/10"
                                            @click="quickVerify(log)"
                                            title="Verifikasi Sah"
                                        >
                                            <CheckCircle2 class="h-3 w-3 mr-1" />
                                            <span>Sah</span>
                                        </Button>

                                        <!-- Block Button -->
                                        <Button
                                            v-if="log.status !== 'blocked'"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2 text-[10px] text-rose-600 border-rose-500/30 hover:bg-rose-500/10"
                                            @click="openActionModal(log, 'blocked')"
                                            title="Bekukan Reward (Fraud)"
                                        >
                                            <Lock class="h-3 w-3 mr-1" />
                                            <span>Bekukan</span>
                                        </Button>

                                        <!-- Mark Suspect Button -->
                                        <Button
                                            v-if="log.status === 'valid'"
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 px-2 text-[10px] text-amber-600 hover:bg-amber-500/10"
                                            @click="openActionModal(log, 'suspect')"
                                            title="Tandai Mencurigakan"
                                        >
                                            <AlertTriangle class="h-3 w-3 mr-1" />
                                            <span>Curiga</span>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Anti-Fraud Action Modal -->
            <div
                v-if="isActionModalOpen"
                class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 animate-in fade-in duration-200"
            >
                <div class="bg-card w-full max-w-md rounded-2xl border shadow-xl overflow-hidden p-6 space-y-4">
                    <div class="flex items-center gap-3">
                        <div 
                            class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0"
                            :class="actionForm.status === 'blocked' ? 'bg-rose-500/10 text-rose-600' : 'bg-amber-500/10 text-amber-600'"
                        >
                            <Lock v-if="actionForm.status === 'blocked'" class="h-5 w-5" />
                            <AlertTriangle v-else class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-base text-foreground">
                                {{ actionForm.status === 'blocked' ? 'Bekukan Reward Referral' : 'Tandai Log Referral Mencurigakan' }}
                            </h3>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                {{ actionForm.status === 'blocked' ? 'Reward diskon akan dinonaktifkan sehingga pengguna tidak dapat menggunakannya.' : 'Menandai relasi referral ini untuk penyelidikan lebih lanjut.' }}
                            </p>
                        </div>
                    </div>

                    <div v-if="activeModalLog" class="p-3 bg-muted/40 rounded-xl border text-xs space-y-1">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Pemberi Referral:</span>
                            <span class="font-bold text-foreground">{{ activeModalLog.referrer.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Akun Terdaftar:</span>
                            <span class="font-bold text-foreground">{{ activeModalLog.referee.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Nilai Potongan:</span>
                            <span class="font-bold text-emerald-600">{{ formatRupiah(activeModalLog.reward_discount) }}</span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-foreground">Alasan Pembekuan / Indikasi Fraud</label>
                        <Input
                            v-model="actionForm.fraud_reason"
                            placeholder="Contoh: Akun kloningan, nomor IP sama, atau registrasi massal"
                            class="text-xs rounded-xl"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3.5 mt-2 border-t">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-xs font-semibold"
                            @click="closeActionModal"
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            :variant="actionForm.status === 'blocked' ? 'destructive' : 'default'"
                            size="sm"
                            class="text-xs font-bold"
                            :disabled="actionForm.processing"
                            @click="submitAction"
                        >
                            <span v-if="actionForm.processing">Memproses...</span>
                            <span v-else>{{ actionForm.status === 'blocked' ? 'Konfirmasi Bekukan Reward' : 'Simpan Status' }}</span>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
