<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { 
    MessageCircle, 
    X, 
    Send, 
    Loader2, 
    Headphones, 
    CheckCheck,
    Sparkles,
    ChevronRight,
    Minus,
    GripVertical
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

// Drag & Drop and Docking State
const isDockedHidden = ref(false);
const bubblePos = ref({ x: null, y: null });
const bookFoldTop = ref(null);

let isDragging = false;
let startPointerX = 0;
let startPointerY = 0;
let startBubbleX = 0;
let startBubbleY = 0;
let hasDragged = false;

// Book Fold Drag State
let isDraggingFold = false;
let startFoldPointerY = 0;
let startFoldTop = 0;
let hasFoldDragged = false;

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

// --- DRAG AND DROP HANDLERS FOR FLOATING BUBBLE ---
const onBubblePointerDown = (e) => {
    if (e.button !== undefined && e.button !== 0) return;

    const el = e.currentTarget;
    const rect = el.getBoundingClientRect();

    startPointerX = e.clientX;
    startPointerY = e.clientY;
    startBubbleX = rect.left;
    startBubbleY = rect.top;
    hasDragged = false;
    isDragging = true;

    window.addEventListener('pointermove', onBubblePointerMove, { passive: false });
    window.addEventListener('pointerup', onBubblePointerUp);
    window.addEventListener('pointercancel', onBubblePointerUp);
};

const onBubblePointerMove = (e) => {
    if (!isDragging) return;

    const dx = e.clientX - startPointerX;
    const dy = e.clientY - startPointerY;

    if (Math.hypot(dx, dy) > 6) {
        hasDragged = true;
    }

    if (hasDragged) {
        e.preventDefault();

        const bubbleWidth = 60;
        const bubbleHeight = 60;
        const minX = 10;
        const maxX = window.innerWidth - bubbleWidth - 10;
        const minY = 64;
        const maxY = window.innerHeight - bubbleHeight - 75;

        const nextX = Math.max(minX, Math.min(maxX, startBubbleX + dx));
        const nextY = Math.max(minY, Math.min(maxY, startBubbleY + dy));

        bubblePos.value = { x: nextX, y: nextY };
    }
};

const onBubblePointerUp = () => {
    if (!isDragging) return;
    isDragging = false;

    window.removeEventListener('pointermove', onBubblePointerMove);
    window.removeEventListener('pointerup', onBubblePointerUp);
    window.removeEventListener('pointercancel', onBubblePointerUp);

    if (hasDragged && bubblePos.value.x !== null) {
        try {
            localStorage.setItem('sirkelta_support_bubble_pos', JSON.stringify(bubblePos.value));
        } catch (e) {}
    }
};

const handleLauncherClick = () => {
    if (hasDragged) {
        hasDragged = false;
        return;
    }
    toggleChat();
};

// --- DOCKING (HIDE / UNHIDE LIKE BOOK FOLD) ---
const dockToBookFold = (e) => {
    if (e) e.stopPropagation();
    isDockedHidden.value = true;
    isOpen.value = false;

    if (bubblePos.value.y !== null) {
        bookFoldTop.value = Math.max(100, Math.min(window.innerHeight - 120, bubblePos.value.y));
    } else {
        bookFoldTop.value = Math.max(100, Math.min(window.innerHeight - 150, window.innerHeight * 0.6));
    }

    try {
        localStorage.setItem('sirkelta_support_bubble_hidden', 'true');
        localStorage.setItem('sirkelta_support_bookfold_top', String(bookFoldTop.value));
    } catch (e) {}
};

const unhideBookFold = () => {
    isDockedHidden.value = false;
    try {
        localStorage.setItem('sirkelta_support_bubble_hidden', 'false');
    } catch (e) {}
};

// --- DRAG HANDLER FOR BOOK FOLD TAB (Along Right Screen Edge) ---
const onFoldPointerDown = (e) => {
    if (e.button !== undefined && e.button !== 0) return;
    isDraggingFold = true;
    hasFoldDragged = false;
    startFoldPointerY = e.clientY;
    startFoldTop = bookFoldTop.value || window.innerHeight * 0.6;

    window.addEventListener('pointermove', onFoldPointerMove, { passive: false });
    window.addEventListener('pointerup', onFoldPointerUp);
    window.addEventListener('pointercancel', onFoldPointerUp);
};

const onFoldPointerMove = (e) => {
    if (!isDraggingFold) return;
    const dy = e.clientY - startFoldPointerY;
    if (Math.abs(dy) > 6) {
        hasFoldDragged = true;
    }
    if (hasFoldDragged) {
        e.preventDefault();
        const minTop = 80;
        const maxTop = window.innerHeight - 120;
        bookFoldTop.value = Math.max(minTop, Math.min(maxTop, startFoldTop + dy));
    }
};

const onFoldPointerUp = () => {
    if (!isDraggingFold) return;
    isDraggingFold = false;
    window.removeEventListener('pointermove', onFoldPointerMove);
    window.removeEventListener('pointerup', onFoldPointerUp);
    window.removeEventListener('pointercancel', onFoldPointerUp);

    if (hasFoldDragged) {
        try {
            localStorage.setItem('sirkelta_support_bookfold_top', String(bookFoldTop.value));
        } catch (e) {}
    } else {
        // Klik singkat: keluar dari mode lipatan dan langsung buka chat
        unhideBookFold();
        toggleChat();
    }
};

// Dynamic styling for floating chat window
const chatWindowStyle = computed(() => {
    if (typeof window === 'undefined' || window.innerWidth < 640 || bubblePos.value.x === null) {
        return {};
    }
    const chatWidth = 384;
    const chatHeight = 480;

    let left = bubblePos.value.x;
    if (left + chatWidth > window.innerWidth - 20) {
        left = window.innerWidth - chatWidth - 20;
    }
    left = Math.max(20, left);

    let top = bubblePos.value.y - chatHeight + 50;
    if (top < 70) {
        top = 70;
    }
    if (top + chatHeight > window.innerHeight - 20) {
        top = window.innerHeight - chatHeight - 20;
    }

    return {
        position: 'fixed',
        left: `${left}px`,
        top: `${top}px`,
    };
});

onMounted(() => {
    guestToken.value = getGuestToken();

    try {
        const savedHidden = localStorage.getItem('sirkelta_support_bubble_hidden');
        if (savedHidden === 'true') {
            isDockedHidden.value = true;
        }

        const savedPos = localStorage.getItem('sirkelta_support_bubble_pos');
        if (savedPos) {
            const parsed = JSON.parse(savedPos);
            if (parsed && typeof parsed.x === 'number' && typeof parsed.y === 'number') {
                if (parsed.x < window.innerWidth - 30 && parsed.y < window.innerHeight - 30) {
                    bubblePos.value = parsed;
                }
            }
        }

        const savedFoldTop = localStorage.getItem('sirkelta_support_bookfold_top');
        if (savedFoldTop) {
            const parsedTop = parseFloat(savedFoldTop);
            if (!isNaN(parsedTop)) {
                bookFoldTop.value = Math.max(80, Math.min(window.innerHeight - 120, parsedTop));
            }
        } else {
            bookFoldTop.value = window.innerHeight * 0.6;
        }
    } catch (e) {}
});
</script>

<template>
    <!-- 1. DOCKED STATE: LIPATAN BUKU DI KANAN LAYAR (BOOKMARK TAB) -->
    <div
        v-if="isDockedHidden"
        @pointerdown="onFoldPointerDown"
        class="fixed right-0 z-50 cursor-pointer select-none group touch-none animate-in slide-in-from-right-4 duration-300"
        :style="{ top: (bookFoldTop || 350) + 'px' }"
        title="Bantuan Layanan Sirkelta (Tahan & geser untuk atur posisi, klik untuk buka)"
    >
        <div class="bg-primary text-primary-foreground shadow-2xl rounded-l-2xl pl-3 pr-2 py-3.5 border-y border-l border-white/20 flex items-center justify-center gap-1.5 hover:pl-4 transition-all duration-200">
            <div class="relative flex items-center justify-center">
                <Headphones class="h-5 w-5 text-primary-foreground group-hover:scale-110 transition-transform" />
                <span class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500 border border-primary"></span>
                </span>
            </div>
        </div>
    </div>

    <!-- 2. EXPANDED / NORMAL FLOATING BUBBLE (DRAGGABLE) -->
    <div 
        v-else
        class="z-50 select-none touch-none"
        :class="{
            'fixed bottom-[calc(5.25rem+env(safe-area-inset-bottom,0px))] right-4 sm:bottom-6 sm:right-6': bubblePos.x === null,
            'fixed': bubblePos.x !== null
        }"
        :style="bubblePos.x !== null ? { left: bubblePos.x + 'px', top: bubblePos.y + 'px' } : {}"
    >
        <!-- Floating Launcher Button with Drag & Hide controls -->
        <div 
            v-if="!isOpen"
            @pointerdown="onBubblePointerDown"
            class="relative group cursor-grab active:cursor-grabbing flex items-center justify-center"
        >
            <!-- Tombol Sembunyikan ke Lipatan Kanan -->
            <button
                @click.stop="dockToBookFold"
                class="absolute -top-2 -left-2 bg-card text-muted-foreground hover:text-foreground border shadow-sm rounded-full p-1 opacity-0 group-hover:opacity-100 sm:group-hover:opacity-100 transition-opacity z-10 hover:scale-110"
                title="Sembunyikan ke lipatan samping kanan"
            >
                <ChevronRight class="h-3 w-3" />
            </button>

            <!-- Tombol Bubble Utama -->
            <button
                @click="handleLauncherClick"
                class="h-13 w-13 sm:h-14 sm:w-14 rounded-full bg-primary text-primary-foreground shadow-lg hover:shadow-xl hover:scale-105 active:scale-95 transition-transform duration-200 flex items-center justify-center relative"
                title="Tahan & geser untuk pindah posisi, klik untuk buka bantuan"
            >
                <MessageCircle class="h-6 w-6 sm:h-7 sm:w-7 transition-transform group-hover:rotate-6" />
                <span class="absolute -top-1 -right-1 flex h-3.5 w-3.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500 border-2 border-background"></span>
                </span>
            </button>
        </div>

        <!-- Floating Chat Window Card -->
        <div
            v-else
            :style="chatWindowStyle"
            class="fixed inset-x-3 bottom-[calc(5rem+env(safe-area-inset-bottom,0px))] sm:inset-x-auto w-auto sm:w-96 max-w-sm h-[480px] max-h-[75vh] bg-card text-card-foreground border shadow-2xl rounded-3xl flex flex-col overflow-hidden animate-in fade-in slide-in-from-bottom-5 duration-200"
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
                <div class="flex items-center gap-1">
                    <!-- Minimize to bookfold tab -->
                    <button
                        @click="dockToBookFold"
                        class="h-8 w-8 rounded-full hover:bg-white/15 flex items-center justify-center text-primary-foreground/80 hover:text-white transition-colors"
                        title="Sembunyikan ke lipatan samping"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                    <!-- Close button -->
                    <button
                        @click="isOpen = false"
                        class="h-8 w-8 rounded-full hover:bg-white/15 flex items-center justify-center text-primary-foreground/80 hover:text-white transition-colors"
                        title="Tutup jendela chat"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
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

            <!-- Guest Name bar (Only for guests if no active ticket yet) -->
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
