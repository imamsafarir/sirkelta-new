<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    ArrowLeft,
    Loader2,
    Mail,
    KeyRound,
    ShieldCheck,
    CheckCircle2,
    RotateCcw,
    Eye,
    EyeOff,
    Check,
    Lock,
    Sparkles,
} from 'lucide-vue-next';

// State alur: 1 = Input Email/Username, 2 = Input OTP, 3 = Buat Password Baru
const step = ref(1);

// Step 1: Request OTP State
const loginInput = ref('');
const isSendingOtp = ref(false);
const otpError = ref('');

// Step 2: Verify OTP State
const userId = ref(null);
const maskedEmail = ref('');
const otpCode = ref('');
const isVerifyingOtp = ref(false);
const resendCooldown = ref(0);
let cooldownTimer = null;

// Step 3: Password Reset Form
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
const resetToken = ref('');

const resetForm = useForm({
    user_id: null,
    reset_token: '',
    password: '',
    password_confirmation: '',
});

const startCooldown = (seconds = 60) => {
    resendCooldown.value = seconds;
    clearInterval(cooldownTimer);
    cooldownTimer = setInterval(() => {
        if (resendCooldown.value > 0) {
            resendCooldown.value -= 1;
        } else {
            clearInterval(cooldownTimer);
        }
    }, 1000);
};

onUnmounted(() => {
    clearInterval(cooldownTimer);
});

const getCsrfToken = () => {
    return (
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    );
};

// --- ACTION STEP 1: Kirim OTP ---
const handleSendOtp = async () => {
    otpError.value = '';
    const cleanInput = loginInput.value.trim();

    if (!cleanInput) {
        otpError.value = 'Silakan masukkan alamat email atau username Anda.';
        return;
    }

    isSendingOtp.value = true;
    try {
        const res = await fetch(route('password.otp.send'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ login: cleanInput }),
        });

        const data = await res.json();

        if (res.ok && data.success) {
            userId.value = data.user_id;
            maskedEmail.value = data.masked_email;
            step.value = 2;
            startCooldown(60);
        } else {
            otpError.value = data.message || 'Gagal mengirim kode OTP. Silakan periksa kembali email atau username Anda.';
        }
    } catch {
        otpError.value = 'Terjadi gangguan koneksi saat menghubungi server. Silakan coba sesaat lagi.';
    } finally {
        isSendingOtp.value = false;
    }
};

// --- ACTION STEP 2: Verifikasi OTP ---
const handleVerifyOtp = async () => {
    otpError.value = '';
    const cleanOtp = otpCode.value.replace(/\D/g, '').trim();

    if (cleanOtp.length !== 6) {
        otpError.value = 'Kode OTP harus terdiri dari 6 angka.';
        return;
    }

    isVerifyingOtp.value = true;
    try {
        const res = await fetch(route('password.otp.verify'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                user_id: userId.value,
                otp: cleanOtp,
            }),
        });

        const data = await res.json();

        if (res.ok && data.success) {
            resetToken.value = data.reset_token;
            resetForm.user_id = data.user_id;
            resetForm.reset_token = data.reset_token;
            step.value = 3;
        } else {
            otpError.value = data.message || 'Kode OTP tidak cocok atau telah kedaluwarsa.';
        }
    } catch {
        otpError.value = 'Terjadi kesalahan sistem saat memverifikasi OTP. Silakan coba lagi.';
    } finally {
        isVerifyingOtp.value = false;
    }
};

// Kirim Ulang OTP di Step 2
const handleResendOtp = async () => {
    if (resendCooldown.value > 0 || isSendingOtp.value) return;
    await handleSendOtp();
};

// Kembali ke Step 1 untuk ubah email
const handleChangeEmail = () => {
    step.value = 1;
    otpCode.value = '';
    otpError.value = '';
    clearInterval(cooldownTimer);
    resendCooldown.value = 0;
};

// --- ACTION STEP 3: Submit Password Baru ---
const passwordsMatch = computed(() => {
    return (
        resetForm.password &&
        resetForm.password_confirmation &&
        resetForm.password === resetForm.password_confirmation
    );
});

