<script setup>
import { ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { 
    Scale, 
    ArrowUpRight, 
    ArrowDownRight, 
    CheckCircle2, 
    Clock, 
    AlertTriangle,
    Calendar,
    Wallet,
    Plus,
    Pencil,
    Trash2,
    Lock
} from 'lucide-vue-next';
import TransactionModal from '../../Components/TransactionModal.vue';

const page = usePage();

const props = defineProps({
    piutang: {
        type: Array,
        default: () => [],
    },
    utang: {
        type: Array,
        default: () => [],
    },
    totalPiutangAktif: {
        type: Number,
        default: 0,
    },
    totalUtangAktif: {
        type: Number,
        default: 0,
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
const activeTab = ref('piutang'); // 'piutang' or 'utang'

const openCreateModal = () => {
    transactionToEdit.value = null;
    isModalOpen.value = true;
};

const openEditModal = (item) => {
    transactionToEdit.value = item;
    isModalOpen.value = true;
};

const deleteDebt = (item) => {
    const typeLabel = item.type === 'debt_receivable' ? 'piutang' : 'utang';
    if (confirm(`Apakah Anda yakin ingin menghapus catatan ${typeLabel} sebesar ${formatRupiah(item.amount)} ini?`)) {
        router.delete(route('keuangan.transactions.destroy', item.id), {
            preserveScroll: true,
        });
    }
};

const updateStatus = (item, newStatus) => {
    router.patch(
        route('keuangan.debts.update-status', item.id),
        { debt_status: newStatus },
        { preserveScroll: true }
    );
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(val || 0);
};

const isOverdue = (dueDate, status) => {
    if (!dueDate || status === 'paid') return false;
    const due = new Date(dueDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return due < today;
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'paid':
            return { label: 'Lunas', class: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' };
        case 'partially_paid':
            return { label: 'Sebagian', class: 'bg-amber-500/10 text-amber-600 border-amber-500/20' };
        case 'unpaid':
            return { label: 'Belum Lunas', class: 'bg-rose-500/10 text-rose-600 border-rose-500/20' };
        default:
            return { label: status || 'Belum Lunas', class: 'bg-muted text-foreground' };
    }
};
</script>

<template>
    <DashboardLayout :user="user" active-module="keuangan" title="Radar Utang & Piutang">
        <div class="space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Radar Utang & Piutang</h1>
                </div>
                <div class="flex items-center gap-2 self-stretch sm:self-auto justify-end">
                    <Button @click="openCreateModal" class="gap-1.5 text-xs font-bold shadow-xs">
                        <Plus class="h-4 w-4" />
                        <span>{{ activeTab === 'piutang' ? 'Catat Piutang' : 'Catat Utang' }}</span>
                    </Button>
                </div>
            </div>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Piutang Aktif Card -->
                <Card class="shadow-2xs border-l-4 border-l-indigo-500">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Total Hak Tagih (Piutang Aktif)</span>
                            <ArrowUpRight class="h-4 w-4 text-indigo-500" />
                        </CardDescription>
                        <CardTitle class="text-2xl sm:text-3xl font-extrabold tracking-tight tabular-nums text-indigo-600 dark:text-indigo-400">
                            {{ formatRupiah(totalPiutangAktif) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-[11px] text-muted-foreground pt-0">
                        Uang Anda yang masih berada di pihak ketiga
                    </CardContent>
                </Card>

                <!-- Utang Aktif Card -->
                <Card class="shadow-2xs border-l-4 border-l-rose-500">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Total Kewajiban (Utang Aktif)</span>
                            <ArrowDownRight class="h-4 w-4 text-rose-500" />
                        </CardDescription>
                        <CardTitle class="text-2xl sm:text-3xl font-extrabold tracking-tight tabular-nums text-rose-600 dark:text-rose-400">
                            {{ formatRupiah(totalUtangAktif) }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-[11px] text-muted-foreground pt-0">
                        Kewajiban pembayaran yang harus diselesaikan
                    </CardContent>
                </Card>
            </div>

            <!-- Segmented Tab Switcher -->
            <div class="flex items-center gap-2 p-1 bg-muted rounded-xl w-fit">
                <button
                    type="button"
                    @click="activeTab = 'piutang'"
                    class="px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2"
                    :class="activeTab === 'piutang' ? 'bg-card text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                >
                    <ArrowUpRight class="w-3.5 h-3.5 text-indigo-500" />
                    <span>Daftar Piutang ({{ piutang.length }})</span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'utang'"
                    class="px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2"
                    :class="activeTab === 'utang' ? 'bg-card text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                >
                    <ArrowDownRight class="w-3.5 h-3.5 text-rose-500" />
                    <span>Daftar Utang ({{ utang.length }})</span>
                </button>
            </div>

            <!-- List Content -->
            <Card class="shadow-2xs overflow-hidden">
                <CardHeader class="pb-3 border-b">
                    <CardTitle class="text-base font-bold">
                        {{ activeTab === 'piutang' ? 'Daftar Hak Tagih (Piutang)' : 'Daftar Kewajiban (Utang)' }}
                    </CardTitle>
                    <CardDescription class="text-xs mt-0.5">
                        Kelola dan perbarui status pelunasan secara real-time.
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-0 divide-y">
                    <template v-if="activeTab === 'piutang'">
                        <div 
                            v-for="item in piutang" 
                            :key="item.id"
                            class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-muted/30 transition-colors"
                        >
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-foreground">
                                        {{ item.party_name || 'Tanpa Nama Debitur' }}
                                    </span>
                                    <Badge 
                                        variant="outline" 
                                        :class="getStatusBadge(item.debt_status).class"
                                        class="text-[10px] uppercase font-bold"
                                    >
                                        {{ getStatusBadge(item.debt_status).label }}
                                    </Badge>
                                </div>
                                <div class="text-[11px] text-muted-foreground flex flex-wrap items-center gap-3">
                                    <span class="flex items-center gap-1">
                                        <Wallet class="w-3 h-3" />
                                        <span>{{ item.wallet?.name || 'Dompet' }}</span>
                                        <span v-if="item.wallet?.user?.username && item.wallet.user.username !== $page.props.auth?.user?.username" class="text-primary font-bold text-[10px]">
                                            (Milik @{{ item.wallet.user.username }})
                                        </span>
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <Calendar class="w-3 h-3" />
                                        <span>Jatuh Tempo: {{ item.due_date ? new Date(item.due_date).toLocaleDateString('id-ID') : 'Tidak ditentukan' }}</span>
                                        <Badge v-if="isOverdue(item.due_date, item.debt_status)" variant="destructive" class="text-[9px] px-1 py-0 font-bold ml-1">
                                            Lewat Tempo
                                        </Badge>
                                    </span>
                                    <span class="text-[10px]">
                                        Dicatat oleh: <strong :class="item.user_id === $page.props.auth?.user?.id ? 'text-primary' : 'text-foreground'">@{{ item.user?.username || item.user?.name || 'user' }}{{ item.user_id === $page.props.auth?.user?.id ? ' (Anda)' : '' }}</strong>
                                    </span>
                                    <span v-if="item.description">• {{ item.description }}</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0">
                                <span class="text-sm sm:text-base font-extrabold tabular-nums text-indigo-600 dark:text-indigo-400">
                                    {{ formatRupiah(item.amount) }}
                                </span>
                                <select
                                    :value="item.debt_status || 'unpaid'"
                                    :disabled="item.user_id !== $page.props.auth?.user?.id && !$page.props.auth?.user?.roles?.includes('superadmin')"
                                    @change="updateStatus(item, $event.target.value)"
                                    class="h-8 rounded-lg border border-input bg-transparent px-2 text-xs font-semibold shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-50"
                                >
                                    <option value="unpaid">Belum Lunas</option>
                                    <option value="partially_paid">Sebagian</option>
                                    <option value="paid">Lunas</option>
                                </select>
                                <div v-if="item.user_id === $page.props.auth?.user?.id || $page.props.auth?.user?.roles?.includes('superadmin')" class="flex items-center gap-1">
                                    <Button 
                                        variant="ghost" 
                                        size="icon" 
                                        class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                        @click="openEditModal(item)"
                                        title="Ubah Catatan Saya"
                                    >
                                        <Pencil class="w-3.5 h-3.5" />
                                    </Button>
                                    <Button 
                                        variant="ghost" 
                                        size="icon" 
                                        class="h-8 w-8 text-muted-foreground hover:text-destructive"
                                        @click="deleteDebt(item)"
                                        title="Hapus Catatan Saya"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </Button>
                                </div>
                                <div v-else class="text-[10px] text-muted-foreground italic flex items-center justify-center p-1" title="Catatan milik pengguna lain (Hanya Lihat)">
                                    <Lock class="w-3.5 h-3.5 opacity-60" />
                                </div>
                            </div>
                        </div>

                        <div v-if="piutang.length === 0" class="p-8 text-center text-xs text-muted-foreground">
                            Tidak ada catatan piutang aktif.
                        </div>
                    </template>

                    <template v-else>
                        <div 
                            v-for="item in utang" 
                            :key="item.id"
                            class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-muted/30 transition-colors"
                        >
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-foreground">
                                        {{ item.party_name || 'Tanpa Nama Kreditur' }}
                                    </span>
                                    <Badge 
                                        variant="outline" 
                                        :class="getStatusBadge(item.debt_status).class"
                                        class="text-[10px] uppercase font-bold"
                                    >
                                        {{ getStatusBadge(item.debt_status).label }}
                                    </Badge>
                                </div>
                                <div class="text-[11px] text-muted-foreground flex flex-wrap items-center gap-3">
                                    <span class="flex items-center gap-1">
                                        <Wallet class="w-3 h-3" />
                                        <span>{{ item.wallet?.name || 'Dompet' }}</span>
                                        <span v-if="item.wallet?.user?.username && item.wallet.user.username !== $page.props.auth?.user?.username" class="text-primary font-bold text-[10px]">
                                            (Milik @{{ item.wallet.user.username }})
                                        </span>
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <Calendar class="w-3 h-3" />
                                        <span>Jatuh Tempo: {{ item.due_date ? new Date(item.due_date).toLocaleDateString('id-ID') : 'Tidak ditentukan' }}</span>
                                        <Badge v-if="isOverdue(item.due_date, item.debt_status)" variant="destructive" class="text-[9px] px-1 py-0 font-bold ml-1">
                                            Lewat Tempo
                                        </Badge>
                                    </span>
                                    <span class="text-[10px]">
                                        Dicatat oleh: <strong :class="item.user_id === $page.props.auth?.user?.id ? 'text-primary' : 'text-foreground'">@{{ item.user?.username || item.user?.name || 'user' }}{{ item.user_id === $page.props.auth?.user?.id ? ' (Anda)' : '' }}</strong>
                                    </span>
                                    <span v-if="item.description">• {{ item.description }}</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0">
                                <span class="text-sm sm:text-base font-extrabold tabular-nums text-rose-600 dark:text-rose-400">
                                    {{ formatRupiah(item.amount) }}
                                </span>
                                <select
                                    :value="item.debt_status || 'unpaid'"
                                    :disabled="item.user_id !== $page.props.auth?.user?.id && !$page.props.auth?.user?.roles?.includes('superadmin')"
                                    @change="updateStatus(item, $event.target.value)"
                                    class="h-8 rounded-lg border border-input bg-transparent px-2 text-xs font-semibold shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-50"
                                >
                                    <option value="unpaid">Belum Lunas</option>
                                    <option value="partially_paid">Sebagian</option>
                                    <option value="paid">Lunas</option>
                                </select>
                                <div v-if="item.user_id === $page.props.auth?.user?.id || $page.props.auth?.user?.roles?.includes('superadmin')" class="flex items-center gap-1">
                                    <Button 
                                        variant="ghost" 
                                        size="icon" 
                                        class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                        @click="openEditModal(item)"
                                        title="Ubah Catatan Saya"
                                    >
                                        <Pencil class="w-3.5 h-3.5" />
                                    </Button>
                                    <Button 
                                        variant="ghost" 
                                        size="icon" 
                                        class="h-8 w-8 text-muted-foreground hover:text-destructive"
                                        @click="deleteDebt(item)"
                                        title="Hapus Catatan Saya"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </Button>
                                </div>
                                <div v-else class="text-[10px] text-muted-foreground italic flex items-center justify-center p-1" title="Catatan milik pengguna lain (Hanya Lihat)">
                                    <Lock class="w-3.5 h-3.5 opacity-60" />
                                </div>
                            </div>
                        </div>

                        <div v-if="utang.length === 0" class="p-8 text-center text-xs text-muted-foreground">
                            Tidak ada catatan utang aktif.
                        </div>
                    </template>
                </CardContent>
            </Card>

            <!-- Transaction Modal -->
            <TransactionModal 
                :is-open="isModalOpen" 
                :wallets="wallets" 
                :initial-type="activeTab === 'piutang' ? 'debt_receivable' : 'debt_payable'"
                :transaction-to-edit="transactionToEdit"
                @close="isModalOpen = false" 
            />
        </div>
    </DashboardLayout>
</template>

