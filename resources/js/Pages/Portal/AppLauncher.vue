<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import {
    Wallet,
    Send,
    LogOut,
    ArrowRight,
    Lock,
    ChevronDown,
    Sparkles,
    CheckCircle2,
    ShieldCheck,
    User,
    X,
    UserCheck,
    Plus,
    Loader2,
} from 'lucide-vue-next';

const props = defineProps({
    apps: {
        type: Array,
        required: true,
    },
    user: {
        type: Object,
        required: true,
    },
    showOnboarding: {
        type: Boolean,
        default: false,
    },
});

const isModalOpen = ref(props.showOnboarding);

const closeModal = () => {
    isModalOpen.value = false;
};

const logout = () => {
    if (typeof navigator !== 'undefined' && 'serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'PURGE_AUTH_CACHE' });
    }
    router.post(route('logout'));
};

const getInitials = (name) => {
    return (
        name
            ?.split(' ')
            .map((n) => n[0])
            .join('')
            .substring(0, 2)
            .toUpperCase() || 'SK'
    );
};

const activateForm = useForm({
    module: '',
});

const handleActivate = (moduleId) => {
    activateForm.module = moduleId;
    activateForm.post(route('profile.modules.activate'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Pilih Aplikasi - Sirkelta" />

    <div class="min-h-screen bg-muted/20 text-foreground flex flex-col font-sans selection:bg-primary selection:text-primary-foreground">
        <!-- Top Navbar with Safe Area -->
        <header class="sticky top-0 z-40 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 pt-safe">
            <div class="container mx-auto flex h-16 items-center justify-between px-4 sm:px-8 max-w-5xl">
                <!-- Brand -->
                <div class="flex items-center gap-3">
                    <img src="/images/logo.png" alt="Sirkelta Logo" class="h-9 w-9 rounded-xl shadow-xs object-contain" />
                    <span class="text-xl font-extrabold tracking-tight">Sirkelta</span>
                </div>

                <!-- User Account Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" class="flex items-center gap-2.5 h-10 px-2 sm:px-3 rounded-full hover:bg-muted">
                            <Avatar class="h-8 w-8 border">
                                <AvatarFallback class="bg-primary/10 text-primary font-bold text-xs">
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="hidden sm:flex flex-col text-left">
                                <span class="text-xs font-semibold leading-none">{{ user.name }}</span>
                                <span class="text-[10px] text-muted-foreground mt-0.5">{{ user.email }}</span>
                            </div>
                            <ChevronDown class="h-4 w-4 text-muted-foreground" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="w-56" align="end">
                        <DropdownMenuLabel>
                            <p class="text-xs font-semibold">{{ user.name }}</p>
                            <p class="text-[11px] text-muted-foreground font-normal">{{ user.email }}</p>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem as-child>
                            <Link :href="route('profile.edit')" class="flex items-center w-full cursor-pointer">
                                <User class="h-4 w-4 mr-2" />
                                <span>Pengaturan Profil</span>
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="logout" class="text-destructive cursor-pointer">
                            <LogOut class="h-4 w-4 mr-2" />
                            <span>Keluar</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>

        <!-- Main Body -->
        <main class="flex-1 flex flex-col justify-center py-8 sm:py-16">
            <div class="container mx-auto px-4 sm:px-8 max-w-4xl">
                <!-- Welcome & Onboarding Banner -->
                <div
                    v-if="showOnboarding"
                    class="mb-6 p-4 rounded-xl border border-emerald-500/20 bg-emerald-500/5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-xs animate-in fade-in"
                >
                    <div class="flex items-start sm:items-center gap-3">
                        <div class="h-9 w-9 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            <CheckCircle2 class="h-5 w-5" />
                        </div>
                        <div class="text-xs sm:text-sm">
                            <p class="font-bold text-foreground">Akun Berhasil Diaktifkan & Terverifikasi! 🎉</p>
                            <p class="text-muted-foreground text-[11px] sm:text-xs">
                                Seluruh data profil Anda sudah siap. Silakan pilih ruang kerja (Keuangan atau WaSender) di bawah ini untuk mulai menggunakan aplikasi.
                            </p>
                        </div>
                    </div>
                    <Link :href="route('profile.edit')" class="w-full sm:w-auto">
                        <Button variant="outline" size="sm" class="gap-1.5 font-semibold shrink-0 w-full sm:w-auto text-xs cursor-pointer">
                            <span>Pengaturan Profil</span>
                            <ArrowRight class="h-3.5 w-3.5" />
                        </Button>
                    </Link>
                </div>

                <!-- Clean Page Header -->
                <div class="mb-8 text-center sm:text-left">
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                        Pilih Aplikasi
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Pilih ruang kerja yang ingin Anda buka.
                    </p>
                </div>

                <!-- Apps Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <Card
                        v-for="app in apps"
                        :key="app.id"
                        class="border shadow-xs hover:border-primary/50 transition-all flex flex-col justify-between"
                        :class="!app.has_access ? 'bg-muted/10 border-border/80' : 'bg-card'"
                    >
                        <div>
                            <CardHeader class="pb-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div
                                        class="h-11 w-11 rounded-xl flex items-center justify-center shrink-0"
                                        :class="app.id === 'keuangan' ? 'bg-primary/10 text-primary' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'"
                                    >
                                        <Wallet v-if="app.id === 'keuangan'" class="h-5 w-5" />
                                        <Send v-else class="h-5 w-5" />
                                    </div>
                                    <Badge
                                        :variant="app.has_access ? 'secondary' : 'outline'"
                                        class="text-[11px] font-semibold"
                                        :class="!app.has_access ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20' : ''"
                                    >
                                        <span v-if="app.has_access">{{ app.role_label }}</span>
                                        <span v-else class="flex items-center gap-1 font-bold">
                                            <Sparkles class="h-3 w-3 text-emerald-600 dark:text-emerald-400" />
                                            Tersedia Gratis
                                        </span>
                                    </Badge>
                                </div>
                                <CardTitle class="text-xl font-bold">{{ app.name }}</CardTitle>
                                <CardDescription class="text-xs sm:text-sm mt-1 leading-relaxed">
                                    {{ app.description }}
                                </CardDescription>
                            </CardHeader>
                        </div>

                        <CardFooter class="pt-4 pb-5">
                            <template v-if="app.has_access">
                                <Link :href="app.route" class="w-full">
                                    <Button class="w-full gap-2 font-semibold shadow-xs cursor-pointer">
                                        <span>Buka Aplikasi</span>
                                        <ArrowRight class="h-4 w-4" />
                                    </Button>
                                </Link>
                            </template>
                            <template v-else>
                                <Button
                                    type="button"
                                    class="w-full gap-2 font-bold shadow-xs cursor-pointer bg-emerald-600 hover:bg-emerald-700 text-white"
                                    :disabled="activateForm.processing && activateForm.module === app.id"
                                    @click="handleActivate(app.id)"
                                >
                                    <Loader2 v-if="activateForm.processing && activateForm.module === app.id" class="h-4 w-4 animate-spin" />
                                    <Plus v-else class="h-4 w-4" />
                                    <span>{{ activateForm.processing && activateForm.module === app.id ? 'Memasang Aplikasi...' : 'Aktifkan Gratis Sekarang' }}</span>
                                </Button>
                            </template>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </main>

        <!-- Onboarding Guidance Modal Dialog -->
        <Teleport to="body">
            <div
                v-if="isModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm animate-in fade-in duration-200"
            >
                <div class="relative w-full max-w-lg bg-card text-card-foreground border border-border rounded-2xl shadow-xl overflow-hidden p-6 sm:p-7 space-y-5">
                    <!-- Close button -->
                    <button
                        type="button"
                        @click="closeModal"
                        class="absolute right-4 top-4 text-muted-foreground hover:text-foreground p-1 rounded-md transition-colors"
                        title="Tutup"
                    >
                        <X class="w-5 h-5" />
                    </button>

                    <!-- Header icon -->
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
                            <Sparkles class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-lg sm:text-xl font-extrabold tracking-tight">
                                Selamat Datang, {{ user.name }}! 🎉
                            </h3>
                            <p class="text-xs sm:text-sm text-muted-foreground">
                                Akun Anda telah aktif dan email terverifikasi.
                            </p>
                        </div>
                    </div>

                    <!-- Guidance Body -->
                    <div class="space-y-3 bg-muted/40 p-4 rounded-xl border border-border/60 text-xs sm:text-sm">
                        <p class="font-semibold text-foreground text-xs uppercase tracking-wider text-muted-foreground">
                            Status Akun Anda:
                        </p>

                        <!-- Step 1 -->
                        <div class="flex items-start gap-3">
                            <div class="w-6 h-6 rounded-full bg-emerald-500/15 text-emerald-600 flex items-center justify-center shrink-0 mt-0.5">
                                <CheckCircle2 class="w-4 h-4" />
                            </div>
                            <div>
                                <p class="font-bold text-xs sm:text-sm text-foreground">Pendaftaran & Email Terverifikasi</p>
                                <p class="text-[11px] text-muted-foreground">Email {{ user.email }} telah sah terverifikasi dan akun aktif.</p>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div class="flex items-start gap-3">
                            <div class="w-6 h-6 rounded-full bg-emerald-500/15 text-emerald-600 flex items-center justify-center shrink-0 mt-0.5">
                                <UserCheck class="w-4 h-4" />
                            </div>
                            <div>
                                <p class="font-bold text-xs sm:text-sm text-foreground">Data Profil Lengkap</p>
                                <p class="text-[11px] text-muted-foreground">
                                    Nama lengkap, username unik (@{{ user.username || 'user' }}), dan kredensial Anda sudah tersimpan dengan aman.
                                </p>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div class="flex items-start gap-3">
                            <div class="w-6 h-6 rounded-full bg-primary/15 text-primary flex items-center justify-center shrink-0 mt-0.5">
                                <Sparkles class="w-4 h-4" />
                            </div>
                            <div>
                                <p class="font-bold text-xs sm:text-sm text-foreground">Siap Menggunakan Seluruh Modul</p>
                                <p class="text-[11px] text-muted-foreground">Pilih Sirkelta Keuangan untuk kelola arus kas atau WaSender untuk broadcast pesan WhatsApp.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex flex-col sm:flex-row gap-2.5 pt-1">
                        <Button class="flex-1 gap-2 font-bold text-xs sm:text-sm cursor-pointer shadow-xs" @click="closeModal">
                            <span>Mulai Buka Aplikasi</span>
                            <ArrowRight class="h-4 w-4" />
                        </Button>
                        <Link :href="route('profile.edit')" class="w-full sm:w-auto">
                            <Button
                                type="button"
                                variant="outline"
                                class="w-full text-xs font-semibold text-muted-foreground hover:text-foreground cursor-pointer"
                            >
                                Kelola Profil
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
