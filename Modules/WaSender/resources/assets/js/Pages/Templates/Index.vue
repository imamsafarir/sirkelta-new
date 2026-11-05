<script setup>
import { onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

onMounted(() => {
    router.visit(route('wasender.contacts.index', { tab: 'templates' }), { replace: true });
});
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-6 text-sm text-muted-foreground">
        <span>Memuat Template Pesan...</span>
    </div>
</template>
