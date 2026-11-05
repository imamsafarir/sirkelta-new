<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    Phone, 
    Mail, 
    MapPin, 
    Clock, 
    MessageSquare, 
    Send, 
    Loader2, 
    CheckCircle2 
} from 'lucide-vue-next';
import { runWithSmartQueue } from '@/lib/smartQueue';

const props = defineProps({
    supportWhatsapp: {
        type: String,
        default: '6281234567890',
    },
    supportEmail: {
        type: String,
        default: 'support@sirkelta.test',
    },
    contactAddress: {
        type: String,
        default: 'Indonesia',
    },
    contactHours: {
        type: String,
        default: 'Senin - Sabtu: 08.00 - 18.00 WIB',
    },
    contactDescription: {
        type: String,
        default: 'Punya pertanyaan, kendala, atau penawaran kerja sama? Jangan ragu untuk menghubungi tim dukungan pelanggan kami.',
    },
});

const form = useForm({
    guest_name: '',
    guest_email: '',
    message: '',
});

const isSubmitted = ref(false);

const submit = async () => {
    await runWithSmartQueue(async () => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const res = await fetch('/support/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    message: form.message,
                    guest_name: form.guest_name,
                    guest_email: form.guest_email,
                }),
            });

            if (res.ok) {
                isSubmitted.value = true;
                form.reset();
            }
        } catch (e) {
            console.error(e);
        }
    }, {
        method: 'post',
        url: '/support/chat',
        data: { message: form.message, guest_name: form.guest_name, guest_email: form.guest_email },
        statusText: 'Mengirim pesan dukungan...',
    });
};
</script>

<template>
    <PublicLayout title="Hubungi Kami">
        <div class="space-y-10 max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center space-y-3">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-bold">
                    <MessageSquare class="h-3.5 w-3.5" />
                    <span>Layanan Pengguna & Bantuan</span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-foreground">
                    Hubungi Tim Sirkelta
                </h1>
                <p class="text-sm text-muted-foreground max-w-xl mx-auto">
                    {{ contactDescription }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                <!-- Contact Channels Card -->
                <div class="md:col-span-2 space-y-4">
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base font-bold">Saluran Komunikasi Resmi</CardTitle>
                            <CardDescription class="text-xs">
                                Respon cepat selama jam operasional aktif.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4 text-xs">
                            <div class="flex items-start gap-3">
                                <div class="h-8 w-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                                    <Phone class="h-4 w-4" />
                                </div>
                                <div>
                                    <h4 class="font-bold text-foreground">WhatsApp Resmi</h4>
                                    <p class="text-muted-foreground font-medium tabular-nums">+{{ supportWhatsapp }}</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <Mail class="h-4 w-4" />
                                </div>
                                <div>
                                    <h4 class="font-bold text-foreground">Email Dukungan</h4>
                                    <p class="text-muted-foreground">{{ supportEmail }}</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div class="h-8 w-8 rounded-lg bg-muted text-muted-foreground flex items-center justify-center shrink-0">
                                    <Clock class="h-4 w-4" />
                                </div>
                                <div>
                                    <h4 class="font-bold text-foreground">Jam Kerja Operasional</h4>
                                    <p class="text-muted-foreground">{{ contactHours }}</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div class="h-8 w-8 rounded-lg bg-muted text-muted-foreground flex items-center justify-center shrink-0">
                                    <MapPin class="h-4 w-4" />
                                </div>
                                <div>
                                    <h4 class="font-bold text-foreground">Lokasi Redaksi</h4>
                                    <p class="text-muted-foreground">{{ contactAddress }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Bubble Chat Tip -->
                    <div class="p-4 rounded-2xl bg-primary/5 border border-primary/20 text-xs text-muted-foreground leading-relaxed">
                        <p class="font-bold text-foreground text-xs mb-1">Butuh Respon Instan?</p>
                        Klik ikon bubble chat di sudut kanan bawah layar Anda untuk terhubung langsung dengan Customer Support kami.
                    </div>
                </div>

                <!-- Contact Message Form -->
                <div class="md:col-span-3">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base font-bold">Kirim Pesan Aduan / Pertanyaan</CardTitle>
                            <CardDescription class="text-xs">
                                Pesan Anda akan langsung masuk ke antrean layanan dan dapat ditindaklanjuti oleh admin.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="isSubmitted" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 text-xs space-y-2">
                                <div class="flex items-center gap-2 font-bold">
                                    <CheckCircle2 class="h-4 w-4" />
                                    <span>Pesan Anda Berhasil Terkirim!</span>
                                </div>
                                <p class="leading-relaxed">
                                    Terima kasih telah menghubungi kami. Pesan Anda telah masuk ke sistem antrean aduan. Anda juga dapat memantau jawaban langsung melalui bubble chat di pojok kanan bawah.
                                </p>
                                <Button size="sm" variant="outline" class="text-xs" @click="isSubmitted = false">
                                    Kirim Pesan Baru
                                </Button>
                            </div>

                            <form v-else @submit.prevent="submit" class="space-y-4">
                                <div class="space-y-1.5">
                                    <Label for="guest_name">Nama Lengkap</Label>
                                    <Input
                                        id="guest_name"
                                        v-model="form.guest_name"
                                        type="text"
                                        placeholder="Nama Anda"
                                        required
                                    />
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="guest_email">Alamat Email</Label>
                                    <Input
                                        id="guest_email"
                                        v-model="form.guest_email"
                                        type="email"
                                        placeholder="email@anda.com"
                                        required
                                    />
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="message">Pesan / Aduan</Label>
                                    <textarea
                                        id="message"
                                        v-model="form.message"
                                        rows="4"
                                        placeholder="Tuliskan kendala atau pertanyaan Anda secara rinci..."
                                        class="w-full bg-muted/40 border rounded-xl p-3 text-xs focus:outline-hidden focus:ring-1 focus:ring-primary"
                                        required
                                    ></textarea>
                                </div>

                                <Button type="submit" class="w-full gap-2 font-bold text-xs" :disabled="form.processing">
                                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                                    <Send v-else class="h-4 w-4" />
                                    <span>Kirimkan Aduan Sekarang</span>
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
