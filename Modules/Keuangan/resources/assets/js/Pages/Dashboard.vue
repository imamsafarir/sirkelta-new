<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { 
    Plus, 
    CreditCard, 
    ArrowUpRight, 
    ArrowDownRight,
    ArrowLeftRight,
    TrendingUp,
    Wallet,
    Scale,
    Calendar,
    ArrowRight,
    Activity,
    Layers,
    AlertCircle,
    CheckCircle2,
    Download,
    TrendingDown,
    BarChart3,
    Table as TableIcon,
    Sparkles,
    Clock,
    Coins,
    FileText,
    Tag,
    Filter
} from 'lucide-vue-next';
import TransactionModal from '../Components/TransactionModal.vue';

const props = defineProps({
    metrics: {
        type: Object,
        required: true,
    },
    wallets: {
        type: Array,
        default: () => [],
    },
    recentTransactions: {
        type: Array,
        default: () => [],
    },
    radar: {
        type: Object,
        default: () => ({ receivables: [], payables: [] }),
    },
    budgetRadar: {
        type: Object,
        default: () => ({}),
    },
    monthlyTrend: {
        type: Array,
        default: () => [],
    },
    trendSummary: {
        type: Object,
        default: () => null,
    },
    user: {
        type: Object,
        required: true,
    },
});

const isModalOpen = ref(false);
const initialModalType = ref('expense');
const isBudgetEditing = ref(false);

const trendViewMode = ref('chart'); // 'chart' | 'table'
const selectedMonthIndex = ref(
    props.monthlyTrend && props.monthlyTrend.length > 0 ? props.monthlyTrend.length - 1 : 0
);

// Transaction filter state
const recentTxFilter = ref('all'); // 'all' | 'expense' | 'income' | 'transfer' | 'debt'

const filteredRecentTransactions = computed(() => {
    if (!props.recentTransactions) return [];
    if (recentTxFilter.value === 'all') return props.recentTransactions;
    if (recentTxFilter.value === 'expense') return props.recentTransactions.filter(t => t.type === 'expense');
    if (recentTxFilter.value === 'income') return props.recentTransactions.filter(t => t.type === 'income');
    if (recentTxFilter.value === 'transfer') return props.recentTransactions.filter(t => t.type === 'transfer');
    if (recentTxFilter.value === 'debt') return props.recentTransactions.filter(t => t.type === 'debt_payable' || t.type === 'debt_receivable');
    return props.recentTransactions;
});

// Debt radar view tab
const debtRadarTab = ref('all'); // 'all' | 'receivables' | 'payables'

const summary = computed(() => {
    if (props.trendSummary) {
        return props.trendSummary;
    }
    const trend = props.monthlyTrend || [];
    const totalInc = trend.reduce((acc, t) => acc + (t.income || 0), 0);
    const totalExp = trend.reduce((acc, t) => acc + (t.expense || 0), 0);
    const totalNet = totalInc - totalExp;
    return {
        total_income: totalInc,
        total_expense: totalExp,
        total_net: totalNet,
        avg_monthly_income: Math.round(totalInc / 6),
        avg_monthly_expense: Math.round(totalExp / 6),
        avg_monthly_net: Math.round(totalNet / 6),
        avg_savings_rate: totalInc > 0 ? Math.round((totalNet / totalInc) * 1000) / 10 : 0,
        surplus_months_count: trend.filter(t => t.net > 0).length,
        deficit_months_count: trend.filter(t => t.net < 0).length,
        best_month: [...trend].sort((a, b) => b.net - a.net)[0] || null,
        highest_expense_month: [...trend].sort((a, b) => b.expense - a.expense)[0] || null,
    };
});

const selectedMonth = computed(() => {
    if (!props.monthlyTrend || props.monthlyTrend.length === 0) {
        return null;
    }
    return props.monthlyTrend[selectedMonthIndex.value] || props.monthlyTrend[props.monthlyTrend.length - 1] || null;
});

const currentPeriodFormatted = computed(() => {
    return new Date().toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
});

const formatRelativeDate = (dateStr) => {
    if (!dateStr) return '-';
    try {
        const date = new Date(dateStr);
        const today = new Date();
        if (date.toDateString() === today.toDateString()) {
            return 'Hari Ini';
        }
        const yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);
        if (date.toDateString() === yesterday.toDateString()) {
            return 'Kemarin';
        }
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    } catch {
        return dateStr;
    }
};

