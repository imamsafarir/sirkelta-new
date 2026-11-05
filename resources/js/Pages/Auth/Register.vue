<script setup>
import { ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    ArrowLeft,
    Loader2,
    UserPlus,
    Wallet,
    Send,
    Gift,
    AtSign,
    Eye,
    EyeOff,
    Check,
    CheckCircle2,
    XCircle,
} from 'lucide-vue-next';

const props = defineProps({
    referralCode: {
        type: String,
        default: '',
    },
});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const isCheckingUsername = ref(false);
const usernameCheckStatus = ref(null); // 'available' | 'unavailable' | 'invalid' | null
const usernameCheckMessage = ref('');
let usernameCheckTimeout = null;

const form = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    referral_code: props.referralCode || '',
    initial_modules: ['keuangan'],
});

const onNameInput = () => {
    if (!form.username || form.username === form.name.toLowerCase().replace(/[^a-z0-9]/g, '')) {
        form.username = form.name.toLowerCase().replace(/[^a-z0-9]/g, '');
    }
};

const checkUsername = async (val) => {
    const clean = (val || '').toLowerCase().trim();
    if (!clean || clean.length < 3) {
        usernameCheckStatus.value = clean ? 'invalid' : null;
        usernameCheckMessage.value = clean ? 'Minimal 3 karakter.' : '';
        isCheckingUsername.value = false;
        return;
    }

    isCheckingUsername.value = true;
    try {
        const res = await fetch(`/check-username?username=${encodeURIComponent(clean)}`, {
            headers: {
                'Accept': 'application/json',
                'X-Silent': 'true',
            },
        });
        const data = await res.json();
        if (form.username === val) {
            usernameCheckStatus.value = data.available ? 'available' : 'unavailable';
            usernameCheckMessage.value = data.message;
        }
    } catch {
        // Fallback silently on connection issue
    } finally {
        if (form.username === val) {
            isCheckingUsername.value = false;
        }
    }
};

watch(() => form.username, (newVal) => {
    clearTimeout(usernameCheckTimeout);
    if (!newVal) {
        usernameCheckStatus.value = null;
        usernameCheckMessage.value = '';
        isCheckingUsername.value = false;
        return;
    }
    // Prevent spaces and force lowercase
    if (/\s/.test(newVal) || /[A-Z]/.test(newVal)) {
        form.username = newVal.replace(/\s+/g, '').toLowerCase();
        return;
    }
    usernameCheckTimeout = setTimeout(() => {
        checkUsername(newVal);
    }, 350);
});

const toggleModule = (moduleKey) => {
    const idx = form.initial_modules.indexOf(moduleKey);
    if (idx > -1) {
        form.initial_modules.splice(idx, 1);
    } else {
        form.initial_modules.push(moduleKey);
    }
    if (form.initial_modules.length > 0 && form.errors.initial_modules) {
        form.clearErrors('initial_modules');
    }
};

