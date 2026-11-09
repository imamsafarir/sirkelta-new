<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Loader2,
    AtSign,
    Eye,
    EyeOff,
    Check,
    CheckCircle2,
    XCircle,
    UserCheck,
    Lock,
    User,
    Gift,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
    referralCode: {
        type: String,
        default: null,
    },
});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const isCheckingUsername = ref(false);
const usernameCheckStatus = ref(null); // 'available' | 'unavailable' | 'invalid' | null
const usernameCheckMessage = ref('');
let usernameCheckTimeout = null;

const form = useForm({
    email: props.email,
    token: props.token,
    name: '',
    username: '',
    password: '',
    password_confirmation: '',
    referral_code: props.referralCode || '',
});

const onNameInput = () => {
    // Only auto-suggest username if user hasn't explicitly typed something different
    if (!form.username || form.username === form.name.toLowerCase().replace(/[^a-z0-9]/g, '')) {
        form.username = form.name.toLowerCase().replace(/[^a-z0-9_]/g, '');
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
                Accept: 'application/json',
                'X-Silent': 'true',
            },
        });
        const data = await res.json();
        if (form.username === val) {
            usernameCheckStatus.value = data.available ? 'available' : 'unavailable';
            usernameCheckMessage.value = data.message;
        }
    } catch {
        // Fallback silently
    } finally {
        if (form.username === val) {
            isCheckingUsername.value = false;
        }
    }
};

watch(
    () => form.username,
    (newVal) => {
        clearTimeout(usernameCheckTimeout);
        if (!newVal) {
            usernameCheckStatus.value = null;
            usernameCheckMessage.value = '';
            isCheckingUsername.value = false;
            return;
        }
        // Force lowercase and strip forbidden characters
        if (/\s/.test(newVal) || /[A-Z]/.test(newVal)) {
            form.username = newVal.replace(/\s+/g, '').toLowerCase();
            return;
        }
        usernameCheckTimeout = setTimeout(() => {
            checkUsername(newVal);
        }, 350);
    }
);

const passwordsMatch = computed(() => {
    return form.password && form.password_confirmation && form.password === form.password_confirmation;
});

