<script setup>
import { ref, computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { HelpCircle, Search, ChevronDown, ChevronUp } from 'lucide-vue-next';

const props = defineProps({
    faqs: {
        type: Array,
        default: () => [],
    },
});

const searchQuery = ref('');
const activeCategory = ref('Semua');
const openFaqIds = ref(new Set());

const categories = computed(() => {
    const list = ['Semua'];
    props.faqs.forEach((faq) => {
        if (faq.category && !list.includes(faq.category)) {
            list.push(faq.category);
        }
    });
    return list;
});

const filteredFaqs = computed(() => {
    return props.faqs.filter((faq) => {
        const matchesCategory = activeCategory.value === 'Semua' || faq.category === activeCategory.value;
        const matchesSearch = !searchQuery.value.trim() || 
            faq.question.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            faq.answer.toLowerCase().includes(searchQuery.value.toLowerCase());
        return matchesCategory && matchesSearch;
    });
});

const toggleFaq = (id) => {
    if (openFaqIds.value.has(id)) {
        openFaqIds.value.delete(id);
    } else {
        openFaqIds.value.add(id);
    }
};
</script>

<template>
    <PublicLayout title="Tanya Jawab (FAQ)">
        <div class="space-y-8">
            <!-- Header -->
            <div class="text-center max-w-2xl mx-auto space-y-3">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-bold">
                    <HelpCircle class="h-3.5 w-3.5" />
                    <span>Pusat Bantuan & Tanya Jawab</span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-foreground">
                    Pertanyaan yang Sering Diajukan (FAQ)
                </h1>
                <p class="text-sm text-muted-foreground">
                    Temukan jawaban cepat atas pertanyaan seputar penggunaan akun, login, program referral, dan aplikasi di Sirkelta.
                </p>
            </div>

            <!-- Search Bar -->
            <div class="max-w-xl mx-auto relative">
                <Search class="h-4 w-4 text-muted-foreground absolute left-3.5 top-3.5" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari pertanyaan atau kata kunci (contoh: login, referral, pro)..."
                    class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-card border shadow-xs text-sm focus:outline-hidden focus:ring-2 focus:ring-primary/40"
                />
            </div>

            <!-- Category Pills -->
            <div class="flex flex-wrap items-center justify-center gap-2">
                <button
                    v-for="cat in categories"
                    :key="cat"
                    @click="activeCategory = cat"
                    class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all"
                    :class="activeCategory === cat ? 'bg-primary text-primary-foreground shadow-xs' : 'bg-muted hover:bg-muted/80 text-muted-foreground'"
                >
                    {{ cat }}
                </button>
            </div>

            <!-- FAQ List Accordion -->
            <div class="max-w-3xl mx-auto space-y-3">
                <Card 
                    v-for="faq in filteredFaqs" 
                    :key="faq.id"
                    class="transition-all duration-200 overflow-hidden cursor-pointer hover:border-primary/50"
                    @click="toggleFaq(faq.id)"
                >
                    <CardHeader class="p-4 sm:p-5 flex flex-row items-center justify-between gap-4">
                        <div class="space-y-1">
                            <Badge variant="outline" class="text-[10px] font-semibold mb-1">
                                {{ faq.category }}
                            </Badge>
                            <CardTitle class="text-sm sm:text-base font-bold text-foreground">
                                {{ faq.question }}
                            </CardTitle>
                        </div>
                        <div class="h-7 w-7 rounded-full bg-muted flex items-center justify-center text-muted-foreground shrink-0">
                            <ChevronUp v-if="openFaqIds.has(faq.id)" class="h-4 w-4" />
                            <ChevronDown v-else class="h-4 w-4" />
                        </div>
                    </CardHeader>
                    <div 
                        v-if="openFaqIds.has(faq.id)" 
                        class="px-4 sm:px-5 pb-5 pt-0 text-xs sm:text-sm text-muted-foreground leading-relaxed border-t bg-muted/10 animate-in fade-in duration-200"
                    >
                        <p class="whitespace-pre-line pt-3">{{ faq.answer }}</p>
                    </div>
                </Card>

                <div v-if="filteredFaqs.length === 0" class="text-center py-12 text-muted-foreground text-sm">
                    Tidak ditemukan pertanyaan yang cocok dengan pencarian Anda. Silakan hubungi kami lewat bubble chat di kanan bawah.
                </div>
            </div>
        </div>
    </PublicLayout>
</template>

