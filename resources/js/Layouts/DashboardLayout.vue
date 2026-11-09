<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator
} from '@/components/ui/dropdown-menu';
import {
    Wallet,
    Send,
    LogOut,
    Layers,
    ChevronLeft,
    ChevronRight,
    LayoutDashboard,
    CreditCard,
    ArrowLeftRight,
    Smartphone,
    Users,
    MessageSquare,
    ChevronDown,
    ExternalLink,
    Menu,
    X,
    Scale,
    BarChart3,
    CheckCircle2,
    AlertCircle,
    User as UserIcon,
    ShieldCheck,
    Gift,
    Settings,
    Headphones,
    Plus,
    Download,
    Sparkles
} from 'lucide-vue-next';
import SupportChatBubble from '@/Components/SupportChatBubble.vue';
import TransactionModal from '@modules/Keuangan/resources/assets/js/Components/TransactionModal.vue';

const props = defineProps({
    title: {
        type: String,
        default: 'Dashboard',
    },
    activeModule: {
        type: String,
        required: true, // 'keuangan', 'wasender', or 'admin'
    },
    user: {
        type: Object,
        required: true,
    },
});

const isSuperadmin = computed(() => {
    return props.user?.roles?.includes('superadmin');
});

const isAdminKeuangan = computed(() => {
    return props.user?.roles?.includes('admin_keuangan');
});

const isAdminWeb = computed(() => {
    return props.user?.roles?.includes('admin_web');
});

const hasAdminAccess = computed(() => {
    return isSuperadmin.value || isAdminKeuangan.value || isAdminWeb.value || props.user?.roles?.includes('admin_wasender') || props.user?.roles?.includes('admin');
});

const canSwitchWorkspaces = computed(() => {
    return true; // All users can open switcher to explore or switch workspaces
});

const currentWorkspaceLabel = computed(() => {
    if (props.activeModule === 'admin') {
        return 'Ruang Kerja Admin';
    }
    if (props.activeModule === 'keuangan') {
        return 'Sirkelta Keuangan';
    }
    if (props.activeModule === 'wasender') {
        return 'Sirkelta WaSender';
    }
    return 'Sirkelta';
});

const page = usePage();
const toast = ref({ show: false, message: '', type: 'success' });
let toastTimeout = null;

const showToast = (message, type = 'success') => {
    if (!message) return;
    toast.value = { show: true, message, type };
    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toast.value.show = false;
    }, 4000);
};

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            showToast(flash.success, 'success');
        } else if (flash?.error) {
            showToast(flash.error, 'error');
        }
    },
    { deep: true }
);

const isCollapsed = ref(false);
const isMobileOpen = ref(false);

const isGlobalTxModalOpen = ref(false);
const globalTxModalType = ref('expense');

const openGlobalTransactionModal = (type = 'expense') => {
    globalTxModalType.value = type;
    isGlobalTxModalOpen.value = true;
};

onMounted(() => {
    const saved = localStorage.getItem('sirkelta_sidebar_collapsed');
    if (saved !== null) {
        isCollapsed.value = saved === 'true';
    }

    if (page.props.flash?.success) {
        showToast(page.props.flash.success, 'success');
    } else if (page.props.flash?.error) {
        showToast(page.props.flash.error, 'error');
    }
});

const toggleSidebar = () => {
    isCollapsed.value = !isCollapsed.value;
    localStorage.setItem('sirkelta_sidebar_collapsed', isCollapsed.value);
};

const logout = () => {
    if (typeof navigator !== 'undefined' && 'serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'PURGE_AUTH_CACHE' });
    }
    router.post(route('logout'));
};

