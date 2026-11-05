<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Download,
    Upload,
    Plus,
    Search,
    Pencil,
    Trash2,
    Users,
    X,
    FileText,
    Copy,
    Check,
    Folder,
    ArrowLeft,
    Sparkles,
    ExternalLink,
    ChevronRight,
    FolderPlus,
    Shuffle,
    Hash,
    Layers
} from 'lucide-vue-next';

const props = defineProps({
    contacts: {
        type: Object,
        required: true,
    },
    groups: {
        type: Array,
        default: () => [],
    },
    groupsWithCounts: {
        type: Array,
        default: () => [],
    },
    templates: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', group: 'all' }),
    },
    quota: {
        type: Object,
        default: () => ({
            total_saved: 0,
            max_contacts: 100,
            is_unlimited: false,
            remaining: 100,
            can_import_csv: true,
        }),
    },
    templatesQuota: {
        type: Object,
        default: () => ({
            total_saved: 0,
            max_templates: 10,
            is_unlimited: false,
            remaining: 10,
            can_use_spintax: false,
        }),
    },
    defaultTab: {
        type: String,
        default: 'contacts',
    },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user);

// Main Tab State ('contacts' | 'templates')
const activeTab = ref(props.defaultTab || 'contacts');

// Group View State: null means showing all group cards, string means viewing a specific group ('Semua Kontak' or specific group name)
const currentGroup = ref(
    props.filters.group && props.filters.group !== 'all' && props.filters.group !== '__all__' && props.filters.group !== 'Semua Kontak'
        ? props.filters.group
        : null
);
const contactSearch = ref(props.filters.search || '');

watch(
    () => props.filters.group,
    (newGroup) => {
        if (!newGroup || newGroup === 'all' || newGroup === '__all__' || newGroup === 'Semua Kontak') {
            currentGroup.value = null;
        } else {
            currentGroup.value = newGroup;
        }
    }
);

function selectGroupCard(groupName) {
    if (!groupName) {
        currentGroup.value = null;
        contactSearch.value = '';
        router.get(route('wasender.contacts.index'), {
            tab: 'contacts',
        }, {
            preserveState: true,
            replace: true,
        });
        return;
    }

    const queryGroup = groupName === 'Semua Kontak' ? 'all' : groupName;
    currentGroup.value = groupName;
    router.get(route('wasender.contacts.index'), {
        tab: 'contacts',
        group: queryGroup,
        search: contactSearch.value,
    }, {
        preserveState: true,
        replace: true,
    });
}

function handleContactFilter() {
    const queryGroup = currentGroup.value === 'Semua Kontak' ? 'all' : (currentGroup.value || 'all');
    router.get(route('wasender.contacts.index'), {
        tab: 'contacts',
        group: queryGroup,
        search: contactSearch.value,
    }, {
        preserveState: true,
        replace: true,
    });
}

// Contact Add/Edit Modal
const showContactModal = ref(false);
const editingContact = ref(null);

const contactForm = useForm({
    name: '',
    phone: '',
    group_name: 'Umum',
    notes: '',
});

function openAddContactModal(targetGroup) {
    editingContact.value = null;
    contactForm.reset();
    contactForm.group_name = targetGroup && targetGroup !== 'Semua Kontak'
        ? targetGroup
        : (currentGroup.value && currentGroup.value !== 'Semua Kontak' ? currentGroup.value : 'Umum');
    showContactModal.value = true;
}

function openEditContactModal(c) {
    editingContact.value = c;
    contactForm.name = c.name;
    contactForm.phone = c.phone;
    contactForm.group_name = c.group_name || 'Umum';
    contactForm.notes = c.notes || '';
    showContactModal.value = true;
}

function saveContact() {
    if (editingContact.value) {
        contactForm.put(route('wasender.contacts.update', editingContact.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showContactModal.value = false;
                contactForm.reset();
            },
        });
    } else {
        contactForm.post(route('wasender.contacts.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showContactModal.value = false;
                contactForm.reset();
            },
        });
    }
}

function deleteContact(id, name) {
    if (confirm(`Hapus kontak "${name}"?`)) {
        router.delete(route('wasender.contacts.destroy', id), {
            preserveScroll: true,
        });
    }
}

// New Group Creation Modal
const showNewGroupModal = ref(false);
const newGroupName = ref('');

