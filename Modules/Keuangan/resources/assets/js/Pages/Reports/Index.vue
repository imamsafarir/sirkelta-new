<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { 
    Download, 
    TrendingUp, 
    TrendingDown, 
    ArrowUpRight, 
    ArrowDownRight, 
    Wallet,
    PieChart,
    BarChart3,
    Calendar,
    Scale,
    Filter
} from 'lucide-vue-next';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
    expenseByCategory: {
        type: Object,
        default: () => ({}),
    },
    incomeByCategory: {
        type: Object,
        default: () => ({}),
    },
    wallets: {
        type: Array,
        default: () => [],
    },
    currentPeriod: {
        type: String,
        default: 'this_month',
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

const activeCategoryTab = ref('expense'); // 'expense' or 'income'
const customDateFrom = ref(props.filters?.date_from || '');
const customDateTo = ref(props.filters?.date_to || '');

const changePeriod = (period) => {
    if (period === 'custom') {
        router.get(
            route('keuangan.reports.index'),
            { 
                period, 
                date_from: customDateFrom.value || undefined, 
                date_to: customDateTo.value || undefined 
            },
            { preserveState: true, preserveScroll: true }
        );
    } else {
        router.get(
            route('keuangan.reports.index'),
            { period },
            { preserveState: true, preserveScroll: true }
        );
    }
};

const applyCustomDate = () => {
    changePeriod('custom');
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(val || 0);
};

const categoryEntries = computed(() => {
    const isExpense = activeCategoryTab.value === 'expense';
    const source = isExpense ? props.expenseByCategory : props.incomeByCategory;
    const total = isExpense
        ? (Number(props.summary.totalExpense) || 1)
        : (Number(props.summary.totalIncome) || 1);

    return Object.entries(source || {}).map(([name, amount]) => {
        const percent = Math.round((Number(amount) / total) * 100);
        return {
            name,
            amount: Number(amount),
            percent,
        };
    });
});

const exportUrl = computed(() => {
    return route('keuangan.reports.export', {
        period: props.currentPeriod,
        date_from: props.currentPeriod === 'custom' ? customDateFrom.value : undefined,
        date_to: props.currentPeriod === 'custom' ? customDateTo.value : undefined,
    });
});
</script>

<template>
    <DashboardLayout :user="user" active-module="keuangan" title="Laporan Arus Kas">
        <div class="space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Laporan Arus Kas</h1>
                    <p class="text-xs text-muted-foreground mt-0.5">Analisis arus kas masuk, pengeluaran, pos kategori, dan saldo rekening berjalan.</p>
                </div>
                <div class="flex items-center gap-2 self-stretch sm:self-auto justify-between sm:justify-end">
                    <a :href="exportUrl" target="_blank">
                        <Button class="gap-1.5 text-xs font-bold shadow-xs">
                            <Download class="h-4 w-4" />
                            <span>Unduh CSV</span>
                        </Button>
                    </a>
                </div>
            </div>

            <!-- Period Filter Switcher -->
            <div class="space-y-3">
                <div class="flex items-center gap-1.5 p-1 bg-muted rounded-xl w-fit flex-wrap">
                    <button
                        type="button"
                        @click="changePeriod('this_month')"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        :class="currentPeriod === 'this_month' ? 'bg-card text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                    >
                        Bulan Ini
                    </button>
                    <button
                        type="button"
                        @click="changePeriod('last_month')"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        :class="currentPeriod === 'last_month' ? 'bg-card text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                    >
                        Bulan Lalu
                    </button>
                    <button
                        type="button"
                        @click="changePeriod('this_year')"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        :class="currentPeriod === 'this_year' ? 'bg-card text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                    >
                        Tahun Ini
                    </button>
                    <button
                        type="button"
                        @click="changePeriod('all')"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        :class="currentPeriod === 'all' ? 'bg-card text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                    >
                        Semua Waktu
                    </button>
                    <button
                        type="button"
                        @click="changePeriod('custom')"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1"
                        :class="currentPeriod === 'custom' ? 'bg-card text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                    >
                        <Calendar class="w-3 h-3" />
                        <span>Rentang Khusus</span>
                    </button>
                </div>

                <!-- Custom Date Range Picker Form -->
                <div v-if="currentPeriod === 'custom'" class="p-3 bg-card border rounded-xl flex flex-wrap items-center gap-3 animate-in fade-in duration-200">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-muted-foreground">Dari:</span>
                        <Input
                            v-model="customDateFrom"
                            type="date"
                            class="h-8 w-36 text-xs"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-muted-foreground">Sampai:</span>
                        <Input
                            v-model="customDateTo"
                            type="date"
                            class="h-8 w-36 text-xs"
                        />
                    </div>
                    <Button 
                        size="sm" 
                        class="h-8 text-xs font-bold px-3 shadow-2xs"
                        @click="applyCustomDate"
                    >
                        Terapkan Rentang
                    </Button>
                </div>
            </div>

            <!-- Summary KPI Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Total Income -->
                <Card class="shadow-2xs border-l-4 border-l-emerald-500">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Total Pemasukan</span>
                            <ArrowUpRight class="h-4 w-4 text-emerald-500" />
                        </CardDescription>
                        <CardTitle class="text-2xl font-extrabold tracking-tight tabular-nums text-emerald-600 dark:text-emerald-400">
                            {{ formatRupiah(summary.totalIncome) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-[11px] text-muted-foreground pt-0">
                        Total akumulasi kas masuk periode ini
                    </CardContent>
                </Card>

                <!-- Total Expense -->
                <Card class="shadow-2xs border-l-4 border-l-rose-500">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Total Pengeluaran</span>
                            <ArrowDownRight class="h-4 w-4 text-rose-500" />
                        </CardDescription>
                        <CardTitle class="text-2xl font-extrabold tracking-tight tabular-nums text-rose-600 dark:text-rose-400">
                            {{ formatRupiah(summary.totalExpense) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-[11px] text-muted-foreground pt-0">
                        Total akumulasi kas keluar periode ini
                    </CardContent>
                </Card>

                <!-- Net Cash Flow -->
                <Card 
                    class="shadow-2xs border-l-4"
                    :class="summary.netFlow >= 0 ? 'border-l-primary' : 'border-l-amber-500'"
                >
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Arus Kas Bersih (Net Flow)</span>
                            <TrendingUp v-if="summary.netFlow >= 0" class="h-4 w-4 text-primary" />
                            <TrendingDown v-else class="h-4 w-4 text-amber-500" />
                        </CardDescription>
                        <CardTitle 
                            class="text-2xl font-extrabold tracking-tight tabular-nums"
                            :class="summary.netFlow >= 0 ? 'text-primary' : 'text-amber-600 dark:text-amber-400'"
                        >
                            {{ formatRupiah(summary.netFlow) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-[11px] text-muted-foreground pt-0">
                        Surplus / defisit kas dari aktivitas operasional
                    </CardContent>
                </Card>
            </div>

            <!-- Active Liabilities & Receivables Glance -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-3.5 bg-card rounded-2xl border shadow-2xs flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center shrink-0">
                            <ArrowUpRight class="w-4 h-4" />
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-muted-foreground block tracking-wider">Piutang Aktif (Hak Tagih)</span>
                            <span class="text-sm sm:text-base font-extrabold tabular-nums text-indigo-600 dark:text-indigo-400">
                                {{ formatRupiah(summary.totalPiutang) }}
                            </span>
                        </div>
                    </div>
                    <span class="text-[11px] text-muted-foreground hidden sm:block">Belum lunas</span>
                </div>

                <div class="p-3.5 bg-card rounded-2xl border shadow-2xs flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-rose-500/10 text-rose-600 flex items-center justify-center shrink-0">
                            <ArrowDownRight class="w-4 h-4" />
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-muted-foreground block tracking-wider">Utang Aktif (Kewajiban)</span>
                            <span class="text-sm sm:text-base font-extrabold tabular-nums text-rose-600 dark:text-rose-400">
                                {{ formatRupiah(summary.totalUtang) }}
                            </span>
                        </div>
                    </div>
                    <span class="text-[11px] text-muted-foreground hidden sm:block">Belum lunas</span>
                </div>
            </div>

            <!-- Two-Column Breakdown Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Breakdown per Kategori with Toggle -->
                <Card class="shadow-2xs">
                    <CardHeader class="pb-3 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <CardTitle class="text-base font-bold flex items-center gap-2">
                                <PieChart class="w-4 h-4 text-primary" />
                                <span>Distribusi Kategori</span>
                            </CardTitle>
                            <CardDescription class="text-xs mt-0.5">
                                {{ activeCategoryTab === 'expense' ? 'Alokasi pengeluaran berdasarkan pos belanja' : 'Alokasi sumber pemasukan kas' }}
                            </CardDescription>
                        </div>

                        <!-- Segmented Switcher for Expense / Income Category -->
                        <div class="flex items-center gap-1 p-0.5 bg-muted rounded-lg w-fit">
                            <button
                                type="button"
                                @click="activeCategoryTab = 'expense'"
                                class="px-2.5 py-1 rounded-md text-[11px] font-bold transition-all cursor-pointer"
                                :class="activeCategoryTab === 'expense' ? 'bg-card text-rose-600 dark:text-rose-400 shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                            >
                                Pengeluaran
                            </button>
                            <button
                                type="button"
                                @click="activeCategoryTab = 'income'"
                                class="px-2.5 py-1 rounded-md text-[11px] font-bold transition-all cursor-pointer"
                                :class="activeCategoryTab === 'income' ? 'bg-card text-emerald-600 dark:text-emerald-400 shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                            >
                                Pemasukan
                            </button>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-4 space-y-4">
                        <div v-if="categoryEntries.length > 0" class="space-y-3.5">
                            <div v-for="cat in categoryEntries" :key="cat.name" class="space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-foreground">{{ cat.name }}</span>
                                    <span class="font-extrabold text-muted-foreground">
                                        {{ formatRupiah(cat.amount) }} ({{ cat.percent }}%)
                                    </span>
                                </div>
                                <div class="w-full bg-muted rounded-full h-2 overflow-hidden">
                                    <div 
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="activeCategoryTab === 'expense' ? 'bg-rose-500' : 'bg-emerald-500'"
                                        :style="{ width: Math.min(100, cat.percent) + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-8 text-center text-xs text-muted-foreground">
                            {{ activeCategoryTab === 'expense' ? 'Belum ada data riwayat pengeluaran pada periode ini.' : 'Belum ada data riwayat pemasukan pada periode ini.' }}
                        </div>
                    </CardContent>
                </Card>

                <!-- Rekap Saldo Kas per Dompet -->
                <Card class="shadow-2xs">
                    <CardHeader class="pb-3 border-b">
                        <CardTitle class="text-base font-bold flex items-center gap-2">
                            <Wallet class="w-4 h-4 text-emerald-500" />
                            <span>Rekap Saldo per Dompet</span>
                        </CardTitle>
                        <CardDescription class="text-xs mt-0.5">
                            Ketersediaan kas pada masing-masing pos rekening
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-0 divide-y">
                        <div 
                            v-for="w in wallets" 
                            :key="w.id"
                            class="p-4 flex items-center justify-between hover:bg-muted/30 transition-colors"
                        >
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                                    <Wallet class="w-4 h-4" />
                                </div>
                                <span class="text-xs font-bold">{{ w.name }}</span>
                            </div>
                            <span class="text-xs font-extrabold tabular-nums text-foreground">
                                {{ formatRupiah(w.current_balance) }}
                            </span>
                        </div>
                        <div v-if="wallets.length === 0" class="p-8 text-center text-xs text-muted-foreground">
                            Belum ada dompet aktif.
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </DashboardLayout>
</template>