const getInitials = (name) => {
    return name
        ?.split(' ')
        .map((n) => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase() || 'SK';
};
</script>

<template>
    <Head :title="title + ' - Sirkelta'" />

    <div class="min-h-screen bg-muted/20 text-foreground flex flex-col md:flex-row font-sans selection:bg-primary selection:text-primary-foreground">
        <!-- Mobile Header Bar with Safe Area for iPhone Notch -->
        <div class="md:hidden sticky top-0 z-40 bg-background/95 backdrop-blur-md border-b pt-safe">
            <div class="flex items-center justify-between h-16 px-4">
                <div class="flex items-center gap-2.5">
                    <img src="/images/logo.png" alt="Sirkelta" class="h-8 w-8 rounded-lg object-contain shadow-xs" />
                    <span class="font-extrabold text-base tracking-tight">Sirkelta</span>
                    <Badge variant="outline" class="text-[10px] uppercase font-bold">
                        {{ activeModule === 'admin' ? 'Admin' : activeModule }}
                    </Badge>
                </div>
                <Button variant="ghost" size="icon" @click="isMobileOpen = !isMobileOpen">
                    <Menu v-if="!isMobileOpen" class="h-5 w-5" />
                    <X v-else class="h-5 w-5" />
                </Button>
            </div>
        </div>

        <!-- Mobile Drawer Backdrop -->
        <div 
            v-if="isMobileOpen" 
            class="md:hidden fixed inset-0 z-40 bg-background/80 backdrop-blur-sm"
            @click="isMobileOpen = false"
        ></div>

        <!-- Left Sidebar (Desktop & Mobile Drawer) -->
        <aside
            class="fixed md:sticky top-0 bottom-0 left-0 z-50 h-screen border-r bg-sidebar text-sidebar-foreground transition-all duration-300 flex flex-col justify-between select-none pt-safe pb-safe"
            :class="[
                isCollapsed ? 'md:w-20' : 'md:w-64',
                isMobileOpen ? 'translate-x-0 w-64' : '-translate-x-full md:translate-x-0'
            ]"
        >
            <!-- Sidebar Top: Brand & Collapse Toggle -->
            <div>
                <div class="h-16 flex items-center border-b px-4" :class="isCollapsed ? 'justify-center' : 'justify-between'">
                    <Link 
                        :href="route('portal')" 
                        class="flex items-center gap-3 overflow-hidden"
                    >
                        <img src="/images/logo.png" alt="Sirkelta Logo" class="h-9 w-9 rounded-xl object-contain shadow-sm shrink-0" />
                        <div v-if="!isCollapsed" class="flex flex-col truncate">
                            <span class="font-extrabold text-base tracking-tight leading-none text-foreground">Sirkelta</span>
                            <span class="text-[10px] text-muted-foreground mt-0.5 font-medium tracking-wide">Ecosystem</span>
                        </div>
                    </Link>

                    <Button 
                        variant="ghost" 
                        size="icon" 
                        class="hidden md:flex h-7 w-7 text-muted-foreground hover:text-foreground rounded-lg"
                        @click="toggleSidebar"
                        :title="isCollapsed ? 'Perluas Menu' : 'Sembunyikan Menu'"
                    >
                        <ChevronRight v-if="isCollapsed" class="h-4 w-4" />
                        <ChevronLeft v-else class="h-4 w-4" />
                    </Button>
                </div>

                <!-- App Switcher Section (Pindah Ruang Kerja) -->
                <div class="p-3 border-b">
                    <DropdownMenu v-if="canSwitchWorkspaces">
                        <DropdownMenuTrigger as-child>
                            <Button 
                                variant="outline" 
                                class="w-full flex items-center bg-card border shadow-2xs hover:bg-muted text-foreground transition-all"
                                :class="isCollapsed ? 'justify-center px-0 h-10 w-10 mx-auto' : 'justify-between h-10 px-3'"
                                :title="'Ruang Kerja: ' + currentWorkspaceLabel"
                            >
                                <div class="flex items-center gap-2.5 truncate">
                                    <div 
                                        class="h-6 w-6 rounded-lg flex items-center justify-center shrink-0"
                                        :class="{
                                            'bg-purple-500/10 text-purple-600': activeModule === 'admin',
                                            'bg-primary/10 text-primary': activeModule === 'keuangan',
                                            'bg-emerald-500/10 text-emerald-600': activeModule === 'wasender',
                                        }"
                                    >
                                        <ShieldCheck v-if="activeModule === 'admin'" class="h-3.5 w-3.5" />
                                        <Wallet v-else-if="activeModule === 'keuangan'" class="h-3.5 w-3.5" />
                                        <Send v-else class="h-3.5 w-3.5" />
                                    </div>
                                    <span v-if="!isCollapsed" class="font-bold text-xs truncate">
                                        {{ currentWorkspaceLabel }}
                                    </span>
                                </div>
                                <ChevronDown v-if="!isCollapsed" class="h-3.5 w-3.5 text-muted-foreground shrink-0 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-64" align="start">
                            <DropdownMenuLabel class="text-[10px] text-muted-foreground uppercase font-bold tracking-wider">
                                Pindah Ruang Kerja
                            </DropdownMenuLabel>
                            <DropdownMenuItem as-child>
                                <Link :href="route('portal')" class="cursor-pointer flex items-center gap-2.5 py-2">
                                    <div class="h-7 w-7 rounded-lg bg-muted flex items-center justify-center text-muted-foreground shrink-0">
                                        <Layers class="h-4 w-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs font-semibold">Portal Hub</span>
                                        <span class="text-[10px] text-muted-foreground">Semua Aplikasi</span>
                                    </div>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />

                            <!-- Modul Keuangan -->
                            <DropdownMenuItem 
                                v-if="user?.roles?.includes('superadmin') || user?.accessible_modules?.includes('keuangan')"
                                as-child
                            >
                                <Link 
                                    :href="route('keuangan.dashboard')" 
                                    class="cursor-pointer flex items-center justify-between py-2"
                                    :class="{ 'bg-primary/10 font-bold': activeModule === 'keuangan' }"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-7 w-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                            <Wallet class="h-4 w-4" />
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-xs">Sirkelta Keuangan</span>
                                            <span class="text-[10px] text-muted-foreground">Pencatatan Finansial</span>
                                        </div>
                                    </div>
                                    <Badge v-if="activeModule === 'keuangan'" variant="secondary" class="text-[9px]">Aktif</Badge>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem 
                                v-else
                                as-child
                            >
                                <Link 
                                    :href="route('portal')" 
                                    class="cursor-pointer flex items-center justify-between py-2 text-muted-foreground hover:text-foreground"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-7 w-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                            <Wallet class="h-4 w-4" />
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-xs">Sirkelta Keuangan</span>
                                            <span class="text-[10px] text-muted-foreground">Pencatatan Finansial</span>
                                        </div>
                                    </div>
                                    <Badge variant="outline" class="text-[9px] text-primary border-primary/30 bg-primary/5 font-bold">+ Pasang</Badge>
                                </Link>
                            </DropdownMenuItem>

                            <!-- Modul WaSender -->
                            <DropdownMenuItem 
                                v-if="user?.roles?.includes('superadmin') || user?.accessible_modules?.includes('wasender')"
                                as-child
                            >
                                <Link 
                                    :href="route('wasender.dashboard')" 
                                    class="cursor-pointer flex items-center justify-between py-2"
                                    :class="{ 'bg-emerald-500/10 font-bold': activeModule === 'wasender' }"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-7 w-7 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                                            <Send class="h-4 w-4" />
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-xs">Sirkelta WaSender</span>
                                            <span class="text-[10px] text-muted-foreground">Broadcast WhatsApp</span>
                                        </div>
                                    </div>
                                    <Badge v-if="activeModule === 'wasender'" variant="secondary" class="text-[9px]">Aktif</Badge>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem 
                                v-else
                                as-child
                            >
                                <Link 
                                    :href="route('portal')" 
                                    class="cursor-pointer flex items-center justify-between py-2 text-muted-foreground hover:text-foreground"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-7 w-7 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                                            <Send class="h-4 w-4" />
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-xs">Sirkelta WaSender</span>
                                            <span class="text-[10px] text-muted-foreground">Broadcast WhatsApp</span>
                                        </div>
                                    </div>
                                    <Badge variant="outline" class="text-[9px] text-emerald-600 border-emerald-500/30 bg-emerald-500/10 font-bold">+ Pasang</Badge>
                                </Link>
                            </DropdownMenuItem>

                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link :href="route('portal')" class="cursor-pointer flex items-center justify-between py-2 text-primary font-bold">
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-7 w-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                            <Plus class="h-4 w-4" />
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-xs">Jelajahi Ekosistem</span>
                                            <span class="text-[10px] text-muted-foreground">Aktifkan modul aplikasi baru</span>
                                        </div>
                                    </div>
                                    <ChevronRight class="h-3.5 w-3.5 text-muted-foreground" />
                                </Link>
                            </DropdownMenuItem>

                            <!-- Ruang Kerja Admin (Ke Halaman Admin) -->
                            <template v-if="hasAdminAccess">
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel class="text-[10px] text-muted-foreground uppercase font-bold tracking-wider">
                                    Ruang Kerja Admin
                                </DropdownMenuLabel>

                                <!-- Superadmin: 2 Pintu Utama (Pusat Kontrol Operasional & Pengaturan Sistem) -->
                                <template v-if="isSuperadmin">
                                    <DropdownMenuItem as-child>
                                        <Link 
                                            :href="route('admin.dashboard')"
                                            class="cursor-pointer flex items-center justify-between py-2"
                                            :class="{ 'bg-purple-500/10 font-bold': route().current('admin.dashboard') }"
                                        >
                                            <div class="flex items-center gap-2.5">
                                                <div class="h-7 w-7 rounded-lg bg-purple-500/10 text-purple-600 flex items-center justify-center shrink-0">
                                                    <LayoutDashboard class="h-4 w-4" />
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="text-xs">Pusat Kontrol Admin</span>
                                                    <span class="text-[10px] text-muted-foreground">Operasional Harian</span>
                                                </div>
                                            </div>
                                            <Badge v-if="route().current('admin.dashboard')" variant="secondary" class="text-[9px]">Aktif</Badge>
                                        </Link>
                                    </DropdownMenuItem>

                                    <DropdownMenuItem as-child>
                                        <Link 
                                            :href="route('admin.website.index')"
                                            class="cursor-pointer flex items-center justify-between py-2"
                                            :class="{ 'bg-purple-500/10 font-bold': route().current('admin.website.*') }"
                                        >
                                            <div class="flex items-center gap-2.5">
                                                <div class="h-7 w-7 rounded-lg bg-indigo-500/10 text-indigo-600 flex items-center justify-center shrink-0">
                                                    <Settings class="h-4 w-4" />
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="text-xs">Pengaturan Sistem</span>
                                                    <span class="text-[10px] text-muted-foreground">Metode Bayar, FAQ & Legal</span>
                                                </div>
                                            </div>
                                            <Badge v-if="route().current('admin.website.*')" variant="secondary" class="text-[9px]">Aktif</Badge>
                                        </Link>
                                    </DropdownMenuItem>
                                </template>

                                <!-- Admin Biasa: Masuk ke Pusat Kontrol Admin sesuai hak akses -->
                                <template v-else>
                                    <DropdownMenuItem as-child>
                                        <Link 
                                            :href="route('admin.dashboard')"
                                            class="cursor-pointer flex items-center justify-between py-2"
                                            :class="{ 'bg-purple-500/10 font-bold': route().current('admin.dashboard') }"
                                        >
                                            <div class="flex items-center gap-2.5">
                                                <div class="h-7 w-7 rounded-lg bg-purple-500/10 text-purple-600 flex items-center justify-center shrink-0">
                                                    <LayoutDashboard class="h-4 w-4" />
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="text-xs">Pusat Kontrol Admin</span>
                                                    <span class="text-[10px] text-muted-foreground">Ruang Kerja Admin</span>
                                                </div>
                                            </div>
                                            <Badge v-if="route().current('admin.dashboard')" variant="secondary" class="text-[9px]">Aktif</Badge>
                                        </Link>
                                    </DropdownMenuItem>
                                </template>
                            </template>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Static display if user has only 1 module AND NO admin access -->
                    <div 
                        v-else
                        class="w-full flex items-center bg-card border rounded-lg shadow-2xs text-foreground"
                        :class="isCollapsed ? 'justify-center h-10 w-10 mx-auto' : 'justify-start h-10 px-3'"
                    >
                        <div class="flex items-center gap-2.5 truncate">
                            <div 
                                class="h-6 w-6 rounded-lg flex items-center justify-center shrink-0"
                                :class="activeModule === 'keuangan' ? 'bg-primary/10 text-primary' : 'bg-emerald-500/10 text-emerald-600'"
                            >
                                <Wallet v-if="activeModule === 'keuangan'" class="h-3.5 w-3.5" />
                                <Send v-else class="h-3.5 w-3.5" />
                            </div>
                            <span v-if="!isCollapsed" class="font-bold text-xs truncate">
                                {{ currentWorkspaceLabel }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Navigation Links List -->
                <nav class="p-3 space-y-1 text-sm font-medium">
                    <!-- Keuangan Menu Set (Cleaned up: No admin links inside finance workspace) -->
                    <template v-if="activeModule === 'keuangan'">
                        <Link 
                            :href="route('keuangan.dashboard')"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all font-semibold"
                            :class="route().current('keuangan.dashboard') ? 'bg-primary text-primary-foreground shadow-2xs' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-foreground'"
                            :title="isCollapsed ? 'Ringkasan Kas' : ''"
                        >
                            <LayoutDashboard class="h-4 w-4 shrink-0" />
                            <span v-if="!isCollapsed" class="truncate text-xs">Ringkasan Kas</span>
                        </Link>
                        <Link 
                            :href="route('keuangan.wallets.index')"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all font-semibold"
                            :class="route().current('keuangan.wallets.*') ? 'bg-primary text-primary-foreground shadow-2xs' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-foreground'"
                            :title="isCollapsed ? 'Dompet Saya' : ''"
                        >
                            <CreditCard class="h-4 w-4 shrink-0" />
                            <span v-if="!isCollapsed" class="truncate text-xs">Dompet Saya</span>
                        </Link>
                        <Link 
                            :href="route('keuangan.transactions.index')"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all font-semibold"
                            :class="route().current('keuangan.transactions.*') ? 'bg-primary text-primary-foreground shadow-2xs' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-foreground'"
                            :title="isCollapsed ? 'Transaksi Mutasi' : ''"
                        >
                            <ArrowLeftRight class="h-4 w-4 shrink-0" />
                            <span v-if="!isCollapsed" class="truncate text-xs">Transaksi & Mutasi</span>
                        </Link>
                        <Link 
                            :href="route('keuangan.debts.index')"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all font-semibold"
                            :class="route().current('keuangan.debts.*') ? 'bg-primary text-primary-foreground shadow-2xs' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-foreground'"
                            :title="isCollapsed ? 'Radar Utang-Piutang' : ''"
                        >
                            <Scale class="h-4 w-4 shrink-0" />
                            <span v-if="!isCollapsed" class="truncate text-xs">Radar Utang-Piutang</span>
                        </Link>
                        <Link 
                            :href="route('keuangan.reports.index')"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all font-semibold"
                            :class="route().current('keuangan.reports.*') ? 'bg-primary text-primary-foreground shadow-2xs' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-foreground'"
                            :title="isCollapsed ? 'Laporan Arus Kas' : ''"
                        >
                            <BarChart3 class="h-4 w-4 shrink-0" />
                            <span v-if="!isCollapsed" class="truncate text-xs">Laporan Arus Kas</span>
                        </Link>
                    </template>

                    <!-- WaSender Menu Set (Chrome Extension Architecture) -->
                    <template v-else-if="activeModule === 'wasender'">
                        <Link 
                            :href="route('wasender.dashboard')"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all font-semibold"
                            :class="route().current('wasender.dashboard') ? 'bg-primary text-primary-foreground shadow-2xs' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-foreground'"
                            :title="isCollapsed ? 'Status & Ekstensi' : ''"
                        >
                            <LayoutDashboard class="h-4 w-4 shrink-0" />
                            <span v-if="!isCollapsed" class="truncate text-xs">Status & Ekstensi</span>
                        </Link>
                        <Link 
                            :href="route('wasender.contacts.index')"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all font-semibold"
                            :class="route().current('wasender.contacts.*') ? 'bg-primary text-primary-foreground shadow-2xs' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-foreground'"
                            :title="isCollapsed ? 'Buku Kontak & Grup' : ''"
                        >
                            <Users class="h-4 w-4 shrink-0" />
                            <span v-if="!isCollapsed" class="truncate text-xs">Buku Kontak & Grup</span>
                        </Link>
                        <Link 
                            :href="route('wasender.templates.index')"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all font-semibold"
                            :class="route().current('wasender.templates.*') ? 'bg-primary text-primary-foreground shadow-2xs' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-foreground'"
                            :title="isCollapsed ? 'Template Pesan' : ''"
                        >
                            <MessageSquare class="h-4 w-4 shrink-0" />
                            <span v-if="!isCollapsed" class="truncate text-xs">Template Pesan</span>
                        </Link>
                        <a 
                            :href="route('wasender.download.extension')"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all font-semibold text-muted-foreground hover:bg-sidebar-accent hover:text-foreground"
                            :title="isCollapsed ? 'Unduh Ekstensi' : ''"
                        >
                            <Download class="h-4 w-4 shrink-0 text-primary" />
                            <span v-if="!isCollapsed" class="truncate text-xs">Unduh Ekstensi</span>
                        </a>
                    </template>

                    <!-- Admin Workspace Menu Set (Shown when activeModule === 'admin') -->
                    <template v-else-if="activeModule === 'admin'">
                        <!-- Pusat Kontrol Admin (Semua Admin) -->
                        <Link 
                            :href="route('admin.dashboard')"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all font-semibold"
                            :class="route().current('admin.dashboard') ? 'bg-primary text-primary-foreground shadow-2xs' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-foreground'"
                            :title="isCollapsed ? 'Pusat Kontrol Admin' : ''"
                        >
                            <LayoutDashboard class="h-4 w-4 shrink-0" />
                            <span v-if="!isCollapsed" class="truncate text-xs">Pusat Kontrol Admin</span>
                        </Link>

                        <!-- Pengaturan Sistem & Website (Khusus Superadmin) -->
                        <Link 
                            v-if="isSuperadmin"
                            :href="route('admin.website.index')"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all font-semibold"
                            :class="route().current('admin.website.*') ? 'bg-primary text-primary-foreground shadow-2xs' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-foreground'"
                            :title="isCollapsed ? 'Pengaturan Sistem' : ''"
                        >
                            <Settings class="h-4 w-4 shrink-0" />
                            <span v-if="!isCollapsed" class="truncate text-xs">Pengaturan Sistem</span>
                        </Link>
                    </template>
                </nav>
            </div>

            <!-- Sidebar Bottom: User Profile & Quick Actions -->
            <div class="p-3 border-t bg-card/40">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button 
                            variant="ghost" 
                            class="w-full flex items-center hover:bg-muted p-2 rounded-xl transition-all font-sans"
                            :class="isCollapsed ? 'justify-center h-10 w-10 mx-auto px-0' : 'justify-between h-auto'"
                        >
                            <div class="flex items-center gap-2.5 overflow-hidden">
                                <Avatar class="h-8 w-8 border shrink-0">
                                    <AvatarFallback class="bg-primary/10 text-primary font-bold text-xs">
                                        {{ getInitials(user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div v-if="!isCollapsed" class="flex flex-col text-left truncate">
                                    <span class="text-xs font-bold leading-tight text-foreground truncate">{{ user.name }}</span>
                                    <span class="text-[10px] font-medium text-muted-foreground truncate">
                                        {{ ($page.props.auth?.user?.username || user.username) ? '@' + ($page.props.auth?.user?.username || user.username) : user.email }}
                                    </span>
                                </div>
                            </div>
                            <ChevronDown v-if="!isCollapsed" class="h-3.5 w-3.5 text-muted-foreground shrink-0 ml-1" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="w-56 font-sans" align="end" side="top">
                        <DropdownMenuItem as-child>
                            <Link :href="route('profile.edit', { section: 'pro', app: activeModule })" class="cursor-pointer flex items-center gap-2.5 py-2 text-xs font-semibold text-amber-600 dark:text-amber-400">
                                <Sparkles class="h-4 w-4 shrink-0 text-amber-500 fill-amber-400" />
                                <span>Upgrade Paket Pro</span>
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link :href="route('profile.edit')" class="cursor-pointer flex items-center gap-2.5 py-2 text-xs font-semibold">
                                <UserIcon class="h-4 w-4 text-primary shrink-0" />
                                <span>Profil & Keamanan</span>
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="logout" class="text-destructive cursor-pointer flex items-center gap-2.5 py-2 text-xs font-semibold">
                            <LogOut class="h-4 w-4 shrink-0" />
                            <span>Keluar (Logout)</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
            <main class="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto pb-[calc(5.5rem+env(safe-area-inset-bottom,0px))] md:pb-8">
                <slot />
            </main>
        </div>

        <!-- Mobile Bottom Navigation Bar (Native App Style with Safe Area) -->
        <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-card/95 backdrop-blur-md border-t pb-safe shadow-lg">
            <div class="h-16 px-1 flex items-center justify-around">
                <!-- Keuangan Mobile Nav Items -->
                <template v-if="activeModule === 'keuangan'">
                <!-- 1. Ringkasan -->
                <Link
                    :href="route('keuangan.dashboard')"
                    class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-all"
                    :class="route().current('keuangan.dashboard') ? 'text-primary font-bold' : 'text-muted-foreground hover:text-foreground'"
                >
                    <div class="relative">
                        <LayoutDashboard class="h-5 w-5" />
                        <span v-if="route().current('keuangan.dashboard')" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-primary"></span>
                    </div>
                    <span class="text-[10px] mt-1 leading-none tracking-tight">Ringkasan</span>
                </Link>

                <!-- 2. Dompet -->
                <Link
                    :href="route('keuangan.wallets.index')"
                    class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-all"
                    :class="route().current('keuangan.wallets.*') ? 'text-primary font-bold' : 'text-muted-foreground hover:text-foreground'"
                >
                    <div class="relative">
                        <Wallet class="h-5 w-5" />
                        <span v-if="route().current('keuangan.wallets.*')" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-primary"></span>
                    </div>
                    <span class="text-[10px] mt-1 leading-none tracking-tight">Dompet</span>
                </Link>

                <!-- 3. CENTER '+' QUICK RECORD BUTTON -->
                <div class="flex flex-col items-center justify-center flex-1 h-full py-0">
                    <button
                        type="button"
                        @click="openGlobalTransactionModal('expense')"
                        class="w-12 h-12 -translate-y-3.5 rounded-full bg-gradient-to-tr from-primary to-blue-500 text-primary-foreground shadow-lg shadow-primary/30 flex items-center justify-center border-4 border-background active:scale-90 transition-all cursor-pointer"
                        title="Catat Mutasi Cepat (+)"
                    >
                        <Plus class="h-6 w-6 stroke-[3]" />
                    </button>
                    <span class="text-[9px] -mt-2 font-bold text-primary tracking-tight">Catat</span>
                </div>

                <!-- 4. Mutasi -->
                <Link
                    :href="route('keuangan.transactions.index')"
                    class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-all"
                    :class="route().current('keuangan.transactions.*') ? 'text-primary font-bold' : 'text-muted-foreground hover:text-foreground'"
                >
                    <div class="relative">
                        <ArrowLeftRight class="h-5 w-5" />
                        <span v-if="route().current('keuangan.transactions.*')" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-primary"></span>
                    </div>
                    <span class="text-[10px] mt-1 leading-none tracking-tight">Mutasi</span>
                </Link>

                <!-- 5. Laporan -->
                <Link
                    :href="route('keuangan.reports.index')"
                    class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-all"
                    :class="route().current('keuangan.reports.*') ? 'text-primary font-bold' : 'text-muted-foreground hover:text-foreground'"
                >
                    <div class="relative">
                        <BarChart3 class="h-5 w-5" />
                        <span v-if="route().current('keuangan.reports.*')" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-primary"></span>
                    </div>
                    <span class="text-[10px] mt-1 leading-none tracking-tight">Laporan</span>
                </Link>
            </template>

            <!-- WaSender Mobile Nav Items -->
            <template v-else-if="activeModule === 'wasender'">
                <Link
                    :href="route('wasender.dashboard')"
                    class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-all"
                    :class="route().current('wasender.dashboard') ? 'text-primary font-bold' : 'text-muted-foreground hover:text-foreground'"
                >
                    <div class="relative">
                        <LayoutDashboard class="h-5 w-5" />
                        <span v-if="route().current('wasender.dashboard')" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-primary"></span>
                    </div>
                    <span class="text-[10px] mt-1 leading-none tracking-tight">Status</span>
                </Link>

                <Link
                    :href="route('wasender.contacts.index')"
                    class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-all"
                    :class="route().current('wasender.contacts.*') ? 'text-primary font-bold' : 'text-muted-foreground hover:text-foreground'"
                >
                    <div class="relative">
                        <Users class="h-5 w-5" />
                        <span v-if="route().current('wasender.contacts.*')" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-primary"></span>
                    </div>
                    <span class="text-[10px] mt-1 leading-none tracking-tight">Kontak</span>
                </Link>

                <Link
                    :href="route('wasender.templates.index')"
                    class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-all"
                    :class="route().current('wasender.templates.*') ? 'text-primary font-bold' : 'text-muted-foreground hover:text-foreground'"
                >
                    <div class="relative">
                        <MessageSquare class="h-5 w-5" />
                        <span v-if="route().current('wasender.templates.*')" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-primary"></span>
                    </div>
                    <span class="text-[10px] mt-1 leading-none tracking-tight">Template</span>
                </Link>

                <a
                    :href="route('wasender.download.extension')"
                    class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-all text-muted-foreground hover:text-foreground"
                >
                    <div class="relative">
                        <Download class="h-5 w-5" />
                    </div>
                    <span class="text-[10px] mt-1 leading-none tracking-tight">Ekstensi</span>
                </a>

                <Link
                    :href="route('portal')"
                    class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-all text-muted-foreground hover:text-foreground"
                >
                    <div class="relative">
                        <Layers class="h-5 w-5" />
                    </div>
                    <span class="text-[10px] mt-1 leading-none tracking-tight">Portal</span>
                </Link>
            </template>

            <!-- Admin Mobile Nav Items -->
            <template v-else-if="activeModule === 'admin'">
                <!-- Superadmin & Admin Mobile Nav Items -->
                <Link
                    :href="route('admin.dashboard')"
                    class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-all"
                    :class="route().current('admin.dashboard') ? 'text-primary font-bold' : 'text-muted-foreground hover:text-foreground'"
                >
                    <div class="relative">
                        <LayoutDashboard class="h-5 w-5" />
                        <span v-if="route().current('admin.dashboard')" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-primary"></span>
                    </div>
                    <span class="text-[10px] mt-1 leading-none tracking-tight">Pusat Kontrol</span>
                </Link>

                <Link
                    v-if="isSuperadmin"
                    :href="route('admin.website.index')"
                    class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-all"
                    :class="route().current('admin.website.*') ? 'text-primary font-bold' : 'text-muted-foreground hover:text-foreground'"
                >
                    <div class="relative">
                        <Settings class="h-5 w-5" />
                        <span v-if="route().current('admin.website.*')" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-primary"></span>
                    </div>
                    <span class="text-[10px] mt-1 leading-none tracking-tight">Pengaturan</span>
                </Link>

                <Link
                    :href="route('portal')"
                    class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-all text-muted-foreground hover:text-foreground"
                >
                    <div class="relative">
                        <Layers class="h-5 w-5" />
                    </div>
                    <span class="text-[10px] mt-1 leading-none tracking-tight">Portal</span>
                </Link>
            </template>
            </div>
        </nav>

        <!-- Floating Flash Toast Notification -->
        <transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="toast.show"
                class="fixed bottom-20 right-4 sm:bottom-5 sm:right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-xl border shadow-xl bg-card text-card-foreground text-xs font-semibold max-w-sm"
                :class="toast.type === 'success' ? 'border-emerald-500/30' : 'border-destructive/30'"
            >
                <CheckCircle2 v-if="toast.type === 'success'" class="w-4 h-4 text-emerald-500 shrink-0" />
                <AlertCircle v-else class="w-4 h-4 text-destructive shrink-0" />
                <span class="flex-1 leading-snug">{{ toast.message }}</span>
                <button @click="toast.show = false" class="text-muted-foreground hover:text-foreground">
                    <X class="w-3.5 h-3.5" />
                </button>
            </div>
        </transition>

        <!-- Floating Support Chat Bubble -->
        <SupportChatBubble />

        <!-- Global Quick Transaction Modal for Mobile & Keuangan -->
        <TransactionModal
            v-if="activeModule === 'keuangan'"
            :is-open="isGlobalTxModalOpen"
            :wallets="($page.props.keuangan_wallets && $page.props.keuangan_wallets.length > 0) ? $page.props.keuangan_wallets : ($page.props.wallets || [])"
            :initial-type="globalTxModalType"
            @close="isGlobalTxModalOpen = false"
            @saved="isGlobalTxModalOpen = false"
        />
    </div>
</template>

