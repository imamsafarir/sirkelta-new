<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Card, CardContent } from '@/components/ui/card';
import { FileText, Calendar } from 'lucide-vue-next';

defineProps({
    content: {
        type: String,
        default: '',
    },
    updated_at: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <PublicLayout title="Syarat dan Ketentuan">
        <div class="space-y-8 max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center space-y-3">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-bold">
                    <FileText class="h-3.5 w-3.5" />
                    <span>Ketentuan Layanan</span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-foreground">
                    Syarat & Ketentuan Penggunaan
                </h1>
                <p v-if="updated_at" class="text-xs text-muted-foreground flex items-center justify-center gap-1.5">
                    <Calendar class="h-3.5 w-3.5" />
                    <span>Terakhir diperbarui: {{ updated_at }}</span>
                </p>
            </div>

            <!-- Dynamic Content Card -->
            <Card>
                <CardContent class="p-6 sm:p-10">
                    <div 
                        class="prose prose-sm sm:prose-base dark:prose-invert max-w-none text-muted-foreground leading-relaxed prose-headings:text-foreground prose-headings:font-extrabold prose-a:text-primary prose-a:font-semibold hover:prose-a:underline prose-strong:text-foreground"
                        v-html="content"
                    ></div>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>
