<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { 
    MessageSquare, 
    CheckCircle2, 
    Clock, 
    Search, 
    User, 
    ArrowRight, 
    ShieldCheck, 
    Trash2,
    Sparkles
} from 'lucide-vue-next';

const props = defineProps({
    tickets: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
    currentStatus: {
        type: String,
        default: 'open',
    },
    search: {
        type: String,
        default: '',
    },
});

const page = usePage();
const searchQuery = ref(props.search || '');

const handleSearch = () => {
    router.get(route('admin.support.index'), {
        status: props.currentStatus,
        search: searchQuery.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const filterStatus = (status) => {
    router.get(route('admin.support.index'), {
        status: status,
        search: searchQuery.value || undefined,
    });
};

const resolveAndCleanTicket = (ticket) => {
    if (confirm(`Selesaikan aduan #${ticket.ticket_number}? Seluruh riwayat percakapan chat akan terhapus bersih dan total aduan selesai akan bertambah.`)) {
        router.post(route('admin.support.resolve', ticket.id));
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <DashboardLayout 
        title="Aduan & Bantuan Pengguna" 
        activeModule="admin" 
        :user="page.props.auth.user"
    >
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-foreground">Aduan Masuk</h1>
                </div>
            </div>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Highlight Card: Resolved Count -->
                <Card class="border-emerald-500/30 bg-emerald-500/5">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-bold text-emerald-700 flex items-center justify-between">
                            <span>Total Aduan Selesai Ditangani</span>
                            <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                        </CardDescription>
                        <CardTitle class="text-2xl sm:text-3xl font-extrabold tracking-tight tabular-nums text-emerald-700">
                            {{ stats.resolved_count }} Aduan
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-[11px] text-emerald-800/80">Chat telah dibersihkan & tersimpan dalam riwayat metrik</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Aduan Aktif Menunggu</span>
                            <Clock class="h-4 w-4 text-amber-500" />
                        </CardDescription>
                        <CardTitle class="text-2xl sm:text-3xl font-extrabold tracking-tight tabular-nums">{{ stats.open_count }} Kasus</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-[11px] text-muted-foreground">Tiket terbuka yang sedang atau butuh ditangani</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-semibold flex items-center justify-between">
                            <span>Pesan Baru Belum Dibaca</span>
                            <MessageSquare class="h-4 w-4 text-primary" />
                        </CardDescription>
                        <CardTitle class="text-2xl sm:text-3xl font-extrabold tracking-tight tabular-nums">{{ stats.unread_messages }} Pesan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-[11px] text-muted-foreground">Pesan aduan dari pengguna yang belum dibuka admin</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filter Status & Search -->
            <Card>
                <CardContent class="p-4">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div class="flex items-center gap-1.5 w-full sm:w-auto">
                            <Button 
                                size="sm" 
                                :variant="currentStatus === 'open' ? 'default' : 'outline'"
                                class="text-xs font-semibold"
                                @click="filterStatus('open')"
                            >
                                Aduan Aktif ({{ stats.open_count }})
                            </Button>
                            <Button 
                                size="sm" 
                                :variant="currentStatus === 'resolved' ? 'default' : 'outline'"
                                class="text-xs font-semibold"
                                @click="filterStatus('resolved')"
                            >
                                Selesai
                            </Button>
                            <Button 
                                size="sm" 
                                :variant="currentStatus === 'all' ? 'default' : 'outline'"
                                class="text-xs font-semibold"
                                @click="filterStatus('all')"
                            >
                                Semua Tiket
                            </Button>
                        </div>

                        <div class="relative w-full sm:w-72">
                            <Search class="h-4 w-4 text-muted-foreground absolute left-3 top-2.5" />
                            <Input
                                v-model="searchQuery"
                                @keyup.enter="handleSearch"
                                placeholder="Cari nomor tiket, nama, email..."
                                class="pl-9 h-9 text-xs"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Tickets Table -->
            <Card>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-muted/50 text-muted-foreground border-b font-semibold">
                                <tr>
                                    <th class="py-3 px-4">No. Tiket</th>
                                    <th class="py-3 px-4">Pengadu</th>
                                    <th class="py-3 px-4">Subjek Aduan</th>
                                    <th class="py-3 px-4">Pesan Masuk</th>
                                    <th class="py-3 px-4">Status</th>
                                    <th class="py-3 px-4">Waktu</th>
                                    <th class="py-3 px-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="t in tickets.data" :key="t.id" class="hover:bg-muted/30">
                                    <td class="py-3 px-4 font-extrabold tabular-nums text-primary">
                                        #{{ t.ticket_number }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-foreground">
                                            {{ t.user?.name || t.guest_name || 'Tamu Pengguna' }}
                                        </div>
                                        <div class="text-[10px] text-muted-foreground">
                                            {{ t.user ? ('@' + t.user.username) : (t.guest_email || 'Pengunjung Website') }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-foreground font-medium max-w-xs truncate">
                                        {{ t.subject }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <Badge 
                                            variant="secondary" 
                                            class="text-[10px]"
                                            :class="t.unread_count > 0 ? 'bg-amber-500/10 text-amber-700 font-bold' : ''"
                                        >
                                            {{ t.messages_count }} pesan
                                            <span v-if="t.unread_count > 0" class="ml-1 text-destructive font-extrabold">({{ t.unread_count }} baru)</span>
                                        </Badge>
                                    </td>
                                    <td class="py-3 px-4">
                                        <Badge 
                                            :variant="t.status === 'open' ? 'secondary' : 'outline'"
                                            class="text-[10px]"
                                            :class="t.status === 'open' ? 'bg-amber-500/10 text-amber-700 font-bold' : 'text-emerald-700 bg-emerald-500/10 font-bold'"
                                        >
                                            {{ t.status === 'open' ? 'Aktif' : 'Selesai' }}
                                        </Badge>
                                    </td>
                                    <td class="py-3 px-4 text-muted-foreground whitespace-nowrap">
                                        {{ formatDate(t.created_at) }}
                                    </td>
                                    <td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">
                                        <Link :href="route('admin.support.show', t.id)">
                                            <Button size="sm" variant="outline" class="h-7 text-[11px] px-2.5 font-semibold">
                                                <span>Buka Chat</span>
                                                <ArrowRight class="h-3 w-3 ml-1" />
                                            </Button>
                                        </Link>
                                        <Button 
                                            v-if="t.status === 'open'"
                                            size="sm" 
                                            class="h-7 text-[11px] px-2.5 font-bold bg-emerald-600 hover:bg-emerald-700 text-white"
                                            @click="resolveAndCleanTicket(t)"
                                            title="Tandai Selesai & Bersihkan Chat"
                                        >
                                            <CheckCircle2 class="h-3 w-3 mr-1" />
                                            Selesai
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="tickets.data.length === 0">
                                    <td colspan="7" class="py-8 text-center text-muted-foreground">
                                        Tidak ada tiket aduan pada kategori ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="tickets.links && tickets.links.length > 3" class="p-4 border-t flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">
                            Menampilkan {{ tickets.from }} - {{ tickets.to }} dari {{ tickets.total }} tiket
                        </span>
                        <div class="flex gap-1">
                            <Link
                                v-for="link in tickets.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all"
                                :class="link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </DashboardLayout>
</template>