const getDueDateBadge = (item) => {
    if (!item || !item.due_date) {
        return { label: 'Tanpa Tempo', class: 'bg-muted text-muted-foreground' };
    }
    if (item.is_overdue) {
        const days = Math.abs(item.days_diff || 1);
        return { label: `Lewat ${days} hr`, class: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/30' };
    }
    if (item.is_due_today) {
        return { label: 'Hari Ini', class: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30' };
    }
    const days = item.days_diff !== null ? item.days_diff : 0;
    return { label: `${days} hr lagi`, class: 'bg-muted/60 text-muted-foreground' };
};

const getWalletCategoryBadge = (label) => {
    const upper = (label || '').toUpperCase();
    if (upper.includes('INVESTASI')) {
        return { label: 'INVESTASI', class: 'bg-purple-500/10 text-purple-600 border-purple-500/20' };
    }
    if (upper.includes('TABUNGAN')) {
        return { label: 'TABUNGAN', class: 'bg-sky-500/10 text-sky-600 border-sky-500/20' };
    }
    if (upper.includes('BANK')) {
        return { label: 'BANK', class: 'bg-blue-500/10 text-blue-600 border-blue-500/20' };
    }
    if (upper.includes('E-WALLET') || upper.includes('EWALLET')) {
        return { label: 'E-WALLET', class: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' };
    }
    return { label: label || 'KAS MANDIRI', class: 'bg-muted text-muted-foreground' };
};

const formatCompact = (val) => {
    const absVal = Math.abs(val || 0);
    if (absVal >= 1000000000) {
        return (val / 1000000000).toFixed(1).replace(/\.0$/, '') + 'M';
    }
    if (absVal >= 1000000) {
        return (val / 1000000).toFixed(1).replace(/\.0$/, '') + 'jt';
    }
    if (absVal >= 1000) {
        return (val / 1000).toFixed(0) + 'k';
    }
    return val ? val.toString() : '0';
};

const maxTrendValue = computed(() => {
    if (!props.monthlyTrend || props.monthlyTrend.length === 0) return 1000000;
    const maxVal = Math.max(...props.monthlyTrend.map(t => Math.max(t.income, t.expense)));
    return maxVal > 0 ? maxVal : 1000000;
});

const openTransactionModal = (type = 'expense') => {
    initialModalType.value = type;
    isModalOpen.value = true;
};

const budgetForm = useForm({
    monthly_budget: props.budgetRadar?.custom_budget || '',
});

const saveBudget = () => {
    budgetForm.post(route('keuangan.dashboard.budget'), {
        preserveScroll: true,
        onSuccess: () => {
            isBudgetEditing.value = false;
        },
    });
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(val || 0);
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'safe':
            return { label: 'Aman', variant: 'secondary', class: 'bg-emerald-500/10 text-emerald-600' };
        case 'warning':
            return { label: 'Waspada', variant: 'secondary', class: 'bg-amber-500/10 text-amber-600' };
        case 'danger':
            return { label: 'Kritis', variant: 'destructive', class: '' };
        case 'overbudget':
            return { label: 'Overbudget', variant: 'destructive', class: '' };
        default:
            return { label: 'Belum Diatur', variant: 'outline', class: '' };
    }
};

const getTypeMeta = (type) => {
    switch (type) {
        case 'income':
            return { label: 'Pemasukan', class: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' };
        case 'expense':
            return { label: 'Pengeluaran', class: 'bg-rose-500/10 text-rose-600 border-rose-500/20' };
        case 'transfer':
            return { label: 'Transfer', class: 'bg-sky-500/10 text-sky-600 border-sky-500/20' };
        case 'debt_payable':
            return { label: 'Utang', class: 'bg-amber-500/10 text-amber-600 border-amber-500/20' };
        case 'debt_receivable':
            return { label: 'Piutang', class: 'bg-indigo-500/10 text-indigo-600 border-indigo-500/20' };
        default:
            return { label: type, class: 'bg-muted text-foreground' };
    }
};
</script>

<template>
    <DashboardLayout :user="user" active-module="keuangan" title="Ringkasan Finansial">
        <div class="space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b">
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-foreground">Ringkasan Finansial</h1>
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full bg-primary/10 text-primary border border-primary/20">
                            <Calendar class="w-3.5 h-3.5" />
                            <span>{{ currentPeriodFormatted }}</span>
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-muted-foreground mt-1">
                        Pantauan menyeluruh kekayaan bersih, arus kas, dan radar anggaran Anda
                    </p>
                </div>
                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <Link :href="route('keuangan.reports.index')">
                        <Button variant="outline" size="sm" class="text-xs font-bold gap-1.5 cursor-pointer shadow-2xs">
                            <FileText class="w-3.5 h-3.5 text-muted-foreground" />
                            <span>Laporan Kas</span>
                        </Button>
                    </Link>
                    <Button size="sm" class="text-xs font-bold gap-1.5 shadow-2xs cursor-pointer" @click="openTransactionModal('expense')">
                        <Plus class="w-3.5 h-3.5" />
                        <span>Catat Transaksi</span>
                    </Button>
                </div>
            </div>

            <!-- Quick Action Bar -->
            <div class="p-3 bg-card rounded-2xl border shadow-2xs">
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                    <button
                        type="button"
                        @click="openTransactionModal('expense')"
                        class="p-2.5 rounded-xl border bg-rose-500/5 hover:bg-rose-500/10 text-rose-600 dark:text-rose-400 font-bold text-xs flex items-center justify-center gap-2 transition-all active:scale-95 cursor-pointer"
                    >
                        <div class="w-6 h-6 rounded-lg bg-rose-500 text-white flex items-center justify-center shrink-0">
                            <ArrowDownRight class="w-3.5 h-3.5" />
                        </div>
                        <span>Pengeluaran</span>
                    </button>

                    <button
                        type="button"
                        @click="openTransactionModal('income')"
                        class="p-2.5 rounded-xl border bg-emerald-500/5 hover:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold text-xs flex items-center justify-center gap-2 transition-all active:scale-95 cursor-pointer"
                    >
                        <div class="w-6 h-6 rounded-lg bg-emerald-500 text-white flex items-center justify-center shrink-0">
                            <ArrowUpRight class="w-3.5 h-3.5" />
                        </div>
                        <span>Pemasukan</span>
                    </button>

                    <button
                        type="button"
                        @click="openTransactionModal('transfer')"
                        class="p-2.5 rounded-xl border bg-sky-500/5 hover:bg-sky-500/10 text-sky-600 dark:text-sky-400 font-bold text-xs flex items-center justify-center gap-2 transition-all active:scale-95 cursor-pointer"
                    >
                        <div class="w-6 h-6 rounded-lg bg-sky-500 text-white flex items-center justify-center shrink-0">
                            <ArrowLeftRight class="w-3.5 h-3.5" />
                        </div>
                        <span>Transfer Dompet</span>
                    </button>

                    <button
                        type="button"
                        @click="openTransactionModal('debt_payable')"
                        class="p-2.5 rounded-xl border bg-amber-500/5 hover:bg-amber-500/10 text-amber-600 dark:text-amber-400 font-bold text-xs flex items-center justify-center gap-2 transition-all active:scale-95 cursor-pointer"
                    >
                        <div class="w-6 h-6 rounded-lg bg-amber-500 text-white flex items-center justify-center shrink-0">
                            <Scale class="w-3.5 h-3.5" />
                        </div>
                        <span>Utang / Piutang</span>
                    </button>

                    <a
                        :href="route('keuangan.reports.export')"
                        target="_blank"
                        class="col-span-2 sm:col-span-1 p-2.5 rounded-xl border bg-muted/40 hover:bg-muted text-foreground font-bold text-xs flex items-center justify-center gap-2 transition-all active:scale-95 cursor-pointer"
                    >
                        <div class="w-6 h-6 rounded-lg bg-foreground text-background flex items-center justify-center shrink-0">
                            <Download class="w-3.5 h-3.5" />
                        </div>
                        <span>Unduh CSV</span>
                    </a>
                </div>
            </div>

            <!-- Top Metric Cards Grid: Neraca Finansial -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Net Worth -->
                <Card class="shadow-2xs border-l-4 border-l-primary hover:border-primary/60 transition-all">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Kekayaan Bersih (Net Worth)</span>
                            <Activity class="h-4 w-4 text-primary" />
                        </CardDescription>
                        <CardTitle class="text-2xl font-extrabold tracking-tight tabular-nums text-foreground">
                            {{ formatRupiah(metrics.netWorth) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-[11px] text-muted-foreground pt-0 flex items-center justify-between">
                        <span>(Saldo Kas + Piutang) - Utang</span>
                        <Badge variant="outline" class="text-[9px] px-1.5 py-0 h-4 border-primary/30 text-primary font-bold">
                            Total Ekuitas
                        </Badge>
                    </CardContent>
                </Card>

                <!-- Kas Siap Pakai -->
                <Card class="shadow-2xs border-l-4 border-l-emerald-500 hover:border-emerald-500/60 transition-all">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Kas Siap Pakai (Likuid)</span>
                            <Wallet class="h-4 w-4 text-emerald-500" />
                        </CardDescription>
                        <CardTitle class="text-2xl font-extrabold tracking-tight tabular-nums text-emerald-600 dark:text-emerald-400">
                            {{ formatRupiah(metrics.kasSiapPakai) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-[11px] text-muted-foreground pt-0 flex items-center justify-between">
                        <span>{{ wallets.length }} rekening kas aktif</span>
                        <Link :href="route('keuangan.wallets.index')" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold hover:underline flex items-center gap-0.5">
                            <span>Kelola</span>
                            <ArrowRight class="w-2.5 h-2.5" />
                        </Link>
                    </CardContent>
                </Card>

                <!-- Piutang Aktif (Hak Tagih) -->
                <Card class="shadow-2xs border-l-4 border-l-indigo-500 hover:border-indigo-500/60 transition-all">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Piutang Aktif (Hak Tagih)</span>
                            <ArrowUpRight class="h-4 w-4 text-indigo-500" />
                        </CardDescription>
                        <CardTitle class="text-2xl font-extrabold tracking-tight tabular-nums text-indigo-600 dark:text-indigo-400">
                            {{ formatRupiah(metrics.totalPiutang) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-[11px] text-muted-foreground pt-0 flex items-center justify-between">
                        <span>{{ metrics.piutangCount }} tagihan berjalan</span>
                        <Link :href="route('keuangan.debts.index')" class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold hover:underline flex items-center gap-0.5">
                            <span>Radar</span>
                            <ArrowRight class="w-2.5 h-2.5" />
                        </Link>
                    </CardContent>
                </Card>

                <!-- Utang Aktif (Kewajiban) -->
                <Card class="shadow-2xs border-l-4 border-l-rose-500 hover:border-rose-500/60 transition-all">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Utang Aktif (Kewajiban)</span>
                            <ArrowDownRight class="h-4 w-4 text-rose-500" />
                        </CardDescription>
                        <CardTitle class="text-2xl font-extrabold tracking-tight tabular-nums text-rose-600 dark:text-rose-400">
                            {{ formatRupiah(metrics.totalUtang) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-[11px] text-muted-foreground pt-0 flex items-center justify-between">
                        <span>{{ metrics.utangCount }} kewajiban belum lunas</span>
                        <Link :href="route('keuangan.debts.index')" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold hover:underline flex items-center gap-0.5">
                            <span>Jadwal</span>
                            <ArrowRight class="w-2.5 h-2.5" />
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <!-- Current Month Cashflow Performance Banner -->
            <div class="p-3 sm:p-4 rounded-2xl bg-card border shadow-2xs">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Kinerja Arus Kas Bulan Berjalan</span>
                        <Badge variant="secondary" class="text-[10px] font-bold">
                            {{ currentPeriodFormatted }}
                        </Badge>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="text-muted-foreground">Status Arus Kas:</span>
                        <Badge 
                            variant="outline" 
                            class="text-xs font-extrabold"
                            :class="metrics.monthNet >= 0 ? 'text-emerald-600 bg-emerald-500/10 border-emerald-500/20' : 'text-rose-600 bg-rose-500/10 border-rose-500/20'"
                        >
                            {{ metrics.monthNet >= 0 ? 'Surplus Bersih' : 'Defisit Belanja' }}
                        </Badge>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 pt-3">
                    <!-- Pemasukan Bulan Ini -->
                    <div class="p-2.5 sm:p-3 rounded-xl bg-emerald-500/5 border border-emerald-500/15 flex flex-col justify-between">
                        <span class="text-[10px] uppercase font-bold text-emerald-700 dark:text-emerald-400 block">Pemasukan Bulan Ini</span>
                        <span class="text-base sm:text-lg font-extrabold text-emerald-600 dark:text-emerald-400 block mt-0.5 tabular-nums">
                            +{{ formatRupiah(metrics.monthIncome) }}
                        </span>
                        <span class="text-[10px] text-muted-foreground block mt-0.5">Kas masuk terakumulasi</span>
                    </div>

                    <!-- Pengeluaran Bulan Ini -->
                    <div class="p-2.5 sm:p-3 rounded-xl bg-rose-500/5 border border-rose-500/15 flex flex-col justify-between">
                        <span class="text-[10px] uppercase font-bold text-rose-700 dark:text-rose-400 block">Pengeluaran Bulan Ini</span>
                        <span class="text-base sm:text-lg font-extrabold text-rose-600 dark:text-rose-400 block mt-0.5 tabular-nums">
                            -{{ formatRupiah(metrics.monthExpense) }}
                        </span>
                        <span class="text-[10px] text-muted-foreground block mt-0.5">Total realisasi belanja</span>
                    </div>

                    <!-- Surplus Bersih Bulan Ini -->
                    <div class="p-2.5 sm:p-3 rounded-xl border flex flex-col justify-between" :class="metrics.monthNet >= 0 ? 'bg-emerald-500/5 border-emerald-500/15' : 'bg-rose-500/5 border-rose-500/15'">
                        <span class="text-[10px] uppercase font-bold text-muted-foreground block">Selisih Kas Bersih</span>
                        <span class="text-base sm:text-lg font-extrabold block mt-0.5 tabular-nums" :class="metrics.monthNet >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                            {{ metrics.monthNet >= 0 ? '+' : '' }}{{ formatRupiah(metrics.monthNet) }}
                        </span>
                        <span class="text-[10px] text-muted-foreground block mt-0.5">
                            {{ metrics.monthNet >= 0 ? 'Sisa surplus kas' : 'Defisit dana berjalan' }}
                        </span>
                    </div>

                    <!-- Tingkat Simpanan (Savings Rate) -->
                    <div class="p-2.5 sm:p-3 rounded-xl bg-muted/30 border flex flex-col justify-between space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] uppercase font-bold text-muted-foreground">Rasio Simpanan</span>
                            <Badge variant="outline" class="text-[9px] px-1 py-0 h-4" :class="metrics.monthSavingsRate >= 20 ? 'text-emerald-600 border-emerald-500/20' : 'text-amber-600 border-amber-500/20'">
                                {{ metrics.monthSavingsRate >= 20 ? 'Ideal' : 'Ketat' }}
                            </Badge>
                        </div>
                        <div class="text-base sm:text-lg font-extrabold text-foreground tabular-nums">
                            {{ metrics.monthSavingsRate }}%
                        </div>
                        <div class="w-full bg-muted rounded-full h-1.5 overflow-hidden">
                            <div 
                                class="h-full bg-primary rounded-full transition-all duration-500"
                                :style="{ width: Math.max(0, Math.min(100, metrics.monthSavingsRate)) + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two-Column Main Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left 2 Cols: Budget Radar, Trend & Recent Transactions -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Budget Health & Burn-rate Card -->
                    <Card class="shadow-2xs">
                        <CardHeader class="flex flex-row items-center justify-between pb-3 border-b">
                            <div>
                                <CardTitle class="text-base font-bold">Radar Kesehatan Anggaran</CardTitle>
                                <CardDescription class="text-xs mt-0.5">
                                    Pantauan laju pengeluaran bulan ini (Burn Rate)
                                </CardDescription>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge 
                                    :variant="getStatusBadge(budgetRadar.status).variant"
                                    :class="getStatusBadge(budgetRadar.status).class"
                                    class="text-xs font-semibold"
                                >
                                    {{ getStatusBadge(budgetRadar.status).label }}
                                </Badge>
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    class="text-xs h-7 px-2"
                                    @click="isBudgetEditing = !isBudgetEditing"
                                >
                                    {{ isBudgetEditing ? 'Tutup' : 'Ubah Target' }}
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-4 space-y-4">
                            <!-- Quick Budget Editor -->
                            <div v-if="isBudgetEditing" class="p-3 bg-muted/50 rounded-xl border flex flex-col sm:flex-row items-center gap-3">
                                <div class="flex-1 w-full space-y-1">
                                    <span class="text-[11px] font-semibold text-muted-foreground block">Target Batas Anggaran Bulanan (IDR)</span>
                                    <Input 
                                        v-model="budgetForm.monthly_budget" 
                                        type="number" 
                                        min="0"
                                        placeholder="Contoh: 5000000" 
                                        class="h-8 text-xs font-bold"
                                        :class="{ 'border-destructive': budgetForm.errors.monthly_budget }"
                                    />
                                    <span v-if="budgetForm.errors.monthly_budget" class="text-[11px] text-destructive block">
                                        {{ budgetForm.errors.monthly_budget }}
                                    </span>
                                </div>
                                <Button 
                                    size="sm" 
                                    class="text-xs font-bold shrink-0 self-end sm:self-auto"
                                    :disabled="budgetForm.processing"
                                    @click="saveBudget"
                                >
                                    Simpan Target
                                </Button>
                            </div>

                            <!-- Progress Bar with Marker Hari Ini -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-xs font-medium">
                                    <span class="text-muted-foreground">
                                        Terpakai: <strong class="text-foreground">{{ formatRupiah(budgetRadar.spent) }}</strong>
                                    </span>
                                    <span class="font-bold flex items-center gap-1.5">
                                        <span>Target: {{ formatRupiah(budgetRadar.target_budget) }} ({{ budgetRadar.used_percent }}%)</span>
                                        <Badge variant="outline" class="text-[9px] px-1 py-0 h-4 text-muted-foreground">
                                            {{ budgetRadar.is_custom ? 'Kustom' : 'Otomatis' }}
                                        </Badge>
                                    </span>
                                </div>
                                <div class="relative w-full bg-muted rounded-full h-3 overflow-visible my-1">
                                    <div 
                                        class="h-full rounded-full transition-all duration-500 overflow-hidden"
                                        :class="budgetRadar.used_percent > 85 ? 'bg-rose-500' : (budgetRadar.used_percent > 65 ? 'bg-amber-500' : 'bg-primary')"
                                        :style="{ width: Math.min(100, budgetRadar.used_percent) + '%' }"
                                    ></div>
                                    <!-- Marker for Today (Elapsed Days in Month) -->
                                    <div 
                                        class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-3.5 h-3.5 rounded-full bg-foreground border-2 border-background shadow-xs z-10"
                                        :style="{ left: Math.min(100, Math.max(0, budgetRadar.elapsed_percent)) + '%' }"
                                        :title="'Posisi hari ini: hari ke-' + budgetRadar.elapsed_days + ' (' + budgetRadar.elapsed_percent + '%)'"
                                    ></div>
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>Hari ke-{{ budgetRadar.elapsed_days }} dari {{ budgetRadar.total_days }} hari ({{ budgetRadar.elapsed_percent }}%)</span>
                                    <span class="font-medium" :class="budgetRadar.burn_rate_ratio > 1.2 ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-emerald-600 dark:text-emerald-400'">
                                        {{ budgetRadar.burn_rate_ratio > 1.25 ? '⚠️ Laju belanja lebih cepat dari tempo hari' : '✓ Laju pengeluaran terkendali' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Budget Stats Subgrid -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-1">
                                <div class="p-3 rounded-xl bg-muted/40 border">
                                    <span class="text-[10px] text-muted-foreground uppercase font-bold block">Sisa Anggaran</span>
                                    <span class="text-sm font-extrabold text-foreground block mt-0.5">
                                        {{ formatRupiah(budgetRadar.remaining) }}
                                    </span>
                                </div>
                                <div class="p-3 rounded-xl bg-muted/40 border">
                                    <span class="text-[10px] text-muted-foreground uppercase font-bold block">Alokasi Harian</span>
                                    <span class="text-sm font-extrabold text-foreground block mt-0.5">
                                        {{ formatRupiah(budgetRadar.daily_allowance) }}/hari
                                    </span>
                                </div>
                                <div class="p-3 rounded-xl bg-muted/40 border">
                                    <span class="text-[10px] text-muted-foreground uppercase font-bold block">Hari Tersisa</span>
                                    <span class="text-sm font-extrabold text-foreground block mt-0.5">
                                        {{ budgetRadar.remaining_days }} hari
                                    </span>
                                </div>
                                <div class="p-3 rounded-xl bg-muted/40 border">
                                    <span class="text-[10px] text-muted-foreground uppercase font-bold block">Pos Terbesar</span>
                                    <span class="text-sm font-extrabold text-foreground block mt-0.5 truncate" :title="budgetRadar.top_category?.name || '-'">
                                        {{ budgetRadar.top_category?.name || '-' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Top Spending Categories Breakdown -->
                            <div v-if="budgetRadar.top_categories && budgetRadar.top_categories.length > 0" class="pt-2 border-t">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground block mb-2">
                                    Distribusi Belanja Terbesar Bulan Ini
                                </span>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    <div 
                                        v-for="cat in budgetRadar.top_categories" 
                                        :key="cat.name"
                                        class="p-2 rounded-lg bg-muted/30 border text-xs flex flex-col justify-between"
                                    >
                                        <div class="flex items-center justify-between gap-1 text-[11px]">
                                            <span class="font-bold truncate text-foreground">{{ cat.name }}</span>
                                            <span class="text-[10px] text-muted-foreground font-semibold">{{ cat.percent }}%</span>
                                        </div>
                                        <span class="font-extrabold text-rose-600 dark:text-rose-400 text-xs mt-1">
                                            {{ formatRupiah(cat.amount) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- 6-Month Cashflow Trend & Deep Analytics Card -->
                    <Card class="shadow-2xs">
                        <CardHeader class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b">
                            <div>
                                <div class="flex items-center gap-2">
                                    <CardTitle class="text-base font-bold">Tren Arus Kas 6 Bulan Terakhir</CardTitle>
                                    <Badge variant="outline" class="text-[10px] font-semibold text-emerald-600 bg-emerald-500/10 border-emerald-500/20">
                                        {{ summary.surplus_months_count }} / {{ monthlyTrend.length }} Bulan Surplus
                                    </Badge>
                                </div>
                                <CardDescription class="text-xs mt-0.5">
                                    Pantauan arus kas masuk, keluar, rasio tabungan, dan pertumbuhan bulanan
                                </CardDescription>
                            </div>

                            <!-- Header Controls: Legend & View Switcher -->
                            <div class="flex items-center flex-wrap gap-2 sm:gap-3">
                                <div class="hidden md:flex items-center gap-3 text-[11px] font-semibold text-muted-foreground mr-1">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-sm bg-emerald-500"></span>
                                        <span>Pemasukan</span>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-sm bg-rose-500"></span>
                                        <span>Pengeluaran</span>
                                    </span>
                                </div>

                                <!-- View Mode Toggle (Chart vs Table) -->
                                <div class="flex items-center p-0.5 bg-muted rounded-lg border">
                                    <button
                                        type="button"
                                        @click="trendViewMode = 'chart'"
                                        class="flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer"
                                        :class="trendViewMode === 'chart' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                    >
                                        <BarChart3 class="w-3.5 h-3.5" />
                                        <span>Grafik</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="trendViewMode = 'table'"
                                        class="flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer"
                                        :class="trendViewMode === 'table' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                    >
                                        <TableIcon class="w-3.5 h-3.5" />
                                        <span>Tabel Rincian</span>
                                    </button>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent class="pt-4 space-y-4">
                            <!-- 1. 6-Month Summary KPI Cards -->
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
                                <!-- Total Akumulasi Arus Kas -->
                                <div class="p-3 rounded-xl bg-muted/30 border space-y-1">
                                    <div class="flex items-center justify-between text-muted-foreground">
                                        <span class="text-[10px] uppercase font-bold tracking-wider">Akumulasi Kas (6 Bln)</span>
                                        <TrendingUp v-if="summary.total_net >= 0" class="w-3.5 h-3.5 text-emerald-500" />
                                        <TrendingDown v-else class="w-3.5 h-3.5 text-rose-500" />
                                    </div>
                                    <div class="text-sm sm:text-base font-extrabold truncate" :class="summary.total_net >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                        {{ summary.total_net >= 0 ? '+' : '' }}{{ formatRupiah(summary.total_net) }}
                                    </div>
                                    <span class="text-[10px] text-muted-foreground block truncate">
                                        {{ summary.total_net >= 0 ? 'Total surplus tersimpan' : 'Defisit kas akumulatif' }}
                                    </span>
                                </div>

                                <!-- Rata-rata Bersih Bulanan -->
                                <div class="p-3 rounded-xl bg-muted/30 border space-y-1">
                                    <div class="flex items-center justify-between text-muted-foreground">
                                        <span class="text-[10px] uppercase font-bold tracking-wider">Rata-rata Simpanan</span>
                                        <Wallet class="w-3.5 h-3.5 text-primary" />
                                    </div>
                                    <div class="text-sm sm:text-base font-extrabold truncate text-foreground">
                                        {{ summary.avg_monthly_net >= 0 ? '+' : '' }}{{ formatRupiah(summary.avg_monthly_net) }}
                                        <span class="text-[10px] font-normal text-muted-foreground">/bln</span>
                                    </div>
                                    <span class="text-[10px] text-muted-foreground block truncate">
                                        Rata-rata surplus per bulan
                                    </span>
                                </div>

                                <!-- Rasio Tabungan Rata-rata -->
                                <div class="p-3 rounded-xl bg-muted/30 border space-y-1">
                                    <div class="flex items-center justify-between text-muted-foreground">
                                        <span class="text-[10px] uppercase font-bold tracking-wider">Rasio Tabungan</span>
                                        <Activity class="w-3.5 h-3.5 text-sky-500" />
                                    </div>
                                    <div class="text-sm sm:text-base font-extrabold truncate text-foreground flex items-center gap-1.5">
                                        <span>{{ summary.avg_savings_rate }}%</span>
                                        <Badge :variant="summary.avg_savings_rate >= 20 ? 'secondary' : 'outline'" class="text-[9px] px-1.5 py-0 h-4" :class="summary.avg_savings_rate >= 20 ? 'bg-emerald-500/10 text-emerald-600' : 'text-muted-foreground'">
                                            {{ summary.avg_savings_rate >= 20 ? 'Ideal' : 'Ketat' }}
                                        </Badge>
                                    </div>
                                    <span class="text-[10px] text-muted-foreground block truncate">
                                        % pemasukan yang disisihkan
                                    </span>
                                </div>

                                <!-- Puncak Arus Kas (Bulan Terbaik) -->
                                <div class="p-3 rounded-xl bg-muted/30 border space-y-1">
                                    <div class="flex items-center justify-between text-muted-foreground">
                                        <span class="text-[10px] uppercase font-bold tracking-wider">Puncak Surplus</span>
                                        <Sparkles class="w-3.5 h-3.5 text-amber-500" />
                                    </div>
                                    <div class="text-sm sm:text-base font-extrabold truncate text-foreground">
                                        {{ summary.best_month?.month_name || summary.best_month?.month || '-' }}
                                    </div>
                                    <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold block truncate" v-if="summary.best_month">
                                        +{{ formatRupiah(summary.best_month.net) }}
                                    </span>
                                    <span class="text-[10px] text-muted-foreground block truncate" v-else>
                                        Belum ada data
                                    </span>
                                </div>
                            </div>

                            <!-- 2. Interactive Visual Chart View -->
                            <div v-if="trendViewMode === 'chart'" class="space-y-4">
                                <div class="p-3 sm:p-4 rounded-2xl bg-muted/15 border relative">
                                    <!-- Mobile / Sub Legend -->
                                    <div class="flex items-center justify-between text-xs text-muted-foreground mb-3 pb-2 border-b">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                                Klik kolom bulan untuk rincian
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 text-[10px] font-medium">
                                            <span class="text-muted-foreground">Skala Maks:</span>
                                            <span class="font-bold text-foreground">{{ formatRupiah(maxTrendValue) }}</span>
                                        </div>
                                    </div>

                                    <!-- Chart Grid -->
                                    <div class="grid grid-cols-6 gap-1 sm:gap-3 text-center">
                                        <div
                                            v-for="(item, idx) in monthlyTrend"
                                            :key="item.period_key || item.month"
                                            @click="selectedMonthIndex = idx"
                                            class="flex flex-col items-center justify-between p-1 sm:p-2.5 rounded-xl border transition-all cursor-pointer group relative select-none"
                                            :class="[
                                                selectedMonthIndex === idx 
                                                    ? 'bg-background shadow-sm border-primary ring-2 ring-primary/20' 
                                                    : 'bg-muted/30 hover:bg-muted/60 border-transparent hover:border-border'
                                            ]"
                                        >
                                            <!-- Top Status / Net Badge -->
                                            <div class="w-full text-center">
                                                <span 
                                                    class="inline-block text-[9px] sm:text-[10px] font-extrabold px-1 sm:px-1.5 py-0.5 rounded leading-none truncate max-w-full"
                                                    :class="item.net >= 0 ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400'"
                                                >
                                                    {{ item.net >= 0 ? '+' : '' }}{{ formatCompact(item.net) }}
                                                </span>
                                            </div>

                                            <!-- Bars Area -->
                                            <div class="flex items-end gap-1 sm:gap-2 h-36 sm:h-44 w-full justify-center my-2">
                                                <!-- Income Bar Column -->
                                                <div class="flex flex-col items-center h-full justify-end w-3 sm:w-5">
                                                    <div 
                                                        class="w-full bg-emerald-500 hover:bg-emerald-400 rounded-t-md transition-all duration-500 relative group/bar shadow-2xs"
                                                        :style="{ height: Math.max(6, Math.round((item.income / maxTrendValue) * 144)) + 'px' }"
                                                    >
                                                        <!-- Tooltip -->
                                                        <div class="opacity-0 group-hover/bar:opacity-100 pointer-events-none absolute bottom-full mb-1 left-1/2 -translate-x-1/2 z-20 bg-popover text-popover-foreground text-[10px] font-bold px-2 py-1 rounded shadow-md whitespace-nowrap transition-opacity">
                                                            Masuk: {{ formatRupiah(item.income) }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Expense Bar Column -->
                                                <div class="flex flex-col items-center h-full justify-end w-3 sm:w-5">
                                                    <div 
                                                        class="w-full bg-rose-500 hover:bg-rose-400 rounded-t-md transition-all duration-500 relative group/bar shadow-2xs"
                                                        :style="{ height: Math.max(6, Math.round((item.expense / maxTrendValue) * 144)) + 'px' }"
                                                    >
                                                        <!-- Tooltip -->
                                                        <div class="opacity-0 group-hover/bar:opacity-100 pointer-events-none absolute bottom-full mb-1 left-1/2 -translate-x-1/2 z-20 bg-popover text-popover-foreground text-[10px] font-bold px-2 py-1 rounded shadow-md whitespace-nowrap transition-opacity">
                                                            Keluar: {{ formatRupiah(item.expense) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Month Label & Indicator -->
                                            <div class="w-full pt-1 border-t border-muted/50">
                                                <div class="flex items-center justify-center gap-1">
                                                    <span 
                                                        class="text-[10px] sm:text-xs font-bold block truncate"
                                                        :class="selectedMonthIndex === idx ? 'text-primary' : 'text-foreground'"
                                                    >
                                                        {{ item.month_short || item.month.split(' ')[0] }}
                                                    </span>
                                                    <span 
                                                        v-if="item.is_current_month" 
                                                        class="w-1.5 h-1.5 rounded-full bg-primary shrink-0" 
                                                        title="Bulan Berjalan"
                                                    ></span>
                                                </div>
                                                <span class="text-[9px] text-muted-foreground block truncate">
                                                    {{ item.savings_rate > 0 ? item.savings_rate + '%' : '0%' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Selected Month Interactive Inspector -->
                                <div v-if="selectedMonth" class="p-4 rounded-xl bg-card border shadow-2xs space-y-3">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2.5 border-b">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Detail Periode:</span>
                                            <span class="text-sm font-extrabold text-foreground">{{ selectedMonth.month_name || selectedMonth.month }}</span>
                                            <Badge v-if="selectedMonth.is_current_month" variant="secondary" class="text-[10px] bg-primary/10 text-primary font-semibold">
                                                Bulan Ini
                                            </Badge>
                                            <Badge 
                                                variant="outline" 
                                                class="text-[10px] font-semibold"
                                                :class="selectedMonth.net >= 0 ? 'text-emerald-600 bg-emerald-500/10 border-emerald-500/20' : 'text-rose-600 bg-rose-500/10 border-rose-500/20'"
                                            >
                                                {{ selectedMonth.net >= 0 ? 'Surplus Kas' : 'Defisit Kas' }}
                                            </Badge>
                                        </div>

                                        <Link 
                                            :href="route('keuangan.transactions.index', { date_from: selectedMonth.start_date, date_to: selectedMonth.end_date })"
                                            class="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:underline self-start sm:self-auto cursor-pointer"
                                        >
                                            <span>Lihat {{ selectedMonth.total_tx_count || 0 }} Transaksi Bulan Ini</span>
                                            <ArrowRight class="w-3.5 h-3.5" />
                                        </Link>
                                    </div>

                                    <!-- 4 Key Numbers for Selected Month -->
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                        <!-- Income -->
                                        <div class="p-2.5 rounded-lg bg-emerald-500/5 border border-emerald-500/15">
                                            <span class="text-[10px] font-bold uppercase text-emerald-700 dark:text-emerald-400 block">Total Pemasukan</span>
                                            <span class="text-sm sm:text-base font-extrabold text-emerald-600 dark:text-emerald-400 block mt-0.5">
                                                {{ formatRupiah(selectedMonth.income) }}
                                            </span>
                                            <span class="text-[10px] text-muted-foreground block mt-0.5">
                                                {{ selectedMonth.income_count || 0 }} transaksi masuk
                                            </span>
                                        </div>

                                        <!-- Expense -->
                                        <div class="p-2.5 rounded-lg bg-rose-500/5 border border-rose-500/15">
                                            <span class="text-[10px] font-bold uppercase text-rose-700 dark:text-rose-400 block">Total Pengeluaran</span>
                                            <span class="text-sm sm:text-base font-extrabold text-rose-600 dark:text-rose-400 block mt-0.5">
                                                {{ formatRupiah(selectedMonth.expense) }}
                                            </span>
                                            <span class="text-[10px] text-muted-foreground block mt-0.5">
                                                {{ selectedMonth.expense_count || 0 }} transaksi keluar
                                            </span>
                                        </div>

                                        <!-- Net Balance -->
                                        <div class="p-2.5 rounded-lg border" :class="selectedMonth.net >= 0 ? 'bg-emerald-500/5 border-emerald-500/15' : 'bg-rose-500/5 border-rose-500/15'">
                                            <span class="text-[10px] font-bold uppercase block text-muted-foreground">Arus Kas Bersih</span>
                                            <span class="text-sm sm:text-base font-extrabold block mt-0.5" :class="selectedMonth.net >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                                {{ selectedMonth.net >= 0 ? '+' : '' }}{{ formatRupiah(selectedMonth.net) }}
                                            </span>
                                            <span class="text-[10px] text-muted-foreground block mt-0.5">
                                                {{ selectedMonth.net >= 0 ? 'Surplus tersisa' : 'Defisit belanja' }}
                                            </span>
                                        </div>

                                        <!-- Savings Rate & MoM -->
                                        <div class="p-2.5 rounded-lg bg-muted/40 border">
                                            <span class="text-[10px] font-bold uppercase text-muted-foreground block">Rasio Tabungan</span>
                                            <span class="text-sm sm:text-base font-extrabold text-foreground block mt-0.5">
                                                {{ selectedMonth.savings_rate }}%
                                            </span>
                                            <span class="text-[10px] text-muted-foreground block mt-0.5 truncate">
                                                <template v-if="selectedMonth.mom_growth !== null">
                                                    {{ selectedMonth.mom_growth >= 0 ? '▲ +' : '▼ ' }}{{ selectedMonth.mom_growth }}% vs lalu
                                                </template>
                                                <template v-else>
                                                    Awal periode 6 bln
                                                </template>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Bottom Insight Strip: Progress comparison & Top Category -->
                                    <div class="pt-2 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
                                        <!-- Visual proportion bar -->
                                        <div class="flex-1 space-y-1">
                                            <div class="flex items-center justify-between text-[11px] font-medium text-muted-foreground">
                                                <span>Proporsi Arus Kas:</span>
                                                <span>
                                                    {{ (selectedMonth.income + selectedMonth.expense > 0)
                                                        ? Math.round((selectedMonth.income / (selectedMonth.income + selectedMonth.expense)) * 100) + '% Masuk / ' + Math.round((selectedMonth.expense / (selectedMonth.income + selectedMonth.expense)) * 100) + '% Keluar' 
                                                        : '0%' 
                                                    }}
                                                </span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-muted overflow-hidden flex">
                                                <div 
                                                    class="h-full bg-emerald-500 transition-all duration-500" 
                                                    :style="{ width: ((selectedMonth.income + selectedMonth.expense > 0) ? (selectedMonth.income / (selectedMonth.income + selectedMonth.expense)) * 100 : 50) + '%' }"
                                                ></div>
                                                <div 
                                                    class="h-full bg-rose-500 transition-all duration-500" 
                                                    :style="{ width: ((selectedMonth.income + selectedMonth.expense > 0) ? (selectedMonth.expense / (selectedMonth.income + selectedMonth.expense)) * 100 : 50) + '%' }"
                                                ></div>
                                            </div>
                                        </div>

                                        <!-- Top category in selected month -->
                                        <div v-if="selectedMonth.top_expense_category" class="md:text-right shrink-0">
                                            <span class="text-[10px] text-muted-foreground block">Pos Belanja Terbesar:</span>
                                            <span class="font-extrabold text-foreground">
                                                {{ selectedMonth.top_expense_category.name }}
                                                <span class="text-rose-600 dark:text-rose-400 font-semibold text-[11px]">
                                                    ({{ formatRupiah(selectedMonth.top_expense_category.amount) }})
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Detailed Monthly Table View -->
                            <div v-else class="overflow-x-auto rounded-xl border">
                                <table class="w-full text-xs text-left">
                                    <thead class="bg-muted/50 border-b text-muted-foreground font-bold uppercase tracking-wider text-[10px]">
                                        <tr>
                                            <th class="py-2.5 px-3">Bulan</th>
                                            <th class="py-2.5 px-3">Pemasukan</th>
                                            <th class="py-2.5 px-3">Pengeluaran</th>
                                            <th class="py-2.5 px-3">Arus Kas Bersih</th>
                                            <th class="py-2.5 px-3">Rasio Tabungan</th>
                                            <th class="py-2.5 px-3">Pertumbuhan (MoM)</th>
                                            <th class="py-2.5 px-3 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr 
                                            v-for="item in [...monthlyTrend].reverse()" 
                                            :key="item.period_key || item.month"
                                            class="hover:bg-muted/20 transition-colors"
                                        >
                                            <td class="py-3 px-3 font-bold text-foreground whitespace-nowrap">
                                                <div class="flex items-center gap-1.5">
                                                    <span>{{ item.month_name || item.month }}</span>
                                                    <Badge v-if="item.is_current_month" variant="secondary" class="text-[9px] px-1 py-0 h-4 bg-primary/10 text-primary">
                                                        Bulan Ini
                                                    </Badge>
                                                </div>
                                            </td>
                                            <td class="py-3 px-3 text-emerald-600 dark:text-emerald-400 font-extrabold whitespace-nowrap">
                                                {{ formatRupiah(item.income) }}
                                                <span class="text-[10px] text-muted-foreground font-normal block">{{ item.income_count || 0 }} tx</span>
                                            </td>
                                            <td class="py-3 px-3 text-rose-600 dark:text-rose-400 font-extrabold whitespace-nowrap">
                                                {{ formatRupiah(item.expense) }}
                                                <span class="text-[10px] text-muted-foreground font-normal block">{{ item.expense_count || 0 }} tx</span>
                                            </td>
                                            <td class="py-3 px-3 whitespace-nowrap">
                                                <Badge 
                                                    variant="outline" 
                                                    class="font-extrabold text-xs"
                                                    :class="item.net >= 0 ? 'text-emerald-600 bg-emerald-500/10 border-emerald-500/20' : 'text-rose-600 bg-rose-500/10 border-rose-500/20'"
                                                >
                                                    {{ item.net >= 0 ? '+' : '' }}{{ formatRupiah(item.net) }}
                                                </Badge>
                                            </td>
                                            <td class="py-3 px-3 whitespace-nowrap">
                                                <span class="font-extrabold text-foreground">{{ item.savings_rate }}%</span>
                                            </td>
                                            <td class="py-3 px-3 whitespace-nowrap text-muted-foreground">
                                                <template v-if="item.mom_growth !== null">
                                                    <span 
                                                        class="font-bold inline-flex items-center gap-0.5"
                                                        :class="item.mom_growth >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                                                    >
                                                        {{ item.mom_growth >= 0 ? '▲ +' : '▼ ' }}{{ item.mom_growth }}%
                                                    </span>
                                                </template>
                                                <template v-else>
                                                    <span>-</span>
                                                </template>
                                            </td>
                                            <td class="py-3 px-3 text-right whitespace-nowrap">
                                                <Link 
                                                    :href="route('keuangan.transactions.index', { date_from: item.start_date, date_to: item.end_date })"
                                                    class="inline-flex items-center gap-1 text-[11px] font-bold text-primary hover:underline cursor-pointer"
                                                >
                                                    <span>Lihat Transaksi</span>
                                                    <ArrowRight class="w-3 h-3" />
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-muted/40 border-t font-bold text-foreground">
                                        <tr>
                                            <td class="py-2.5 px-3">Total 6 Bulan</td>
                                            <td class="py-2.5 px-3 text-emerald-600 dark:text-emerald-400 font-extrabold">
                                                {{ formatRupiah(summary.total_income) }}
                                            </td>
                                            <td class="py-2.5 px-3 text-rose-600 dark:text-rose-400 font-extrabold">
                                                {{ formatRupiah(summary.total_expense) }}
                                            </td>
                                            <td class="py-2.5 px-3 font-extrabold">
                                                <span :class="summary.total_net >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                                    {{ summary.total_net >= 0 ? '+' : '' }}{{ formatRupiah(summary.total_net) }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 px-3 font-extrabold">
                                                Rata-rata {{ summary.avg_savings_rate }}%
                                            </td>
                                            <td colspan="2" class="py-2.5 px-3 text-muted-foreground text-[11px]">
                                                Rata-rata Surplus: {{ formatRupiah(summary.avg_monthly_net) }}/bln
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Recent Transactions List -->
                    <Card class="shadow-2xs">
                        <CardHeader class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b">
                            <div>
                                <CardTitle class="text-base font-bold">Transaksi & Mutasi Terkini</CardTitle>
                                <CardDescription class="text-xs mt-0.5">
                                    Riwayat 8 transaksi kas terakhir
                                </CardDescription>
                            </div>

                            <div class="flex items-center gap-2 flex-wrap">
                                <!-- Filter Pills -->
                                <div class="flex items-center p-0.5 bg-muted rounded-lg border text-[11px]">
                                    <button 
                                        type="button" 
                                        @click="recentTxFilter = 'all'"
                                        class="px-2 py-0.5 font-bold rounded-md transition-all cursor-pointer"
                                        :class="recentTxFilter === 'all' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                    >
                                        Semua
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="recentTxFilter = 'expense'"
                                        class="px-2 py-0.5 font-bold rounded-md transition-all cursor-pointer"
                                        :class="recentTxFilter === 'expense' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                    >
                                        Keluar
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="recentTxFilter = 'income'"
                                        class="px-2 py-0.5 font-bold rounded-md transition-all cursor-pointer"
                                        :class="recentTxFilter === 'income' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                    >
                                        Masuk
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="recentTxFilter = 'transfer'"
                                        class="px-2 py-0.5 font-bold rounded-md transition-all cursor-pointer"
                                        :class="recentTxFilter === 'transfer' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                    >
                                        Transfer
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="recentTxFilter = 'debt'"
                                        class="px-2 py-0.5 font-bold rounded-md transition-all cursor-pointer"
                                        :class="recentTxFilter === 'debt' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                    >
                                        Utang
                                    </button>
                                </div>

                                <Link :href="route('keuangan.transactions.index')">
                                    <Button variant="ghost" size="sm" class="gap-1 text-xs font-semibold h-7 px-2">
                                        <span>Semua</span>
                                        <ArrowRight class="h-3.5 w-3.5" />
                                    </Button>
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-2 px-0 pb-0">
                            <div v-if="filteredRecentTransactions.length > 0" class="divide-y">
                                <div 
                                    v-for="tx in filteredRecentTransactions" 
                                    :key="tx.id"
                                    class="p-4 flex items-center justify-between hover:bg-muted/30 transition-colors"
                                >
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div 
                                            class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 text-xs font-bold"
                                            :class="{
                                                'bg-emerald-500/10 text-emerald-600': tx.type === 'income',
                                                'bg-rose-500/10 text-rose-600': tx.type === 'expense',
                                                'bg-sky-500/10 text-sky-600': tx.type === 'transfer',
                                                'bg-amber-500/10 text-amber-600': tx.type === 'debt_payable',
                                                'bg-indigo-500/10 text-indigo-600': tx.type === 'debt_receivable'
                                            }"
                                        >
                                            <ArrowUpRight v-if="tx.type === 'income'" class="w-4 h-4" />
                                            <ArrowDownRight v-else-if="tx.type === 'expense'" class="w-4 h-4" />
                                            <Scale v-else-if="tx.type === 'debt_payable' || tx.type === 'debt_receivable'" class="w-4 h-4" />
                                            <ArrowRight v-else class="w-4 h-4" />
                                        </div>
                                        <div class="truncate">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold truncate">
                                                    {{ tx.description || tx.category || 'Transaksi Kas' }}
                                                </span>
                                                <Badge v-if="tx.category" variant="outline" class="text-[9px] px-1 py-0 h-4 text-muted-foreground shrink-0 hidden sm:inline-flex">
                                                    {{ tx.category }}
                                                </Badge>
                                            </div>
                                            <span class="text-[11px] text-muted-foreground flex items-center gap-1.5 flex-wrap">
                                                <span>{{ tx.wallet?.name || 'Dompet' }}</span>
                                                <span v-if="tx.target_wallet">→ {{ tx.target_wallet.name }}</span>
                                                <span v-if="tx.wallet?.user?.username && tx.wallet.user.username !== $page.props.auth?.user?.username" class="text-[10px] text-primary font-bold">
                                                    (Milik @{{ tx.wallet.user.username }})
                                                </span>
                                                <span>•</span>
                                                <span class="font-medium text-foreground">{{ formatRelativeDate(tx.transaction_date) }}</span>
                                                <span>•</span>
                                                <span class="text-[10px]">
                                                    Oleh: <strong :class="tx.user_id === $page.props.auth?.user?.id ? 'text-primary' : 'text-foreground'">@{{ tx.user?.username || tx.user?.name || 'user' }}{{ tx.user_id === $page.props.auth?.user?.id ? ' (Anda)' : '' }}</strong>
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0 ml-3">
                                        <span 
                                            class="text-xs font-extrabold block tabular-nums"
                                            :class="tx.type === 'income' ? 'text-emerald-600 dark:text-emerald-400' : (tx.type === 'expense' ? 'text-rose-600 dark:text-rose-400' : 'text-foreground')"
                                        >
                                            {{ tx.type === 'income' ? '+' : (tx.type === 'expense' ? '-' : '') }}{{ formatRupiah(tx.amount) }}
                                        </span>
                                        <Badge variant="outline" :class="getTypeMeta(tx.type).class" class="text-[9px] uppercase font-bold mt-0.5">
                                            {{ getTypeMeta(tx.type).label }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="p-8 text-center text-xs text-muted-foreground space-y-2.5">
                                <p v-if="recentTxFilter !== 'all'">Tidak ada transaksi untuk filter kategori ini.</p>
                                <p v-else>Belum ada transaksi tercatat. Mulai dengan mencatat transaksi pertama Anda.</p>
                                <Button size="sm" variant="outline" class="text-xs gap-1.5 font-bold" @click="openTransactionModal('expense')">
                                    <Plus class="w-3.5 h-3.5" />
                                    <span>Catat Transaksi Pertama</span>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Col: Wallets Glance & Radar Utang Piutang -->
                <div class="space-y-6">
                    <!-- Dompet Saya Glance -->
                    <Card class="shadow-2xs">
                        <CardHeader class="flex flex-row items-center justify-between pb-3 border-b">
                            <div>
                                <div class="flex items-center gap-2">
                                    <CardTitle class="text-base font-bold">Dompet Saya</CardTitle>
                                    <Badge variant="outline" class="text-[10px] font-bold">
                                        {{ wallets.length }} Akun
                                    </Badge>
                                </div>
                                <CardDescription class="text-xs mt-0.5">
                                    Total Saldo: <strong class="text-foreground">{{ formatRupiah(metrics.totalWalletBalance) }}</strong>
                                </CardDescription>
                            </div>
                            <Link :href="route('keuangan.wallets.index')">
                                <Button variant="ghost" size="sm" class="gap-1 text-xs font-semibold h-7 px-2">
                                    <span>Kelola</span>
                                    <ArrowRight class="h-3.5 w-3.5" />
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent class="pt-3 px-4 pb-4 space-y-3">
                            <div v-if="wallets.length === 0" class="p-6 text-center text-xs text-muted-foreground space-y-2.5">
                                <p>Belum ada dompet kas atau rekening terdaftar.</p>
                                <Link :href="route('keuangan.wallets.index')">
                                    <Button size="sm" class="text-xs gap-1.5 font-bold">
                                        <Plus class="h-3.5 w-3.5" />
                                        <span>Tambah Dompet Baru</span>
                                    </Button>
                                </Link>
                            </div>
                            <template v-else>
                                <Link 
                                    v-for="w in wallets" 
                                    :key="w.id"
                                    :href="route('keuangan.transactions.index', { wallet_id: w.id })"
                                    class="p-3 rounded-xl border bg-card flex items-center justify-between hover:border-primary/60 hover:bg-muted/20 transition-all cursor-pointer group"
                                    :title="'Lihat mutasi ' + w.name"
                                >
                                    <div class="flex items-center gap-2.5 truncate">
                                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                            <Wallet class="w-4 h-4" />
                                        </div>
                                        <div class="truncate">
                                            <div class="flex items-center gap-1.5 truncate">
                                                <span class="text-xs font-bold truncate group-hover:text-primary transition-colors">{{ w.name }}</span>
                                                <Badge v-if="w.is_shared" variant="outline" class="text-[9px] px-1 py-0 h-4 text-primary border-primary/30">
                                                    @{{ w.owner_username }} ({{ w.role === 'edit' ? 'Editor' : 'Pelihat' }})
                                                </Badge>
                                            </div>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <Badge variant="outline" :class="getWalletCategoryBadge(w.category_label).class" class="text-[9px] px-1 py-0 h-3.5 font-bold">
                                                    {{ getWalletCategoryBadge(w.category_label).label }}
                                                </Badge>
                                                <span v-if="w.account_number" class="text-[10px] text-muted-foreground truncate">
                                                    No. {{ w.account_number }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0 ml-2">
                                        <span class="text-xs font-extrabold tabular-nums block text-foreground">
                                            {{ formatRupiah(w.current_balance) }}
                                        </span>
                                        <span v-if="metrics.totalWalletBalance > 0" class="text-[10px] text-muted-foreground block">
                                            {{ Math.round((w.current_balance / metrics.totalWalletBalance) * 100) }}%
                                        </span>
                                    </div>
                                </Link>
                            </template>
                        </CardContent>
                    </Card>

                    <!-- Radar Utang & Piutang Jatuh Tempo -->
                    <Card class="shadow-2xs">
                        <CardHeader class="flex flex-row items-center justify-between pb-3 border-b">
                            <div>
                                <div class="flex items-center gap-2">
                                    <CardTitle class="text-base font-bold">Radar Jatuh Tempo</CardTitle>
                                    <Badge 
                                        v-if="radar.receivables?.some(r => r.is_overdue) || radar.payables?.some(p => p.is_overdue)"
                                        variant="destructive"
                                        class="text-[9px] px-1.5 py-0 h-4 animate-pulse font-bold"
                                    >
                                        Ada Lewat Tempo!
                                    </Badge>
                                </div>
                                <CardDescription class="text-xs mt-0.5">
                                    Pantauan utang & piutang aktif terdekat
                                </CardDescription>
                            </div>
                            <Link :href="route('keuangan.debts.index')">
                                <Button variant="ghost" size="sm" class="gap-1 text-xs font-semibold h-7 px-2">
                                    <span>Lengkap</span>
                                    <ArrowRight class="h-3.5 w-3.5" />
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent class="pt-3 px-4 pb-4 space-y-3">
                            <!-- Tab Switcher: Semua / Piutang / Utang -->
                            <div class="flex items-center p-0.5 bg-muted rounded-lg border text-[11px]">
                                <button 
                                    type="button" 
                                    @click="debtRadarTab = 'all'"
                                    class="flex-1 py-1 font-bold rounded-md transition-all cursor-pointer text-center"
                                    :class="debtRadarTab === 'all' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                >
                                    Semua ({{ (radar.receivables?.length || 0) + (radar.payables?.length || 0) }})
                                </button>
                                <button 
                                    type="button" 
                                    @click="debtRadarTab = 'receivables'"
                                    class="flex-1 py-1 font-bold rounded-md transition-all cursor-pointer text-center"
                                    :class="debtRadarTab === 'receivables' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                >
                                    Piutang ({{ radar.receivables?.length || 0 }})
                                </button>
                                <button 
                                    type="button" 
                                    @click="debtRadarTab = 'payables'"
                                    class="flex-1 py-1 font-bold rounded-md transition-all cursor-pointer text-center"
                                    :class="debtRadarTab === 'payables' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                >
                                    Utang ({{ radar.payables?.length || 0 }})
                                </button>
                            </div>

                            <!-- Piutang Terdekat -->
                            <div v-if="debtRadarTab === 'all' || debtRadarTab === 'receivables'">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block">
                                        Piutang (Hak Tagih)
                                    </span>
                                    <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ formatRupiah(metrics.totalPiutang) }}
                                    </span>
                                </div>
                                <div v-if="radar.receivables && radar.receivables.length > 0" class="space-y-2">
                                    <div 
                                        v-for="r in radar.receivables" 
                                        :key="r.id"
                                        class="p-2.5 rounded-lg border bg-muted/20 flex items-center justify-between text-xs hover:border-indigo-500/40 transition-colors"
                                    >
                                        <div class="truncate">
                                            <span class="font-bold block truncate">{{ r.party_name || 'Debitur' }}</span>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <Badge variant="outline" :class="getDueDateBadge(r).class" class="text-[9px] px-1 py-0 h-3.5 font-bold">
                                                    {{ getDueDateBadge(r).label }}
                                                </Badge>
                                                <span class="text-[10px] text-muted-foreground">
                                                    {{ r.due_date_formatted || (r.due_date ? new Date(r.due_date).toLocaleDateString('id-ID') : '-') }}
                                                </span>
                                            </div>
                                        </div>
                                        <span class="font-bold text-indigo-600 dark:text-indigo-400 shrink-0 ml-2 tabular-nums">
                                            {{ formatRupiah(r.amount) }}
                                        </span>
                                    </div>
                                </div>
                                <div v-else class="text-[11px] text-muted-foreground italic py-1">
                                    Tidak ada piutang aktif mendekati tempo.
                                </div>
                            </div>

                            <!-- Utang Terdekat -->
                            <div v-if="debtRadarTab === 'all' || debtRadarTab === 'payables'" :class="{ 'pt-3.5 mt-3 border-t': debtRadarTab === 'all' }">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block">
                                        Utang (Kewajiban)
                                    </span>
                                    <span class="text-[10px] font-bold text-rose-600 dark:text-rose-400">
                                        {{ formatRupiah(metrics.totalUtang) }}
                                    </span>
                                </div>
                                <div v-if="radar.payables && radar.payables.length > 0" class="space-y-2">
                                    <div 
                                        v-for="p in radar.payables" 
                                        :key="p.id"
                                        class="p-2.5 rounded-lg border bg-muted/20 flex items-center justify-between text-xs hover:border-rose-500/40 transition-colors"
                                    >
                                        <div class="truncate">
                                            <span class="font-bold block truncate">{{ p.party_name || 'Kreditur' }}</span>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <Badge variant="outline" :class="getDueDateBadge(p).class" class="text-[9px] px-1 py-0 h-3.5 font-bold">
                                                    {{ getDueDateBadge(p).label }}
                                                </Badge>
                                                <span class="text-[10px] text-muted-foreground">
                                                    {{ p.due_date_formatted || (p.due_date ? new Date(p.due_date).toLocaleDateString('id-ID') : '-') }}
                                                </span>
                                            </div>
                                        </div>
                                        <span class="font-bold text-rose-600 dark:text-rose-400 shrink-0 ml-2 tabular-nums">
                                            {{ formatRupiah(p.amount) }}
                                        </span>
                                    </div>
                                </div>
                                <div v-else class="text-[11px] text-muted-foreground italic py-1">
                                    Tidak ada kewajiban utang aktif mendekati tempo.
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Transaction Modal Component -->
            <TransactionModal 
                :is-open="isModalOpen" 
                :wallets="wallets" 
                :initial-type="initialModalType"
                @close="isModalOpen = false" 
            />
        </div>
    </DashboardLayout>
</template>
