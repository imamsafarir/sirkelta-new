<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { 
    LayoutDashboard,
    CreditCard, 
    Users, 
    Headphones, 
    ShieldAlert, 
    Search, 
    CheckCircle2, 
    Clock, 
    XCircle, 
    DollarSign, 
    FileImage, 
    ExternalLink, 
    Check, 
    X, 
    Eye, 
    AlertCircle, 
    Building2, 
    QrCode, 
    Sparkles, 
    RotateCcw,
    Calendar,
    Settings,
    MessageSquare,
    Send,
    Trash2,
    ShieldCheck,
    Key,
    Phone
} from 'lucide-vue-next';

const props = defineProps({
    currentTab: {
        type: String,
        default: 'payments',
    },
    accessibleTabs: {
        type: Array,
        default: () => ['payments', 'users', 'support', 'fraud'],
    },
    globalAlerts: {
        type: Object,
        default: () => ({
            pending_orders: 0,
            open_tickets: 0,
            unread_messages: 0,
            total_users: 0,
            total_revenue: 0,
            flagged_referrals: 0,
        }),
    },
    payments: {
        type: Object,
        default: () => ({ orders: { data: [] }, metrics: {}, filters: {} }),
    },
    users: {
        type: Object,
        default: () => ({ users: { data: [] }, roles: [], filters: {} }),
    },
    support: {
        type: Object,
        default: () => ({ tickets: { data: [] }, stats: {}, filters: {} }),
    },
    fraud: {
        type: Object,
        default: () => ({ logs: { data: [] }, metrics: {}, filters: {} }),
    },
});

const page = usePage();