const submit = () => {
    form.clearErrors();

    if (!form.name.trim()) {
        form.setError('name', 'Nama lengkap wajib diisi.');
        return;
    }
    if (!form.username.trim() || form.username.trim().length < 3) {
        form.setError('username', 'Username minimal 3 karakter.');
        return;
    }
    if (usernameCheckStatus.value === 'unavailable') {
        form.setError('username', 'Username ini sudah digunakan oleh akun lain.');
        return;
    }
    if (!form.email.trim()) {
        form.setError('email', 'Alamat email wajib diisi.');
        return;
    }
    if (!form.password || form.password.length < 8) {
        form.setError('password', 'Kata sandi minimal 8 karakter.');
        return;
    }
    if (form.password !== form.password_confirmation) {
        form.setError('password_confirmation', 'Konfirmasi kata sandi tidak cocok.');
        return;
    }
    if (!form.initial_modules || form.initial_modules.length === 0) {
        form.setError('initial_modules', 'Pilih minimal satu modul aplikasi.');
        return;
    }

    form.post(route('register.attempt'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Daftar Akun Baru - Sirkelta" />

    <div class="min-h-screen bg-muted/30 flex flex-col justify-center items-center p-4 sm:p-6 font-sans selection:bg-primary selection:text-primary-foreground">
        <!-- Back to home -->
        <div class="w-full max-w-md mb-6">
            <Link :href="route('home')" class="inline-flex items-center gap-1.5 text-xs sm:text-sm text-muted-foreground hover:text-foreground transition-colors font-semibold">
                <ArrowLeft class="h-4 w-4" />
                <span>Kembali ke Beranda</span>
            </Link>
        </div>

        <Card class="w-full max-w-md shadow-md border-border/80">
            <CardHeader class="space-y-1 text-center">
                <div class="flex justify-center">
                    <img src="/images/logo.png" alt="Sirkelta Logo" class="h-14 w-14 rounded-2xl shadow-sm mb-2 object-contain" />
                </div>
                <CardTitle class="text-2xl font-extrabold tracking-tight">Daftar Akun Sirkelta</CardTitle>
                <CardDescription class="text-xs sm:text-sm">
                    Buat akun satu atap untuk mengakses seluruh aplikasi ekosistem
                </CardDescription>
            </CardHeader>

            <form @submit.prevent="submit">
                <CardContent class="space-y-4 pt-2 pb-5">
                    <div class="space-y-1.5">
                        <Label for="name">Nama Lengkap</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="John Doe"
                            required
                            autofocus
                            autocomplete="name"
                            @input="onNameInput"
                            :class="{ 'border-destructive': form.errors.name }"
                        />
                        <p v-if="form.errors.name" class="text-xs text-destructive font-medium">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <Label for="username" class="flex items-center gap-1">
                                    <AtSign class="h-3 w-3 text-muted-foreground" />
                                    <span>Username</span>
                                </Label>
                                <span v-if="isCheckingUsername" class="text-[10px] text-muted-foreground flex items-center gap-1">
                                    <Loader2 class="w-2.5 h-2.5 animate-spin" /> Cek...
                                </span>
                                <span v-else-if="usernameCheckStatus === 'available'" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-0.5">
                                    <Check class="w-3 h-3" /> Tersedia
                                </span>
                                <span v-else-if="usernameCheckStatus === 'unavailable'" class="text-[10px] text-destructive font-bold flex items-center gap-0.5">
                                    <XCircle class="w-3 h-3" /> Terpakai
                                </span>
                            </div>
                            <div class="relative">
                                <Input
                                    id="username"
                                    v-model="form.username"
                                    type="text"
                                    placeholder="johndoe"
                                    required
                                    autocomplete="username"
                                    class="pr-8"
                                    :class="{
                                        'border-destructive focus-visible:ring-destructive': form.errors.username || usernameCheckStatus === 'unavailable',
                                        'border-emerald-500 focus-visible:ring-emerald-500': usernameCheckStatus === 'available' && !form.errors.username,
                                    }"
                                />
                                <div class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">
                                    <Loader2 v-if="isCheckingUsername" class="w-3.5 h-3.5 text-muted-foreground animate-spin" />
                                    <CheckCircle2 v-else-if="usernameCheckStatus === 'available'" class="w-4 h-4 text-emerald-500" />
                                    <XCircle v-else-if="usernameCheckStatus === 'unavailable'" class="w-4 h-4 text-destructive" />
                                </div>
                            </div>
                            <p v-if="form.errors.username" class="text-xs text-destructive font-medium">
                                {{ form.errors.username }}
                            </p>
                            <p v-else-if="usernameCheckStatus === 'available'" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1">
                                <Check class="w-3 h-3" /> {{ usernameCheckMessage }}
                            </p>
                            <p v-else-if="usernameCheckStatus === 'unavailable'" class="text-[11px] text-destructive font-medium flex items-center gap-1">
                                <XCircle class="w-3 h-3" /> {{ usernameCheckMessage }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="email">Alamat Email</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                placeholder="nama@email.com"
                                required
                                autocomplete="email"
                                :class="{ 'border-destructive': form.errors.email }"
                            />
                            <p v-if="form.errors.email" class="text-xs text-destructive font-medium">
                                {{ form.errors.email }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="password">Kata Sandi</Label>
                            <div class="relative">
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    placeholder="Min. 8 karakter"
                                    required
                                    autocomplete="new-password"
                                    class="pr-9"
                                    :class="{ 'border-destructive': form.errors.password }"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                    tabindex="-1"
                                    :title="showPassword ? 'Sembunyikan sandi' : 'Lihat sandi'"
                                >
                                    <EyeOff v-if="showPassword" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="text-xs text-destructive font-medium">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="password_confirmation">Konfirmasi Sandi</Label>
                            <div class="relative">
                                <Input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    :type="showPasswordConfirmation ? 'text' : 'password'"
                                    placeholder="Ulangi sandi"
                                    required
                                    autocomplete="new-password"
                                    class="pr-9"
                                    :class="{ 'border-destructive': form.errors.password_confirmation }"
                                />
                                <button
                                    type="button"
                                    @click="showPasswordConfirmation = !showPasswordConfirmation"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                    tabindex="-1"
                                    :title="showPasswordConfirmation ? 'Sembunyikan sandi' : 'Lihat sandi'"
                                >
                                    <EyeOff v-if="showPasswordConfirmation" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <p v-if="form.errors.password_confirmation" class="text-xs text-destructive font-medium">
                                {{ form.errors.password_confirmation }}
                            </p>
                        </div>
                    </div>

                    <!-- Username Referral Opsional -->
                    <div class="space-y-1.5 pt-1">
                        <Label for="referral_code" class="flex items-center gap-1.5">
                            <Gift class="h-3.5 w-3.5 text-amber-500" />
                            <span>Username Referral (Opsional)</span>
                        </Label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-muted-foreground">@</span>
                            <Input
                                id="referral_code"
                                v-model="form.referral_code"
                                type="text"
                                placeholder="username teman"
                                class="pl-7 text-xs font-medium"
                                :class="{ 'border-destructive': form.errors.referral_code }"
                            />
                        </div>
                        <p v-if="form.errors.referral_code" class="text-xs text-destructive font-medium">
                            {{ form.errors.referral_code }}
                        </p>
                    </div>

                    <!-- Module Activation Selector -->
                    <div class="pt-3.5 border-t space-y-2.5">
                        <Label class="text-xs font-semibold text-foreground/90">
                            Pilih Modul yang Ingin Diaktifkan:
                        </Label>

                        <div class="grid grid-cols-2 gap-3 pt-0.5">
                            <div
                                class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all select-none focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                :class="form.initial_modules.includes('keuangan') ? 'border-primary bg-primary/10 text-foreground ring-1 ring-primary/30 shadow-xs' : 'border-border opacity-70 hover:opacity-100 hover:border-muted-foreground/40'"
                                role="checkbox"
                                :aria-checked="form.initial_modules.includes('keuangan')"
                                tabindex="0"
                                @click="toggleModule('keuangan')"
                                @keydown.space.prevent="toggleModule('keuangan')"
                                @keydown.enter.prevent="toggleModule('keuangan')"
                            >
                                <Checkbox
                                    :checked="form.initial_modules.includes('keuangan')"
                                    class="pointer-events-none"
                                />
                                <div class="text-xs">
                                    <p class="font-bold flex items-center gap-1.5">
                                        <Wallet class="h-3.5 w-3.5 text-primary" />
                                        Keuangan
                                    </p>
                                    <span class="text-[10px] text-muted-foreground">Catat Kas</span>
                                </div>
                            </div>

                            <div
                                class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all select-none focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                :class="form.initial_modules.includes('wasender') ? 'border-primary bg-primary/10 text-foreground ring-1 ring-primary/30 shadow-xs' : 'border-border opacity-70 hover:opacity-100 hover:border-muted-foreground/40'"
                                role="checkbox"
                                :aria-checked="form.initial_modules.includes('wasender')"
                                tabindex="0"
                                @click="toggleModule('wasender')"
                                @keydown.space.prevent="toggleModule('wasender')"
                                @keydown.enter.prevent="toggleModule('wasender')"
                            >
                                <Checkbox
                                    :checked="form.initial_modules.includes('wasender')"
                                    class="pointer-events-none"
                                />
                                <div class="text-xs">
                                    <p class="font-bold flex items-center gap-1.5">
                                        <Send class="h-3.5 w-3.5 text-emerald-600" />
                                        WA Sender
                                    </p>
                                    <span class="text-[10px] text-muted-foreground">Broadcast</span>
                                </div>
                            </div>
                        </div>
                        <p v-if="form.errors.initial_modules" class="text-xs text-destructive font-medium">
                            {{ form.errors.initial_modules }}
                        </p>
                    </div>
                </CardContent>

                <CardFooter class="flex flex-col space-y-3 pt-5 pb-5">
                    <Button
                        type="submit"
                        class="w-full gap-2 font-bold text-xs sm:text-sm cursor-pointer"
                        :disabled="form.processing || usernameCheckStatus === 'unavailable' || isCheckingUsername"
                    >
                        <Loader2 v-if="form.processing || isCheckingUsername" class="h-4 w-4 animate-spin" />
                        <UserPlus v-else class="h-4 w-4" />
                        <span>Daftar Akun</span>
                    </Button>

                    <div class="text-center text-xs sm:text-sm text-muted-foreground">
                        Sudah punya akun?
                        <Link :href="route('login')" class="text-primary hover:underline font-bold ml-1">
                            Masuk di sini
                        </Link>
                    </div>
                </CardFooter>
            </form>
        </Card>
    </div>
</template>
