<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { 
    ArrowLeft, 
    Wallet, 
    Plus, 
    ArrowUpRight, 
    ArrowDownRight, 
    TrendingUp, 
    TrendingDown,
    CreditCard,
    Calendar,
    ArrowLeftRight,
    Scale,
    Pencil,
    Trash2,
    Lock,
    Users
} from 'lucide-vue-next';
import TransactionModal from '../../Components/TransactionModal.vue';

const page = usePage();

const props = defineProps({
    wallet: {
        type: Object,
        required: true,
    },
    isOwner: {
        type: Boolean,
        default: true,
    },
    userRole: {
        type: String,
        default: 'owner', // 'owner', 'edit', 'view'
    },
    stats: {
        type: Object,
        required: true,
    },
    transactions: {
        type: Object,
        required: true,
    },
    wallets: {
        type: Array,
        default: () => [],
    },
    user: {
        type: Object,
        required: true,
    },
});

const isModalOpen = ref(false);
const transactionToEdit = ref(null);

const canCreateTransaction = () => {
    return props.isOwner || props.userRole === 'edit';
};

const openCreateModal = () => {
    if (!canCreateTransaction()) return;
    transactionToEdit.value = null;
    isModalOpen.value = true;
};

const openEditModal = (tx) => {
    // Only creator can edit
    if (tx.user_id !== page.props.auth?.user?.id && !page.props.auth?.user?.roles?.includes('superadmin')) {
        alert('Catatan transaksi ini dibuat oleh pengguna lain dan tidak dapat diubah oleh Anda.');
        return;
    }
    transactionToEdit.value = tx;
    isModalOpen.value = true;
};

