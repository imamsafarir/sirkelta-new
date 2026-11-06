<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    ArrowLeft,
    Loader2,
    Mail,
    Send,
    Gift,
    CheckCircle2,
    RotateCcw,
    Sparkles,
    Inbox,
    ExternalLink,
} from 'lucide-vue-next';

const props = defineProps({
    referralCode: {
        type: String,
        default: '',
    },
    activationSentEmail: {
        type: String,
        default: null,
    },
});

const page = usePage();

const form = useForm({
    email: '',
    referral_code: props.referralCode || '',
});

const showReferralInput = ref(Boolean(props.referralCode));
const sentEmail = ref(props.activationSentEmail || page.props.flash?.activation_sent || null);
const resendCooldown = ref(0);
let cooldownTimer = null;

const startCooldown = (seconds = 30) => {
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

onMounted(() => {
    if (sentEmail.value) {
        startCooldown(30);
    }
});

onUnmounted(() => {
    clearInterval(cooldownTimer);
});

const submit = () => {
    form.clearErrors();

    if (!form.email || !form.email.trim()) {
        form.setError('email', 'Alamat email wajib diisi.');
        return;
    }

    form.post(route('register.attempt'), {
        preserveScroll: true,
        onSuccess: () => {
            sentEmail.value = form.email.trim().toLowerCase();
            startCooldown(30);
        },
    });
};

const resendActivation = () => {
    if (resendCooldown.value > 0 || form.processing) {
        return;
    }

    form.email = sentEmail.value;
    form.post(route('register.attempt'), {
        preserveScroll: true,
        onSuccess: () => {
            startCooldown(30);
        },
    });
};

const changeEmail = () => {
    sentEmail.value = null;
    form.clearErrors();
};

const mailProviderUrl = computed(() => {
    if (!sentEmail.value) return null;
    const domain = sentEmail.value.split('@')[1]?.toLowerCase();
    if (domain === 'gmail.com') return 'https://mail.google.com';
    if (domain === 'yahoo.com' || domain === 'ymail.com') return 'https://mail.yahoo.com';
    if (domain === 'outlook.com' || domain === 'hotmail.com') return 'https://outlook.live.com';
    return null;
});
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

        <Card class="w-full max-w-md shadow-md border-border/80 overflow-hidden">
            <!-- TAMPILAN 1: EMAIL SUDAH TERKIRIM (Menunggu Verifikasi) -->
            <div v-if="sentEmail" class="p-6 sm:p-8 space-y-6 text-center">
                <div class="relative mx-auto w-16 h-16 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                    <Mail class="w-8 h-8" />
                    <span class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-white text-[10px] font-bold ring-2 ring-background">
                        <CheckCircle2 class="w-3.5 h-3.5" />
                    </span>
                </div>

                <div class="space-y-2">
                    <h2 class="text-xl sm:text-2xl font-extrabold tracking-tight">Cek Kotak Masuk Anda</h2>
                    <p class="text-xs sm:text-sm text-muted-foreground leading-relaxed">
                        Kami telah mengirimkan tautan aktivasi akun ke alamat email:
                    </p>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-primary/10 text-primary font-semibold text-xs sm:text-sm max-w-full truncate">
                        <Inbox class="w-3.5 h-3.5 shrink-0" />
                        <span class="truncate">{{ sentEmail }}</span>
                    </div>
                </div>

                <div class="bg-muted/50 rounded-xl p-4 text-left border border-border/60 space-y-2 text-xs text-muted-foreground">
                    <p class="font-semibold text-foreground flex items-center gap-1.5">
                        <Sparkles class="w-3.5 h-3.5 text-amber-500" />
                        Langkah berikutnya:
                    </p>
                    <ol class="list-decimal list-inside space-y-1 pl-1 text-[11px] sm:text-xs leading-relaxed">
                        <li>Buka email dari <strong>Sirkelta</strong> (periksa kotak masuk atau spam).</li>
                        <li>Klik tautan <strong>"Aktifkan Akun & Buat Sandi"</strong>.</li>
                        <li>Buat Username dan Kata Sandi untuk langsung masuk.</li>
                    </ol>
                </div>

                <div class="space-y-3 pt-2">
                    <a
                        v-if="mailProviderUrl"
                        :href="mailProviderUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="w-full inline-flex items-center justify-center gap-2 h-10 px-4 rounded-md bg-primary text-primary-foreground font-semibold text-xs sm:text-sm hover:bg-primary/90 transition-colors shadow-xs"
                    >
                        <span>Buka Email Anda</span>
                        <ExternalLink class="w-4 h-4" />
                    </a>

                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="flex-1 gap-1.5 text-xs font-semibold"
                            :disabled="resendCooldown > 0 || form.processing"
                            @click="resendActivation"
                        >
                            <Loader2 v-if="form.processing" class="h-3.5 w-3.5 animate-spin" />
                            <RotateCcw v-else class="h-3.5 w-3.5" />
                            <span v-if="resendCooldown > 0">Kirim Ulang ({{ resendCooldown }}s)</span>
                            <span v-else>Kirim Ulang Tautan</span>
                        </Button>

                        <Button
                            type="button"
                            variant="ghost"
                            class="text-xs font-semibold text-muted-foreground hover:text-foreground"
                            @click="changeEmail"
                        >
                            Ganti Email
                        </Button>
                    </div>
                </div>

                <div class="text-xs text-muted-foreground pt-2 border-t border-border/60">
                    Sudah punya akun?
                    <Link :href="route('login')" class="text-primary hover:underline font-bold ml-1">
                        Masuk di sini
                    </Link>
                </div>
            </div>

            <!-- TAMPILAN 2: FORM INPUT EMAIL (Langkah Pertama) -->
            <template v-else>
                <CardHeader class="space-y-1 text-center">
                    <div class="flex justify-center">
                        <img src="/images/logo.png" alt="Sirkelta Logo" class="h-14 w-14 rounded-2xl shadow-sm mb-2 object-contain" />
                    </div>
                    <CardTitle class="text-2xl font-extrabold tracking-tight">Daftar Akun Sirkelta</CardTitle>
                    <CardDescription class="text-xs sm:text-sm">
                        Mulai cepat hanya dengan alamat email Anda. Tautan aktivasi akan dikirimkan langsung.
                    </CardDescription>
                </CardHeader>

                <form @submit.prevent="submit">
                    <CardContent class="space-y-4 pt-2 pb-5">
                        <div class="space-y-1.5">
                            <Label for="email" class="text-xs font-semibold">Alamat Email Aktif</Label>
                            <div class="relative">
                                <Mail class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="nama@email.com"
                                    required
                                    autofocus
                                    autocomplete="email"
                                    class="pl-9"
                                    :class="{ 'border-destructive': form.errors.email }"
                                />
                            </div>
                            <p v-if="form.errors.email" class="text-xs text-destructive font-medium">
                                {{ form.errors.email }}
                            </p>
                            <p v-else class="text-[11px] text-muted-foreground">
                                Pastikan email aktif untuk menerima tautan verifikasi.
                            </p>
                        </div>

                        <!-- Username Referral Opsional -->
                        <div class="space-y-1.5 pt-1">
                            <div class="flex items-center justify-between">
                                <button
                                    type="button"
                                    @click="showReferralInput = !showReferralInput"
                                    class="text-xs font-medium text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 cursor-pointer transition-colors"
                                >
                                    <Gift class="h-3.5 w-3.5 text-amber-500" />
                                    <span>Punya kode referral?</span>
                                    <span class="text-[10px] text-primary underline ml-0.5">
                                        {{ showReferralInput ? 'Sembunyikan' : 'Masukkan kode' }}
                                    </span>
                                </button>
                            </div>

                            <div v-if="showReferralInput" class="relative pt-1">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-muted-foreground">@</span>
                                <Input
                                    id="referral_code"
                                    v-model="form.referral_code"
                                    type="text"
                                    placeholder="username referral teman"
                                    class="pl-7 text-xs font-medium"
                                    :class="{ 'border-destructive': form.errors.referral_code }"
                                />
                            </div>
                            <p v-if="form.errors.referral_code" class="text-xs text-destructive font-medium">
                                {{ form.errors.referral_code }}
                            </p>
                        </div>
                    </CardContent>

                    <CardFooter class="flex flex-col space-y-3 pt-2 pb-6">
                        <Button
                            type="submit"
                            class="w-full gap-2 font-bold text-xs sm:text-sm cursor-pointer"
                            :disabled="form.processing || !form.email"
                        >
                            <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                            <Send v-else class="h-4 w-4" />
                            <span>Lanjutkan Pendaftaran</span>
                        </Button>

                        <div class="text-center text-xs sm:text-sm text-muted-foreground pt-1">
                            Sudah punya akun?
                            <Link :href="route('login')" class="text-primary hover:underline font-bold ml-1">
                                Masuk di sini
                            </Link>
                        </div>
                    </CardFooter>
                </form>
            </template>
        </Card>
    </div>
</template>