function submitNewGroup() {
    if (!newGroupName.value.trim()) return;
    const name = newGroupName.value.trim();
    showNewGroupModal.value = false;
    newGroupName.value = '';
    openAddContactModal(name);
}

// CSV Import Modal
const showImportModal = ref(false);
const importForm = useForm({
    file: null,
    group_name: 'Impor CSV',
});

function openImportModal(targetGroup) {
    importForm.reset();
    importForm.group_name = targetGroup && targetGroup !== 'Semua Kontak'
        ? targetGroup
        : (currentGroup.value && currentGroup.value !== 'Semua Kontak' ? currentGroup.value : 'Impor CSV');
    showImportModal.value = true;
}

function handleFileUpload(e) {
    const target = e.target;
    if (target.files && target.files[0]) {
        importForm.file = target.files[0];
    }
}

function submitImport() {
    importForm.post(route('wasender.contacts.import'), {
        preserveScroll: true,
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
        },
    });
}

function exportGroupCsv(groupName) {
    const isAll = !groupName || groupName === 'Semua Kontak' || groupName === 'all' || groupName === '__all__';
    const groupParam = isAll ? '' : `?group=${encodeURIComponent(groupName)}`;
    window.location.href = `/wasender/contacts/export-csv${groupParam}`;
}

// --- TEMPLATES TAB LOGIC ---
const templateSearch = ref('');
const showTemplateModal = ref(false);
const editingTemplate = ref(null);

const canUseSpintax = computed(() => {
    return Boolean(props.templatesQuota?.can_use_spintax);
});

function hasSpintax(text) {
    if (!text) return false;
    return /(\[[^\]]+[\/|][^\]]+\]|\{(?!nama\}|phone\}|nomor\}|kode_acak\})[^\}]+[\/|][^\}]+\})/.test(text);
}

const templateForm = useForm({
    title: '',
    content: '',
    category: 'Umum',
    has_random_code: false,
});

const filteredTemplates = computed(() => {
    if (!templateSearch.value) return props.templates;
    const q = templateSearch.value.toLowerCase();
    return props.templates.filter(
        (t) => t.title.toLowerCase().includes(q) || t.content.toLowerCase().includes(q) || t.category.toLowerCase().includes(q)
    );
});

function openAddTemplateModal() {
    editingTemplate.value = null;
    templateForm.reset();
    templateForm.category = 'Umum';
    templateForm.has_random_code = false;
    showTemplateModal.value = true;
}

function openEditTemplateModal(t) {
    editingTemplate.value = { ...t };
    templateForm.title = t.title;
    templateForm.content = t.content;
    templateForm.category = t.category;
    templateForm.has_random_code = Boolean(t.has_random_code);
    showTemplateModal.value = true;
}

function insertTemplateTag(tag) {
    templateForm.content += tag;
}

function insertSpintaxSample() {
    if (!canUseSpintax.value) {
        alert('Fitur variasi teks acak [pilihan/pilihan] hanya tersedia untuk pengguna paket Pro.');
        return;
    }
    templateForm.content += '[Halo/Selamat pagi/Hai/Salam] ';
}

function saveTemplate() {
    if (editingTemplate.value) {
        templateForm.put(route('wasender.templates.update', editingTemplate.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showTemplateModal.value = false;
                templateForm.reset();
            },
        });
    } else {
        templateForm.post(route('wasender.templates.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showTemplateModal.value = false;
                templateForm.reset();
            },
        });
    }
}

function deleteTemplate(id, title) {
    if (confirm(`Hapus template pesan "${title}"?`)) {
        router.delete(route('wasender.templates.destroy', id), {
            preserveScroll: true,
        });
    }
}

const copiedTemplateId = ref(null);
function copyTemplateContent(id, content) {
    navigator.clipboard.writeText(content);
    copiedTemplateId.value = id;
    setTimeout(() => {
        copiedTemplateId.value = null;
    }, 2000);
}
</script>