const deleteTransaction = (tx) => {
    // Only creator can delete
    if (tx.user_id !== page.props.auth?.user?.id && !page.props.auth?.user?.roles?.includes('superadmin')) {
        alert('Catatan transaksi ini dibuat oleh pengguna lain dan tidak dapat dihapus oleh Anda.');
        return;
    }

    if (confirm('Apakah Anda yakin ingin menghapus mutasi ini? Saldo dompet akan disesuaikan secara otomatis.')) {
        router.delete(route('keuangan.transactions.destroy', tx.id), {
            preserveScroll: true,
        });
    }
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(val || 0);
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
    <DashboardLayout :user="user" active-module="keuangan" :title="wallet.name">
        <div class="space-y-6">
            <!-- Header Bar with Back Button -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b">
                <div class="flex items-center gap-3">
                    <Link :href="route('keuangan.wallets.index')">
                        <Button variant="ghost" size="icon" class="h-9 w-9 rounded-xl border shadow-2xs">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">{{ wallet.name }}</h1>
                            <Badge variant="secondary" class="text-[10px] font-bold uppercase">
                                {{ wallet.category_label || 'Kas Mandiri' }}
                            </Badge>

                            <!-- Ownership / Shared Attribution Badge -->
                            <Badge 
                                v-if="!isOwner" 
                                variant="outline" 
                                class="text-[10px] font-bold bg-primary/10 text-primary border-primary/30"
                            >
                                Dompet Milik: @{{ wallet.user?.username || wallet.user?.name }}
                            </Badge>
                            <Badge 
                                v-if="!isOwner" 
                                :variant="userRole === 'edit' ? 'default' : 'secondary'" 
                                class="text-[10px] font-bold"
                            >
                                {{ userRole === 'edit' ? 'Akses: Bisa Edit' : 'Akses: Hanya Lihat' }}
                            </Badge>

                            <Badge 
                                v-if="isOwner && wallet.shares && wallet.shares.length > 0" 
                                variant="outline" 
                                class="text-[10px] font-semibold text-emerald-600 border-emerald-500/30 gap-1"
                            >
                                <Users class="w-3 h-3" />
                                <span>Dibagikan ke {{ wallet.shares.length }} pengguna</span>
                            </Badge>
                        </div>
                        <p v-if="wallet.account_number" class="text-xs text-muted-foreground mt-0.5">
                            No. {{ wallet.account_number }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-stretch sm:self-auto justify-end">
                    <Button 
                        v-if="canCreateTransaction()" 
                        @click="openCreateModal" 
                        class="gap-1.5 text-xs font-bold shadow-xs"
                    >
                        <Plus class="h-4 w-4" />
                        <span>Catat Mutasi</span>
                    </Button>
                    <div v-else class="text-xs font-medium text-muted-foreground px-3 py-1.5 rounded-lg border bg-muted/30 flex items-center gap-1.5">
                        <Lock class="w-3.5 h-3.5" />
                        <span>Akses Hanya Lihat (View-Only)</span>
                    </div>
                </div>
            </div>

            <!-- KPI Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Saldo Saat Ini -->
                <Card class="shadow-2xs border-l-4 border-l-primary">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Saldo Berjalan</span>
                            <Wallet class="h-4 w-4 text-primary" />
                        </CardDescription>
                        <CardTitle class="text-2xl font-extrabold tracking-tight tabular-nums text-foreground">
                            {{ formatRupiah(stats.current_balance) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-[11px] text-muted-foreground pt-0">
                        Saldo awal: {{ formatRupiah(stats.initial_balance) }}
                    </CardContent>
                </Card>

                <!-- Total Masuk -->
                <Card class="shadow-2xs border-l-4 border-l-emerald-500">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Total Kas Masuk</span>
                            <ArrowUpRight class="h-4 w-4 text-emerald-500" />
                        </CardDescription>
                        <CardTitle class="text-2xl font-extrabold tracking-tight tabular-nums text-emerald-600 dark:text-emerald-400">
                            {{ formatRupiah(stats.total_in) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-[11px] text-muted-foreground pt-0">
                        Pemasukan & transfer masuk
                    </CardContent>
                </Card>

                <!-- Total Keluar -->
                <Card class="shadow-2xs border-l-4 border-l-rose-500">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Total Kas Keluar</span>
                            <ArrowDownRight class="h-4 w-4 text-rose-500" />
                        </CardDescription>
                        <CardTitle class="text-2xl font-extrabold tracking-tight tabular-nums text-rose-600 dark:text-rose-400">
                            {{ formatRupiah(stats.total_out) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-[11px] text-muted-foreground pt-0">
                        Pengeluaran & transfer keluar
                    </CardContent>
                </Card>

                <!-- Net Cashflow -->
                <Card 
                    class="shadow-2xs border-l-4"
                    :class="stats.net_flow >= 0 ? 'border-l-primary' : 'border-l-amber-500'"
                >
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Arus Bersih (Net Flow)</span>
                            <TrendingUp v-if="stats.net_flow >= 0" class="h-4 w-4 text-primary" />
                            <TrendingDown v-else class="h-4 w-4 text-amber-500" />
                        </CardDescription>
                        <CardTitle 
                            class="text-2xl font-extrabold tracking-tight tabular-nums"
                            :class="stats.net_flow >= 0 ? 'text-primary' : 'text-amber-600 dark:text-amber-400'"
                        >
                            {{ formatRupiah(stats.net_flow) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-[11px] text-muted-foreground pt-0">
                        Total {{ stats.total_transactions }} mutasi tercatat
                    </CardContent>
                </Card>
            </div>

            <!-- Transactions Table -->
            <Card class="shadow-2xs overflow-hidden">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="text-base font-bold">Riwayat Mutasi Dompet</CardTitle>
                        <CardDescription class="text-xs mt-0.5">
                            Daftar seluruh transaksi yang memengaruhi saldo dompet ini
                        </CardDescription>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b bg-muted/40 text-muted-foreground text-[11px] font-bold uppercase tracking-wider">
                                    <th class="py-3 px-4">Tanggal</th>
                                    <th class="py-3 px-4">Deskripsi / Kategori</th>
                                    <th class="py-3 px-4">Alur Dompet</th>
                                    <th class="py-3 px-4">Jenis</th>
                                    <th class="py-3 px-4 text-right">Nominal</th>
                                    <th class="py-3 px-4 text-center w-16">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr 
                                    v-for="tx in transactions.data" 
                                    :key="tx.id"
                                    class="hover:bg-muted/30 transition-colors"
                                >
                                    <td class="py-3 px-4 font-semibold whitespace-nowrap text-muted-foreground">
                                        {{ tx.transaction_date ? new Date(tx.transaction_date).toLocaleDateString('id-ID') : '-' }}
                                    </td>
                                    <td class="py-3 px-4 min-w-[200px]">
                                        <span class="font-bold block text-foreground">
                                            {{ tx.description || tx.category || 'Mutasi Dompet' }}
                                        </span>
                                        <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                            <!-- Attribution label: Dicatat oleh siapa -->
                                            <span class="text-[10px] text-muted-foreground font-medium">
                                                Dicatat oleh: 
                                                <strong :class="tx.user_id === $page.props.auth?.user?.id ? 'text-primary' : 'text-foreground'">
                                                    @{{ tx.user?.username || tx.user?.name || 'user' }}
                                                    {{ tx.user_id === $page.props.auth?.user?.id ? ' (Anda)' : '' }}
                                                </strong>
                                            </span>
                                            <span v-if="tx.party_name" class="text-[10px] text-muted-foreground">
                                                • Pihak: {{ tx.party_name }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5 font-medium">
                                            <span>{{ tx.wallet?.name }}</span>
                                            <span v-if="tx.target_wallet" class="text-muted-foreground">
                                                → {{ tx.target_wallet.name }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <Badge 
                                            variant="outline" 
                                            :class="getTypeMeta(tx.type).class"
                                            class="text-[10px] uppercase font-bold"
                                        >
                                            {{ getTypeMeta(tx.type).label }}
                                        </Badge>
                                    </td>
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <span 
                                            class="text-xs font-extrabold tabular-nums"
                                            :class="{
                                                'text-emerald-600 dark:text-emerald-400': tx.type === 'income' || (tx.type === 'transfer' && tx.target_wallet_id === wallet.id),
                                                'text-rose-600 dark:text-rose-400': tx.type === 'expense' || (tx.type === 'transfer' && tx.wallet_id === wallet.id),
                                                'text-foreground': tx.type === 'debt_payable' || tx.type === 'debt_receivable'
                                            }"
                                        >
                                            {{ tx.type === 'income' || (tx.type === 'transfer' && tx.target_wallet_id === wallet.id) ? '+' : (tx.type === 'expense' || (tx.type === 'transfer' && tx.wallet_id === wallet.id) ? '-' : '') }}{{ formatRupiah(tx.amount) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <!-- Actions: ONLY if transaction is created by the logged in user (or superadmin) -->
                                        <div v-if="tx.user_id === $page.props.auth?.user?.id || $page.props.auth?.user?.roles?.includes('superadmin')" class="flex items-center justify-center gap-1">
                                            <Button 
                                                variant="ghost" 
                                                size="icon" 
                                                class="h-7 w-7 text-muted-foreground hover:text-foreground"
                                                @click="openEditModal(tx)"
                                                title="Ubah Mutasi Saya"
                                            >
                                                <Pencil class="w-3.5 h-3.5" />
                                            </Button>
                                            <Button 
                                                variant="ghost" 
                                                size="icon" 
                                                class="h-7 w-7 text-muted-foreground hover:text-destructive"
                                                @click="deleteTransaction(tx)"
                                                title="Hapus Mutasi Saya"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </Button>
                                        </div>
                                        <div v-else class="text-[10px] text-muted-foreground italic flex items-center justify-center gap-1" title="Catatan milik pengguna lain (Hanya Lihat)">
                                            <Lock class="w-3 h-3 opacity-60" />
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="transactions.data.length === 0">
                                    <td colspan="6" class="py-12 text-center text-xs text-muted-foreground">
                                        Belum ada mutasi transaksi untuk dompet ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div 
                        v-if="transactions.links && transactions.links.length > 3" 
                        class="p-4 border-t flex flex-col sm:flex-row items-center justify-between gap-3 text-xs"
                    >
                        <span class="text-muted-foreground text-[11px]">
                            Menampilkan {{ transactions.from || 0 }} - {{ transactions.to || 0 }} dari {{ transactions.total }} transaksi
                        </span>
                        <div class="flex items-center gap-1 flex-wrap">
                            <template v-for="(link, idx) in transactions.links" :key="idx">
                                <Link 
                                    v-if="link.url"
                                    :href="link.url"
                                    class="h-8 px-3 rounded-lg border text-xs font-bold flex items-center justify-center transition-all"
                                    :class="link.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-card text-foreground hover:bg-muted'"
                                    v-html="link.label"
                                />
                                <span 
                                    v-else
                                    class="h-8 px-3 rounded-lg border border-transparent text-muted-foreground flex items-center justify-center text-xs"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Transaction Modal -->
            <TransactionModal 
                :is-open="isModalOpen" 
                :wallets="wallets" 
                :initial-wallet-id="wallet.id"
                :transaction-to-edit="transactionToEdit"
                @close="isModalOpen = false" 
            />
        </div>
    </DashboardLayout>
</template>
