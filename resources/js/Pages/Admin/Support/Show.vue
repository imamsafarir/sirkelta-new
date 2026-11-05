<script setup>
import { ref, onMounted, nextTick } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    ArrowLeft, 
    Send, 
    Loader2, 
    CheckCircle2, 
    Clock, 
    User, 
    Headphones, 
    ShieldCheck, 
    Trash2,
    CheckCheck
} from 'lucide-vue-next';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const chatContainer = ref(null);

const replyForm = useForm({
    message: '',
});

const scrollToBottom = () => {
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    });
};

const sendReply = () => {
    replyForm.clearErrors();
    if (!replyForm.message.trim()) {
        replyForm.setError('message', 'Pesan balasan tidak boleh kosong.');
        return;
    }
    replyForm.post(route('admin.support.reply', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset();
            scrollToBottom();
        },
    });
};

const resolveAndClean = () => {
    if (confirm(`Apakah Anda yakin ingin menyelesaikan aduan #${props.ticket.ticket_number}? Seluruh riwayat obrolan akan terhapus bersih dan counter aduan selesai akan dihitung.`)) {
        router.post(route('admin.support.resolve', props.ticket.id));
    }
};

const formatTime = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

onMounted(() => {
    scrollToBottom();
});
</script>

<template>
    <DashboardLayout 
        :title="'Ruang Obrolan Aduan #' + ticket.ticket_number" 
        activeModule="admin" 
        :user="page.props.auth.user"
    >
        <div class="space-y-6 max-w-4xl mx-auto">
            <!-- Back & Top Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <Link :href="route('admin.support.index')" class="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground font-semibold">
                    <ArrowLeft class="h-4 w-4" />
                    <span>Kembali ke Daftar Aduan</span>
                </Link>

                <div class="flex items-center gap-2">
                    <Button 
                        v-if="ticket.status === 'open'"
                        class="gap-1.5 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white"
                        @click="resolveAndClean"
                    >
                        <CheckCircle2 class="h-4 w-4" />
                        <span>Tandai Selesai & Bersihkan Chat</span>
                    </Button>
                    <Badge v-else variant="secondary" class="bg-emerald-500/10 text-emerald-700 font-bold text-xs py-1 px-3">
                        Aduan Telah Selesai
                    </Badge>
                </div>
            </div>

            <!-- Ticket Meta Card -->
            <Card>
                <CardContent class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-extrabold tabular-nums text-primary text-base">#{{ ticket.ticket_number }}</span>
                            <Badge :variant="ticket.status === 'open' ? 'secondary' : 'outline'" class="text-[10px]">
                                {{ ticket.status === 'open' ? 'Sedang Ditangani' : 'Selesai' }}
                            </Badge>
                        </div>
                        <h2 class="text-sm font-bold text-foreground">{{ ticket.subject }}</h2>
                    </div>

                    <div class="text-xs space-y-1 sm:text-right border-t sm:border-t-0 pt-2 sm:pt-0">
                        <div class="font-semibold text-foreground">
                            {{ ticket.user?.name || ticket.guest_name || 'Tamu Pengguna' }}
                        </div>
                        <div class="text-[11px] text-muted-foreground">
                            {{ ticket.user ? ('@' + ticket.user.username + ' • ' + ticket.user.email) : (ticket.guest_email || 'Pengunjung Tamu') }}
                        </div>
                        <div class="text-[10px] text-muted-foreground">Dibuat: {{ formatDate(ticket.created_at) }}</div>
                    </div>
                </CardContent>
            </Card>

            <!-- Chat Message Window Card -->
            <Card class="flex flex-col h-[520px] overflow-hidden">
                <CardHeader class="border-b py-3 px-4 bg-muted/30 flex flex-row items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Headphones class="h-4 w-4 text-primary" />
                        <span class="text-xs font-bold">Riwayat Percakapan Langsung</span>
                    </div>
                    <span class="text-[11px] text-muted-foreground">{{ ticket.messages?.length || 0 }} pesan</span>
                </CardHeader>

                <!-- Scrollable Messages Area -->
                <div ref="chatContainer" class="flex-1 p-4 overflow-y-auto space-y-3 bg-muted/15 text-xs">
                    <div
                        v-for="msg in ticket.messages"
                        :key="msg.id"
                        class="flex flex-col"
                        :class="msg.sender_type === 'admin' ? 'items-end' : 'items-start'"
                    >
                        <div class="text-[10px] text-muted-foreground mb-1 font-semibold px-1">
                            {{ msg.sender_type === 'admin' ? 'Admin Dukungan' : (ticket.user?.name || ticket.guest_name || 'Pengguna') }}
                        </div>
                        <div
                            class="max-w-[80%] rounded-2xl px-4 py-2.5 shadow-2xs leading-relaxed"
                            :class="msg.sender_type === 'admin' 
                                ? 'bg-primary text-primary-foreground rounded-br-xs' 
                                : 'bg-card border text-foreground rounded-bl-xs'"
                        >
                            <p class="whitespace-pre-wrap break-words text-xs">{{ msg.message }}</p>
                            <div 
                                class="text-[9px] mt-1 flex items-center gap-1"
                                :class="msg.sender_type === 'admin' ? 'text-primary-foreground/75 justify-end' : 'text-muted-foreground'"
                            >
                                <span>{{ formatTime(msg.created_at) }}</span>
                                <CheckCheck v-if="msg.sender_type === 'admin'" class="h-3 w-3" />
                            </div>
                        </div>
                    </div>

                    <div v-if="ticket.messages?.length === 0" class="text-center py-16 text-muted-foreground text-xs space-y-2">
                        <CheckCircle2 class="h-8 w-8 text-emerald-500 mx-auto" />
                        <p class="font-bold text-foreground">Tidak Ada Pesan Aktif</p>
                        <p>Riwayat obrolan tiket ini telah terhapus bersih karena telah ditandai selesai.</p>
                    </div>
                </div>

                <!-- Admin Reply Input Bar -->
                <div v-if="ticket.status === 'open'" class="p-3 bg-card border-t space-y-1.5">
                    <div class="flex items-center gap-2">
                        <input
                            v-model="replyForm.message"
                            @keyup.enter="sendReply"
                            type="text"
                            placeholder="Ketik balasan untuk pengguna..."
                            class="flex-1 bg-muted/40 border rounded-xl px-3.5 py-2 text-xs focus:outline-hidden focus:ring-1 focus:ring-primary"
                            :class="{ 'border-destructive': replyForm.errors.message }"
                            :disabled="replyForm.processing"
                        />
                        <Button 
                            size="sm" 
                            class="gap-1.5 font-bold text-xs shrink-0" 
                            :disabled="!replyForm.message.trim() || replyForm.processing"
                            @click="sendReply"
                        >
                            <Loader2 v-if="replyForm.processing" class="h-3.5 w-3.5 animate-spin" />
                            <Send v-else class="h-3.5 w-3.5" />
                            <span>Kirim Balasan</span>
                        </Button>
                    </div>
                    <p v-if="replyForm.errors.message" class="text-[11px] text-destructive font-medium px-1">
                        {{ replyForm.errors.message }}
                    </p>
                </div>
            </Card>
        </div>
    </DashboardLayout>
</template>

