<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { 
    Plus, 
    Download, 
    Search, 
    Trash2, 
    Pencil,
    ArrowUpRight, 
    ArrowDownRight, 
    ArrowLeftRight, 
    Scale,
    Filter,
    ChevronLeft,
    ChevronRight,
    RotateCcw,
    Lock,
    LoaderCircle
} from 'lucide-vue-next';
import TransactionModal from '../../Components/TransactionModal.vue';

const page = usePage();

const props = defineProps({
    transactions: {
        type: Object,
        required: true,
    },
    wallets: {
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

const isModalOpen = ref(false);
const transactionToEdit = ref(null);
const filterWallet = ref(props.filters.wallet_id || '');
const filterType = ref(props.filters.type || '');
const searchQuery = ref(props.filters.search || '');
const filterDateFrom = ref(props.filters.date_from || '');
const filterDateTo = ref(props.filters.date_to || '');
const filterPerPage = ref(props.filters.per_page || '15');

const openCreateModal = () => {
    transactionToEdit.value = null;
    isModalOpen.value = true;
};

const openEditModal = (tx) => {
    if (tx.user_id !== page.props.auth?.user?.id && !page.props.auth?.user?.roles?.includes('superadmin')) {
        alert('Catatan transaksi ini dibuat oleh pengguna lain dan tidak dapat diubah oleh Anda.');
        return;
    }
    transactionToEdit.value = tx;
    isModalOpen.value = true;
};

const selectedIds = ref([]);
const isDeletingBulk = ref(false);
const deletingSingleId = ref(null);

const applyFilter = () => {
    router.get(
        route('keuangan.transactions.index'),
        {
            wallet_id: filterWallet.value || undefined,
            type: filterType.value || undefined,
            search: searchQuery.value || undefined,
            date_from: filterDateFrom.value || undefined,
            date_to: filterDateTo.value || undefined,
            per_page: filterPerPage.value !== '15' ? filterPerPage.value : undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const resetFilter = () => {
    filterWallet.value = '';
    filterType.value = '';
    searchQuery.value = '';
    filterDateFrom.value = '';
    filterDateTo.value = '';
    filterPerPage.value = '15';
    applyFilter();
};

const selectableTransactions = computed(() => {
    if (!props.transactions.data) return [];
    const currentUserId = page.props.auth?.user?.id;
    const isSuperAdmin = page.props.auth?.user?.roles?.includes('superadmin');
    return props.transactions.data.filter((tx) => isSuperAdmin || tx.user_id === currentUserId);
});

const isAllSelected = computed(() => {
    if (selectableTransactions.value.length === 0) return false;
    return selectableTransactions.value.every((tx) => selectedIds.value.includes(tx.id));
});

const toggleSelectAll = (checked) => {
    if (checked) {
        selectedIds.value = selectableTransactions.value.map((tx) => tx.id);
    } else {
        selectedIds.value = [];
    }
};

const toggleSelectOne = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
};

const deleteSingle = (tx) => {
    if (tx.user_id !== page.props.auth?.user?.id && !page.props.auth?.user?.roles?.includes('superadmin')) {
        alert('Catatan transaksi ini dibuat oleh pengguna lain dan tidak dapat dihapus oleh Anda.');
        return;
    }

    if (deletingSingleId.value || isDeletingBulk.value) return;

    if (confirm('Apakah Anda yakin ingin menghapus transaksi ini? Saldo buku kas akan direkonsiliasi.')) {
        deletingSingleId.value = tx.id;
        router.delete(route('keuangan.transactions.destroy', tx.id), {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = selectedIds.value.filter((id) => id !== tx.id);
            },
            onFinish: () => {
                deletingSingleId.value = null;
            },
        });
    }
};

const deleteBulk = () => {
    if (selectedIds.value.length === 0 || isDeletingBulk.value) return;

    if (confirm(`Apakah Anda yakin ingin menghapus ${selectedIds.value.length} transaksi milik Anda yang dipilih?`)) {
        isDeletingBulk.value = true;
        router.post(route('keuangan.transactions.bulk-destroy'), {
            ids: selectedIds.value,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            },
            onFinish: () => {
                isDeletingBulk.value = false;
            },
        });
    }
};

const exportCsv = () => {
    const params = new URLSearchParams();
    if (filterWallet.value) params.append('wallet_id', filterWallet.value);
    if (filterType.value) params.append('type', filterType.value);
    if (searchQuery.value) params.append('search', searchQuery.value);
    if (filterDateFrom.value) params.append('date_from', filterDateFrom.value);
    if (filterDateTo.value) params.append('date_to', filterDateTo.value);

    window.location.href = route('keuangan.transactions.export') + '?' + params.toString();
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
    <DashboardLayout :user="user" active-module="keuangan" title="Buku Kas & Mutasi Transaksi">
        <div class="space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Mutasi Transaksi</h1>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Rekam jejak seluruh arus kas masuk, keluar, dan kolaborasi dompet bersama.
                    </p>
                </div>
                <div class="flex items-center gap-2 self-stretch sm:self-auto justify-end flex-wrap">
                    <Button 
                        variant="outline" 
                        size="sm" 
                        class="gap-1.5 text-xs font-semibold shadow-2xs"
                        @click="exportCsv"
                    >
                        <Download class="h-3.5 w-3.5" />
                        <span>Ekspor CSV</span>
                    </Button>
                    <Button @click="openCreateModal" class="gap-1.5 text-xs font-bold shadow-xs">
                        <Plus class="h-4 w-4" />
                        <span>Catat Transaksi</span>
                    </Button>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Wallet Filter -->
                    <select
                        v-model="filterWallet"
                        @change="applyFilter"
                        class="h-9 rounded-xl border border-input bg-transparent px-3 py-1 text-xs font-semibold shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        <option value="">Semua Dompet</option>
                        <option v-for="w in wallets" :key="w.id" :value="w.id">
                            {{ w.name }} {{ w.is_shared ? `(Milik @${w.owner_username})` : '' }}
                        </option>
                    </select>

                    <!-- Type Filter -->
                    <select
                        v-model="filterType"
                        @change="applyFilter"
                        class="h-9 rounded-xl border border-input bg-transparent px-3 py-1 text-xs font-semibold shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        <option value="">Semua Jenis</option>
                        <option value="income">Pemasukan</option>
                        <option value="expense">Pengeluaran</option>
                        <option value="transfer">Transfer</option>
                        <option value="debt_payable">Utang</option>
                        <option value="debt_receivable">Piutang</option>
                    </select>

                    <!-- Date Range -->
                    <div class="flex items-center gap-1.5">
                        <Input
                            v-model="filterDateFrom"
                            @change="applyFilter"
                            type="date"
                            title="Dari Tanggal"
                            class="h-9 w-32 text-xs"
                        />
                        <span class="text-muted-foreground">-</span>
                        <Input
                            v-model="filterDateTo"
                            @change="applyFilter"
                            type="date"
                            title="Sampai Tanggal"
                            class="h-9 w-32 text-xs"
                        />
                    </div>

                    <!-- Search Input -->
                    <div class="relative flex-1 min-w-[160px]">
                        <Search class="w-3.5 h-3.5 text-muted-foreground absolute left-3 top-3" />
                        <Input
                            v-model="searchQuery"
                            @keyup.enter="applyFilter"
                            type="text"
                            placeholder="Cari transaksi..."
                            class="pl-8 h-9 text-xs"
                        />
                    </div>

                    <Button @click="applyFilter" size="sm" class="h-9 text-xs font-bold gap-1 shadow-2xs">
                        <span>Cari</span>
                    </Button>

                    <!-- Per Page Selector -->
                    <select
                        v-model="filterPerPage"
                        @change="applyFilter"
                        class="h-9 rounded-xl border border-input bg-transparent px-2.5 py-1 text-xs font-semibold shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        title="Tampilkan per halaman"
                    >
                        <option value="15">15 / hal</option>
                        <option value="25">25 / hal</option>
                        <option value="50">50 / hal</option>
                        <option value="100">100 / hal</option>
                        <option value="all">Semua</option>
                    </select>

                    <Button 
                        v-if="filterWallet || filterType || searchQuery || filterDateFrom || filterDateTo || filterPerPage !== '15'" 
                        variant="ghost" 
                        size="sm" 
                        class="h-9 text-xs text-muted-foreground"
                        @click="resetFilter"
                    >
                        <RotateCcw class="w-3.5 h-3.5 mr-1" />
                        <span>Reset</span>
                    </Button>
                </div>

                <!-- Bulk Selection Indicator -->
                <div v-if="selectedIds.length > 0" class="flex items-center gap-2">
                    <span class="text-xs font-bold text-muted-foreground">
                        {{ selectedIds.length }} terpilih
                    </span>
                    <Button 
                        variant="destructive" 
                        size="sm" 
                        class="h-9 text-xs font-bold gap-1.5 shadow-2xs"
                        :disabled="isDeletingBulk"
                        @click="deleteBulk"
                    >
                        <LoaderCircle v-if="isDeletingBulk" class="w-3.5 h-3.5 animate-spin" />
                        <Trash2 v-else class="w-3.5 h-3.5" />
                        <span>{{ isDeletingBulk ? 'Memproses Antrean...' : 'Hapus Terpilih' }}</span>
                    </Button>
                </div>
            </div>

            <!-- Transactions Table Card -->
            <div class="bg-card rounded-2xl border shadow-2xs overflow-hidden">
                <!-- Desktop Table -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b bg-muted/40 text-muted-foreground text-[11px] font-bold uppercase tracking-wider">
                                <th class="py-3 px-3 w-10 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        @change="toggleSelectAll($event.target.checked)"
                                        class="rounded border-input text-primary focus:ring-ring cursor-pointer w-4 h-4"
                                    />
                                </th>
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">Deskripsi / Kategori</th>
                                <th class="py-3 px-4">Dompet</th>
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
                                :class="{ 'bg-primary/5': selectedIds.includes(tx.id) }"
                            >
                                <td class="py-3 px-3 text-center">
                                    <input
                                        v-if="tx.user_id === $page.props.auth?.user?.id || $page.props.auth?.user?.roles?.includes('superadmin')"
                                        type="checkbox"
                                        :checked="selectedIds.includes(tx.id)"
                                        @change="toggleSelectOne(tx.id)"
                                        class="rounded border-input text-primary focus:ring-ring cursor-pointer w-4 h-4"
                                    />
                                    <span v-else class="text-[10px] text-muted-foreground opacity-50" title="Catatan milik pengguna lain">-</span>
                                </td>
                                <td class="py-3 px-4 font-semibold whitespace-nowrap text-muted-foreground">
                                    {{ tx.transaction_date ? new Date(tx.transaction_date).toLocaleDateString('id-ID') : '-' }}
                                </td>
                                <td class="py-3 px-4 min-w-[200px]">
                                    <span class="font-bold block text-foreground">
                                        {{ tx.description || tx.category || '-' }}
                                    </span>
                                    <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                        <!-- Small attribution label: Dicatat oleh siapa -->
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
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-1.5 font-medium">
                                            <span>{{ tx.wallet?.name || 'Dompet' }}</span>
                                            <span v-if="tx.target_wallet" class="text-muted-foreground">
                                                → {{ tx.target_wallet.name }}
                                            </span>
                                        </div>
                                        <!-- Small attribution label: Dompet Milik Siapa -->
                                        <span 
                                            v-if="tx.wallet?.user?.username && tx.wallet.user.username !== $page.props.auth?.user?.username" 
                                            class="text-[10px] text-primary font-bold"
                                        >
                                            Milik: @{{ tx.wallet.user.username }}
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
                                            'text-emerald-600 dark:text-emerald-400': tx.type === 'income',
                                            'text-rose-600 dark:text-rose-400': tx.type === 'expense',
                                            'text-sky-600 dark:text-sky-400': tx.type === 'transfer',
                                            'text-foreground': tx.type === 'debt_payable' || tx.type === 'debt_receivable'
                                        }"
                                    >
                                        {{ tx.type === 'income' ? '+' : (tx.type === 'expense' ? '-' : '') }}{{ formatRupiah(tx.amount) }}
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
                                            title="Ubah Transaksi Saya"
                                        >
                                            <Pencil class="w-3.5 h-3.5" />
                                        </Button>
                                        <Button 
                                            variant="ghost" 
                                            size="icon" 
                                            class="h-7 w-7 text-muted-foreground hover:text-destructive"
                                            :disabled="deletingSingleId === tx.id || isDeletingBulk"
                                            @click="deleteSingle(tx)"
                                            title="Hapus Transaksi Saya"
                                        >
                                            <LoaderCircle v-if="deletingSingleId === tx.id" class="w-3.5 h-3.5 animate-spin text-destructive" />
                                            <Trash2 v-else class="w-3.5 h-3.5" />
                                        </Button>
                                    </div>
                                    <div v-else class="text-[10px] text-muted-foreground italic flex items-center justify-center gap-1" title="Catatan milik pengguna lain (Hanya Lihat)">
                                        <Lock class="w-3 h-3 opacity-60" />
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="transactions.data.length === 0">
                                <td colspan="7" class="py-12 text-center text-xs text-muted-foreground">
                                    Tidak ada data transaksi yang sesuai filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List View -->
                <div class="sm:hidden divide-y">
                    <div 
                        v-for="tx in transactions.data" 
                        :key="tx.id"
                        class="p-4 space-y-2"
                        :class="{ 'bg-primary/5': selectedIds.includes(tx.id) }"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <input
                                    v-if="tx.user_id === $page.props.auth?.user?.id || $page.props.auth?.user?.roles?.includes('superadmin')"
                                    type="checkbox"
                                    :checked="selectedIds.includes(tx.id)"
                                    @change="toggleSelectOne(tx.id)"
                                    class="rounded border-input text-primary focus:ring-ring w-4 h-4"
                                />
                                <Badge 
                                    variant="outline" 
                                    :class="getTypeMeta(tx.type).class"
                                    class="text-[9px] uppercase font-bold"
                                >
                                    {{ getTypeMeta(tx.type).label }}
                                </Badge>
                            </div>
                            <span 
                                class="text-xs font-extrabold tabular-nums text-right"
                                :class="{
                                    'text-emerald-600 dark:text-emerald-400': tx.type === 'income',
                                    'text-rose-600 dark:text-rose-400': tx.type === 'expense',
                                    'text-foreground': tx.type !== 'income' && tx.type !== 'expense'
                                }"
                            >
                                {{ tx.type === 'income' ? '+' : (tx.type === 'expense' ? '-' : '') }}{{ formatRupiah(tx.amount) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-xs font-bold block">{{ tx.description || tx.category || 'Transaksi' }}</span>
                            <div class="text-[11px] text-muted-foreground flex items-center gap-1.5 flex-wrap mt-0.5">
                                <span>{{ tx.wallet?.name || 'Dompet' }}</span>
                                <span v-if="tx.wallet?.user?.username && tx.wallet.user.username !== $page.props.auth?.user?.username" class="text-primary font-bold text-[10px]">
                                    (Milik @{{ tx.wallet.user.username }})
                                </span>
                                <span v-if="tx.target_wallet">→ {{ tx.target_wallet.name }}</span>
                                <span>•</span>
                                <span>{{ tx.transaction_date ? new Date(tx.transaction_date).toLocaleDateString('id-ID') : '-' }}</span>
                            </div>
                            <!-- Attribution on mobile -->
                            <span class="text-[10px] text-muted-foreground block mt-0.5">
                                Dicatat oleh: 
                                <strong :class="tx.user_id === $page.props.auth?.user?.id ? 'text-primary' : 'text-foreground'">
                                    @{{ tx.user?.username || tx.user?.name || 'user' }}
                                    {{ tx.user_id === $page.props.auth?.user?.id ? ' (Anda)' : '' }}
                                </strong>
                            </span>
                        </div>
                        <div class="flex items-center justify-between pt-2.5 mt-1 border-t text-[11px]">
                            <span v-if="tx.party_name" class="text-muted-foreground">Pihak: {{ tx.party_name }}</span>
                            <span v-else class="text-muted-foreground">ID: #{{ tx.id }}</span>
                            <!-- Actions: only if owned by user -->
                            <div v-if="tx.user_id === $page.props.auth?.user?.id || $page.props.auth?.user?.roles?.includes('superadmin')" class="flex items-center gap-2">
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-6 px-2 text-foreground text-[11px]"
                                    @click="openEditModal(tx)"
                                >
                                    Ubah
                                </Button>
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-6 px-2 text-destructive text-[11px] flex items-center gap-1"
                                    :disabled="deletingSingleId === tx.id || isDeletingBulk"
                                    @click="deleteSingle(tx)"
                                >
                                    <LoaderCircle v-if="deletingSingleId === tx.id" class="w-3 h-3 animate-spin" />
                                    <span>{{ deletingSingleId === tx.id ? 'Menghapus...' : 'Hapus' }}</span>
                                </Button>
                            </div>
                            <div v-else class="text-[10px] text-muted-foreground italic">
                                Hanya Lihat
                            </div>
                        </div>
                    </div>

                    <div v-if="transactions.data.length === 0" class="p-8 text-center text-xs text-muted-foreground">
                        Tidak ada transaksi ditemukan.
                    </div>
                </div>

                <!-- Pagination Footer -->
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
            </div>

            <!-- Transaction Modal -->
            <TransactionModal 
                :is-open="isModalOpen" 
                :wallets="wallets" 
                :transaction-to-edit="transactionToEdit"
                @close="isModalOpen = false" 
            />
        </div>
    </DashboardLayout>
</template>