// Tab Switcher
const switchTab = (tabName) => {
    router.get(route('admin.dashboard'), { tab: tabName }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(val || 0);
};

// ==========================================
// 1. PAYMENTS TAB STATE & LOGIC
// ==========================================
const paymentSearch = ref(props.payments.filters?.search || '');
const paymentStatus = ref(props.payments.filters?.status || 'all');
const paymentModule = ref(props.payments.filters?.module || 'all');
const paymentMethod = ref(props.payments.filters?.payment_method || 'all');

const applyPaymentFilters = () => {
    router.get(route('admin.dashboard'), {
        tab: 'payments',
        search: paymentSearch.value || undefined,
        status: paymentStatus.value !== 'all' ? paymentStatus.value : undefined,
        module: paymentModule.value !== 'all' ? paymentModule.value : undefined,
        payment_method: paymentMethod.value !== 'all' ? paymentMethod.value : undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetPaymentFilters = () => {
    paymentSearch.value = '';
    paymentStatus.value = 'all';
    paymentModule.value = 'all';
    paymentMethod.value = 'all';
    applyPaymentFilters();
};

const activeProofOrder = ref(null);
const activeRejectOrder = ref(null);
const rejectForm = useForm({ rejection_reason: '' });

const openProofModal = (order) => { activeProofOrder.value = order; };
const closeProofModal = () => { activeProofOrder.value = null; };

const openRejectModal = (order) => {
    activeRejectOrder.value = order;
    rejectForm.reset();
    rejectForm.clearErrors();
};
const closeRejectModal = () => {
    activeRejectOrder.value = null;
    rejectForm.reset();
};

const approveOrder = (order) => {
    if (confirm(`Setujui pesanan ${order.order_number} untuk ${order.user.name}? Masa aktif langganan akan otomatis diperpanjang ${order.duration_months} bulan.`)) {
        router.post(route('admin.payments.approve', order.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                if (activeProofOrder.value?.id === order.id) closeProofModal();
            },
        });
    }
};

const submitReject = () => {
    if (!rejectForm.rejection_reason.trim()) {
        rejectForm.setError('rejection_reason', 'Alasan penolakan wajib diisi.');
        return;
    }
    rejectForm.post(route('admin.payments.reject', activeRejectOrder.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeRejectModal();
            if (activeProofOrder.value?.id === activeRejectOrder.value?.id) closeProofModal();
        },
    });
};

// ==========================================
// 2. USERS TAB STATE & LOGIC
// ==========================================
const userSearch = ref(props.users.filters?.search || '');
const userRoleFilter = ref(props.users.filters?.role || '');

const applyUserFilters = () => {
    router.get(route('admin.dashboard'), {
        tab: 'users',
        search: userSearch.value || undefined,
        role: userRoleFilter.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetUserFilters = () => {
    userSearch.value = '';
    userRoleFilter.value = '';
    applyUserFilters();
};

const activeUserRoleModal = ref(null);
const userRoleForm = useForm({ roles: [] });

const openRoleModal = (u) => {
    userRoleForm.clearErrors();
    activeUserRoleModal.value = u;
    userRoleForm.roles = [...(u.roles?.map(r => typeof r === 'string' ? r : r.name) || [])];
};
const closeRoleModal = () => {
    activeUserRoleModal.value = null;
    userRoleForm.reset();
};

const toggleUserRole = (rName) => {
    const idx = userRoleForm.roles.indexOf(rName);
    if (idx === -1) {
        userRoleForm.roles.push(rName);
    } else {
        if (userRoleForm.roles.length > 1) {
            userRoleForm.roles.splice(idx, 1);
        }
    }
};

const saveUserRoles = () => {
    userRoleForm.put(route('admin.users.roles', activeUserRoleModal.value.id), {
        preserveScroll: true,
        onSuccess: () => closeRoleModal(),
    });
};

const deleteUser = (u) => {
    if (confirm(`Hapus pengguna "${u.name}" (@${u.username}) secara permanen? Seluruh data terkait akan dihapus.`)) {
        router.delete(route('admin.users.destroy', u.id), {
            preserveScroll: true,
        });
    }
};

// ==========================================
// 3. SUPPORT TAB STATE & LOGIC
// ==========================================
const supportSearch = ref(props.support.filters?.search || '');
const supportStatus = ref(props.support.filters?.ticket_status || 'open');

const applySupportFilters = () => {
    router.get(route('admin.dashboard'), {
        tab: 'support',
        ticket_status: supportStatus.value,
        search: supportSearch.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const setSupportStatus = (st) => {
    supportStatus.value = st;
    applySupportFilters();
};

const activeTicketModal = ref(null);
const replyForm = useForm({ message: '' });

const openTicketModal = (t) => {
    activeTicketModal.value = t;
    replyForm.reset();
    replyForm.clearErrors();
};

const closeTicketModal = () => {
    activeTicketModal.value = null;
    replyForm.reset();
};

const submitReply = () => {
    if (!replyForm.message.trim()) {
        replyForm.setError('message', 'Pesan balasan wajib diisi.');
        return;
    }
    replyForm.post(route('admin.support.reply', activeTicketModal.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset();
        },
    });
};

const resolveTicket = (t) => {
    if (confirm(`Tandai aduan #${t.ticket_number} selesai dan bersihkan chat?`)) {
        router.post(route('admin.support.resolve', t.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                if (activeTicketModal.value?.id === t.id) closeTicketModal();
            },
        });
    }
};

// ==========================================
// 4. FRAUD TAB STATE & LOGIC
// ==========================================
const fraudSearch = ref(props.fraud.filters?.search || '');
const fraudFilter = ref(props.fraud.filters?.referral_filter || '');

const applyFraudFilters = () => {
    router.get(route('admin.dashboard'), {
        tab: 'fraud',
        search: fraudSearch.value || undefined,
        referral_filter: fraudFilter.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFraudFilters = () => {
    fraudSearch.value = '';
    fraudFilter.value = '';
    applyFraudFilters();
};

const activeFraudModal = ref(null);
const fraudForm = useForm({ status: 'valid', fraud_reason: '' });

const openFraudStatusModal = (log, st) => {
    activeFraudModal.value = log;
    fraudForm.status = st;
    fraudForm.fraud_reason = log.fraud_reason || '';
};

const closeFraudModal = () => {
    activeFraudModal.value = null;
    fraudForm.reset();
};

const submitFraudStatus = () => {
    fraudForm.patch(route('keuangan.admin.referrals.update-status', activeFraudModal.value.id), {
        preserveScroll: true,
        onSuccess: () => closeFraudModal(),
    });
};
</script>

<template>
    <DashboardLayout 
        title="Pusat Kontrol Admin" 
        activeModule="admin" 
        :user="page.props.auth.user"
    >
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-foreground flex items-center gap-2.5">
                        <span>Pusat Kontrol Admin</span>
                    </h1>
                    <p class="text-xs text-muted-foreground mt-1">
                        Pusat kendali operasional terpadu: kelola transaksi pesanan, pengguna, bantuan aduan, dan audit referral dalam satu layar kerja.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link :href="route('admin.website.index')">
                        <Button variant="outline" size="sm" class="gap-1.5 text-xs font-semibold">
                            <Settings class="h-3.5 w-3.5 text-primary" />
                            <span>Pengaturan Sistem & Website</span>
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Action Required Attention Cards (Alert Banners) -->
            <div v-if="globalAlerts.pending_orders > 0 || globalAlerts.open_tickets > 0 || globalAlerts.flagged_referrals > 0" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <!-- Alert 1: Pending Orders -->
                <div 
                    v-if="globalAlerts.pending_orders > 0 && accessibleTabs.includes('payments')"
                    @click="switchTab('payments')"
                    class="p-3.5 rounded-xl border border-amber-500/30 bg-amber-500/10 flex items-center justify-between gap-3 cursor-pointer hover:bg-amber-500/15 transition-all shadow-2xs"
                >
                    <div class="flex items-center gap-2.5">
                        <div class="h-9 w-9 rounded-lg bg-amber-500/20 text-amber-700 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <Clock class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="text-xs font-bold text-amber-800 dark:text-amber-300">
                                {{ globalAlerts.pending_orders }} Pesanan Perlu Verifikasi
                            </div>
                            <div class="text-[11px] text-amber-700/80 dark:text-amber-400/80">Transfer manual belum disetujui</div>
                        </div>
                    </div>
                    <Badge variant="outline" class="text-[10px] bg-amber-500/20 border-amber-500/40 text-amber-800 dark:text-amber-300 shrink-0">
                        Buka
                    </Badge>
                </div>

                <!-- Alert 2: Open Tickets -->
                <div 
                    v-if="globalAlerts.open_tickets > 0 && accessibleTabs.includes('support')"
                    @click="switchTab('support')"
                    class="p-3.5 rounded-xl border border-blue-500/30 bg-blue-500/10 flex items-center justify-between gap-3 cursor-pointer hover:bg-blue-500/15 transition-all shadow-2xs"
                >
                    <div class="flex items-center gap-2.5">
                        <div class="h-9 w-9 rounded-lg bg-blue-500/20 text-blue-700 dark:text-blue-400 flex items-center justify-center shrink-0">
                            <Headphones class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="text-xs font-bold text-blue-800 dark:text-blue-300">
                                {{ globalAlerts.open_tickets }} Aduan Masuk Aktif
                            </div>
                            <div class="text-[11px] text-blue-700/80 dark:text-blue-400/80">Pengguna membutuhkan respon</div>
                        </div>
                    </div>
                    <Badge variant="outline" class="text-[10px] bg-blue-500/20 border-blue-500/40 text-blue-800 dark:text-blue-300 shrink-0">
                        Balas
                    </Badge>
                </div>

                <!-- Alert 3: Suspect Referrals -->
                <div 
                    v-if="globalAlerts.flagged_referrals > 0 && accessibleTabs.includes('fraud')"
                    @click="switchTab('fraud')"
                    class="p-3.5 rounded-xl border border-rose-500/30 bg-rose-500/10 flex items-center justify-between gap-3 cursor-pointer hover:bg-rose-500/15 transition-all shadow-2xs"
                >
                    <div class="flex items-center gap-2.5">
                        <div class="h-9 w-9 rounded-lg bg-rose-500/20 text-rose-700 dark:text-rose-400 flex items-center justify-center shrink-0">
                            <ShieldAlert class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="text-xs font-bold text-rose-800 dark:text-rose-300">
                                {{ globalAlerts.flagged_referrals }} Referral Mencurigakan
                            </div>
                            <div class="text-[11px] text-rose-700/80 dark:text-rose-400/80">Perlu audit anti-fraud</div>
                        </div>
                    </div>
                    <Badge variant="outline" class="text-[10px] bg-rose-500/20 border-rose-500/40 text-rose-800 dark:text-rose-300 shrink-0">
                        Audit
                    </Badge>
                </div>
            </div>

            <!-- Main Tab Bar Navigation -->
            <div class="flex flex-wrap items-center gap-2 border-b pb-3">
                <!-- Tab: Payments -->
                <button
                    v-if="accessibleTabs.includes('payments')"
                    @click="switchTab('payments')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                    :class="currentTab === 'payments' 
                        ? 'bg-primary text-primary-foreground shadow-xs' 
                        : 'bg-card border text-muted-foreground hover:bg-muted/70 hover:text-foreground'"
                >
                    <CreditCard class="h-3.5 w-3.5" />
                    <span>Transaksi Pro</span>
                    <Badge 
                        v-if="globalAlerts.pending_orders > 0" 
                        class="text-[10px] py-0 px-1.5 font-bold"
                        :class="currentTab === 'payments' ? 'bg-amber-300 text-amber-950' : 'bg-amber-500 text-white'"
                    >
                        {{ globalAlerts.pending_orders }}
                    </Badge>
                </button>

                <!-- Tab: Users -->
                <button
                    v-if="accessibleTabs.includes('users')"
                    @click="switchTab('users')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                    :class="currentTab === 'users' 
                        ? 'bg-primary text-primary-foreground shadow-xs' 
                        : 'bg-card border text-muted-foreground hover:bg-muted/70 hover:text-foreground'"
                >
                    <Users class="h-3.5 w-3.5" />
                    <span>Kelola Pengguna</span>
                    <Badge 
                        class="text-[10px] py-0 px-1.5 font-bold"
                        :class="currentTab === 'users' ? 'bg-primary-foreground/20 text-primary-foreground' : 'bg-muted text-foreground'"
                    >
                        {{ globalAlerts.total_users }}
                    </Badge>
                </button>

                <!-- Tab: Support -->
                <button
                    v-if="accessibleTabs.includes('support')"
                    @click="switchTab('support')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                    :class="currentTab === 'support' 
                        ? 'bg-primary text-primary-foreground shadow-xs' 
                        : 'bg-card border text-muted-foreground hover:bg-muted/70 hover:text-foreground'"
                >
                    <Headphones class="h-3.5 w-3.5" />
                    <span>Layanan Aduan</span>
                    <Badge 
                        v-if="globalAlerts.open_tickets > 0" 
                        class="text-[10px] py-0 px-1.5 font-bold"
                        :class="currentTab === 'support' ? 'bg-blue-300 text-blue-950' : 'bg-blue-500 text-white'"
                    >
                        {{ globalAlerts.open_tickets }}
                    </Badge>
                </button>

                <!-- Tab: Fraud -->
                <button
                    v-if="accessibleTabs.includes('fraud')"
                    @click="switchTab('fraud')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                    :class="currentTab === 'fraud' 
                        ? 'bg-primary text-primary-foreground shadow-xs' 
                        : 'bg-card border text-muted-foreground hover:bg-muted/70 hover:text-foreground'"
                >
                    <ShieldCheck class="h-3.5 w-3.5" />
                    <span>Audit Referral & Anti-Fraud</span>
                    <Badge 
                        v-if="globalAlerts.flagged_referrals > 0" 
                        class="text-[10px] py-0 px-1.5 font-bold"
                        :class="currentTab === 'fraud' ? 'bg-rose-300 text-rose-950' : 'bg-rose-500 text-white'"
                    >
                        {{ globalAlerts.flagged_referrals }}
                    </Badge>
                </button>
            </div>

            <!-- ========================================== -->
            <!-- TAB 1 CONTENT: TRANSAKSI & PEMBAYARAN PRO -->
            <!-- ========================================== -->
            <div v-if="currentTab === 'payments'" class="space-y-4">
                <!-- Payments Metrics Strip -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card class="p-4 border shadow-xs flex items-center justify-between">
                        <div>
                            <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Total Pesanan</div>
                            <div class="text-2xl font-black text-foreground mt-0.5 tabular-nums">{{ payments.metrics?.total_orders || 0 }}</div>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <CreditCard class="h-5 w-5" />
                        </div>
                    </Card>

                    <Card class="p-4 border shadow-xs flex items-center justify-between">
                        <div>
                            <div class="text-[11px] font-medium text-amber-600 dark:text-amber-400 uppercase tracking-wider">Perlu Verifikasi</div>
                            <div class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-0.5 tabular-nums">{{ payments.metrics?.pending_orders || 0 }}</div>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                            <Clock class="h-5 w-5" />
                        </div>
                    </Card>

                    <Card class="p-4 border shadow-xs flex items-center justify-between">
                        <div>
                            <div class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Pesanan Lunas</div>
                            <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5 tabular-nums">{{ payments.metrics?.paid_orders || 0 }}</div>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                            <CheckCircle2 class="h-5 w-5" />
                        </div>
                    </Card>

                    <Card class="p-4 border shadow-xs flex items-center justify-between">
                        <div>
                            <div class="text-[11px] font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Total Pendapatan</div>
                            <div class="text-xl sm:text-2xl font-black text-foreground mt-0.5 tabular-nums">{{ formatRupiah(payments.metrics?.total_revenue) }}</div>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center shrink-0">
                            <DollarSign class="h-5 w-5" />
                        </div>
                    </Card>
                </div>

                <!-- Payments Filters Toolbar -->
                <Card class="p-4 border shadow-xs space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <div class="lg:col-span-2 relative">
                            <Search class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="paymentSearch"
                                type="text"
                                placeholder="Cari invoice, nama user, email..."
                                class="pl-9 text-xs"
                                @keyup.enter="applyPaymentFilters"
                            />
                        </div>

                        <div>
                            <select 
                                v-model="paymentStatus" 
                                @change="applyPaymentFilters"
                                class="w-full bg-background border border-input rounded-xl px-3 py-2 text-xs focus:outline-hidden focus:ring-1 focus:ring-primary font-medium"
                            >
                                <option value="all">Semua Status</option>
                                <option value="pending">Menunggu Verifikasi</option>
                                <option value="paid">Lunas (Paid)</option>
                                <option value="rejected">Ditolak</option>
                            </select>
                        </div>

                        <div>
                            <select 
                                v-model="paymentModule" 
                                @change="applyPaymentFilters"
                                class="w-full bg-background border border-input rounded-xl px-3 py-2 text-xs focus:outline-hidden focus:ring-1 focus:ring-primary font-medium"
                            >
                                <option value="all">Semua Modul</option>
                                <option value="keuangan">Sirkelta Keuangan</option>
                                <option value="wasender">Sirkelta WaSender</option>
                            </select>
                        </div>

                        <div>
                            <select 
                                v-model="paymentMethod" 
                                @change="applyPaymentFilters"
                                class="w-full bg-background border border-input rounded-xl px-3 py-2 text-xs focus:outline-hidden focus:ring-1 focus:ring-primary font-medium"
                            >
                                <option value="all">Semua Metode</option>
                                <option value="manual_bank">Transfer Bank</option>
                                <option value="manual_qris">QRIS Manual</option>
                                <option value="midtrans">Midtrans Otomatis</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs pt-1 border-t">
                        <span class="text-muted-foreground">
                            Ditemukan <strong class="text-foreground">{{ payments.orders?.total || 0 }}</strong> transaksi
                        </span>
                        <Button 
                            variant="ghost" 
                            size="sm" 
                            class="h-7 text-xs text-muted-foreground hover:text-foreground gap-1 cursor-pointer"
                            @click="resetPaymentFilters"
                        >
                            <RotateCcw class="h-3 w-3" />
                            <span>Reset Filter</span>
                        </Button>
                    </div>
                </Card>

                <!-- Payments Table -->
                <Card class="border shadow-xs overflow-hidden">
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-muted/50 text-muted-foreground border-b font-semibold">
                                    <tr>
                                        <th class="py-3 px-4">No. Order</th>
                                        <th class="py-3 px-4">Pengguna</th>
                                        <th class="py-3 px-4">Aplikasi & Paket</th>
                                        <th class="py-3 px-4">Metode Bayar</th>
                                        <th class="py-3 px-4 text-right">Nominal</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                        <th class="py-3 px-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60">
                                    <tr 
                                        v-for="order in payments.orders?.data" 
                                        :key="order.id"
                                        class="hover:bg-muted/30 transition-colors"
                                    >
                                        <td class="py-3 px-4 align-top">
                                            <div class="font-mono font-bold text-foreground">{{ order.order_number }}</div>
                                            <div class="text-[11px] text-muted-foreground flex items-center gap-1 mt-0.5">
                                                <Calendar class="h-3 w-3" />
                                                <span>{{ order.created_at }}</span>
                                            </div>
                                        </td>

                                        <td class="py-3 px-4 align-top">
                                            <div class="font-bold text-foreground">{{ order.user.name }}</div>
                                            <div class="text-[11px] text-muted-foreground font-mono">@{{ order.user.username }}</div>
                                        </td>

                                        <td class="py-3 px-4 align-top">
                                            <div class="font-semibold text-foreground">{{ order.app_name }}</div>
                                            <Badge variant="outline" class="text-[10px] mt-0.5">{{ order.duration_months }} Bulan</Badge>
                                        </td>

                                        <td class="py-3 px-4 align-top">
                                            <div class="font-medium text-foreground flex items-center gap-1.5">
                                                <Building2 v-if="order.payment_method === 'manual_bank'" class="h-3.5 w-3.5 text-primary" />
                                                <QrCode v-else-if="order.payment_method === 'manual_qris'" class="h-3.5 w-3.5 text-primary" />
                                                <Sparkles v-else class="h-3.5 w-3.5 text-indigo-500" />
                                                <span>{{ order.payment_method_label }}</span>
                                            </div>
                                            <div v-if="order.payment_proof_path" class="mt-1">
                                                <button 
                                                    @click="openProofModal(order)"
                                                    class="text-[11px] text-primary hover:underline font-semibold flex items-center gap-1 cursor-pointer"
                                                >
                                                    <FileImage class="h-3 w-3" />
                                                    <span>Lihat Bukti Bayar</span>
                                                </button>
                                            </div>
                                        </td>

                                        <td class="py-3 px-4 align-top text-right font-mono font-bold">
                                            <div>{{ formatRupiah(order.final_amount) }}</div>
                                            <div v-if="order.discount_applied > 0" class="text-[10px] text-emerald-600 font-medium">
                                                Diskon: -{{ formatRupiah(order.discount_applied) }}
                                            </div>
                                        </td>

                                        <td class="py-3 px-4 align-top text-center">
                                            <Badge 
                                                :variant="order.status === 'paid' ? 'default' : (order.status === 'pending' ? 'secondary' : 'destructive')"
                                                class="text-[10px] font-bold"
                                            >
                                                {{ order.status === 'paid' ? 'Lunas' : (order.status === 'pending' ? 'Perlu Verifikasi' : 'Ditolak') }}
                                            </Badge>
                                        </td>

                                        <td class="py-3 px-4 align-top text-right">
                                            <div v-if="order.status === 'pending'" class="flex items-center justify-end gap-1.5">
                                                <Button 
                                                    size="sm" 
                                                    class="h-7 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white gap-1 px-2.5 cursor-pointer"
                                                    @click="approveOrder(order)"
                                                >
                                                    <Check class="h-3 w-3" />
                                                    <span>Setujui</span>
                                                </Button>
                                                <Button 
                                                    variant="outline" 
                                                    size="sm" 
                                                    class="h-7 text-xs font-bold text-destructive hover:bg-destructive/10 border-destructive/30 gap-1 px-2 cursor-pointer"
                                                    @click="openRejectModal(order)"
                                                >
                                                    <X class="h-3 w-3" />
                                                    <span>Tolak</span>
                                                </Button>
                                            </div>
                                            <div v-else class="text-muted-foreground text-[11px]">-</div>
                                        </td>
                                    </tr>

                                    <tr v-if="!payments.orders?.data?.length">
                                        <td colspan="7" class="py-12 text-center text-muted-foreground">
                                            Belum ada data transaksi yang cocok dengan filter ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Payments Pagination -->
                        <div v-if="payments.orders?.links?.length > 3" class="p-4 border-t flex items-center justify-between">
                            <span class="text-xs text-muted-foreground">
                                Menampilkan {{ payments.orders.from || 0 }} - {{ payments.orders.to || 0 }} dari {{ payments.orders.total }} transaksi
                            </span>
                            <div class="flex gap-1">
                                <Link
                                    v-for="(link, i) in payments.orders.links"
                                    :key="i"
                                    :href="link.url || '#'"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold"
                                    :class="link.active ? 'bg-primary text-primary-foreground font-bold' : (!link.url ? 'text-muted-foreground/30 pointer-events-none' : 'text-muted-foreground hover:bg-muted')"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- ========================================== -->
            <!-- TAB 2 CONTENT: KELOLA PENGGUNA -->
            <!-- ========================================== -->
            <div v-else-if="currentTab === 'users'" class="space-y-4">
                <!-- User Search & Role Filter -->
                <Card class="p-4 border shadow-xs space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2 relative">
                            <Search class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="userSearch"
                                type="text"
                                placeholder="Cari nama pengguna, username, email, atau kode referral..."
                                class="pl-9 text-xs"
                                @keyup.enter="applyUserFilters"
                            />
                        </div>

                        <div>
                            <select 
                                v-model="userRoleFilter" 
                                @change="applyUserFilters"
                                class="w-full bg-background border border-input rounded-xl px-3 py-2 text-xs focus:outline-hidden focus:ring-1 focus:ring-primary font-medium"
                            >
                                <option value="">Semua Peran (Role)</option>
                                <option v-for="r in users.roles" :key="r" :value="r">{{ r }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs pt-1 border-t">
                        <span class="text-muted-foreground">
                            Total <strong class="text-foreground">{{ users.users?.total || 0 }}</strong> pengguna terdaftar
                        </span>
                        <Button 
                            variant="ghost" 
                            size="sm" 
                            class="h-7 text-xs text-muted-foreground hover:text-foreground gap-1 cursor-pointer"
                            @click="resetUserFilters"
                        >
                            <RotateCcw class="h-3 w-3" />
                            <span>Reset Filter</span>
                        </Button>
                    </div>
                </Card>

                <!-- Users Table -->
                <Card class="border shadow-xs overflow-hidden">
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-muted/50 text-muted-foreground border-b font-semibold">
                                    <tr>
                                        <th class="py-3 px-4">Pengguna</th>
                                        <th class="py-3 px-4">Kontak & Email</th>
                                        <th class="py-3 px-4">Peran & Akses Modul</th>
                                        <th class="py-3 px-4">Referral</th>
                                        <th class="py-3 px-4 text-center">Aktivitas</th>
                                        <th class="py-3 px-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60">
                                    <tr 
                                        v-for="u in users.users?.data" 
                                        :key="u.id"
                                        class="hover:bg-muted/30 transition-colors"
                                    >
                                        <td class="py-3 px-4">
                                            <div class="font-bold text-foreground">{{ u.name }}</div>
                                            <div class="text-[11px] text-muted-foreground font-mono">@{{ u.username }}</div>
                                        </td>

                                        <td class="py-3 px-4 text-muted-foreground">
                                            <div>{{ u.email }}</div>
                                        </td>

                                        <td class="py-3 px-4">
                                            <div class="flex flex-wrap gap-1">
                                                <Badge 
                                                    v-for="r in u.roles" 
                                                    :key="r.id || r" 
                                                    variant="outline" 
                                                    class="text-[10px] py-0 px-1.5"
                                                    :class="r.name === 'superadmin' ? 'border-purple-500 bg-purple-500/10 text-purple-700 dark:text-purple-300 font-bold' : ''"
                                                >
                                                    {{ r.name || r }}
                                                </Badge>
                                            </div>
                                        </td>

                                        <td class="py-3 px-4 text-muted-foreground">
                                            <div v-if="u.referral_code" class="font-mono text-[11px]">Kode: {{ u.referral_code }}</div>
                                            <div class="text-[10px]">Undang: {{ u.referees_count || 0 }} user</div>
                                        </td>

                                        <td class="py-3 px-4 text-center text-muted-foreground text-[11px]">
                                            <div>{{ u.wallets_count || 0 }} Dompet</div>
                                            <div>{{ u.transactions_count || 0 }} Mutasi</div>
                                        </td>

                                        <td class="py-3 px-4 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <Button 
                                                    variant="outline" 
                                                    size="sm" 
                                                    class="h-7 text-xs gap-1 cursor-pointer"
                                                    @click="openRoleModal(u)"
                                                >
                                                    <Key class="h-3 w-3" />
                                                    <span>Ubah Role</span>
                                                </Button>
                                                <Button 
                                                    variant="ghost" 
                                                    size="icon" 
                                                    class="h-7 w-7 text-destructive hover:bg-destructive/10 cursor-pointer"
                                                    @click="deleteUser(u)"
                                                >
                                                    <Trash2 class="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Users Pagination -->
                        <div v-if="users.users?.links?.length > 3" class="p-4 border-t flex items-center justify-between">
                            <span class="text-xs text-muted-foreground">
                                Menampilkan {{ users.users.from || 0 }} - {{ users.users.to || 0 }} dari {{ users.users.total }} pengguna
                            </span>
                            <div class="flex gap-1">
                                <Link
                                    v-for="(link, i) in users.users.links"
                                    :key="i"
                                    :href="link.url || '#'"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold"
                                    :class="link.active ? 'bg-primary text-primary-foreground font-bold' : (!link.url ? 'text-muted-foreground/30 pointer-events-none' : 'text-muted-foreground hover:bg-muted')"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- ========================================== -->
            <!-- TAB 3 CONTENT: LAYANAN ADUAN PENGGUNA -->
            <!-- ========================================== -->
            <div v-else-if="currentTab === 'support'" class="space-y-4">
                <!-- Support Status & Search Strip -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Button 
                            size="sm" 
                            :variant="supportStatus === 'open' ? 'default' : 'outline'"
                            class="text-xs font-bold gap-1.5 cursor-pointer"
                            @click="setSupportStatus('open')"
                        >
                            <span>Perlu Ditangani</span>
                            <Badge variant="secondary" class="text-[10px] py-0 px-1.5 font-bold">
                                {{ support.stats?.open_count || 0 }}
                            </Badge>
                        </Button>

                        <Button 
                            size="sm" 
                            :variant="supportStatus === 'resolved' ? 'default' : 'outline'"
                            class="text-xs font-bold gap-1.5 cursor-pointer"
                            @click="setSupportStatus('resolved')"
                        >
                            <span>Selesai (Resolved)</span>
                            <Badge variant="secondary" class="text-[10px] py-0 px-1.5 font-bold">
                                {{ support.stats?.resolved_count || 0 }}
                            </Badge>
                        </Button>

                        <Button 
                            size="sm" 
                            :variant="supportStatus === 'all' ? 'default' : 'outline'"
                            class="text-xs font-bold cursor-pointer"
                            @click="setSupportStatus('all')"
                        >
                            Semua Tiket
                        </Button>
                    </div>

                    <div class="relative w-full sm:w-64">
                        <Search class="h-3.5 w-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="supportSearch"
                            type="text"
                            placeholder="Cari tiket, nama, email..."
                            class="pl-9 text-xs h-8"
                            @keyup.enter="applySupportFilters"
                        />
                    </div>
                </div>

                <!-- Tickets Table -->
                <Card class="border shadow-xs overflow-hidden">
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-muted/50 text-muted-foreground border-b font-semibold">
                                    <tr>
                                        <th class="py-3 px-4">No. Tiket & Tanggal</th>
                                        <th class="py-3 px-4">Pengirim</th>
                                        <th class="py-3 px-4">Subjek Masalah</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                        <th class="py-3 px-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60">
                                    <tr 
                                        v-for="t in support.tickets?.data" 
                                        :key="t.id"
                                        class="hover:bg-muted/30 transition-colors"
                                    >
                                        <td class="py-3 px-4">
                                            <div class="font-mono font-bold text-foreground">#{{ t.ticket_number }}</div>
                                            <div class="text-[10px] text-muted-foreground">{{ t.created_at_human }}</div>
                                        </td>

                                        <td class="py-3 px-4">
                                            <div class="font-semibold text-foreground">
                                                {{ t.user ? t.user.name : t.guest_name }}
                                            </div>
                                            <div class="text-[11px] text-muted-foreground">
                                                {{ t.user ? t.user.email : t.guest_email }}
                                            </div>
                                        </td>

                                        <td class="py-3 px-4">
                                            <div class="font-bold text-foreground max-w-sm truncate">{{ t.subject }}</div>
                                            <div class="flex items-center gap-2 mt-0.5 text-[11px] text-muted-foreground">
                                                <span>{{ t.messages_count }} pesan</span>
                                                <Badge v-if="t.unread_count > 0" class="bg-blue-500 text-white text-[9px] py-0 px-1 font-bold">
                                                    {{ t.unread_count }} baru
                                                </Badge>
                                            </div>
                                        </td>

                                        <td class="py-3 px-4 text-center">
                                            <Badge 
                                                :variant="t.status === 'open' ? 'secondary' : 'default'"
                                                class="text-[10px] font-bold"
                                            >
                                                {{ t.status === 'open' ? 'Terbuka' : 'Selesai' }}
                                            </Badge>
                                        </td>

                                        <td class="py-3 px-4 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <Link :href="route('admin.support.show', t.id)">
                                                    <Button size="sm" class="h-7 text-xs font-bold gap-1 cursor-pointer">
                                                        <MessageSquare class="h-3 w-3" />
                                                        <span>Buka Chat</span>
                                                    </Button>
                                                </Link>
                                                <Button 
                                                    v-if="t.status === 'open'"
                                                    variant="outline" 
                                                    size="sm" 
                                                    class="h-7 text-xs font-bold text-emerald-600 border-emerald-500/30 hover:bg-emerald-500/10 gap-1 cursor-pointer"
                                                    @click="resolveTicket(t)"
                                                >
                                                    <Check class="h-3 w-3" />
                                                    <span>Selesai</span>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="!support.tickets?.data?.length">
                                        <td colspan="5" class="py-12 text-center text-muted-foreground">
                                            Tidak ada tiket aduan pada kategori ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Support Pagination -->
                        <div v-if="support.tickets?.links?.length > 3" class="p-4 border-t flex items-center justify-between">
                            <span class="text-xs text-muted-foreground">
                                Menampilkan {{ support.tickets.from || 0 }} - {{ support.tickets.to || 0 }} dari {{ support.tickets.total }} aduan
                            </span>
                            <div class="flex gap-1">
                                <Link
                                    v-for="(link, i) in support.tickets.links"
                                    :key="i"
                                    :href="link.url || '#'"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold"
                                    :class="link.active ? 'bg-primary text-primary-foreground font-bold' : (!link.url ? 'text-muted-foreground/30 pointer-events-none' : 'text-muted-foreground hover:bg-muted')"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- ========================================== -->
            <!-- TAB 4 CONTENT: AUDIT REFERRAL & ANTI-FRAUD -->
            <!-- ========================================== -->
            <div v-else-if="currentTab === 'fraud'" class="space-y-4">
                <!-- Fraud Metrics Strip -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card class="p-4 border shadow-xs flex items-center justify-between">
                        <div>
                            <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Total Referral</div>
                            <div class="text-2xl font-black text-foreground mt-0.5 tabular-nums">{{ fraud.metrics?.total_referrals_count || 0 }}</div>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <Users class="h-5 w-5" />
                        </div>
                    </Card>

                    <Card class="p-4 border shadow-xs flex items-center justify-between">
                        <div>
                            <div class="text-[11px] font-medium text-amber-600 dark:text-amber-400 uppercase tracking-wider">Mencurigakan</div>
                            <div class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-0.5 tabular-nums">{{ fraud.metrics?.flagged_referrals_count || 0 }}</div>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                            <AlertCircle class="h-5 w-5" />
                        </div>
                    </Card>

                    <Card class="p-4 border shadow-xs flex items-center justify-between">
                        <div>
                            <div class="text-[11px] font-medium text-rose-600 dark:text-rose-400 uppercase tracking-wider">Hadiah Diblokir</div>
                            <div class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-0.5 tabular-nums">{{ fraud.metrics?.blocked_referrals_count || 0 }}</div>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-rose-500/10 text-rose-600 flex items-center justify-center shrink-0">
                            <XCircle class="h-5 w-5" />
                        </div>
                    </Card>

                    <Card class="p-4 border shadow-xs flex items-center justify-between">
                        <div>
                            <div class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Reward Beredar</div>
                            <div class="text-xl sm:text-2xl font-black text-foreground mt-0.5 tabular-nums">{{ formatRupiah(fraud.metrics?.total_rewards_available) }}</div>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                            <DollarSign class="h-5 w-5" />
                        </div>
                    </Card>
                </div>

                <!-- Fraud Filters Toolbar -->
                <Card class="p-4 border shadow-xs space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2 relative">
                            <Search class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="fraudSearch"
                                type="text"
                                placeholder="Cari nama pengundang, yang diundang, atau kode referral..."
                                class="pl-9 text-xs"
                                @keyup.enter="applyFraudFilters"
                            />
                        </div>

                        <div>
                            <select 
                                v-model="fraudFilter" 
                                @change="applyFraudFilters"
                                class="w-full bg-background border border-input rounded-xl px-3 py-2 text-xs focus:outline-hidden focus:ring-1 focus:ring-primary font-medium"
                            >
                                <option value="">Semua Status Audit</option>
                                <option value="valid">Sah (Valid)</option>
                                <option value="suspect">Mencurigakan (Suspect)</option>
                                <option value="blocked">Diblokir (Blocked)</option>
                                <option value="redeemed">Telah Dipakai</option>
                                <option value="unredeemed">Belum Dipakai</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs pt-1 border-t">
                        <span class="text-muted-foreground">
                            Menampilkan data audit referral
                        </span>
                        <Button 
                            variant="ghost" 
                            size="sm" 
                            class="h-7 text-xs text-muted-foreground hover:text-foreground gap-1 cursor-pointer"
                            @click="resetFraudFilters"
                        >
                            <RotateCcw class="h-3 w-3" />
                            <span>Reset Filter</span>
                        </Button>
                    </div>
                </Card>

                <!-- Fraud Logs Table -->
                <Card class="border shadow-xs overflow-hidden">
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-muted/50 text-muted-foreground border-b font-semibold">
                                    <tr>
                                        <th class="py-3 px-4">Pengundang (Referrer)</th>
                                        <th class="py-3 px-4">Yang Diundang (Referee)</th>
                                        <th class="py-3 px-4">Indikator Risiko</th>
                                        <th class="py-3 px-4 text-right">Reward Diskon</th>
                                        <th class="py-3 px-4 text-center">Status Audit</th>
                                        <th class="py-3 px-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60">
                                    <tr 
                                        v-for="log in fraud.logs?.data" 
                                        :key="log.id"
                                        class="hover:bg-muted/30 transition-colors"
                                    >
                                        <td class="py-3 px-4">
                                            <div class="font-bold text-foreground">{{ log.referrer.name }}</div>
                                            <div class="text-[11px] text-muted-foreground font-mono">@{{ log.referrer.username }}</div>
                                        </td>

                                        <td class="py-3 px-4">
                                            <div class="font-bold text-foreground">{{ log.referee.name }}</div>
                                            <div class="text-[11px] text-muted-foreground">
                                                {{ log.referee.wallets_count }} Dompet • Gabung {{ log.referee.joined_at }}
                                            </div>
                                        </td>

                                        <td class="py-3 px-4">
                                            <div class="flex flex-wrap gap-1">
                                                <Badge 
                                                    v-for="rf in log.risk_flags" 
                                                    :key="rf" 
                                                    variant="destructive" 
                                                    class="text-[9px] py-0 px-1 font-bold"
                                                >
                                                    {{ rf }}
                                                </Badge>
                                                <span v-if="!log.risk_flags?.length" class="text-muted-foreground text-[11px]">-</span>
                                            </div>
                                        </td>

                                        <td class="py-3 px-4 text-right font-mono font-bold">
                                            <div>{{ formatRupiah(log.reward_discount) }}</div>
                                            <div v-if="log.is_redeemed" class="text-[10px] text-primary">Telah ditebus</div>
                                        </td>

                                        <td class="py-3 px-4 text-center">
                                            <Badge 
                                                :variant="log.status === 'valid' ? 'default' : (log.status === 'suspect' ? 'secondary' : 'destructive')"
                                                class="text-[10px] font-bold"
                                            >
                                                {{ log.status === 'valid' ? 'Sah' : (log.status === 'suspect' ? 'Mencurigakan' : 'Diblokir') }}
                                            </Badge>
                                        </td>

                                        <td class="py-3 px-4 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <Button 
                                                    v-if="log.status !== 'valid'"
                                                    size="sm" 
                                                    variant="outline" 
                                                    class="h-7 text-xs text-emerald-600 hover:bg-emerald-500/10 cursor-pointer"
                                                    @click="openFraudStatusModal(log, 'valid')"
                                                >
                                                    Sah
                                                </Button>
                                                <Button 
                                                    v-if="log.status !== 'suspect'"
                                                    size="sm" 
                                                    variant="outline" 
                                                    class="h-7 text-xs text-amber-600 hover:bg-amber-500/10 cursor-pointer"
                                                    @click="openFraudStatusModal(log, 'suspect')"
                                                >
                                                    Suspect
                                                </Button>
                                                <Button 
                                                    v-if="log.status !== 'blocked'"
                                                    size="sm" 
                                                    variant="outline" 
                                                    class="h-7 text-xs text-destructive hover:bg-destructive/10 cursor-pointer"
                                                    @click="openFraudStatusModal(log, 'blocked')"
                                                >
                                                    Blokir
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="!fraud.logs?.data?.length">
                                        <td colspan="6" class="py-12 text-center text-muted-foreground">
                                            Tidak ada riwayat klaim referral pada filter ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Fraud Pagination -->
                        <div v-if="fraud.logs?.links?.length > 3" class="p-4 border-t flex items-center justify-between">
                            <span class="text-xs text-muted-foreground">
                                Menampilkan {{ fraud.logs.from || 0 }} - {{ fraud.logs.to || 0 }} dari {{ fraud.logs.total }} klaim
                            </span>
                            <div class="flex gap-1">
                                <Link
                                    v-for="(link, i) in fraud.logs.links"
                                    :key="i"
                                    :href="link.url || '#'"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold"
                                    :class="link.active ? 'bg-primary text-primary-foreground font-bold' : (!link.url ? 'text-muted-foreground/30 pointer-events-none' : 'text-muted-foreground hover:bg-muted')"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- ========================================== -->
            <!-- SHARED MODALS -->
            <!-- ========================================== -->

            <!-- Modal 1: Bukti Pembayaran -->
            <div 
                v-if="activeProofOrder" 
                class="fixed inset-0 z-50 bg-background/80 backdrop-blur-xs flex items-center justify-center p-4"
            >
                <div class="bg-card border shadow-xl rounded-2xl max-w-lg w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b pb-3">
                        <div>
                            <h3 class="font-bold text-base">Bukti Pembayaran Manual</h3>
                            <p class="text-xs text-muted-foreground font-mono">{{ activeProofOrder.order_number }}</p>
                        </div>
                        <Button variant="ghost" size="icon" class="h-7 w-7 cursor-pointer" @click="closeProofModal">
                            <X class="h-4 w-4" />
                        </Button>
                    </div>

                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-2 p-3 bg-muted/40 rounded-xl text-xs">
                            <div>
                                <span class="text-muted-foreground">Pembeli:</span>
                                <div class="font-bold text-foreground">{{ activeProofOrder.user.name }}</div>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Paket:</span>
                                <div class="font-bold text-foreground">{{ activeProofOrder.app_name }} ({{ activeProofOrder.duration_months }} bln)</div>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Total Tagihan:</span>
                                <div class="font-black text-foreground font-mono">{{ formatRupiah(activeProofOrder.final_amount) }}</div>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Metode:</span>
                                <div class="font-semibold text-foreground">{{ activeProofOrder.payment_method_label }}</div>
                            </div>
                        </div>

                        <div class="border rounded-xl p-2 bg-muted/20 flex flex-col items-center justify-center min-h-60">
                            <img 
                                v-if="activeProofOrder.payment_proof_path" 
                                :src="activeProofOrder.payment_proof_path" 
                                alt="Bukti Transfer"
                                class="max-h-96 w-auto object-contain rounded-lg shadow-xs" 
                            />
                            <div v-else class="text-xs text-muted-foreground py-10">
                                Tidak ada berkas gambar bukti bayar.
                            </div>
                        </div>

                        <div v-if="activeProofOrder.payment_proof_path" class="text-center">
                            <a 
                                :href="activeProofOrder.payment_proof_path" 
                                target="_blank" 
                                class="text-xs text-primary hover:underline inline-flex items-center gap-1 font-semibold"
                            >
                                <span>Buka Gambar Ukuran Penuh</span>
                                <ExternalLink class="h-3 w-3" />
                            </a>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t">
                        <Button variant="outline" size="sm" class="text-xs cursor-pointer" @click="closeProofModal">Tutup</Button>
                        <div v-if="activeProofOrder.status === 'pending'" class="flex items-center gap-2">
                            <Button 
                                variant="outline" 
                                size="sm" 
                                class="text-xs text-destructive border-destructive/30 hover:bg-destructive/10 cursor-pointer"
                                @click="openRejectModal(activeProofOrder)"
                            >
                                Tolak
                            </Button>
                            <Button 
                                size="sm" 
                                class="text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5 cursor-pointer"
                                @click="approveOrder(activeProofOrder)"
                            >
                                <Check class="h-3.5 w-3.5" />
                                <span>Setujui & Aktifkan</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal 2: Tolak Pesanan -->
            <div 
                v-if="activeRejectOrder" 
                class="fixed inset-0 z-50 bg-background/80 backdrop-blur-xs flex items-center justify-center p-4"
            >
                <div class="bg-card border shadow-xl rounded-2xl max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                    <div class="flex items-center justify-between border-b pb-3">
                        <div>
                            <h3 class="font-bold text-base text-destructive">Tolak Pesanan</h3>
                            <p class="text-xs text-muted-foreground font-mono">{{ activeRejectOrder.order_number }}</p>
                        </div>
                        <Button variant="ghost" size="icon" class="h-7 w-7 cursor-pointer" @click="closeRejectModal">
                            <X class="h-4 w-4" />
                        </Button>
                    </div>

                    <form @submit.prevent="submitReject" class="space-y-4">
                        <div class="space-y-1.5">
                            <Label for="reject_reason" class="text-xs font-semibold">Alasan Penolakan</Label>
                            <textarea
                                id="reject_reason"
                                v-model="rejectForm.rejection_reason"
                                rows="3"
                                placeholder="Contoh: Bukti transfer tidak valid / dana belum masuk."
                                required
                                class="w-full bg-muted/40 border rounded-xl p-3 text-xs focus:outline-hidden focus:ring-1 focus:ring-primary font-medium"
                                :class="{ 'border-destructive': rejectForm.errors.rejection_reason }"
                            ></textarea>
                            <p v-if="rejectForm.errors.rejection_reason" class="text-[11px] text-destructive font-medium">
                                {{ rejectForm.errors.rejection_reason }}
                            </p>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t">
                            <Button type="button" variant="outline" size="sm" class="text-xs cursor-pointer" @click="closeRejectModal">Batal</Button>
                            <Button 
                                type="submit" 
                                size="sm" 
                                variant="destructive" 
                                class="text-xs font-bold cursor-pointer"
                                :disabled="rejectForm.processing"
                            >
                                {{ rejectForm.processing ? 'Menolak...' : 'Konfirmasi Tolak' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal 3: Role Pengguna -->
            <div 
                v-if="activeUserRoleModal" 
                class="fixed inset-0 z-50 bg-background/80 backdrop-blur-xs flex items-center justify-center p-4"
            >
                <div class="bg-card border shadow-xl rounded-2xl max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                    <div class="flex items-center justify-between border-b pb-3">
                        <div>
                            <h3 class="font-bold text-base">Ubah Role Pengguna</h3>
                            <p class="text-xs text-muted-foreground">{{ activeUserRoleModal.name }} (@{{ activeUserRoleModal.username }})</p>
                        </div>
                        <Button variant="ghost" size="icon" class="h-7 w-7 cursor-pointer" @click="closeRoleModal">
                            <X class="h-4 w-4" />
                        </Button>
                    </div>

                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-muted-foreground">Pilih peran yang diberikan:</p>
                        <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                            <div 
                                v-for="r in users.roles" 
                                :key="r"
                                @click="toggleUserRole(r)"
                                class="flex items-center justify-between p-2.5 rounded-xl border cursor-pointer transition-all text-xs"
                                :class="userRoleForm.roles.includes(r) ? 'border-primary bg-primary/5 font-bold text-foreground' : 'border-border text-muted-foreground hover:bg-muted/40'"
                            >
                                <span>{{ r }}</span>
                                <Check v-if="userRoleForm.roles.includes(r)" class="h-4 w-4 text-primary" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t">
                        <Button variant="outline" size="sm" class="text-xs cursor-pointer" @click="closeRoleModal">Batal</Button>
                        <Button size="sm" class="text-xs font-bold cursor-pointer" :disabled="userRoleForm.processing" @click="saveUserRoles">
                            Simpan Perubahan
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Modal 4: Status Fraud Referral -->
            <div 
                v-if="activeFraudModal" 
                class="fixed inset-0 z-50 bg-background/80 backdrop-blur-xs flex items-center justify-center p-4"
            >
                <div class="bg-card border shadow-xl rounded-2xl max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                    <div class="flex items-center justify-between border-b pb-3">
                        <div>
                            <h3 class="font-bold text-base">Perbarui Status Anti-Fraud</h3>
                            <p class="text-xs text-muted-foreground">Pengundang: {{ activeFraudModal.referrer.name }}</p>
                        </div>
                        <Button variant="ghost" size="icon" class="h-7 w-7 cursor-pointer" @click="closeFraudModal">
                            <X class="h-4 w-4" />
                        </Button>
                    </div>

                    <form @submit.prevent="submitFraudStatus" class="space-y-4">
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Status yang Dipilih</Label>
                            <div class="font-bold text-xs capitalize p-2.5 rounded-xl border bg-muted/40">
                                {{ fraudForm.status === 'valid' ? 'Sah / Diverifikasi (Valid)' : (fraudForm.status === 'suspect' ? 'Mencurigakan (Suspect)' : 'Dibekukan / Diblokir (Blocked)') }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="fraud_reason" class="text-xs font-semibold">Catatan / Alasan Peninjauan</Label>
                            <Input
                                id="fraud_reason"
                                v-model="fraudForm.fraud_reason"
                                type="text"
                                placeholder="Contoh: Akun kloningan terdeteksi / tidak ada aktivitas finansial."
                                class="text-xs"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t">
                            <Button type="button" variant="outline" size="sm" class="text-xs cursor-pointer" @click="closeFraudModal">Batal</Button>
                            <Button 
                                type="submit" 
                                size="sm" 
                                class="text-xs font-bold cursor-pointer"
                                :disabled="fraudForm.processing"
                            >
                                {{ fraudForm.processing ? 'Menyimpan...' : 'Simpan Status' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

