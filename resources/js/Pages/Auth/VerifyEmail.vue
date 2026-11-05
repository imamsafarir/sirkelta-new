<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Mail, CheckCircle2, ArrowRight, LogOut, Loader2, RefreshCw } from 'lucide-vue-next';

const props = defineProps({
    status: {
        type: String,
        default: null,
    },
    userEmail: {
        type: String,
        default: '',
    },
});

const form = useForm({});

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<template>
    <Head title="Verifikasi Email - Sirkelta" />

    <div class="min-h-screen flex items-center justify-center bg-muted/40 p-4">
        <div class="w-full max-w-md space-y-6">
            <!-- Brand Logo -->
            <div class="text-center space-y-1">
                <Link href="/" class="inline-flex items-center gap-2 group">
                    <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-primary-foreground font-black text-xl shadow-md group-hover:scale-105 transition-transform">
                        S
                    </div>
                    <span class="font-extrabold text-2xl tracking-tight text-foreground">Sirkelta</span>
                </Link>
            </div>

            <Card class="border-border/60 shadow-xl backdrop-blur-sm bg-card/95">
                <CardHeader class="text-center pb-4 space-y-2">
                    <div class="mx-auto w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mb-1">
                        <Mail class="w-7 h-7 animate-pulse" />
                    </div>
                    <CardTitle class="text-xl font-bold tracking-tight text-foreground">
                        Verifikasi Email Anda
                    </CardTitle>
                    <CardDescription class="text-xs sm:text-sm text-muted-foreground leading-relaxed">
                        Terima kasih telah bergabung! Kami telah mengirimkan tautan verifikasi ke:
                        <span class="block font-semibold text-foreground mt-1 break-all">{{ userEmail }}</span>
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-4 pb-5 text-xs sm:text-sm text-muted-foreground">
                    <div
                        v-if="verificationLinkSent"
                        class="p-3 text-xs text-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-300 rounded-xl border border-emerald-200 dark:border-emerald-800 flex items-start gap-2"
                    >
                        <CheckCircle2 class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
                        <span>Tautan verifikasi baru telah berhasil dikirimkan ke kotak masuk email Anda!</span>
                    </div>

                    <div class="p-3.5 rounded-xl bg-muted/60 border border-border/50 space-y-1.5 text-xs">
                        <p class="font-semibold text-foreground flex items-center gap-1.5">
                            <ArrowRight class="w-3.5 h-3.5 text-primary" />
                            Petunjuk Verifikasi:
                        </p>
                        <p>
                            Buka email Anda dan klik tombol <strong>"Verify Email Address"</strong>. Setelah diklik, aplikasi akan otomatis terbuka dan Anda dapat langsung memilih modul aplikasi.
                        </p>
                        <p class="text-[11px] text-muted-foreground/80 italic pt-1">
                            *Tips: Jika tidak menemukan email di Kotak Masuk (Inbox), periksa juga folder <strong>Spam</strong> atau <strong>Promosi</strong>.
                        </p>
                    </div>
                </CardContent>

                <CardFooter class="flex flex-col space-y-3 pt-5 pb-5">
                    <form @submit.prevent="submit" class="w-full">
                        <Button
                            type="submit"
                            class="w-full font-bold text-xs h-10 gap-2 cursor-pointer"
                            :disabled="form.processing"
                        >
                            <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                            <RefreshCw v-else class="h-4 w-4" />
                            <span>{{ form.processing ? 'Mengirim...' : 'Kirim Ulang Email Verifikasi' }}</span>
                        </Button>
                    </form>

                    <div class="w-full flex justify-center pt-3 border-t border-border/60">
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-destructive transition-colors py-1 cursor-pointer"
                        >
                            <LogOut class="w-3.5 h-3.5" />
                            <span>Keluar dari Akun</span>
                        </Link>
                    </div>
                </CardFooter>
            </Card>
        </div>
    </div>
</template>

