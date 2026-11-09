<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { ArrowLeft, Loader2, LogIn, Eye, EyeOff } from 'lucide-vue-next';

defineProps({
    status: {
        type: String,
        default: null,
    },
});

const showPassword = ref(false);

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.clearErrors();

    if (!form.login.trim()) {
        form.setError('login', 'Email atau username wajib diisi.');
        return;
    }

    if (!form.password) {
        form.setError('password', 'Kata sandi wajib diisi.');
        return;
    }

    form.post(route('login.attempt'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Masuk ke Akun - Sirkelta" />

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
                <CardTitle class="text-2xl font-extrabold tracking-tight">Masuk ke Sirkelta</CardTitle>
                <CardDescription class="text-xs sm:text-sm">
                    Gunakan email atau username Anda untuk mengakses portal aplikasi
                </CardDescription>
            </CardHeader>

            <form @submit.prevent="submit">
                <CardContent class="space-y-4 pt-2 pb-5">
                    <div v-if="status" class="p-3 text-xs sm:text-sm text-emerald-700 bg-emerald-50 rounded-lg border border-emerald-200 font-medium">
                        {{ status }}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="login">Email atau Username</Label>
                        <Input
                            id="login"
                            v-model="form.login"
                            type="text"
                            placeholder="nama@email.com atau username"
                            required
                            autofocus
                            autocomplete="username"
                            :class="{ 'border-destructive': form.errors.login }"
                        />
                        <p v-if="form.errors.login" class="text-xs text-destructive font-medium">
                            {{ form.errors.login }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="password">Kata Sandi</Label>
                            <Link
                                :href="route('password.request')"
                                class="text-xs text-primary hover:underline font-semibold transition-colors"
                            >
                                Lupa kata sandi?
                            </Link>
                        </div>
                        <div class="relative">
                            <Input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="••••••••"
                                required
                                autocomplete="current-password"
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

                    <div class="flex items-center space-x-2 pt-1">
                        <Checkbox id="remember" v-model:checked="form.remember" />
                        <label for="remember" class="text-xs font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer">
                            Ingat saya di perangkat ini
                        </label>
                    </div>
                </CardContent>

                <CardFooter class="flex flex-col space-y-3 pt-5 pb-5">
                    <Button type="submit" class="w-full gap-2 font-bold text-xs sm:text-sm" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                        <LogIn v-else class="h-4 w-4" />
                        <span>Masuk Sekarang</span>
                    </Button>

                    <div class="text-center text-xs sm:text-sm text-muted-foreground">
                        Belum memiliki akun?
                        <Link :href="route('register')" class="text-primary hover:underline font-bold ml-1">
                            Daftar akun baru
                        </Link>
                    </div>
                </CardFooter>
            </form>
        </Card>
    </div>
</template>
