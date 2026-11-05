<script setup>
import { ref, onMounted, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { 
    MessageCircle, 
    X, 
    Send, 
    Loader2, 
    Headphones, 
    ShieldCheck, 
    Clock, 
    CheckCheck,
    Sparkles
} from 'lucide-vue-next';

const page = usePage();
const isOpen = ref(false);
const isLoading = ref(false);
const isSending = ref(false);

const ticket = ref(null);
const messages = ref([]);
const messageInput = ref('');
const guestName = ref('');
const guestEmail = ref('');
const guestToken = ref('');
const chatScroll = ref(null);

const scrollToBottom = async () => {
    await nextTick();
    if (chatScroll.value) {
        chatScroll.value.scrollTop = chatScroll.value.scrollHeight;
    }
};

const getGuestToken = () => {
    let token = localStorage.getItem('sirkelta_guest_support_token');
    if (!token) {
        token = 'gst_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
        localStorage.setItem('sirkelta_guest_support_token', token);
    }
    return token;
};

const fetchChat = async () => {
    isLoading.value = true;
    try {
        guestToken.value = getGuestToken();
        const res = await fetch(`/support/chat?guest_token=${encodeURIComponent(guestToken.value)}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (res.ok) {
            const data = await res.json();
            ticket.value = data.ticket;
            messages.value = data.messages || [];
            scrollToBottom();
        }
    } catch (e) {
        console.error('Failed to load support chat:', e);
    } finally {
        isLoading.value = false;
    }
};

const toggleChat = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        fetchChat();
    }
};

const sendMessage = async () => {
    const text = messageInput.value.trim();
    if (!text || isSending.value) return;

    isSending.value = true;
    try {
        const payload = {
            message: text,
            guest_token: getGuestToken(),
            guest_name: guestName.value || 'Tamu',
            guest_email: guestEmail.value || null,
        };

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const res = await fetch('/support/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        if (res.ok) {
            const data = await res.json();
            ticket.value = data.ticket;
            messages.value = data.messages || [];
            messageInput.value = '';
            scrollToBottom();
        }
    } catch (e) {
        console.error('Failed to send support message:', e);
    } finally {
        isSending.value = false;
    }
};

const formatTime = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};

onMounted(() => {
    guestToken.value = getGuestToken();
});
</script>

<template>
    <div class="fixed bottom-20 right-4 sm:bottom-6 sm:right-6 z-50">
        <!-- Floating Launcher Button -->
        <button
            v-if="!isOpen"
            @click="toggleChat"
            class="h-13 w-13 sm:h-14 sm:w-14 rounded-full bg-primary text-primary-foreground shadow-lg hover:shadow-xl hover:scale-105 active:scale-95 transition-all duration-300 flex items-center justify-center relative group"
            title="Hubungi Kami & Bantuan Langsung"
        >
            <MessageCircle class="h-6 w-6 sm:h-7 sm:w-7 transition-transform group-hover:rotate-6" />
            <span class="absolute -top-1 -right-1 flex h-3.5 w-3.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500 border-2 border-background"></span>
            </span>
        </button>

        <!-- Floating Chat Window Card -->
        <div
            v-else
            class="w-[calc(100vw-2rem)] sm:w-96 max-w-sm h-[480px] max-h-[75vh] bg-card text-card-foreground border shadow-2xl rounded-3xl flex flex-col overflow-hidden animate-in fade-in slide-in-from-bottom-5 duration-200"
        >
            <!-- Chat Window Header -->
            <div class="bg-primary text-primary-foreground p-4 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="h-10 w-10 rounded-2xl bg-white/15 flex items-center justify-center shrink-0">
                        <Headphones class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <h4 class="font-bold text-sm tracking-tight leading-none">Bantuan & Aduan Layanan</h4>
                        <p class="text-[10px] text-primary-foreground/80 mt-1 flex items-center gap-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            <span>Customer Care Sirkelta</span>
                        </p>
                    </div>
                </div>
                <button
                    @click="isOpen = false"
                    class="h-8 w-8 rounded-full hover:bg-white/15 flex items-center justify-center text-primary-foreground/80 hover:text-white transition-colors"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Chat Content Body -->
            <div ref="chatScroll" class="flex-1 p-4 overflow-y-auto space-y-3 bg-muted/20 text-xs">
                <!-- Welcome note -->
                <div class="p-3 rounded-2xl bg-card border text-muted-foreground leading-relaxed shadow-2xs space-y-1">
                    <p class="font-bold text-foreground text-xs flex items-center gap-1.5">
                        <Sparkles class="h-3.5 w-3.5 text-amber-500" />
                        <span>Halo! Ada yang bisa kami bantu?</span>
                    </p>
                    <p class="text-[11px]">
                        Sampaikan kendala, aduan, atau pertanyaan terkait fitur Sirkelta. Tim kami akan segera menanggapi.
                    </p>
                </div>

                <!-- Loading State -->
                <div v-if="isLoading" class="flex justify-center py-6">
                    <Loader2 class="h-6 w-6 animate-spin text-primary" />
                </div>

                <!-- Messages Thread -->
                <template v-else>
                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        class="flex flex-col"
                        :class="msg.sender_type === 'user' ? 'items-end' : 'items-start'"
                    >
                        <div
                            class="max-w-[85%] rounded-2xl px-3.5 py-2.5 shadow-2xs leading-relaxed"
                            :class="msg.sender_type === 'user' 
                                ? 'bg-primary text-primary-foreground rounded-br-xs' 
                                : 'bg-card border text-foreground rounded-bl-xs'"
                        >
                            <p class="whitespace-pre-wrap break-words text-xs">{{ msg.message }}</p>
                            <div 
                                class="text-[9px] mt-1 flex items-center gap-1"
                                :class="msg.sender_type === 'user' ? 'text-primary-foreground/75 justify-end' : 'text-muted-foreground'"
                            >
                                <span>{{ formatTime(msg.created_at) }}</span>
                                <CheckCheck v-if="msg.sender_type === 'user'" class="h-3 w-3" />
                            </div>
                        </div>
                    </div>

                    <!-- Clean Resolution Banner -->
                    <div v-if="ticket?.status === 'resolved' && messages.length === 0" class="p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 text-center space-y-1">
                        <p class="font-bold text-xs">Aduan Anda Sebelumnya Telah Selesai</p>
                        <p class="text-[11px]">Riwayat chat telah dibersihkan secara aman. Silakan ketik pesan baru di bawah ini jika membutuhkan bantuan lainnya.</p>
                    </div>
                </template>
            </div>

            <!-- Guest Name/Email bar (Only for guests if no active ticket yet) -->
            <div v-if="!$page.props.auth?.user && (!ticket || ticket.status === 'resolved')" class="px-3 pt-2 bg-card border-t text-[11px] space-y-1.5">
                <input
                    v-model="guestName"
                    type="text"
                    placeholder="Nama Anda (opsional)"
                    class="w-full bg-muted/40 border rounded-lg px-2.5 py-1 text-xs"
                />
            </div>

            <!-- Message Input & Send Bar -->
            <div class="p-3 bg-card border-t shrink-0 flex items-center gap-2">
                <input
                    v-model="messageInput"
                    @keyup.enter="sendMessage"
                    type="text"
                    placeholder="Ketik pertanyaan atau aduan..."
                    class="flex-1 bg-muted/40 border rounded-xl px-3 py-2 text-xs focus:outline-hidden focus:ring-1 focus:ring-primary"
                    :disabled="isSending"
                />
                <Button 
                    size="sm" 
                    class="h-9 w-9 p-0 rounded-xl shrink-0" 
                    :disabled="!messageInput.trim() || isSending"
                    @click="sendMessage"
                >
                    <Loader2 v-if="isSending" class="h-4 w-4 animate-spin" />
                    <Send v-else class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>

