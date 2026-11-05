<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import { saveOfflineTransaction } from '@/lib/offlineStorage';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    X, 
    Plus, 
    Minus, 
    ArrowLeftRight, 
    Scale, 
    Wallet as WalletIcon,
    Calendar,
    Tag,
    User,
    FileText,
    LoaderCircle
} from 'lucide-vue-next';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    wallets: {
        type: Array,
        default: () => [],
    },
    initialType: {
        type: String,
        default: 'expense',
    },
    initialWalletId: {
        type: [Number, String],
        default: null,
    },
    transactionToEdit: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const todayDate = () => {
    const d = new Date();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${month}-${day}`;
};

const form = useForm({
    wallet_id: '',
    target_wallet_id: '',
    type: 'expense',
    amount: '',
    category: '',
    party_name: '',
    due_date: '',
    debt_status: 'unpaid',
    description: '',
    transaction_date: todayDate(),
});

watch(
    () => props.isOpen,
    (open) => {
        if (open) {
            form.clearErrors();
            if (props.transactionToEdit) {
                const tx = props.transactionToEdit;
                form.wallet_id = tx.wallet_id;
                form.target_wallet_id = tx.target_wallet_id || '';
                form.type = tx.type;
                form.amount = tx.amount;
                form.category = tx.category || '';
                form.party_name = tx.party_name || '';
                form.due_date = tx.due_date ? tx.due_date.substring(0, 10) : '';
                form.debt_status = tx.debt_status || 'unpaid';
                form.description = tx.description || '';
                form.transaction_date = tx.transaction_date ? tx.transaction_date.substring(0, 10) : todayDate();
            } else {
                form.reset();
                form.type = props.initialType || 'expense';
                form.transaction_date = todayDate();
                if (props.initialWalletId) {
                    form.wallet_id = props.initialWalletId;
                } else if (props.wallets && props.wallets.length > 0) {
                    form.wallet_id = props.wallets[0].id;
                }
            }
        }
    }
);

const setType = (type) => {
    form.type = type;
    if (type !== 'transfer') {
        form.target_wallet_id = '';
    }
};

const typeColorClass = computed(() => {
    switch (form.type) {
        case 'expense':
            return 'bg-rose-500 text-white';
        case 'income':
            return 'bg-emerald-500 text-white';
        case 'transfer':
            return 'bg-sky-500 text-white';
        case 'debt_payable':
            return 'bg-amber-500 text-white';
        case 'debt_receivable':
            return 'bg-indigo-500 text-white';
        default:
            return 'bg-primary text-primary-foreground';
    }
});

const modalTitle = computed(() => {
    const isEdit = !!props.transactionToEdit;
    switch (form.type) {
        case 'expense':
            return isEdit ? 'Ubah Pengeluaran Kas' : 'Catat Pengeluaran Kas';
        case 'income':
            return isEdit ? 'Ubah Pemasukan Kas' : 'Catat Pemasukan Kas';
        case 'transfer':
            return isEdit ? 'Ubah Transfer Antar Dompet' : 'Transfer Antar Dompet';
        case 'debt_payable':
            return isEdit ? 'Ubah Utang (Kewajiban)' : 'Catat Utang (Kewajiban Bayar)';
        case 'debt_receivable':
            return isEdit ? 'Ubah Piutang (Hak Tagih)' : 'Catat Piutang (Hak Tagih)';
        default:
            return isEdit ? 'Ubah Transaksi' : 'Catat Transaksi';
    }
});

const closeModal = () => {
    emit('close');
};

const selectableWallets = computed(() => {
    if (!props.wallets) return [];
    if (form.type === 'transfer') {
        return props.wallets.filter(w => !w.is_shared && w.role !== 'view');
    }
    return props.wallets.filter(w => w.role !== 'view');
});

const submitForm = () => {
    form.clearErrors();

    if (!form.wallet_id) {
        form.setError('wallet_id', 'Harap pilih dompet terlebih dahulu.');
        return;
    }
    if (!form.amount || Number(form.amount) <= 0) {
        form.setError('amount', 'Nominal transaksi wajib lebih dari Rp 0.');
        return;
    }
    if (form.type === 'transfer') {
        if (!form.target_wallet_id) {
            form.setError('target_wallet_id', 'Dompet tujuan wajib dipilih.');
            return;
        }
        if (String(form.target_wallet_id) === String(form.wallet_id)) {
            form.setError('target_wallet_id', 'Dompet tujuan tidak boleh sama dengan dompet sumber.');
            return;
        }
    }
    if ((form.type === 'debt_payable' || form.type === 'debt_receivable') && !form.party_name?.trim()) {
        form.setError('party_name', 'Nama pihak terkait wajib diisi.');
        return;
    }
    if (!form.transaction_date) {
        form.setError('transaction_date', 'Tanggal transaksi wajib diisi.');
        return;
    }

    if (props.transactionToEdit) {
        form.put(route('keuangan.transactions.update', props.transactionToEdit.id), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                emit('saved');
            },
        });
    } else {
        // Deteksi mode offline (pencatatan saat tidak ada sinyal internet)
        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            saveOfflineTransaction({
                wallet_id: form.wallet_id,
                target_wallet_id: form.target_wallet_id,
                type: form.type,
                amount: form.amount,
                category: form.category,
                party_name: form.party_name,
                due_date: form.due_date,
                debt_status: form.debt_status,
                description: form.description,
                transaction_date: form.transaction_date,
            }).then(() => {
                closeModal();
                emit('saved');
            }).catch((err) => {
                console.error('Gagal menyimpan offline:', err);
                form.setError('amount', 'Gagal mencatat transaksi secara offline.');
            });
            return;
        }

        form.post(route('keuangan.transactions.store'), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                emit('saved');
            },
        });
    }
};
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-background/80 backdrop-blur-xs select-none"
        @click.self="closeModal"
    >
        <div
            class="w-full max-w-lg bg-card text-card-foreground rounded-2xl border shadow-xl relative animate-in fade-in zoom-in-95 duration-150 max-h-[92vh] flex flex-col"
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-4 sm:p-5 border-b shrink-0">
                <div class="flex items-center gap-3">
                    <div
                        class="w-9 h-9 rounded-xl flex items-center justify-center font-bold shadow-2xs transition-colors shrink-0"
                        :class="typeColorClass"
                    >
                        <Minus v-if="form.type === 'expense'" class="w-4 h-4 stroke-[3]" />
                        <Plus v-else-if="form.type === 'income'" class="w-4 h-4 stroke-[3]" />
                        <ArrowLeftRight v-else-if="form.type === 'transfer'" class="w-4 h-4 stroke-[2.5]" />
                        <Scale v-else class="w-4 h-4 stroke-[2]" />
                    </div>
                    <div>
                        <h3 class="font-extrabold text-sm sm:text-base leading-tight">
                            {{ modalTitle }}
                        </h3>
                        <span class="text-[11px] text-muted-foreground font-medium">
                            {{ (form.type === 'debt_payable' || form.type === 'debt_receivable') ? 'Tercatat pada Radar Utang & Piutang' : 'Otomatis rekonsiliasi saldo dompet' }}
                        </span>
                    </div>
                </div>

                <Button
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8 rounded-lg text-muted-foreground hover:text-foreground"
                    @click="closeModal"
                >
                    <X class="w-4 h-4" />
                </Button>
            </div>

            <!-- Form Body -->
            <form @submit.prevent="submitForm" class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4">
                <!-- If No Wallets Exist -->
                <div
                    v-if="!wallets || wallets.length === 0"
                    class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-xl flex items-center justify-between gap-3 text-amber-700 dark:text-amber-400"
                >
                    <div class="flex items-center gap-2 text-xs font-semibold">
                        <WalletIcon class="w-4 h-4 shrink-0" />
                        <span>Belum ada dompet aktif. Harap buat dompet terlebih dahulu.</span>
                    </div>
                    <Link
                        :href="route('keuangan.wallets.index')"
                        @click="closeModal"
                        class="underline text-xs font-bold shrink-0 hover:text-amber-800 dark:hover:text-amber-300"
                    >
                        Buat Dompet
                    </Link>
                </div>

                <!-- Transaction Type Toggle -->
                <div class="grid grid-cols-5 gap-1 p-1 bg-muted rounded-xl text-xs font-semibold">
                    <button
                        type="button"
                        @click="setType('expense')"
                        class="py-1.5 px-1 rounded-lg transition-all text-center flex flex-col items-center justify-center gap-0.5"
                        :class="form.type === 'expense' ? 'bg-rose-500 text-white shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                    >
                        <Minus class="w-3.5 h-3.5 stroke-[3]" />
                        <span class="text-[10px]">Keluar</span>
                    </button>
                    <button
                        type="button"
                        @click="setType('income')"
                        class="py-1.5 px-1 rounded-lg transition-all text-center flex flex-col items-center justify-center gap-0.5"
                        :class="form.type === 'income' ? 'bg-emerald-500 text-white shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                    >
                        <Plus class="w-3.5 h-3.5 stroke-[3]" />
                        <span class="text-[10px]">Masuk</span>
                    </button>
                    <button
                        type="button"
                        @click="setType('transfer')"
                        class="py-1.5 px-1 rounded-lg transition-all text-center flex flex-col items-center justify-center gap-0.5"
                        :class="form.type === 'transfer' ? 'bg-sky-500 text-white shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                    >
                        <ArrowLeftRight class="w-3.5 h-3.5 stroke-[2.5]" />
                        <span class="text-[10px]">Transfer</span>
                    </button>
                    <button
                        type="button"
                        @click="setType('debt_payable')"
                        class="py-1.5 px-1 rounded-lg transition-all text-center flex flex-col items-center justify-center gap-0.5"
                        :class="form.type === 'debt_payable' ? 'bg-amber-500 text-white shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                    >
                        <Scale class="w-3.5 h-3.5 stroke-[2]" />
                        <span class="text-[10px]">Utang</span>
                    </button>
                    <button
                        type="button"
                        @click="setType('debt_receivable')"
                        class="py-1.5 px-1 rounded-lg transition-all text-center flex flex-col items-center justify-center gap-0.5"
                        :class="form.type === 'debt_receivable' ? 'bg-indigo-500 text-white shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                    >
                        <Scale class="w-3.5 h-3.5 stroke-[2]" />
                        <span class="text-[10px]">Piutang</span>
                    </button>
                </div>

                <!-- Nominal Amount Input -->
                <div class="space-y-1.5">
                    <Label class="text-xs font-semibold">Nominal (IDR)</Label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-xs font-extrabold text-muted-foreground">Rp</span>
                        <Input
                            v-model="form.amount"
                            type="number"
                            step="any"
                            min="0.01"
                            placeholder="0"
                            class="pl-9 text-base font-bold"
                            required
                        />
                    </div>
                    <span v-if="form.errors.amount" class="text-[11px] text-destructive">
                        {{ form.errors.amount }}
                    </span>
                </div>

                <!-- Wallet Selection Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">
                            {{ form.type === 'transfer' ? 'Dari Dompet Sumber' : 'Pos Dompet / Rekening' }}
                        </Label>
                        <select
                            v-model="form.wallet_id"
                            class="w-full h-9 rounded-md border border-input bg-transparent px-3 py-1 text-xs shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            required
                        >
                            <option value="" disabled>Pilih Dompet...</option>
                            <option v-for="w in selectableWallets" :key="w.id" :value="w.id">
                                {{ w.name }} {{ w.is_shared ? `(Milik @${w.owner_username})` : '' }} (Rp {{ Number(w.current_balance).toLocaleString('id-ID') }})
                            </option>
                        </select>
                        <span v-if="form.errors.wallet_id" class="text-[11px] text-destructive">
                            {{ form.errors.wallet_id }}
                        </span>
                    </div>

                    <!-- Target Wallet (For Transfer) -->
                    <div v-if="form.type === 'transfer'" class="space-y-1.5">
                        <Label class="text-xs font-semibold">Ke Dompet Tujuan</Label>
                        <select
                            v-model="form.target_wallet_id"
                            class="w-full h-9 rounded-md border border-input bg-transparent px-3 py-1 text-xs shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            required
                        >
                            <option value="" disabled>Pilih Dompet Tujuan...</option>
                            <option 
                                v-for="w in selectableWallets.filter(item => item.id !== form.wallet_id)" 
                                :key="w.id" 
                                :value="w.id"
                            >
                                {{ w.name }} (Rp {{ Number(w.current_balance).toLocaleString('id-ID') }})
                            </option>
                        </select>
                        <span v-if="form.errors.target_wallet_id" class="text-[11px] text-destructive">
                            {{ form.errors.target_wallet_id }}
                        </span>
                    </div>

                    <!-- Category (For Income/Expense) -->
                    <div v-else class="space-y-1.5">
                        <Label class="text-xs font-semibold">Kategori</Label>
                        <Input
                            v-model="form.category"
                            type="text"
                            placeholder="e.g. Makanan, Gaji, Transport, Operasional"
                            class="text-xs"
                        />
                        <!-- Quick Category Pills -->
                        <div class="flex items-center gap-1.5 flex-wrap pt-1">
                            <template v-if="form.type === 'expense'">
                                <button
                                    v-for="cat in ['Makanan', 'Transportasi', 'Operasional', 'Tagihan', 'Belanja', 'Keluarga']"
                                    :key="cat"
                                    type="button"
                                    @click="form.category = cat"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-semibold border transition-all cursor-pointer"
                                    :class="form.category === cat ? 'bg-rose-500 text-white border-rose-500' : 'bg-muted/50 text-muted-foreground hover:bg-muted'"
                                >
                                    {{ cat }}
                                </button>
                            </template>
                            <template v-else-if="form.type === 'income'">
                                <button
                                    v-for="cat in ['Gaji', 'Pendapatan Jasa', 'Penjualan', 'Investasi', 'Bonus', 'Komisi']"
                                    :key="cat"
                                    type="button"
                                    @click="form.category = cat"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-semibold border transition-all cursor-pointer"
                                    :class="form.category === cat ? 'bg-emerald-500 text-white border-emerald-500' : 'bg-muted/50 text-muted-foreground hover:bg-muted'"
                                >
                                    {{ cat }}
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Debts Extra Fields -->
                <div v-if="form.type === 'debt_payable' || form.type === 'debt_receivable'" class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-muted/40 rounded-xl border">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">
                            {{ form.type === 'debt_payable' ? 'Pemberi Pinjaman' : 'Peminjam / Debitur' }}
                        </Label>
                        <Input
                            v-model="form.party_name"
                            type="text"
                            placeholder="Nama Pihak Terkait"
                            class="text-xs"
                            required
                            :class="{ 'border-destructive': form.errors.party_name }"
                        />
                        <span v-if="form.errors.party_name" class="text-[11px] text-destructive">
                            {{ form.errors.party_name }}
                        </span>
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Jatuh Tempo</Label>
                        <Input
                            v-model="form.due_date"
                            type="date"
                            class="text-xs"
                            :class="{ 'border-destructive': form.errors.due_date }"
                        />
                        <span v-if="form.errors.due_date" class="text-[11px] text-destructive">
                            {{ form.errors.due_date }}
                        </span>
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Status Pelunasan</Label>
                        <select
                            v-model="form.debt_status"
                            class="w-full h-9 rounded-md border border-input bg-card px-2.5 py-1 text-xs shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="unpaid">Belum Lunas</option>
                            <option value="partially_paid">Sebagian</option>
                            <option value="paid">Lunas</option>
                        </select>
                    </div>
                </div>

                <!-- Date & Description -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Tanggal Transaksi</Label>
                        <Input
                            v-model="form.transaction_date"
                            type="date"
                            class="text-xs"
                            required
                            :class="{ 'border-destructive': form.errors.transaction_date }"
                        />
                        <span v-if="form.errors.transaction_date" class="text-[11px] text-destructive">
                            {{ form.errors.transaction_date }}
                        </span>
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Catatan / Deskripsi</Label>
                        <Input
                            v-model="form.description"
                            type="text"
                            placeholder="Keterangan transaksi..."
                            class="text-xs"
                            :class="{ 'border-destructive': form.errors.description }"
                        />
                        <span v-if="form.errors.description" class="text-[11px] text-destructive">
                            {{ form.errors.description }}
                        </span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="pt-4 mt-2 flex items-center justify-end gap-2 border-t">
                    <Button
                        type="button"
                        variant="ghost"
                        class="text-xs"
                        @click="closeModal"
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="text-xs font-bold px-4 gap-1.5"
                    >
                        <LoaderCircle v-if="form.processing" class="w-3.5 h-3.5 animate-spin" />
                        <span v-if="form.processing">Menyimpan Transaksi...</span>
                        <span v-else>{{ transactionToEdit ? 'Simpan Perubahan' : 'Simpan Transaksi' }}</span>
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>