const passwordRules = computed(() => {
    const pwd = form.password || '';
    const confirm = form.password_confirmation || '';
    return {
        minLength: pwd.length >= 8,
        hasLetterAndNumber: /[A-Za-z]/.test(pwd) && /[0-9]/.test(pwd),
        matches: pwd.length > 0 && pwd === confirm,
    };
});

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
        form.setError('username', 'Username ini sudah digunakan.');
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

    form.post(route('register.complete.submit'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Lengkapi Akun Anda - Sirkelta" />

    <div class="min-h-screen bg-muted/30 flex flex-col justify-center items-center p-4 sm:p-6 font-sans selection:bg-primary selection:text-primary-foreground">
        <Card class="w-full max-w-lg shadow-md border-border/80 overflow-hidden">
            <CardHeader class="space-y-2 text-center pb-4">
                <div class="flex justify-center">
                    <img src="/images/logo.png" alt="Sirkelta Logo" class="h-12 w-12 rounded-2xl shadow-xs mb-1 object-contain" />
                </div>
                <CardTitle class="text-2xl font-extrabold tracking-tight">Satu Langkah Terakhir!</CardTitle>
                <CardDescription class="text-xs sm:text-sm">
                    Buat username & kata sandi untuk mengaktifkan akun dan mulai menggunakan Sirkelta.
                </CardDescription>

                <!-- Email Verified Badge Pill -->
                <div class="pt-2 flex justify-center">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 text-xs font-medium">
                        <ShieldCheck class="w-3.5 h-3.5 shrink-0" />
                        <span class="font-bold">Email Terverifikasi:</span>
                        <span class="font-mono text-[11px]">{{ email }}</span>
                    </div>
                </div>
            </CardHeader>

            <form @submit.prevent="submit">
                <CardContent class="space-y-4 pt-2 pb-5">
                    <!-- Nama Lengkap -->
                    <div class="space-y-1.5">
                        <Label for="name" class="flex items-center gap-1.5 text-xs font-semibold">
                            <User class="h-3.5 w-3.5 text-muted-foreground" />
                            <span>Nama Lengkap</span>
                        </Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Contoh: Budi Santoso"
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

                    <!-- Username -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="username" class="flex items-center gap-1.5 text-xs font-semibold">
                                <AtSign class="h-3.5 w-3.5 text-muted-foreground" />
                                <span>Username Akun</span>
                            </Label>
                            <span v-if="isCheckingUsername" class="text-[11px] text-muted-foreground flex items-center gap-1">
                                <Loader2 class="w-3 h-3 animate-spin" /> Memeriksa...
                            </span>
                            <span v-else-if="usernameCheckStatus === 'available'" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-0.5">
                                <Check class="w-3 h-3" /> Tersedia
                            </span>
                            <span v-else-if="usernameCheckStatus === 'unavailable'" class="text-[11px] text-destructive font-bold flex items-center gap-0.5">
                                <XCircle class="w-3 h-3" /> Sudah Digunakan
                            </span>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-muted-foreground pointer-events-none">@</span>
                            <Input
                                id="username"
                                v-model="form.username"
                                type="text"
                                placeholder="budisantoso"
                                required
                                autocomplete="username"
                                class="pl-7 pr-8 font-mono text-xs sm:text-sm"
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
                        <p v-else class="text-[10px] text-muted-foreground">
                            Username digunakan untuk login dan tautan referral Anda.
                        </p>
                    </div>

                    <!-- Password & Konfirmasi -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div class="space-y-1.5">
                            <Label for="password" class="flex items-center gap-1.5 text-xs font-semibold">
                                <Lock class="h-3.5 w-3.5 text-muted-foreground" />
                                <span>Kata Sandi</span>
                            </Label>
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
                            <div class="flex items-center justify-between">
                                <Label for="password_confirmation" class="text-xs font-semibold">Ulangi Sandi</Label>
                                <span v-if="passwordsMatch" class="text-[10px] text-emerald-600 font-bold flex items-center gap-0.5">
                                    <Check class="w-3 h-3" /> Cocok
                                </span>
                            </div>
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

                    <!-- Ketentuan Kata Sandi -->
                    <div class="p-3 rounded-xl bg-muted/40 border border-border/70 space-y-1.5 text-xs">
                        <p class="text-[11px] font-bold text-foreground flex items-center gap-1.5">
                            <ShieldCheck class="w-3.5 h-3.5 text-primary" />
                            <span>Ketentuan Kata Sandi:</span>
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-1.5 text-[11px]">
                            <div
                                class="flex items-center gap-1.5 transition-colors"
                                :class="passwordRules.minLength ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-muted-foreground'"
                            >
                                <CheckCircle2 v-if="passwordRules.minLength" class="w-3.5 h-3.5 shrink-0 text-emerald-500" />
                                <span v-else class="w-1.5 h-1.5 rounded-full bg-muted-foreground/50 ml-1 mr-1 shrink-0"></span>
                                <span>Minimal 8 karakter</span>
                            </div>
                            <div
                                class="flex items-center gap-1.5 transition-colors"
                                :class="passwordRules.hasLetterAndNumber ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-muted-foreground'"
                            >
                                <CheckCircle2 v-if="passwordRules.hasLetterAndNumber" class="w-3.5 h-3.5 shrink-0 text-emerald-500" />
                                <span v-else class="w-1.5 h-1.5 rounded-full bg-muted-foreground/50 ml-1 mr-1 shrink-0"></span>
                                <span>Kombinasi huruf & angka</span>
                            </div>
                            <div
                                class="flex items-center gap-1.5 transition-colors"
                                :class="passwordRules.matches ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-muted-foreground'"
                            >
                                <CheckCircle2 v-if="passwordRules.matches" class="w-3.5 h-3.5 shrink-0 text-emerald-500" />
                                <span v-else class="w-1.5 h-1.5 rounded-full bg-muted-foreground/50 ml-1 mr-1 shrink-0"></span>
                                <span>Konfirmasi sandi cocok</span>
                            </div>
                        </div>
                    </div>

                    <!-- Referral info if present -->
                    <div v-if="referralCode" class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center gap-2.5 text-xs text-amber-700 dark:text-amber-300">
                        <Gift class="w-4 h-4 shrink-0 text-amber-500" />
                        <span>Mendaftar melalui referral teman: <strong class="font-mono">@{{ referralCode }}</strong></span>
                    </div>
                </CardContent>

                <CardFooter class="flex flex-col space-y-3 pt-2 pb-6">
                    <Button
                        type="submit"
                        class="w-full gap-2 font-bold text-xs sm:text-sm cursor-pointer shadow-sm"
                        :disabled="form.processing || usernameCheckStatus === 'unavailable' || isCheckingUsername"
                    >
                        <Loader2 v-if="form.processing || isCheckingUsername" class="h-4 w-4 animate-spin" />
                        <UserCheck v-else class="h-4 w-4" />
                        <span>Selesaikan & Masuk ke Sirkelta</span>
                    </Button>

                    <p class="text-center text-[11px] text-muted-foreground">
                        Dengan menyelesaikan pendaftaran, Anda menyetujui Syarat dan Ketentuan Layanan Sirkelta.
                    </p>
                </CardFooter>
            </form>
        </Card>
    </div>
</template>