const handleResetPassword = () => {
    resetForm.clearErrors();

    if (!resetForm.password || resetForm.password.length < 8) {
        resetForm.setError('password', 'Kata sandi minimal 8 karakter.');
        return;
    }

    if (resetForm.password !== resetForm.password_confirmation) {
        resetForm.setError('password_confirmation', 'Konfirmasi kata sandi tidak cocok.');
        return;
    }

    resetForm.post(route('password.reset.submit'), {
        onFinish: () => resetForm.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Lupa Kata Sandi - Sirkelta" />

    <div class="min-h-screen bg-muted/30 flex flex-col justify-center items-center p-4 sm:p-6 font-sans selection:bg-primary selection:text-primary-foreground">
        <!-- Back Button -->
        <div class="w-full max-w-md mb-6">
            <Link :href="route('login')" class="inline-flex items-center gap-1.5 text-xs sm:text-sm text-muted-foreground hover:text-foreground transition-colors font-semibold">
                <ArrowLeft class="h-4 w-4" />
                <span>Kembali ke Halaman Masuk</span>
            </Link>
        </div>

        <Card class="w-full max-w-md shadow-md border-border/80 overflow-hidden">
            <!-- TAHAP 1: INPUT EMAIL ATAU USERNAME -->
            <template v-if="step === 1">
                <CardHeader class="space-y-1 text-center">
                    <div class="flex justify-center">
                        <img src="/images/logo.png" alt="Sirkelta Logo" class="h-14 w-14 rounded-2xl shadow-sm mb-2 object-contain" />
                    </div>
                    <CardTitle class="text-2xl font-extrabold tracking-tight">Lupa Kata Sandi</CardTitle>
                    <CardDescription class="text-xs sm:text-sm">
                        Masukkan email atau username Anda untuk menerima 6-digit kode OTP pemulihan kata sandi.
                    </CardDescription>
                </CardHeader>

                <form @submit.prevent="handleSendOtp">
                    <CardContent class="space-y-4 pt-2 pb-5">
                        <div v-if="otpError" class="p-3 text-xs text-destructive bg-destructive/10 border border-destructive/20 rounded-lg font-medium leading-relaxed">
                            {{ otpError }}
                        </div>

                        <div class="space-y-1.5">
                            <Label for="loginInput" class="text-xs font-semibold">Alamat Email atau Username</Label>
                            <div class="relative">
                                <Mail class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                                <Input
                                    id="loginInput"
                                    v-model="loginInput"
                                    type="text"
                                    placeholder="nama@email.com atau username"
                                    required
                                    autofocus
                                    class="pl-9"
                                    :class="{ 'border-destructive': otpError }"
                                />
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Kode verifikasi OTP akan dikirimkan ke email yang tertaut pada akun Anda.
                            </p>
                        </div>
                    </CardContent>

                    <CardFooter class="flex flex-col space-y-3 pt-2 pb-6">
                        <Button
                            type="submit"
                            class="w-full gap-2 font-bold text-xs sm:text-sm cursor-pointer"
                            :disabled="isSendingOtp || !loginInput.trim()"
                        >
                            <Loader2 v-if="isSendingOtp" class="h-4 w-4 animate-spin" />
                            <KeyRound v-else class="h-4 w-4" />
                            <span>Kirim Kode OTP</span>
                        </Button>

                        <div class="text-center text-xs sm:text-sm text-muted-foreground pt-1">
                            Ingat kata sandi Anda?
                            <Link :href="route('login')" class="text-primary hover:underline font-bold ml-1">
                                Masuk di sini
                            </Link>
                        </div>
                    </CardFooter>
                </form>
            </template>

            <!-- TAHAP 2: INPUT & VERIFIKASI KODE OTP -->
            <template v-else-if="step === 2">
                <CardHeader class="space-y-2 text-center pb-2">
                    <div class="mx-auto w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary mb-1">
                        <ShieldCheck class="w-6 h-6" />
                    </div>
                    <CardTitle class="text-xl sm:text-2xl font-extrabold tracking-tight">Verifikasi Kode OTP</CardTitle>
                    <CardDescription class="text-xs sm:text-sm leading-relaxed">
                        Kami telah mengirimkan 6-digit kode verifikasi ke email:
                    </CardDescription>

                    <!-- Email Badge Pill -->
                    <div class="flex justify-center pt-1">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary font-semibold text-xs border border-primary/20">
                            <Mail class="w-3.5 h-3.5" />
                            <span>{{ maskedEmail }}</span>
                        </span>
                    </div>
                </CardHeader>

                <form @submit.prevent="handleVerifyOtp">
                    <CardContent class="space-y-4 pt-3 pb-5">
                        <div v-if="otpError" class="p-3 text-xs text-destructive bg-destructive/10 border border-destructive/20 rounded-lg font-medium leading-relaxed">
                            {{ otpError }}
                        </div>

                        <div class="space-y-2 text-center">
                            <Label for="otpCode" class="text-xs font-semibold">Masukkan 6 Digit Angka OTP</Label>
                            <div class="flex justify-center">
                                <Input
                                    id="otpCode"
                                    v-model="otpCode"
                                    type="text"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    maxlength="6"
                                    placeholder="••••••"
                                    required
                                    autofocus
                                    class="text-center font-mono text-2xl tracking-[0.4em] font-extrabold h-12 max-w-[220px]"
                                    :class="{ 'border-destructive': otpError }"
                                />
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Kode berlaku selama <strong>15 menit</strong>. Jangan bagikan kepada siapa pun.
                            </p>
                        </div>
                    </CardContent>

                    <CardFooter class="flex flex-col space-y-3 pt-2 pb-6">
                        <Button
                            type="submit"
                            class="w-full gap-2 font-bold text-xs sm:text-sm cursor-pointer shadow-xs"
                            :disabled="isVerifyingOtp || otpCode.replace(/\D/g, '').length !== 6"
                        >
                            <Loader2 v-if="isVerifyingOtp" class="h-4 w-4 animate-spin" />
                            <CheckCircle2 v-else class="h-4 w-4" />
                            <span>Verifikasi Kode OTP</span>
                        </Button>

                        <div class="flex items-center justify-between w-full pt-2 border-t border-border/60 text-xs">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-xs gap-1.5 font-semibold text-muted-foreground hover:text-foreground"
                                :disabled="resendCooldown > 0 || isSendingOtp"
                                @click="handleResendOtp"
                            >
                                <Loader2 v-if="isSendingOtp" class="h-3.5 w-3.5 animate-spin" />
                                <RotateCcw v-else class="h-3.5 w-3.5" />
                                <span v-if="resendCooldown > 0">Kirim Ulang ({{ resendCooldown }}s)</span>
                                <span v-else>Kirim Ulang OTP</span>
                            </Button>

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-xs font-semibold text-muted-foreground hover:text-foreground"
                                @click="handleChangeEmail"
                            >
                                Ganti Akun
                            </Button>
                        </div>
                    </CardFooter>
                </form>
            </template>

            <!-- TAHAP 3: FORM BUAT PASSWORD BARU -->
            <template v-else-if="step === 3">
                <CardHeader class="space-y-1 text-center">
                    <div class="mx-auto w-12 h-12 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400 mb-1">
                        <Sparkles class="w-6 h-6" />
                    </div>
                    <CardTitle class="text-2xl font-extrabold tracking-tight">Buat Kata Sandi Baru</CardTitle>
                    <CardDescription class="text-xs sm:text-sm">
                        OTP terverifikasi! Masukkan kata sandi baru untuk mengamankan akun Anda.
                    </CardDescription>
                </CardHeader>

                <form @submit.prevent="handleResetPassword">
                    <CardContent class="space-y-4 pt-2 pb-5">
                        <div class="space-y-1.5">
                            <Label for="password" class="flex items-center gap-1.5 text-xs font-semibold">
                                <Lock class="h-3.5 w-3.5 text-muted-foreground" />
                                <span>Kata Sandi Baru</span>
                            </Label>
                            <div class="relative">
                                <Input
                                    id="password"
                                    v-model="resetForm.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    placeholder="Min. 8 karakter"
                                    required
                                    autofocus
                                    autocomplete="new-password"
                                    class="pr-9"
                                    :class="{ 'border-destructive': resetForm.errors.password }"
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
                            <p v-if="resetForm.errors.password" class="text-xs text-destructive font-medium">
                                {{ resetForm.errors.password }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <Label for="password_confirmation" class="text-xs font-semibold">Konfirmasi Sandi Baru</Label>
                                <span v-if="passwordsMatch" class="text-[10px] text-emerald-600 font-bold flex items-center gap-0.5">
                                    <Check class="w-3 h-3" /> Cocok
                                </span>
                            </div>
                            <div class="relative">
                                <Input
                                    id="password_confirmation"
                                    v-model="resetForm.password_confirmation"
                                    :type="showPasswordConfirmation ? 'text' : 'password'"
                                    placeholder="Ulangi sandi baru"
                                    required
                                    autocomplete="new-password"
                                    class="pr-9"
                                    :class="{ 'border-destructive': resetForm.errors.password_confirmation }"
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
                            <p v-if="resetForm.errors.password_confirmation" class="text-xs text-destructive font-medium">
                                {{ resetForm.errors.password_confirmation }}
                            </p>
                        </div>
                    </CardContent>

                    <CardFooter class="flex flex-col space-y-3 pt-2 pb-6">
                        <Button
                            type="submit"
                            class="w-full gap-2 font-bold text-xs sm:text-sm cursor-pointer shadow-xs"
                            :disabled="resetForm.processing || !resetForm.password"
                        >
                            <Loader2 v-if="resetForm.processing" class="h-4 w-4 animate-spin" />
                            <CheckCircle2 v-else class="h-4 w-4" />
                            <span>Simpan Kata Sandi Baru</span>
                        </Button>
                    </CardFooter>
                </form>
            </template>
        </Card>
    </div>
</template>
