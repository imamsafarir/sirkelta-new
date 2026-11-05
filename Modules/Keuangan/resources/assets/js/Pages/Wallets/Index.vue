<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    Plus, 
    Wallet, 
    CreditCard, 
    Pencil, 
    Trash2, 
    X,
    Building2,
    DollarSign,
    FolderKanban,
    Share2,
    Users,
    Check,
    Eye,
    Shield,
    Clock,
    UserCheck,
    ToggleLeft,
    ToggleRight
} from 'lucide-vue-next';

const props = defineProps({
    wallets: {
        type: Array,
        default: () => [],
    },
    totalBalance: {
        type: Number,
        default: 0,
    },
    pendingShares: {
        type: Array,
        default: () => [],
    },
    sharedWallets: {
        type: Array,
        default: () => [],
    },
    user: {
        type: Object,
        required: true,
    },
});

const isModalOpen = ref(false);
const editingWallet = ref(null);

const form = useForm({
    name: '',
    category_label: '',
    account_number: '',
    icon: 'wallet',
    initial_balance: 0,
});

// Share Modal state
const isShareModalOpen = ref(false);
const activeShareWallet = ref(null);
const shareForm = useForm({
    username: '',
    role: 'view',
});

const openCreateModal = () => {
    editingWallet.value = null;
    form.reset();
    form.clearErrors();
    form.category_label = 'KAS MANDIRI';
    form.icon = 'wallet';
    isModalOpen.value = true;
};

const openEditModal = (w) => {
    editingWallet.value = w;
    form.reset();
    form.clearErrors();
    form.name = w.name;
    form.category_label = w.category_label || '';
    form.account_number = w.account_number || '';
    form.icon = w.icon || 'wallet';
    isModalOpen.value = true;
};

const openShareModal = (w) => {
    activeShareWallet.value = w;
    shareForm.reset();
    shareForm.clearErrors();
    shareForm.role = 'view';
    isShareModalOpen.value = true;
};

