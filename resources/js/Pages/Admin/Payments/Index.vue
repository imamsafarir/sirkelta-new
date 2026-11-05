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
    CreditCard, 
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
    User as UserIcon,
    Calendar
} from 'lucide-vue-next';

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },
    metrics: {
        type: Object,
        default: () => ({
            total_orders: 0,
            pending_orders: 0,
            paid_orders: 0,
            total_revenue: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            status: 'all',
            module: 'all',
            payment_method: 'all',
            search: '',
        }),
    },
});

const page = usePage();

// Filters State
const search = ref(props.filters.search || '');
const selectedStatus = ref(props.filters.status || 'all');
const selectedModule = ref(props.filters.module || 'all');
const selectedMethod = ref(props.filters.payment_method || 'all');

const applyFilters = () => {
    router.get(route('admin.payments.index'), {
        search: search.value || undefined,
        status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
        module: selectedModule.value !== 'all' ? selectedModule.value : undefined,
        payment_method: selectedMethod.value !== 'all' ? selectedMethod.value : undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    search.value = '';
    selectedStatus.value = 'all';
    selectedModule.value = 'all';
    selectedMethod.value = 'all';
    applyFilters();
};

// Modals State
const activeProofOrder = ref(null);
const activeRejectOrder = ref(null);

const rejectForm = useForm({
    rejection_reason: '',
});

const openProofModal = (order) => {
    activeProofOrder.value = order;
};

const closeProofModal = () => {
    activeProofOrder.value = null;
};

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
                if (activeProofOrder.value?.id === order.id) {
                    closeProofModal();
                }
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
            if (activeProofOrder.value?.id === activeRejectOrder.value?.id) {
                closeProofModal();
            }
        },
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

const getStatusBadgeVariant = (status) => {
    switch (status) {
        case 'paid': return 'default';
        case 'pending': return 'secondary';
        case 'rejected': return 'destructive';
        default: return 'outline';
    }
};

const getStatusLabel = (status) => {
    switch (status) {
        case 'paid': return 'Lunas';
        case 'pending': return 'Menunggu Verifikasi';
        case 'rejected': return 'Ditolak';
        case 'expired': return 'Kedaluwarsa';
        default: return status;
    }
};
</script>

<template>
    <DashboardLayout 
        title="Kelola Transaksi & Pembayaran" 
        activeModule="admin" 
        :user="page.props.auth.user"
    >
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-foreground">Kelola Transaksi</h1>
                    <p class="text-xs text-muted-foreground mt-1">
                        Daftar pesanan upgrade pro, verifikasi bukti transfer manual, dan riwayat mutasi pembayaran otomatis.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link :href="route('admin.website.index', { tab: 'payment' })">
                        <Button variant="outline" size="sm" class="gap-1.5 text-xs font-semibold">
                            <CreditCard class="h-3.5 w-3.5 text-primary" />
                            <span>Pengaturan Pembayaran</span>
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <Card class="p-4 border shadow-xs flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Total Pesanan</div>
                        <div class="text-2xl font-black text-foreground mt-0.5 tabular-nums">{{ metrics.total_orders }}</div>
                        <div class="text-[11px] text-muted-foreground mt-1">Semua transaksi tercatat</div>
                    </div>
                    <div class="h-11 w-11 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <CreditCard class="h-5 w-5" />
                    </div>
                </Card>

                <Card class="p-4 border shadow-xs flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-medium text-amber-600 dark:text-amber-400 uppercase tracking-wider">Perlu Verifikasi</div>
                        <div class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-0.5 tabular-nums">{{ metrics.pending_orders }}</div>
                        <div class="text-[11px] text-muted-foreground mt-1">Transfer manual pending</div>
                    </div>
                    <div class="h-11 w-11 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                        <Clock class="h-5 w-5" />
                    </div>
                </Card>

                <Card class="p-4 border shadow-xs flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Pesanan Lunas</div>
                        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5 tabular-nums">{{ metrics.paid_orders }}</div>
                        <div class="text-[11px] text-muted-foreground mt-1">Langganan aktif</div>
                    </div>
                    <div class="h-11 w-11 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                        <CheckCircle2 class="h-5 w-5" />
                    </div>
                </Card>

                <Card class="p-4 border shadow-xs flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Total Pendapatan</div>
                        <div class="text-xl sm:text-2xl font-black text-foreground mt-0.5 tabular-nums">{{ formatRupiah(metrics.total_revenue) }}</div>
                        <div class="text-[11px] text-muted-foreground mt-1">Total pembayaran sukses</div>
                    </div>
                    <div class="h-11 w-11 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center shrink-0">
                        <DollarSign class="h-5 w-5" />
                    </div>
                </Card>
            </div>

            <!-- Filters & Search Toolbar -->
            <Card class="p-4 border shadow-xs space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <!-- Search Input -->
                    <div class="lg:col-span-2 relative">
                        <Search class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="search"
                            type="text"
                            placeholder="Cari nomor order, nama user, email..."
                            class="pl-9 text-xs"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <select 
                            v-model="selectedStatus" 
                            @change="applyFilters"
                            class="w-full bg-background border border-input rounded-xl px-3 py-2 text-xs focus:outline-hidden focus:ring-1 focus:ring-primary font-medium"
                        >
                            <option value="all">Semua Status</option>
                            <option value="pending">Menunggu Verifikasi</option>
                            <option value="paid">Lunas (Paid)</option>
                            <option value="rejected">Ditolak</option>
                            <option value="expired">Kedaluwarsa</option>
                        </select>
                    </div>

                    <!-- Module Filter -->
                    <div>
                        <select 
                            v-model="selectedModule" 
                            @change="applyFilters"
                            class="w-full bg-background border border-input rounded-xl px-3 py-2 text-xs focus:outline-hidden focus:ring-1 focus:ring-primary font-medium"
                        >
                            <option value="all">Semua Modul</option>
                            <option value="keuangan">Sirkelta Keuangan</option>
                            <option value="katalog">Sirkelta Katalog</option>
                        </select>
                    </div>

                    <!-- Payment Method Filter -->
                    <div>
                        <select 
                            v-model="selectedMethod" 
                            @change="applyFilters"
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
                        Ditemukan <strong class="text-foreground">{{ orders.total }}</strong> transaksi
                    </span>
                    <Button 
                        variant="ghost" 
                        size="sm" 
                        class="h-7 text-xs text-muted-foreground hover:text-foreground gap-1"
                        @click="resetFilters"
                    >
                        <RotateCcw class="h-3 w-3" />
                        <span>Reset Filter</span>
                    </Button>
                </div>
            </Card>

            <!-- Transaction Table Card -->
            <Card class="border shadow-xs overflow-hidden">
                <CardHeader class="p-4 border-b">
                    <CardTitle class="text-base font-bold">Daftar Transaksi</CardTitle>
                    <CardDescription class="text-xs">
                        Klik tombol setujui untuk transaksi transfer manual yang telah Anda periksa mutasinya.
                    </CardDescription>
                </CardHeader>

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
                                    v-for="order in orders.data" 
                                    :key="order.id"
                                    class="hover:bg-muted/30 transition-colors"
                                >
                                    <!-- Order Number & Date -->
                                    <td class="py-3.5 px-4 align-top">
                                        <div class="font-mono font-bold text-foreground">{{ order.order_number }}</div>
                                        <div class="text-[11px] text-muted-foreground flex items-center gap-1 mt-0.5">
                                            <Calendar class="h-3 w-3" />
                                            <span>{{ order.created_at }}</span>
                                        </div>
                                    </td>

                                    <!-- User Info -->
                                    <td class="py-3.5 px-4 align-top">
                                        <div class="font-bold text-foreground">{{ order.user.name }}</div>
                                        <div class="text-[11px] text-muted-foreground font-mono">@{{ order.user.username }}</div>
                                        <div class="text-[11px] text-muted-foreground">{{ order.user.email }}</div>
                                    </td>

                                    <!-- App & Duration -->
                                    <td class="py-3.5 px-4 align-top">
                                        <div class="font-semibold text-foreground">{{ order.app_name }}</div>
                                        <div class="inline-flex items-center gap-1 mt-0.5">
                                            <Badge variant="outline" class="text-[10px] py-0 px-1.5 font-bold bg-muted/50">
                                                {{ order.duration_months }} Bulan
                                            </Badge>
                                        </div>
                                    </td>

                                    <!-- Payment Method -->
                                    <td class="py-3.5 px-4 align-top">
                                        <div class="font-medium text-foreground flex items-center gap-1.5">
                                            <Building2 v-if="order.payment_method === 'manual_bank'" class="h-3.5 w-3.5 text-primary" />
                                            <QrCode v-else-if="order.payment_method === 'manual_qris'" class="h-3.5 w-3.5 text-primary" />
                                            <Sparkles v-else class="h-3.5 w-3.5 text-indigo-500" />
                                            <span>{{ order.payment_method_label }}</span>
                                        </div>

                                        <!-- Midtrans Transaction ID / Proof Link -->
                                        <div v-if="order.midtrans_transaction_id" class="text-[10px] text-muted-foreground font-mono mt-0.5">
                                            ID: {{ order.midtrans_transaction_id }}
                                        </div>
                                        <div v-else-if="order.payment_proof_path" class="mt-1">
                                            <button 
                                                @click="openProofModal(order)"
                                                class="text-[11px] text-primary hover:underline font-semibold flex items-center gap-1"
                                            >
                                                <FileImage class="h-3 w-3" />
                                                <span>Lihat Bukti Transfer</span>
                                            </button>
                                        </div>
                                    </td>

                                    <!-- Amount -->
                                    <td class="py-3.5 px-4 align-top text-right">
                                        <div class="font-bold text-foreground font-mono text-sm">
                                            {{ formatRupiah(order.final_amount) }}
                                        </div>
                                        <div v-if="order.discount_applied > 0" class="text-[10px] text-emerald-600 font-medium">
                                            Diskon: -{{ formatRupiah(order.discount_applied) }}
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="py-3.5 px-4 align-top text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <Badge 
                                                :variant="getStatusBadgeVariant(order.status)"
                                                class="text-[10px] font-bold"
                                            >
                                                {{ getStatusLabel(order.status) }}
                                            </Badge>

                                            <div v-if="order.status === 'paid' && order.verifier" class="text-[10px] text-muted-foreground">
                                                Oleh: {{ order.verifier.name }}
                                            </div>
                                            <div v-if="order.status === 'rejected' && order.rejection_reason" class="text-[10px] text-destructive max-w-xs truncate" :title="order.rejection_reason">
                                                "{{ order.rejection_reason }}"
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-3.5 px-4 align-top text-right">
                                        <div v-if="order.status === 'pending'" class="flex items-center justify-end gap-1.5">
                                            <Button 
                                                size="sm" 
                                                class="h-7 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white gap-1 px-2.5"
                                                @click="approveOrder(order)"
                                            >
                                                <Check class="h-3 w-3" />
                                                <span>Setujui</span>
                                            </Button>
                                            <Button 
                                                variant="outline" 
                                                size="sm" 
                                                class="h-7 text-xs font-bold text-destructive hover:bg-destructive/10 border-destructive/30 gap-1 px-2"
                                                @click="openRejectModal(order)"
                                            >
                                                <X class="h-3 w-3" />
                                                <span>Tolak</span>
                                            </Button>
                                        </div>
                                        <div v-else-if="order.payment_proof_path">
                                            <Button 
                                                variant="ghost" 
                                                size="sm" 
                                                class="h-7 text-xs text-muted-foreground gap-1"
                                                @click="openProofModal(order)"
                                            >
                                                <Eye class="h-3 w-3" />
                                                <span>Bukti</span>
                                            </Button>
                                        </div>
                                        <div v-else class="text-[11px] text-muted-foreground">
                                            -
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="orders.data.length === 0">
                                    <td colspan="7" class="py-12 text-center text-muted-foreground">
                                        <CreditCard class="h-8 w-8 mx-auto text-muted-foreground/40 mb-2" />
                                        <p class="font-medium">Tidak ada data transaksi yang ditemukan.</p>
                                        <p class="text-[11px] mt-0.5">Coba sesuaikan kata kunci pencarian atau filter status Anda.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="orders.links && orders.links.length > 3" class="p-4 border-t flex flex-col sm:flex-row items-center justify-between gap-3">
                        <span class="text-xs text-muted-foreground">
                            Menampilkan {{ orders.from || 0 }} - {{ orders.to || 0 }} dari {{ orders.total }} pesanan
                        </span>
                        <div class="flex flex-wrap gap-1">
                            <Link
                                v-for="(link, i) in orders.links"
                                :key="i"
                                :href="link.url || '#'"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all"
                                :class="link.active 
                                    ? 'bg-primary text-primary-foreground font-bold' 
                                    : !link.url 
                                        ? 'text-muted-foreground/40 pointer-events-none' 
                                        : 'text-muted-foreground hover:bg-muted'"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

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
                        <Button variant="ghost" size="icon" class="h-7 w-7" @click="closeProofModal">
                            <X class="h-4 w-4" />
                        </Button>
                    </div>

                    <div class="space-y-3">
                        <!-- Transaction Details Snapshot -->
                        <div class="grid grid-cols-2 gap-2 p-3 bg-muted/40 rounded-xl text-xs">
                            <div>
                                <span class="text-muted-foreground">Pembeli:</span>
                                <div class="font-bold text-foreground">{{ activeProofOrder.user.name }}</div>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Aplikasi:</span>
                                <div class="font-bold text-foreground">{{ activeProofOrder.app_name }} ({{ activeProofOrder.duration_months }} bln)</div>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Nominal Tagihan:</span>
                                <div class="font-black text-foreground font-mono">{{ formatRupiah(activeProofOrder.final_amount) }}</div>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Metode:</span>
                                <div class="font-semibold text-foreground">{{ activeProofOrder.payment_method_label }}</div>
                            </div>
                        </div>

                        <!-- Image Display -->
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

                    <!-- Action buttons inside modal if still pending -->
                    <div class="flex items-center justify-between pt-3 border-t">
                        <Button variant="outline" size="sm" class="text-xs" @click="closeProofModal">Tutup</Button>
                        
                        <div v-if="activeProofOrder.status === 'pending'" class="flex items-center gap-2">
                            <Button 
                                variant="outline" 
                                size="sm" 
                                class="text-xs text-destructive border-destructive/30 hover:bg-destructive/10"
                                @click="openRejectModal(activeProofOrder)"
                            >
                                Tolak
                            </Button>
                            <Button 
                                size="sm" 
                                class="text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5"
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
                        <Button variant="ghost" size="icon" class="h-7 w-7" @click="closeRejectModal">
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
                                placeholder="Contoh: Bukti transfer tidak valid / dana belum masuk ke mutasi rekening."
                                required
                                class="w-full bg-muted/40 border rounded-xl p-3 text-xs focus:outline-hidden focus:ring-1 focus:ring-primary font-medium"
                                :class="{ 'border-destructive': rejectForm.errors.rejection_reason }"
                            ></textarea>
                            <p v-if="rejectForm.errors.rejection_reason" class="text-[11px] text-destructive font-medium">
                                {{ rejectForm.errors.rejection_reason }}
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                Alasan ini dapat dilihat oleh pengguna sebagai keterangan status pesanannya.
                            </p>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t">
                            <Button type="button" variant="outline" size="sm" class="text-xs" @click="closeRejectModal">Batal</Button>
                            <Button 
                                type="submit" 
                                size="sm" 
                                variant="destructive" 
                                class="text-xs font-bold"
                                :disabled="rejectForm.processing"
                            >
                                {{ rejectForm.processing ? 'Menolak...' : 'Konfirmasi Tolak' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

