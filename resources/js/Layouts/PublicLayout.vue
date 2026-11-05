<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import SupportChatBubble from '@/Components/SupportChatBubble.vue';
import { ArrowLeft, ExternalLink, ShieldCheck, HelpCircle } from 'lucide-vue-next';

defineProps({
    title: {
        type: String,
        default: 'Sirkelta',
    },
});

const page = usePage();
</script>

<template>
    <Head :title="title + ' - Sirkelta'" />

    <div class="min-h-screen bg-muted/20 text-foreground flex flex-col selection:bg-primary selection:text-primary-foreground font-sans">
        <!-- Top Navbar with Safe Area -->
        <header class="border-b bg-background/90 backdrop-blur-md sticky top-0 z-40 pt-safe">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
                <Link :href="route('home')" class="flex items-center gap-2.5">
                    <img src="/images/logo.png" alt="Sirkelta Logo" class="h-8 w-8 rounded-xl object-contain shadow-xs" />
                    <span class="font-extrabold text-lg tracking-tight">Sirkelta</span>
                </Link>

                <nav class="hidden md:flex items-center gap-6 text-sm font-semibold text-muted-foreground">
                    <Link :href="route('home')" class="hover:text-foreground transition-colors">Beranda</Link>
                    <Link :href="route('faq')" class="hover:text-foreground transition-colors" :class="{ 'text-primary font-bold': $page.url === '/faq' }">FAQ</Link>
                    <Link :href="route('about')" class="hover:text-foreground transition-colors" :class="{ 'text-primary font-bold': $page.url === '/tentang-kami' }">Tentang Kami</Link>
                    <Link :href="route('contact')" class="hover:text-foreground transition-colors" :class="{ 'text-primary font-bold': $page.url === '/hubungi-kami' }">Hubungi Kami</Link>
                </nav>

                <div class="flex items-center gap-2">
                    <template v-if="page.props.auth?.user">
                        <Link :href="route('portal')">
                            <Button size="sm" class="gap-1.5 text-xs font-bold">
                                <span>Buka Portal</span>
                            </Button>
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')">
                            <Button variant="ghost" size="sm" class="text-xs font-semibold">
                                Masuk
                            </Button>
                        </Link>
                        <Link :href="route('register')">
                            <Button size="sm" class="text-xs font-bold">
                                Daftar Gratis
                            </Button>
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 py-8 sm:py-12">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t bg-card text-muted-foreground mt-auto">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 pb-[calc(2.5rem+env(safe-area-inset-bottom,0px))]">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8 text-xs">
                    <div class="space-y-3 md:col-span-1">
                        <div class="flex items-center gap-2">
                            <img src="/images/logo.png" alt="Sirkelta Logo" class="h-6 w-6 rounded-md object-contain" />
                            <span class="font-extrabold text-foreground text-sm">Sirkelta</span>
                        </div>
                        <p class="leading-relaxed">
                            Ekosistem produktivitas bisnis dan keuangan mandiri: Sirkelta Keuangan & Sirkelta WaSender.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <h5 class="font-bold text-foreground uppercase tracking-wider text-[11px]">Informasi</h5>
                        <ul class="space-y-1.5">
                            <li><Link :href="route('about')" class="hover:text-primary transition-colors">Tentang Kami</Link></li>
                            <li><Link :href="route('faq')" class="hover:text-primary transition-colors">FAQ & Tanya Jawab</Link></li>
                            <li><Link :href="route('contact')" class="hover:text-primary transition-colors">Hubungi Kami</Link></li>
                        </ul>
                    </div>

                    <div class="space-y-2">
                        <h5 class="font-bold text-foreground uppercase tracking-wider text-[11px]">Legalitas & Privasi</h5>
                        <ul class="space-y-1.5">
                            <li><Link :href="route('privacy')" class="hover:text-primary transition-colors">Kebijakan Privasi</Link></li>
                            <li><Link :href="route('terms')" class="hover:text-primary transition-colors">Syarat & Ketentuan</Link></li>
                            <li><Link :href="route('refund')" class="hover:text-primary transition-colors">Pengembalian Dana (Refund)</Link></li>
                        </ul>
                    </div>

                    <div class="space-y-2">
                        <h5 class="font-bold text-foreground uppercase tracking-wider text-[11px]">Aplikasi</h5>
                        <ul class="space-y-1.5">
                            <li><span class="text-foreground font-medium">Sirkelta Keuangan</span> - Catat Kas & Arus Keuangan</li>
                            <li><span class="text-foreground font-medium">Sirkelta WaSender</span> - Broadcast WhatsApp Cerdas</li>
                        </ul>
                    </div>
                </div>

                <div class="border-t pt-6 flex flex-col sm:flex-row items-center justify-between text-xs gap-3">
                    <p>&copy; {{ new Date().getFullYear() }} Sirkelta. Seluruh hak cipta dilindungi undang-undang.</p>
                    <div class="flex items-center gap-1.5 text-emerald-600 font-semibold">
                        <ShieldCheck class="h-4 w-4" />
                        <span>Sistem Aman, Mandiri, & Terverifikasi</span>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Floating Chat Bubble -->
        <SupportChatBubble />
    </div>
</template>