const submitWalletForm = () => {
    form.clearErrors();

    if (!form.name.trim()) {
        form.setError('name', 'Nama dompet wajib diisi.');
        return;
    }

    if (!editingWallet.value && (form.initial_balance === '' || Number(form.initial_balance) < 0)) {
        form.setError('initial_balance', 'Saldo awal tidak boleh kurang dari Rp 0.');
        return;
    }

    if (editingWallet.value) {
        form.put(route('keuangan.wallets.update', editingWallet.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    } else {
        form.post(route('keuangan.wallets.store'), {
            preserveScroll: true,
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    }
};

const deleteWallet = (w) => {
    if (confirm(`Apakah Anda yakin ingin menghapus dompet "${w.name}"? Seluruh riwayat transaksi di dompet ini juga akan terhapus.`)) {
        router.delete(route('keuangan.wallets.destroy', w.id), {
            preserveScroll: true,
        });
    }
};

const submitShareForm = () => {
    if (!activeShareWallet.value) return;

    shareForm.post(route('keuangan.wallets.shares.store', activeShareWallet.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            shareForm.reset();
            // keep modal open to see updated shares list or close
            isShareModalOpen.value = false;
        },
    });
};

const revokeShare = (share) => {
    if (!activeShareWallet.value) return;
    if (confirm(`Cabut izin akses untuk @${share.user?.username || 'pengguna ini'}?`)) {
        router.delete(route('keuangan.wallets.shares.destroy', [activeShareWallet.value.id, share.id]), {
            preserveScroll: true,
            onSuccess: () => {
                if (activeShareWallet.value.shares) {
                    activeShareWallet.value.shares = activeShareWallet.value.shares.filter(s => s.id !== share.id);
                }
            },
        });
    }
};

const acceptShare = (share) => {
    router.post(route('keuangan.wallets.shares.accept', share.id), {}, {
        preserveScroll: true,
    });
};

const rejectShare = (share) => {
    if (confirm('Tolak undangan akses dompet ini?')) {
        router.post(route('keuangan.wallets.shares.reject', share.id), {}, {
            preserveScroll: true,
        });
    }
};

const toggleSharedWallet = (share) => {
    router.patch(route('keuangan.wallets.shares.toggle', share.id), {}, {
        preserveScroll: true,
    });
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(val || 0);
};
</script>

<template>
    <DashboardLayout :user="user" active-module="keuangan" title="Dompet Saya">
        <div class="space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Dompet Saya</h1>
                    <p class="text-xs text-muted-foreground mt-0.5">Kelola rekening kas mandiri dan kolaborasi dompet bersama.</p>
                </div>
                <div class="flex items-center gap-3 self-stretch sm:self-auto justify-between sm:justify-end">
                    <div class="px-3.5 py-1.5 rounded-xl border bg-card text-right shadow-2xs">
                        <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block">Total Saldo Kas Pribadi</span>
                        <span class="text-sm sm:text-base font-extrabold tabular-nums text-emerald-600 dark:text-emerald-400">
                            {{ formatRupiah(totalBalance) }}
                        </span>
                    </div>
                    <Button @click="openCreateModal" class="gap-2 text-xs font-bold shadow-xs">
                        <Plus class="h-4 w-4" />
                        <span>Tambah Dompet</span>
                    </Button>
                </div>
            </div>

            <!-- 1. PENDING INVITATIONS BANNER / SECTION -->
            <div v-if="pendingShares && pendingShares.length > 0" class="space-y-3">
                <div class="flex items-center gap-2">
                    <Badge variant="outline" class="bg-amber-500/10 text-amber-600 border-amber-500/20 text-xs font-bold px-2 py-0.5 gap-1.5">
                        <Clock class="w-3.5 h-3.5 animate-pulse" />
                        <span>{{ pendingShares.length }} Undangan Kolaborasi Dompet Masuk</span>
                    </Badge>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div 
                        v-for="share in pendingShares" 
                        :key="share.id"
                        class="p-4 rounded-2xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/50 dark:bg-amber-950/20 flex flex-col justify-between gap-3 shadow-2xs"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-sm text-foreground">
                                        {{ share.wallet?.name || 'Dompet Kas' }}
                                    </span>
                                    <Badge :variant="share.role === 'edit' ? 'default' : 'secondary'" class="text-[10px] uppercase font-bold">
                                        {{ share.role === 'edit' ? 'Bisa Edit' : 'Hanya Lihat' }}
                                    </Badge>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Pengguna <strong class="text-foreground">@{{ share.shared_by?.username }}</strong> ingin membagikan akses dompet ini kepada Anda.
                                </p>
                                <span class="text-[10px] text-muted-foreground block">
                                    * Anda harus menerima undangan ini sebelum dapat menyalakan dompet di pembukuan Anda.
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-3 mt-1.5 border-t border-amber-200/60 dark:border-amber-900/40 justify-end">
                            <Button 
                                size="sm" 
                                variant="outline" 
                                class="h-8 text-xs font-semibold text-muted-foreground hover:text-destructive"
                                @click="rejectShare(share)"
                            >
                                Tolak
                            </Button>
                            <Button 
                                size="sm" 
                                class="h-8 text-xs font-bold gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white"
                                @click="acceptShare(share)"
                            >
                                <Check class="w-3.5 h-3.5" />
                                <span>Terima Undangan</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. OWNED WALLETS SECTION (DOMPET PRIBADI SAYA) -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-muted-foreground">Dompet Pribadi Saya</h2>
                    <span class="text-xs text-muted-foreground">{{ wallets.length }} dompet</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Card 
                        v-for="(w, idx) in wallets" 
                        :key="w.id"
                        class="shadow-2xs hover:border-primary/40 transition-all flex flex-col justify-between"
                    >
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between gap-2 pb-2 border-b">
                                <Badge variant="secondary" class="text-[10px] font-bold uppercase">
                                    {{ w.category_label || 'Kas Mandiri' }}
                                </Badge>
                                <Link 
                                    :href="route('keuangan.transactions.index', { wallet_id: w.id })"
                                    class="text-[11px] text-muted-foreground hover:text-primary font-semibold flex items-center gap-1 transition-colors"
                                    :title="'Lihat daftar mutasi ' + w.name"
                                >
                                    <span>{{ w.transactions_count }} transaksi</span>
                                    <span>→</span>
                                </Link>
                            </div>
                            <Link :href="route('keuangan.wallets.show', w.id)" class="flex items-center gap-3 pt-2 group cursor-pointer">
                                <div 
                                    class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-white shadow-2xs shrink-0 group-hover:scale-105 transition-transform"
                                    :class="[
                                        idx % 4 === 0 ? 'bg-gradient-to-tr from-emerald-600 to-teal-500' : '',
                                        idx % 4 === 1 ? 'bg-gradient-to-tr from-blue-600 to-cyan-500' : '',
                                        idx % 4 === 2 ? 'bg-gradient-to-tr from-indigo-600 to-purple-500' : '',
                                        idx % 4 === 3 ? 'bg-gradient-to-tr from-amber-600 to-orange-500' : '',
                                    ]"
                                >
                                    {{ w.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="truncate">
                                    <CardTitle class="text-base font-bold truncate group-hover:text-primary transition-colors">{{ w.name }}</CardTitle>
                                    <CardDescription v-if="w.account_number" class="text-xs truncate">
                                        No. {{ w.account_number }}
                                    </CardDescription>
                                    <!-- Collaboration Indicator -->
                                    <div v-if="w.shares && w.shares.length > 0" class="flex items-center gap-1 mt-1 text-[10px] text-primary font-semibold">
                                        <Users class="w-3 h-3" />
                                        <span>Dibagikan ke {{ w.shares.length }} user</span>
                                    </div>
                                </div>
                            </Link>
                        </CardHeader>
                        <CardFooter class="pt-3.5 pb-3.5 border-t flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block">Saldo Saat Ini</span>
                                <span class="text-base font-extrabold tabular-nums text-foreground">
                                    {{ formatRupiah(w.current_balance) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    class="h-8 px-2.5 text-xs font-semibold gap-1 text-primary hover:bg-primary/10 hover:text-primary"
                                    :title="'Bagi akses dompet ke username lain'"
                                    @click="openShareModal(w)"
                                >
                                    <Share2 class="h-3.5 w-3.5" />
                                    <span>Bagi</span>
                                </Button>
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    class="h-8 px-2 text-xs font-semibold"
                                    @click="openEditModal(w)"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </Button>
                                <Button 
                                    variant="ghost" 
                                    size="icon" 
                                    class="h-8 w-8 text-destructive hover:bg-destructive/10"
                                    @click="deleteWallet(w)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </CardFooter>
                    </Card>

                    <!-- Empty State for Owned Wallets -->
                    <div 
                        v-if="wallets.length === 0" 
                        class="col-span-full p-8 text-center rounded-2xl border border-dashed bg-muted/20 space-y-3"
                    >
                        <div class="w-10 h-10 rounded-2xl bg-muted flex items-center justify-center mx-auto text-muted-foreground">
                            <Wallet class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-foreground">Belum ada dompet pribadi</h3>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                Tambahkan dompet untuk mulai mencatat keuangan dan berbagi akses dengan rekan Anda.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. SHARED WALLETS SECTION (DOMPET BERSAMA DIBAGIKAN KEPADA SAYA) -->
            <div v-if="sharedWallets && sharedWallets.length > 0" class="space-y-3 pt-4 border-t">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Users class="w-4 h-4 text-primary" />
                            <span>Dompet Bersama (Dibagikan kepada Saya)</span>
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Dompet milik pengguna lain yang telah Anda terima. Anda dapat menyalakan atau mematikan tampilannya.
                        </p>
                    </div>
                    <span class="text-xs text-muted-foreground">{{ sharedWallets.length }} dompet</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Card 
                        v-for="sw in sharedWallets" 
                        :key="sw.id"
                        class="shadow-2xs transition-all flex flex-col justify-between border-dashed"
                        :class="sw.is_active ? 'border-primary/50 bg-primary/5 dark:bg-primary/5' : 'opacity-70 bg-muted/10'"
                    >
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between gap-2 pb-2 border-b">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <Badge variant="outline" class="text-[10px] font-bold text-primary border-primary/30">
                                        Milik: @{{ sw.shared_by?.username || sw.wallet?.user?.username }}
                                    </Badge>
                                    <Badge :variant="sw.role === 'edit' ? 'default' : 'secondary'" class="text-[10px] font-semibold">
                                        {{ sw.role === 'edit' ? 'Bisa Edit' : 'Hanya Lihat' }}
                                    </Badge>
                                </div>
                                <Link 
                                    v-if="sw.wallet"
                                    :href="route('keuangan.transactions.index', { wallet_id: sw.wallet.id })"
                                    class="text-[11px] text-muted-foreground hover:text-primary font-semibold flex items-center gap-1 transition-colors"
                                    :title="'Lihat mutasi dompet ' + sw.wallet.name"
                                >
                                    <span>{{ sw.wallet.transactions_count }} mutasi</span>
                                    <span>→</span>
                                </Link>
                            </div>

                            <div v-if="sw.wallet" class="flex items-center gap-3 pt-2">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-500 flex items-center justify-center font-bold text-white shadow-2xs shrink-0">
                                    {{ sw.wallet.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="truncate">
                                    <CardTitle class="text-base font-bold truncate">{{ sw.wallet.name }}</CardTitle>
                                    <CardDescription class="text-xs truncate">
                                        Pemilik: {{ sw.shared_by?.name || sw.wallet?.user?.name }} (@{{ sw.shared_by?.username || sw.wallet?.user?.username }})
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>

                        <CardFooter class="pt-3.5 pb-3.5 border-t flex items-center justify-between gap-2">
                            <div>
                                <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block">Saldo Saat Ini</span>
                                <span class="text-base font-extrabold tabular-nums text-foreground">
                                    {{ formatRupiah(sw.wallet?.current_balance) }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <!-- Nyalakan / Matikan Dompet Toggle -->
                                <Button
                                    size="sm"
                                    :variant="sw.is_active ? 'default' : 'outline'"
                                    class="h-8 text-xs font-bold gap-1.5"
                                    :class="sw.is_active ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'text-muted-foreground'"
                                    :title="sw.is_active ? 'Klik untuk mematikan tampilan dompet ini' : 'Klik untuk menyalakan tampilan dompet ini'"
                                    @click="toggleSharedWallet(sw)"
                                >
                                    <ToggleRight v-if="sw.is_active" class="w-4 h-4 text-white" />
                                    <ToggleLeft v-else class="w-4 h-4 text-muted-foreground" />
                                    <span>{{ sw.is_active ? 'Menyala' : 'Mati' }}</span>
                                </Button>

                                <Link v-if="sw.wallet" :href="route('keuangan.wallets.show', sw.wallet.id)">
                                    <Button variant="outline" size="sm" class="h-8 px-2 text-xs font-semibold gap-1">
                                        <Eye class="w-3.5 h-3.5" />
                                        <span>Rincian</span>
                                    </Button>
                                </Link>
                            </div>
                        </CardFooter>
                    </Card>
                </div>
            </div>

            <!-- 4. MODAL: CREATE / EDIT DOMPET PRIBADI -->
            <div
                v-if="isModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-3 bg-background/80 backdrop-blur-xs select-none"
                @click.self="isModalOpen = false"
            >
                <div class="w-full max-w-md bg-card text-card-foreground rounded-2xl border shadow-xl relative animate-in fade-in zoom-in-95 duration-150 p-5 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b">
                        <div>
                            <h3 class="font-extrabold text-base">
                                {{ editingWallet ? 'Ubah Dompet' : 'Tambah Dompet Baru' }}
                            </h3>
                            <span class="text-[11px] text-muted-foreground">
                                Pos rekening atau brankas penyimpanan kas
                            </span>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 rounded-lg"
                            @click="isModalOpen = false"
                        >
                            <X class="w-4 h-4" />
                        </Button>
                    </div>

                    <form @submit.prevent="submitWalletForm" class="space-y-3.5">
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Nama Dompet / Rekening</Label>
                            <Input
                                v-model="form.name"
                                type="text"
                                placeholder="e.g. Rekening BCA, Dompet Tunai, GoPay"
                                class="text-xs"
                                required
                            />
                            <span v-if="form.errors.name" class="text-[11px] text-destructive">
                                {{ form.errors.name }}
                            </span>
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Kategori Pos</Label>
                            <Input
                                v-model="form.category_label"
                                type="text"
                                placeholder="e.g. BANK, DOMPET DIGITAL, TUNAI, INVESTASI"
                                class="text-xs"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Nomor Rekening / Akun (Opsional)</Label>
                            <Input
                                v-model="form.account_number"
                                type="text"
                                placeholder="e.g. 8830192842"
                                class="text-xs"
                            />
                        </div>

                        <div v-if="!editingWallet" class="space-y-1.5">
                            <Label class="text-xs font-semibold">Saldo Awal (IDR)</Label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-xs font-bold text-muted-foreground">Rp</span>
                                <Input
                                    v-model="form.initial_balance"
                                    type="number"
                                    step="any"
                                    placeholder="0"
                                    class="pl-9 text-xs font-bold"
                                    required
                                />
                            </div>
                            <span v-if="form.errors.initial_balance" class="text-[11px] text-destructive">
                                {{ form.errors.initial_balance }}
                            </span>
                        </div>

                        <div class="pt-3 flex items-center justify-end gap-2 border-t">
                            <Button
                                type="button"
                                variant="ghost"
                                class="text-xs"
                                @click="isModalOpen = false"
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                :disabled="form.processing"
                                class="text-xs font-bold"
                            >
                                <span v-if="form.processing">Menyimpan...</span>
                                <span v-else>{{ editingWallet ? 'Simpan Perubahan' : 'Buat Dompet' }}</span>
                            </Button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 5. MODAL: BAGI AKSES DOMPET (TRANSFER / SHARING HAK AKSES) -->
            <div
                v-if="isShareModalOpen && activeShareWallet"
                class="fixed inset-0 z-50 flex items-center justify-center p-3 bg-background/80 backdrop-blur-xs select-none"
                @click.self="isShareModalOpen = false"
            >
                <div class="w-full max-w-lg bg-card text-card-foreground rounded-2xl border shadow-xl relative animate-in fade-in zoom-in-95 duration-150 p-5 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold">
                                <Share2 class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="font-extrabold text-base">
                                    Bagi Akses Dompet "{{ activeShareWallet.name }}"
                                </h3>
                                <span class="text-[11px] text-muted-foreground">
                                    Undang pengguna lain via username untuk kolaborasi pembukuan
                                </span>
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 rounded-lg"
                            @click="isShareModalOpen = false"
                        >
                            <X class="w-4 h-4" />
                        </Button>
                    </div>

                    <!-- Form Invite User -->
                    <form @submit.prevent="submitShareForm" class="space-y-4">
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Username Pengguna Tujuan</Label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-xs font-bold text-muted-foreground">@</span>
                                <Input
                                    v-model="shareForm.username"
                                    type="text"
                                    placeholder="masukkan username (contoh: budi_santoso)"
                                    class="pl-7 text-xs font-medium"
                                    required
                                />
                            </div>
                            <span v-if="shareForm.errors.username" class="text-[11px] text-destructive">
                                {{ shareForm.errors.username }}
                            </span>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-xs font-semibold">Pilih Hak Akses</Label>
                            <div class="grid grid-cols-2 gap-2">
                                <div 
                                    class="p-3 rounded-xl border cursor-pointer transition-all flex flex-col gap-1.5"
                                    :class="shareForm.role === 'view' ? 'border-primary bg-primary/10 text-primary shadow-2xs' : 'border-border hover:bg-muted/30'"
                                    @click="shareForm.role = 'view'"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold">Hanya Lihat (View)</span>
                                        <Eye class="w-4 h-4" />
                                    </div>
                                    <p class="text-[10px] text-muted-foreground leading-tight">
                                        Hanya bisa melihat perubahan isi saldo dan riwayat mutasi tanpa bisa menambah transaksi.
                                    </p>
                                </div>

                                <div 
                                    class="p-3 rounded-xl border cursor-pointer transition-all flex flex-col gap-1.5"
                                    :class="shareForm.role === 'edit' ? 'border-primary bg-primary/10 text-primary shadow-2xs' : 'border-border hover:bg-muted/30'"
                                    @click="shareForm.role = 'edit'"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold">Bisa Edit (Editor)</span>
                                        <Pencil class="w-4 h-4" />
                                    </div>
                                    <p class="text-[10px] text-muted-foreground leading-tight">
                                        Bisa melihat saldo serta menambahkan pemasukan dan pengeluaran kas ke dompet ini.
                                    </p>
                                </div>
                            </div>
                            <span v-if="shareForm.errors.role" class="text-[11px] text-destructive">
                                {{ shareForm.errors.role }}
                            </span>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <Button
                                type="submit"
                                :disabled="shareForm.processing"
                                class="text-xs font-bold gap-1.5"
                            >
                                <Share2 class="w-3.5 h-3.5" />
                                <span>{{ shareForm.processing ? 'Mengirim...' : 'Kirim Undangan' }}</span>
                            </Button>
                        </div>
                    </form>

                    <!-- Existing Shares List for This Wallet -->
                    <div class="pt-3 border-t space-y-2.5">
                        <Label class="text-xs font-semibold text-muted-foreground block">
                            Daftar Pengguna yang Diberi Akses
                        </Label>

                        <div v-if="!activeShareWallet.shares || activeShareWallet.shares.length === 0" class="text-xs text-muted-foreground p-3 rounded-xl bg-muted/20 text-center">
                            Belum ada pengguna yang diundang untuk dompet ini.
                        </div>

                        <div v-else class="space-y-1.5 max-h-48 overflow-y-auto">
                            <div 
                                v-for="share in activeShareWallet.shares" 
                                :key="share.id"
                                class="flex items-center justify-between p-2.5 rounded-xl border bg-card text-xs"
                            >
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-muted flex items-center justify-center font-bold text-muted-foreground text-[10px]">
                                        @
                                    </div>
                                    <div>
                                        <span class="font-bold text-foreground">@{{ share.user?.username || share.user?.name }}</span>
                                        <div class="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                            <Badge :variant="share.role === 'edit' ? 'default' : 'secondary'" class="text-[9px] px-1 py-0 h-4">
                                                {{ share.role === 'edit' ? 'Bisa Edit' : 'Hanya Lihat' }}
                                            </Badge>
                                            <span>•</span>
                                            <span :class="share.status === 'accepted' ? 'text-emerald-600 font-semibold' : 'text-amber-600 font-semibold'">
                                                {{ share.status === 'accepted' ? 'Diterima' : 'Menunggu Persetujuan' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <Button 
                                    size="sm" 
                                    variant="ghost" 
                                    class="h-7 px-2 text-destructive hover:bg-destructive/10 text-xs font-semibold gap-1"
                                    @click="revokeShare(share)"
                                >
                                    <Trash2 class="w-3 h-3" />
                                    <span>Cabut</span>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