<template>
    <DashboardLayout :user="authUser" active-module="wasender" title="Buku Kontak & Draft Pesan">
        <Head title="Buku Kontak & Draft Pesan - WaSender" />

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header Section & Navigation Tabs -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-3 border-b border-border">
                <div>
                    <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight text-foreground flex items-center gap-2.5">
                        <Users class="w-6 h-6 text-emerald-600 shrink-0" />
                        <span>Buku Kontak & Draft Pesan</span>
                    </h1>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Kelola grup kontak penerima WhatsApp dan draf template pesan personalisasi untuk ekstensi Chrome.
                    </p>
                </div>

                <!-- Tab Switcher Navigation -->
                <div class="inline-flex p-1 bg-muted rounded-2xl border border-border shrink-0 self-start sm:self-auto">
                    <button
                        type="button"
                        @click="activeTab = 'contacts'"
                        :class="[
                            'inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer',
                            activeTab === 'contacts'
                                ? 'bg-background text-emerald-700 dark:text-emerald-400 shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        ]"
                    >
                        <Users class="w-3.5 h-3.5 text-emerald-600" />
                        <span>Buku Kontak & Grup</span>
                        <span class="ml-1 text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 font-mono">
                            {{ quota.total_saved }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'templates'"
                        :class="[
                            'inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer',
                            activeTab === 'templates'
                                ? 'bg-background text-emerald-700 dark:text-emerald-400 shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        ]"
                    >
                        <FileText class="w-3.5 h-3.5 text-teal-600" />
                        <span>Draft Pesan</span>
                        <span class="ml-1 text-[10px] px-2 py-0.5 rounded-full bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300 font-mono">
                            {{ templates.length }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- TAB 1: BUKU KONTAK & GRUP -->
            <div v-if="activeTab === 'contacts'" class="space-y-6">
                <!-- Action Bar & Capacity Highlight -->
                <Card class="shadow-xs border-border">
                    <CardContent class="p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3.5">
                            <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center font-bold shrink-0">
                                <Users class="w-5 h-5" />
                            </div>
                            <div>
                                <span class="text-[11px] text-muted-foreground font-semibold uppercase tracking-wider block">Kapasitas Kontak Tersimpan</span>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <strong class="text-lg font-extrabold text-foreground font-mono">
                                        {{ quota.total_saved }}
                                        <span class="text-xs text-muted-foreground font-normal">
                                            / {{ quota.is_unlimited ? 'Unlimited' : `${quota.max_contacts} kontak` }}
                                        </span>
                                    </strong>
                                    <Badge
                                        v-if="!quota.is_unlimited && Number(quota.remaining) <= 10"
                                        variant="outline"
                                        class="text-[10px] bg-amber-50 text-amber-700 border-amber-200"
                                    >
                                        Sisa {{ quota.remaining }}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <!-- Global Actions -->
                        <div class="flex items-center gap-2 flex-wrap">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="openImportModal()"
                                class="text-xs font-semibold gap-1.5 cursor-pointer"
                            >
                                <Upload class="w-3.5 h-3.5 text-muted-foreground" />
                                <span>Impor CSV</span>
                            </Button>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="exportGroupCsv(currentGroup)"
                                class="text-xs font-semibold gap-1.5 cursor-pointer"
                            >
                                <Download class="w-3.5 h-3.5 text-muted-foreground" />
                                <span>Ekspor CSV</span>
                            </Button>

                            <Button
                                type="button"
                                size="sm"
                                @click="openAddContactModal()"
                                class="text-xs font-semibold gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer shadow-xs"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Tambah Kontak</span>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- VIEW 1: GROUP CARDS GRID (When no group is opened) -->
                <div v-if="currentGroup === null" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-foreground">Kartu Segmentasi Grup</h2>
                            <p class="text-xs text-muted-foreground">Pilih salah satu kartu grup di bawah untuk mengelola nomor kontak di dalamnya.</p>
                        </div>

                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            @click="showNewGroupModal = true"
                            class="text-xs font-semibold gap-1.5 cursor-pointer"
                        >
                            <FolderPlus class="w-3.5 h-3.5 text-emerald-600" />
                            <span>Buat Grup Baru</span>
                        </Button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <!-- Card: Semua Kontak -->
                        <div
                            @click="selectGroupCard('Semua Kontak')"
                            class="p-5 rounded-2xl bg-card border border-border hover:border-emerald-500 shadow-xs hover:shadow-md transition cursor-pointer group flex flex-col justify-between"
                        >
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center group-hover:scale-105 transition-transform">
                                        <Users class="w-5 h-5" />
                                    </div>
                                    <span class="text-xs font-bold font-mono text-emerald-800 bg-emerald-50 dark:bg-emerald-950 dark:text-emerald-300 px-2.5 py-0.5 rounded-full border border-emerald-200">
                                        {{ quota.total_saved }} Kontak
                                    </span>
                                </div>
                                <h3 class="text-sm font-bold text-foreground group-hover:text-emerald-700 transition-colors">
                                    Semua Kontak
                                </h3>
                                <p class="text-[11px] text-muted-foreground mt-1">
                                    Seluruh kontak tersimpan dari semua grup.
                                </p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-border flex items-center justify-between text-xs font-semibold text-emerald-600 group-hover:translate-x-1 transition-transform">
                                <span>Buka Semua Kontak</span>
                                <ChevronRight class="w-4 h-4" />
                            </div>
                        </div>

                        <!-- Card Tiap Grup Dinamis -->
                        <div
                            v-for="group in groupsWithCounts"
                            :key="group.name"
                            @click="selectGroupCard(group.name)"
                            class="p-5 rounded-2xl bg-card border border-border hover:border-emerald-500 shadow-xs hover:shadow-md transition cursor-pointer group flex flex-col justify-between"
                        >
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200 flex items-center justify-center group-hover:scale-105 transition-transform">
                                        <Folder class="w-5 h-5" />
                                    </div>
                                    <span class="text-xs font-bold font-mono text-foreground bg-muted px-2.5 py-0.5 rounded-full border border-border">
                                        {{ group.count }} Kontak
                                    </span>
                                </div>
                                <h3 class="text-sm font-bold text-foreground group-hover:text-indigo-700 transition-colors truncate">
                                    {{ group.name }}
                                </h3>
                                <p class="text-[11px] text-muted-foreground mt-1">
                                    Grup penerima pesan terpisah.
                                </p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-border flex items-center justify-between text-xs font-semibold text-indigo-600 group-hover:translate-x-1 transition-transform">
                                <span>Buka Kontak Grup</span>
                                <ChevronRight class="w-4 h-4" />
                            </div>
                        </div>

                        <!-- Card: + Buat Grup Baru -->
                        <div
                            @click="showNewGroupModal = true"
                            class="p-5 rounded-2xl bg-muted/30 border-2 border-dashed border-border hover:border-emerald-400 hover:bg-emerald-50/20 transition cursor-pointer flex flex-col items-center justify-center text-center group min-h-[160px]"
                        >
                            <div class="w-10 h-10 rounded-xl bg-card border border-border flex items-center justify-center text-muted-foreground group-hover:text-emerald-600 group-hover:border-emerald-300 transition mb-2">
                                <Plus class="w-5 h-5" />
                            </div>
                            <span class="text-xs font-bold text-foreground group-hover:text-emerald-700">
                                + Buat Grup Baru
                            </span>
                            <span class="text-[10px] text-muted-foreground mt-0.5">Kelompokkan nomor kontak baru</span>
                        </div>
                    </div>
                </div>

                <!-- VIEW 2: ISI KONTAK DALAM GRUP (Opened when a card is clicked) -->
                <div v-else class="space-y-4">
                    <!-- Navigation Bar -->
                    <div class="p-3.5 bg-card rounded-2xl border border-border shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="selectGroupCard(null)"
                                class="text-xs font-bold gap-1 cursor-pointer"
                                title="Kembali ke semua kartu grup"
                            >
                                <ArrowLeft class="w-3.5 h-3.5" />
                                <span>Kembali ke Kartu Grup</span>
                            </Button>

                            <div class="border-l border-border pl-3">
                                <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider block">Grup Terbuka:</span>
                                <strong class="text-sm font-bold text-foreground">{{ currentGroup }}</strong>
                            </div>
                        </div>

                        <!-- In-group actions -->
                        <div class="flex items-center gap-2 flex-wrap">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="openImportModal(currentGroup)"
                                class="text-xs font-semibold gap-1.5 cursor-pointer"
                            >
                                <Upload class="w-3.5 h-3.5 text-muted-foreground" />
                                <span>{{ currentGroup === 'Semua Kontak' ? 'Impor CSV' : 'Impor ke Grup Ini' }}</span>
                            </Button>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="exportGroupCsv(currentGroup)"
                                class="text-xs font-semibold gap-1.5 cursor-pointer"
                            >
                                <Download class="w-3.5 h-3.5 text-muted-foreground" />
                                <span>{{ currentGroup === 'Semua Kontak' ? 'Ekspor Semua' : 'Ekspor Grup' }}</span>
                            </Button>

                            <Button
                                type="button"
                                size="sm"
                                @click="openAddContactModal(currentGroup)"
                                class="text-xs font-semibold gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer shadow-xs"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>{{ currentGroup === 'Semua Kontak' ? '+ Tambah Kontak' : '+ Kontak di Grup Ini' }}</span>
                            </Button>
                        </div>
                    </div>

                    <!-- Search Filter inside group -->
                    <div class="bg-card p-3 rounded-2xl border border-border shadow-xs">
                        <div class="relative">
                            <Search class="w-4 h-4 text-muted-foreground absolute left-3.5 top-2.5" />
                            <input
                                v-model="contactSearch"
                                @keyup.enter="handleContactFilter"
                                type="text"
                                placeholder="Cari nama, nomor WhatsApp, atau catatan..."
                                class="w-full pl-10 pr-24 py-2 text-xs rounded-xl bg-muted/50 border border-border text-foreground focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                            />
                            <button
                                type="button"
                                @click="handleContactFilter"
                                class="absolute right-2 top-1.5 px-3 py-1 text-xs font-semibold bg-muted hover:bg-muted/80 text-foreground rounded-lg transition cursor-pointer"
                            >
                                Cari
                            </button>
                        </div>
                    </div>

                    <!-- Contacts Table -->
                    <div class="bg-card rounded-2xl border border-border overflow-hidden shadow-xs">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs min-w-[700px]">
                                <thead class="bg-muted/40 text-muted-foreground uppercase font-semibold border-b border-border text-[11px]">
                                    <tr>
                                        <th class="p-3.5">Nama Kontak</th>
                                        <th class="p-3.5">Nomor WhatsApp</th>
                                        <th class="p-3.5">Grup</th>
                                        <th class="p-3.5">Catatan</th>
                                        <th class="p-3.5">Waktu Disimpan</th>
                                        <th class="p-3.5 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border text-foreground">
                                    <tr v-for="c in contacts.data" :key="c.id" class="hover:bg-muted/30 transition">
                                        <td class="p-3.5 font-bold text-foreground">
                                            {{ c.name }}
                                        </td>
                                        <td class="p-3.5">
                                            <a
                                                :href="`https://wa.me/${c.phone}`"
                                                target="_blank"
                                                class="font-mono font-semibold text-emerald-600 hover:text-emerald-700 hover:underline inline-flex items-center gap-1"
                                                title="Chat via WhatsApp"
                                            >
                                                <span>{{ c.phone }}</span>
                                                <ExternalLink class="w-3 h-3 text-muted-foreground" />
                                            </a>
                                        </td>
                                        <td class="p-3.5">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 border border-indigo-200">
                                                {{ c.group_name || 'Umum' }}
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-muted-foreground text-[11px] max-w-xs truncate">
                                            {{ c.notes || '-' }}
                                        </td>
                                        <td class="p-3.5 text-muted-foreground text-[11px]">
                                            {{ c.created_at }}
                                        </td>
                                        <td class="p-3.5 text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    @click="openEditContactModal(c)"
                                                    class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-lg transition cursor-pointer"
                                                    title="Ubah Kontak"
                                                >
                                                    <Pencil class="w-3.5 h-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="deleteContact(c.id, c.name)"
                                                    class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950 rounded-lg transition cursor-pointer"
                                                    title="Hapus Kontak"
                                                >
                                                    <Trash2 class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="contacts.data.length === 0">
                                        <td colspan="6" class="p-8 text-center text-muted-foreground">
                                            Belum ada kontak di dalam grup ini. Klik "Tambah Kontak" atau "Impor CSV" untuk menambahkan nomor.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Links -->
                        <div v-if="contacts.links && contacts.links.length > 3" class="p-3 border-t border-border flex items-center justify-between text-xs">
                            <div class="flex items-center gap-1 flex-wrap">
                                <Component
                                    :is="link.url ? Link : 'span'"
                                    v-for="(link, i) in contacts.links"
                                    :key="i"
                                    :href="link.url || '#'"
                                    v-html="link.label"
                                    class="px-3 py-1.5 rounded-lg text-xs transition"
                                    :class="link.active ? 'bg-emerald-600 text-white font-bold' : (link.url ? 'text-foreground hover:bg-muted' : 'text-muted-foreground opacity-50')"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: DRAFT TEMPLATE PESAN -->
            <div v-if="activeTab === 'templates'" class="space-y-6">
                <!-- Highlight Bar -->
                <Card class="shadow-xs border-border">
                    <CardContent class="p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3.5">
                            <div class="w-11 h-11 rounded-2xl bg-teal-50 text-teal-700 border border-teal-200 flex items-center justify-center font-bold shrink-0">
                                <FileText class="w-5 h-5" />
                            </div>
                            <div>
                                <span class="text-[11px] text-muted-foreground font-semibold uppercase tracking-wider block">Kapasitas Template Pesan</span>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <strong class="text-lg font-extrabold text-foreground font-mono">
                                        {{ templates.length }}
                                        <span class="text-xs text-muted-foreground font-normal">
                                            / {{ templatesQuota.is_unlimited ? 'Unlimited' : `${templatesQuota.max_templates} template` }}
                                        </span>
                                    </strong>
                                    <Badge
                                        v-if="!templatesQuota.is_unlimited && Number(templatesQuota.remaining) <= 2"
                                        variant="outline"
                                        class="text-[10px] bg-amber-50 text-amber-700 border-amber-200"
                                    >
                                        Sisa {{ templatesQuota.remaining }}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <Button
                            type="button"
                            size="sm"
                            @click="openAddTemplateModal()"
                            class="text-xs font-semibold gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer shadow-xs"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Buat Template Baru</span>
                        </Button>
                    </CardContent>
                </Card>

                <!-- Search Filter -->
                <div class="bg-card p-3 rounded-2xl border border-border shadow-xs">
                    <div class="relative">
                        <Search class="w-4 h-4 text-muted-foreground absolute left-3.5 top-2.5" />
                        <input
                            v-model="templateSearch"
                            type="text"
                            placeholder="Cari judul, kategori, atau isi template..."
                            class="w-full pl-10 pr-4 py-2 text-xs rounded-xl bg-muted/50 border border-border text-foreground focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                        />
                    </div>
                </div>

                <!-- Templates Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="t in filteredTemplates"
                        :key="t.id"
                        class="bg-card p-5 rounded-2xl border border-border shadow-xs flex flex-col justify-between space-y-4 hover:border-emerald-400 transition"
                    >
                        <div class="space-y-2.5">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-bold text-foreground">{{ t.title }}</h3>
                                    <div class="flex items-center gap-1.5 flex-wrap mt-1">
                                        <span class="inline-block text-[10px] font-bold text-indigo-700 bg-indigo-50 dark:bg-indigo-950 dark:text-indigo-300 border border-indigo-200 px-2.5 py-0.5 rounded-full">
                                            {{ t.category }}
                                        </span>
                                        <span
                                            v-if="t.has_random_code"
                                            class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 bg-amber-50 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 px-2 py-0.5 rounded-full"
                                            title="Menyematkan kode unik di baris akhir pesan"
                                        >
                                            <Hash class="w-2.5 h-2.5" />
                                            <span>Kode Acak</span>
                                        </span>
                                        <span
                                            v-if="hasSpintax(t.content)"
                                            class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-50 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 px-2 py-0.5 rounded-full"
                                            title="Teks diacak per penerima (Anti-Banned)"
                                        >
                                            <Shuffle class="w-2.5 h-2.5" />
                                            <span>Teks Acak</span>
                                        </span>
                                    </div>
                                </div>
                                <span class="text-[10px] text-muted-foreground">{{ t.created_at }}</span>
                            </div>

                            <!-- Preview Box -->
                            <div class="p-3.5 rounded-xl bg-muted/40 border border-border text-xs text-foreground font-mono whitespace-pre-wrap line-clamp-4 leading-relaxed">
                                {{ t.content }}
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="flex items-center justify-between pt-3 border-t border-border">
                            <button
                                type="button"
                                @click="copyTemplateContent(t.id, t.content)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                                :class="copiedTemplateId === t.id ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 font-bold' : 'bg-muted hover:bg-muted/80 text-foreground'"
                            >
                                <component :is="copiedTemplateId === t.id ? Check : Copy" class="w-3.5 h-3.5" />
                                <span>{{ copiedTemplateId === t.id ? 'Tersalin!' : 'Salin Pesan' }}</span>
                            </button>

                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    @click="openEditTemplateModal(t)"
                                    class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-lg transition cursor-pointer"
                                    title="Edit Template"
                                >
                                    <Pencil class="w-3.5 h-3.5" />
                                </button>
                                <button
                                    type="button"
                                    @click="deleteTemplate(t.id, t.title)"
                                    class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950 rounded-lg transition cursor-pointer"
                                    title="Hapus Template"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="filteredTemplates.length === 0" class="col-span-full bg-card p-8 rounded-2xl border border-border text-center text-muted-foreground text-xs">
                        Belum ada template pesan yang tersimpan. Klik tombol "Buat Template Baru" untuk menambahkan draf template Anda.
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Tambah / Edit Kontak -->
        <div v-if="showContactModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div class="bg-card rounded-2xl max-w-md w-full p-6 border border-border shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <h3 class="text-sm font-bold text-foreground">
                        {{ editingContact ? 'Ubah Data Kontak' : 'Tambah Kontak Baru' }}
                    </h3>
                    <button type="button" @click="showContactModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="saveContact" class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Nama Penerima</label>
                        <input
                            v-model="contactForm.name"
                            type="text"
                            required
                            placeholder="Misal: Ibu Dewi"
                            class="w-full px-3.5 py-2 text-xs rounded-xl bg-muted/50 border border-border text-foreground focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Nomor WhatsApp</label>
                        <input
                            v-model="contactForm.phone"
                            type="text"
                            required
                            placeholder="08123456789 atau 628123456789"
                            class="w-full px-3.5 py-2 text-xs rounded-xl bg-muted/50 border border-border text-foreground font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                        />
                        <span class="text-[10px] text-muted-foreground mt-0.5 block">Format lokal (08...) otomatis dikonversi ke format internasional (628...).</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Nama Grup</label>
                        <input
                            v-model="contactForm.group_name"
                            type="text"
                            list="group-options"
                            placeholder="Misal: Pelanggan VIP, Reseller, dll"
                            class="w-full px-3.5 py-2 text-xs rounded-xl bg-muted/50 border border-border text-foreground focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                        />
                        <datalist id="group-options">
                            <option v-for="g in groups" :key="g" :value="g" />
                        </datalist>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Catatan Tambahan (Opsional)</label>
                        <textarea
                            v-model="contactForm.notes"
                            rows="2"
                            placeholder="Alamat atau detail pesanan..."
                            class="w-full px-3.5 py-2 text-xs rounded-xl bg-muted/50 border border-border text-foreground focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-border">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="showContactModal = false"
                            class="text-xs font-semibold"
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="contactForm.processing"
                            class="text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white"
                        >
                            {{ editingContact ? 'Simpan Perubahan' : 'Simpan Kontak' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Buat Grup Baru -->
        <div v-if="showNewGroupModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div class="bg-card rounded-2xl max-w-sm w-full p-6 border border-border shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <h3 class="text-sm font-bold text-foreground">Buat Grup Kontak Baru</h3>
                    <button type="button" @click="showNewGroupModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitNewGroup" class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Nama Grup Baru</label>
                        <input
                            v-model="newGroupName"
                            type="text"
                            required
                            placeholder="Misal: Pelanggan Promo"
                            class="w-full px-3.5 py-2 text-xs rounded-xl bg-muted/50 border border-border text-foreground focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="showNewGroupModal = false"
                            class="text-xs font-semibold"
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            class="text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white"
                        >
                            Lanjutkan
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Impor Kontak CSV -->
        <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div class="bg-card rounded-2xl max-w-md w-full p-6 border border-border shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <h3 class="text-sm font-bold text-foreground">Impor Kontak CSV</h3>
                    <button type="button" @click="showImportModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitImport" class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Target Grup Kontak</label>
                        <input
                            v-model="importForm.group_name"
                            type="text"
                            required
                            class="w-full px-3.5 py-2 text-xs rounded-xl bg-muted/50 border border-border text-foreground focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Pilih Berkas CSV (.csv)</label>
                        <input
                            type="file"
                            accept=".csv,text/csv"
                            required
                            @change="handleFileUpload"
                            class="w-full text-xs text-muted-foreground file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-950 dark:file:text-emerald-300 cursor-pointer"
                        />
                        <p class="text-[10px] text-muted-foreground mt-1">
                            Format CSV: Kolom 1 = Nama, Kolom 2 = Nomor WhatsApp.
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-border">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="showImportModal = false"
                            class="text-xs font-semibold"
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="importForm.processing || !importForm.file"
                            class="text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white disabled:opacity-50"
                        >
                            Mulai Impor
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Tambah / Edit Template Pesan -->
        <div v-if="showTemplateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div class="bg-card rounded-2xl max-w-lg w-full p-6 border border-border shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <h3 class="text-sm font-bold text-foreground">
                        {{ editingTemplate ? 'Ubah Template Pesan' : 'Buat Template Pesan Baru' }}
                    </h3>
                    <button type="button" @click="showTemplateModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="saveTemplate" class="space-y-3.5">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-foreground mb-1">Judul Template</label>
                            <input
                                v-model="templateForm.title"
                                type="text"
                                required
                                placeholder="Misal: Tagihan Jatuh Tempo"
                                class="w-full px-3.5 py-2 text-xs rounded-xl bg-muted/50 border border-border text-foreground focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-foreground mb-1">Kategori</label>
                            <select
                                v-model="templateForm.category"
                                class="w-full px-3 py-2 text-xs rounded-xl bg-muted/50 border border-border text-foreground focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                            >
                                <option value="Umum">Umum</option>
                                <option value="Promosi">Promosi</option>
                                <option value="Billing / Tagihan">Billing / Tagihan</option>
                                <option value="Pengumuman">Pengumuman</option>
                                <option value="Follow-Up">Follow-Up</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-semibold text-foreground">Isi Pesan WhatsApp</label>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[10px] text-muted-foreground">Variabel:</span>
                                <button
                                    type="button"
                                    @click="insertTemplateTag('{nama}')"
                                    class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 hover:bg-emerald-100 transition cursor-pointer"
                                    title="Nama Penerima Pesan"
                                >
                                    {nama}
                                </button>
                                <button
                                    type="button"
                                    @click="insertTemplateTag('{nomor}')"
                                    class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 border border-indigo-200 hover:bg-indigo-100 transition cursor-pointer"
                                    title="Nomor WhatsApp Penerima"
                                >
                                    {nomor}
                                </button>
                                <button
                                    type="button"
                                    @click="insertSpintaxSample"
                                    class="text-[10px] font-mono px-2 py-0.5 rounded transition inline-flex items-center gap-1 cursor-pointer bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 hover:bg-amber-100"
                                    title="Sisipkan variasi teks acak [opsi1/opsi2]"
                                >
                                    <Shuffle class="w-3 h-3" />
                                    <span>+ [acak]</span>
                                </button>
                            </div>
                        </div>

                        <textarea
                            v-model="templateForm.content"
                            rows="5"
                            required
                            placeholder="[Halo/Selamat pagi] {nama}, terima kasih telah bergabung..."
                            class="w-full px-3.5 py-2 text-xs rounded-xl bg-muted/50 border border-border text-foreground font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 leading-relaxed"
                        ></textarea>
                    </div>

                    <!-- Anti-Banned & Random Code Options -->
                    <div class="p-3.5 bg-muted/30 border border-border rounded-xl space-y-2.5">
                        <label class="flex items-start gap-2.5 select-none cursor-pointer">
                            <input
                                v-model="templateForm.has_random_code"
                                type="checkbox"
                                class="mt-0.5 rounded text-emerald-600 focus:ring-emerald-500 border-border"
                            />
                            <div class="space-y-0.5">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-foreground">Sematkan kode acak di baris akhir pesan (Anti-Banned)</span>
                                </div>
                                <p class="text-[11px] text-muted-foreground leading-relaxed">
                                    Otomatis menyematkan kode unik (contoh: <code class="font-mono bg-background px-1 py-0.5 rounded border border-border text-foreground">#9AX2M</code>) di baris terakhir pesan agar WhatsApp mengenali setiap pesan sebagai unik dan personal.
                                </p>
                            </div>
                        </label>

                        <div class="pt-2 border-t border-border flex items-start gap-2 text-[11px] text-muted-foreground">
                            <Sparkles class="w-3.5 h-3.5 text-amber-500 shrink-0 mt-0.5" />
                            <div class="leading-relaxed">
                                <strong class="text-foreground">Variasi Teks Acak (Spintax):</strong> Format <code class="bg-background px-1.5 py-0.5 rounded border border-border font-mono text-foreground text-[10px]">[halo/selamat pagi/hai]</code> akan diacak untuk tiap penerima.
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-border">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="showTemplateModal = false"
                            class="text-xs font-semibold"
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="templateForm.processing"
                            class="text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white"
                        >
                            {{ editingTemplate ? 'Simpan Perubahan' : 'Simpan Template' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
