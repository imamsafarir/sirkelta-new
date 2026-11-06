<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { 
    Settings, 
    HelpCircle, 
    Phone, 
    Mail, 
    Gift, 
    CheckCircle2, 
    Plus, 
    Edit, 
    Trash2, 
    X, 
    Save,
    FileText,
    ShieldCheck,
    RotateCcw,
    ExternalLink,
    MapPin,
    Clock,
    Sparkles,
    Calendar,
    CreditCard,
    QrCode,
    Building2,
    Key,
    Check,
    Copy,
    Upload,
    Eye,
    EyeOff,
    AlertCircle,
    AlertTriangle,
    Zap
} from 'lucide-vue-next';
import RichTextEditor from '@/Components/RichTextEditor.vue';

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
    paymentSettings: {
        type: Object,
        default: () => ({}),
    },
    legalPages: {
        type: Object,
        required: true,
    },
    faqs: {
        type: Array,
        default: () => [],
    },
    activeTab: {
        type: String,
        default: 'faqs',
    },
});

const page = usePage();
const currentTab = ref(props.activeTab || 'faqs');

// Settings & Contact Form
const settingsForm = useForm({
    support_whatsapp: props.settings.support_whatsapp || '',
    support_email: props.settings.support_email || '',
    contact_address: props.settings.contact_address || 'Indonesia',
    contact_hours: props.settings.contact_hours || 'Senin - Sabtu: 08.00 - 18.00 WIB',
    contact_description: props.settings.contact_description || '',
    referral_discount_amount: props.settings.referral_discount_amount || 25000,
});

const saveSettings = () => {
    settingsForm.clearErrors();
    if (!settingsForm.support_whatsapp.trim()) {
        settingsForm.setError('support_whatsapp', 'Nomor WhatsApp resmi wajib diisi.');
        return;
    }
    if (!settingsForm.support_email.trim()) {
        settingsForm.setError('support_email', 'Alamat email dukungan wajib diisi.');
        return;
    }
    settingsForm.put(route('admin.website.settings.update'), {
        preserveScroll: true,
    });
};

// Legal Pages Forms
const pageForms = {
    about_us: useForm({
        page_key: 'about_us',
        content: props.legalPages.about_us?.content || '',
    }),
    privacy_policy: useForm({
        page_key: 'privacy_policy',
        content: props.legalPages.privacy_policy?.content || '',
    }),
    terms_of_service: useForm({
        page_key: 'terms_of_service',
        content: props.legalPages.terms_of_service?.content || '',
    }),
    refund_policy: useForm({
        page_key: 'refund_policy',
        content: props.legalPages.refund_policy?.content || '',
    }),
};

const savePageContent = (key) => {
    pageForms[key].clearErrors();
    const rawText = pageForms[key].content ? pageForms[key].content.replace(/<[^>]*>/g, '').trim() : '';
    if (!pageForms[key].content || (!rawText && !pageForms[key].content.includes('<table') && !pageForms[key].content.includes('<hr'))) {
        pageForms[key].setError('content', 'Isi konten halaman tidak boleh kosong.');
        return;
    }
    pageForms[key].put(route('admin.website.page.update'), {
        preserveScroll: true,
    });
};

// FAQ Form & Modal
const isFaqModalOpen = ref(false);
const editingFaq = ref(null);
const faqForm = useForm({
    category: 'Umum',
    question: '',
    answer: '',
    order: 0,
    is_active: true,
});

const openCreateFaq = () => {
    editingFaq.value = null;
    faqForm.reset();
    faqForm.category = 'Umum';
    faqForm.is_active = true;
    isFaqModalOpen.value = true;
};

const openEditFaq = (faq) => {
    editingFaq.value = faq;
    faqForm.category = faq.category;
    faqForm.question = faq.question;
    faqForm.answer = faq.answer;
    faqForm.order = faq.order;
    faqForm.is_active = !!faq.is_active;
    isFaqModalOpen.value = true;
};

const closeFaqModal = () => {
    isFaqModalOpen.value = false;
    editingFaq.value = null;
    faqForm.reset();
};

const saveFaq = () => {
    faqForm.clearErrors();
    if (!faqForm.category.trim()) {
        faqForm.setError('category', 'Kategori wajib diisi.');
        return;
    }
    if (!faqForm.question.trim()) {
        faqForm.setError('question', 'Pertanyaan wajib diisi.');
        return;
    }
    if (!faqForm.answer.trim()) {
        faqForm.setError('answer', 'Jawaban wajib diisi.');
        return;
    }
    if (editingFaq.value) {
        faqForm.put(route('admin.website.faqs.update', editingFaq.value.id), {
            onSuccess: () => closeFaqModal(),
            preserveScroll: true,
        });
    } else {
        faqForm.post(route('admin.website.faqs.store'), {
            onSuccess: () => closeFaqModal(),
            preserveScroll: true,
        });
    }
};

const deleteFaq = (faq) => {
    if (confirm(`Hapus pertanyaan "${faq.question}"?`)) {
        faqForm.delete(route('admin.website.faqs.destroy', faq.id), {
            preserveScroll: true,
        });
    }
};

// Payment Settings Form
const paymentForm = useForm({
    payment_manual_enabled: props.paymentSettings?.payment_manual_enabled ?? true,
    payment_bank_name: props.paymentSettings?.payment_bank_name || '',
    payment_bank_account_number: props.paymentSettings?.payment_bank_account_number || '',
    payment_bank_account_holder: props.paymentSettings?.payment_bank_account_holder || '',
    payment_qris_merchant_name: props.paymentSettings?.payment_qris_merchant_name || '',
    qris_image: null,
    payment_midtrans_enabled: props.paymentSettings?.payment_midtrans_enabled ?? false,
    payment_midtrans_mode: props.paymentSettings?.payment_midtrans_mode || 'sandbox',
    payment_midtrans_client_key: props.paymentSettings?.payment_midtrans_client_key || '',
    payment_midtrans_server_key: props.paymentSettings?.payment_midtrans_server_key || '',
    payment_midtrans_signature_key: props.paymentSettings?.payment_midtrans_signature_key || '',
    payment_midtrans_merchant_id: props.paymentSettings?.payment_midtrans_merchant_id || '',
});

const qrisPreview = ref(props.paymentSettings?.payment_qris_image_path || null);
const showServerKey = ref(false);
const showSignatureKey = ref(false);
const copiedField = ref(null);

const originUrl = computed(() => {
    return typeof window !== 'undefined' ? window.location.origin : '';
});

const midtransUrls = computed(() => [
    {
        key: 'finish_redirect',
        label: 'Finish Redirect URL',
        url: `${originUrl.value}/profile?payment=finish`,
        desc: 'URL tujuan saat pembeli selesai melakukan transaksi di Midtrans Snap agar diarahkan kembali ke akun Sirkelta.',
        badge: 'Redirection'
    },
    {
        key: 'payment_notification',
        label: 'Payment Notification URL',
        url: `${originUrl.value}/api/midtrans/webhook`,
        desc: 'Endpoint webhook utama untuk menerima notifikasi status pembayaran berhasil, pending, atau expired secara otomatis.',
        badge: 'Webhook Utama'
    },
    {
        key: 'recurring_notification',
        label: 'Recurring Payment Notification URL',
        url: `${originUrl.value}/api/midtrans/webhook`,
        desc: 'Endpoint webhook untuk notifikasi transaksi berulang / perpanjangan paket otomatis.',
        badge: 'Recurring'
    },
    {
        key: 'account_linking',
        label: 'Account Linking Notification URL',
        url: `${originUrl.value}/api/midtrans/webhook`,
        desc: 'Endpoint webhook untuk notifikasi penautan akun pembayaran instan (GoPay Tokenization, dsb).',
        badge: 'Account Linking'
    }
]);

const handleQrisFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        paymentForm.qris_image = file;
        qrisPreview.value = URL.createObjectURL(file);
    }
};

const copyUrl = async (url, fieldKey) => {
    try {
        if (navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(url);
        } else {
            const ta = document.createElement('textarea');
            ta.value = url;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        copiedField.value = fieldKey;
        setTimeout(() => {
            if (copiedField.value === fieldKey) {
                copiedField.value = null;
            }
        }, 2000);
    } catch (err) {
        console.error('Gagal menyalin URL:', err);
    }
};

const testingMidtrans = ref(false);
const midtransTestResult = ref(null);

const testMidtransConnection = async () => {
    if (!paymentForm.payment_midtrans_server_key) {
        midtransTestResult.value = {
            success: false,
            message: 'Harap isi Signature Secret Key (Server Key) terlebih dahulu untuk menguji koneksi.',
        };
        return;
    }

    testingMidtrans.value = true;
    midtransTestResult.value = null;

    let testUrl = '/admin/website/payment-settings/test-midtrans';
    try {
        if (typeof route === 'function') {
            testUrl = route('admin.website.payment.test-midtrans');
        }
    } catch {
        testUrl = '/admin/website/payment-settings/test-midtrans';
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || (document.cookie.match(/XSRF-TOKEN=([^;]+)/) ? decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1]) : '');

    try {
        const response = await fetch(testUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-XSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                mode: paymentForm.payment_midtrans_mode,
                server_key: paymentForm.payment_midtrans_server_key,
                client_key: paymentForm.payment_midtrans_client_key,
            }),
        });

        const data = await response.json();
        midtransTestResult.value = {
            success: response.ok && data.success,
            message: data.message || (response.ok ? 'Koneksi berhasil!' : 'Koneksi gagal.'),
        };
    } catch (err) {
        midtransTestResult.value = {
            success: false,
            message: 'Gagal menghubungi server aplikasi: ' + err.message,
        };
    } finally {
        testingMidtrans.value = false;
    }
};

const savePaymentSettings = () => {
    paymentForm
        .transform((data) => ({
            ...data,
            payment_manual_enabled: data.payment_manual_enabled ? 1 : 0,
            payment_midtrans_enabled: data.payment_midtrans_enabled ? 1 : 0,
        }))
        .post(route('admin.website.payment.update'), {
            preserveScroll: true,
            forceFormData: true,
        });
};

const tabs = [
    { id: 'faqs', name: 'FAQ & Tanya Jawab', icon: HelpCircle },
    { id: 'payment', name: 'Metode Pembayaran', icon: CreditCard },
    { id: 'contact', name: 'Kontak & Hubungi Kami', icon: Phone },
    { id: 'about_us', name: 'Tentang Kami', icon: Sparkles },
    { id: 'privacy_policy', name: 'Kebijakan Privasi', icon: ShieldCheck },
    { id: 'terms_of_service', name: 'Syarat & Ketentuan', icon: FileText },
    { id: 'refund_policy', name: 'Pengembalian Dana', icon: RotateCcw },
];

const getPublicUrl = (tabId) => {
    switch (tabId) {
        case 'about_us': return route('about');
        case 'privacy_policy': return route('privacy');
        case 'terms_of_service': return route('terms');
        case 'refund_policy': return route('refund');
        case 'contact': return route('contact');
        case 'faqs': return route('faq');
        case 'payment': return route('profile.edit');
        default: return '#';
    }
};
</script>

<template>
    <DashboardLayout 
        title="Pengaturan Website, FAQ & Halaman Informasi" 
        activeModule="admin" 
        :user="page.props.auth.user"
    >
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-foreground">Pengaturan & FAQ</h1>
                </div>

                <!-- Preview Public Website Button -->
                <div class="flex items-center gap-2">
                    <a :href="getPublicUrl(currentTab)" target="_blank">
                        <Button variant="outline" size="sm" class="gap-1.5 text-xs font-semibold">
                            <span>Lihat Halaman Publik</span>
                            <ExternalLink class="h-3.5 w-3.5" />
                        </Button>
                    </a>
                </div>
            </div>

            <!-- Tab Navigation Bar -->
            <div class="flex flex-wrap items-center gap-2 border-b pb-3">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    @click="currentTab = t.id"
                    class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all"
                    :class="currentTab === t.id 
                        ? 'bg-primary text-primary-foreground shadow-xs' 
                        : 'bg-card border text-muted-foreground hover:bg-muted/70 hover:text-foreground'"
                >
                    <component :is="t.icon" class="h-3.5 w-3.5" />
                    <span>{{ t.name }}</span>
                </button>
            </div>

            <!-- TAB 1: FAQ & Tanya Jawab -->
            <div v-if="currentTab === 'faqs'" class="space-y-4">
                <Card>
                    <CardHeader class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b">
                        <div>
                            <CardTitle class="text-base font-bold">Daftar Tanya Jawab Publik (FAQ)</CardTitle>
                            <CardDescription class="text-xs">
                                Pertanyaan di bawah ini langsung muncul pada halaman <a :href="route('faq')" target="_blank" class="text-primary underline">/faq</a>.
                            </CardDescription>
                        </div>
                        <Button size="sm" class="gap-1.5 text-xs font-bold shrink-0" @click="openCreateFaq">
                            <Plus class="h-3.5 w-3.5" />
                            <span>Tambah Pertanyaan Baru</span>
                        </Button>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-muted/50 text-muted-foreground border-b font-semibold">
                                    <tr>
                                        <th class="py-3 px-4 w-12">No</th>
                                        <th class="py-3 px-4">Kategori</th>
                                        <th class="py-3 px-4">Pertanyaan</th>
                                        <th class="py-3 px-4">Jawaban</th>
                                        <th class="py-3 px-4 w-24">Status</th>
                                        <th class="py-3 px-4 text-right w-24">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="(faq, idx) in faqs" :key="faq.id" class="hover:bg-muted/30">
                                        <td class="py-3 px-4 font-semibold tabular-nums text-muted-foreground">{{ idx + 1 }}</td>
                                        <td class="py-3 px-4">
                                            <Badge variant="outline" class="text-[10px]">{{ faq.category }}</Badge>
                                        </td>
                                        <td class="py-3 px-4 font-semibold text-foreground max-w-xs truncate">
                                            {{ faq.question }}
                                        </td>
                                        <td class="py-3 px-4 text-muted-foreground max-w-md truncate">
                                            {{ faq.answer }}
                                        </td>
                                        <td class="py-3 px-4">
                                            <Badge :variant="faq.is_active ? 'secondary' : 'outline'" class="text-[10px]">
                                                {{ faq.is_active ? 'Aktif' : 'Nonaktif' }}
                                            </Badge>
                                        </td>
                                        <td class="py-3 px-4 text-right space-x-1">
                                            <Button variant="ghost" size="icon" class="h-7 w-7" @click="openEditFaq(faq)">
                                                <Edit class="h-3.5 w-3.5 text-primary" />
                                            </Button>
                                            <Button variant="ghost" size="icon" class="h-7 w-7 text-destructive" @click="deleteFaq(faq)">
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </Button>
                                        </td>
                                    </tr>
                                    <tr v-if="faqs.length === 0">
                                        <td colspan="6" class="py-8 text-center text-muted-foreground">
                                            Belum ada data pertanyaan FAQ. Klik tombol tambah di atas untuk membuat yang pertama.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- TAB: Metode Pembayaran -->
            <div v-else-if="currentTab === 'payment'" class="space-y-6">
                <!-- Warning jika semua metode pembayaran dinonaktifkan -->
                <div 
                    v-if="!paymentForm.payment_manual_enabled && !paymentForm.payment_midtrans_enabled"
                    class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-2xl flex items-start gap-3 text-amber-900 dark:text-amber-200"
                >
                    <AlertTriangle class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                    <div class="space-y-1">
                        <h4 class="text-xs sm:text-sm font-bold text-amber-800 dark:text-amber-300">
                            Seluruh Saluran Pembayaran Sedang Dinonaktifkan
                        </h4>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            Pengguna saat ini <strong>tidak dapat melanjutkan transaksi pembayaran</strong> di halaman profil karena sistem secara otomatis menampilkan pemberitahuan bahwa layanan sedang dalam perbaikan dan menutup akses pembayaran.
                        </p>
                    </div>
                </div>

                <form @submit.prevent="savePaymentSettings" class="space-y-6">
                    <!-- Error Notification Banner -->
                    <div 
                        v-if="paymentForm.hasErrors" 
                        class="p-4 bg-destructive/10 border border-destructive/30 rounded-2xl flex items-start gap-3 text-destructive"
                    >
                        <AlertCircle class="h-5 w-5 text-destructive shrink-0 mt-0.5" />
                        <div class="space-y-1">
                            <h4 class="text-xs sm:text-sm font-bold text-destructive">
                                Gagal Menyimpan Pengaturan Pembayaran
                            </h4>
                            <ul class="text-xs list-disc list-inside space-y-0.5">
                                <li v-for="(err, key) in paymentForm.errors" :key="key">{{ err }}</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Manual Payment Card (Transfer Bank & QRIS) -->
                    <Card>
                        <CardHeader class="pb-3 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <Building2 class="h-4 w-4 text-primary" />
                                    <CardTitle class="text-base font-bold">Pembayaran Manual (Transfer Bank & QRIS)</CardTitle>
                                </div>
                                <CardDescription class="text-xs">
                                    Pengguna mentransfer ke rekening atau memindai barcode QRIS, kemudian mengunggah bukti bayar untuk diverifikasi admin di menu Kelola Transaksi.
                                </CardDescription>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input 
                                    type="checkbox" 
                                    v-model="paymentForm.payment_manual_enabled" 
                                    class="sr-only peer"
                                >
                                <div class="w-11 h-6 bg-muted peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                <span class="ms-2 text-xs font-bold text-foreground">
                                    {{ paymentForm.payment_manual_enabled ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </label>
                        </CardHeader>

                        <CardContent class="space-y-5 pt-4">
                            <!-- Rekening Bank Section -->
                            <div class="space-y-3">
                                <div class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                    <CreditCard class="h-3.5 w-3.5" />
                                    <span>Informasi Rekening Bank</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="space-y-1.5">
                                        <Label for="bank_name" class="text-xs font-semibold">Nama Bank</Label>
                                        <Input
                                            id="bank_name"
                                            v-model="paymentForm.payment_bank_name"
                                            type="text"
                                            placeholder="Contoh: Bank BCA / Mandiri / BRI"
                                            class="text-xs font-medium"
                                            :disabled="!paymentForm.payment_manual_enabled"
                                        />
                                        <p v-if="paymentForm.errors.payment_bank_name" class="text-[11px] text-destructive font-medium">
                                            {{ paymentForm.errors.payment_bank_name }}
                                        </p>
                                    </div>

                                    <div class="space-y-1.5">
                                        <Label for="account_number" class="text-xs font-semibold">Nomor Rekening</Label>
                                        <Input
                                            id="account_number"
                                            v-model="paymentForm.payment_bank_account_number"
                                            type="text"
                                            placeholder="Contoh: 1234567890"
                                            class="text-xs font-mono font-medium"
                                            :disabled="!paymentForm.payment_manual_enabled"
                                        />
                                        <p v-if="paymentForm.errors.payment_bank_account_number" class="text-[11px] text-destructive font-medium">
                                            {{ paymentForm.errors.payment_bank_account_number }}
                                        </p>
                                    </div>

                                    <div class="space-y-1.5">
                                        <Label for="account_holder" class="text-xs font-semibold">Atas Nama Pemilik</Label>
                                        <Input
                                            id="account_holder"
                                            v-model="paymentForm.payment_bank_account_holder"
                                            type="text"
                                            placeholder="Contoh: PT Sirkelta Digital Indonesia"
                                            class="text-xs font-medium"
                                            :disabled="!paymentForm.payment_manual_enabled"
                                        />
                                        <p v-if="paymentForm.errors.payment_bank_account_holder" class="text-[11px] text-destructive font-medium">
                                            {{ paymentForm.errors.payment_bank_account_holder }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="border-t pt-4 space-y-3">
                                <div class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                    <QrCode class="h-3.5 w-3.5" />
                                    <span>Barcode QRIS Manual</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <Label for="qris_merchant" class="text-xs font-semibold">Nama Merchant QRIS</Label>
                                        <Input
                                            id="qris_merchant"
                                            v-model="paymentForm.payment_qris_merchant_name"
                                            type="text"
                                            placeholder="Contoh: SIRKELTA DIGITAL QRIS"
                                            class="text-xs font-medium"
                                            :disabled="!paymentForm.payment_manual_enabled"
                                        />
                                        <p class="text-[11px] text-muted-foreground">
                                            Nama merchant yang tertera pada standar QRIS Nasional (NMID).
                                        </p>
                                        <p v-if="paymentForm.errors.payment_qris_merchant_name" class="text-[11px] text-destructive font-medium">
                                            {{ paymentForm.errors.payment_qris_merchant_name }}
                                        </p>
                                    </div>

                                    <div class="space-y-1.5">
                                        <Label class="text-xs font-semibold">Unggah Gambar QRIS (PNG / JPG / WEBP)</Label>
                                        <div class="flex items-center gap-4">
                                            <div 
                                                v-if="qrisPreview" 
                                                class="h-20 w-20 shrink-0 border rounded-xl overflow-hidden bg-muted/40 p-1 flex items-center justify-center shadow-xs"
                                            >
                                                <img :src="qrisPreview" alt="QRIS Preview" class="h-full w-full object-contain rounded-lg" />
                                            </div>
                                            <div class="flex-1 space-y-1">
                                                <input
                                                    type="file"
                                                    id="qris_file"
                                                    accept="image/*"
                                                    @change="handleQrisFileChange"
                                                    class="block w-full text-xs text-muted-foreground file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer"
                                                    :disabled="!paymentForm.payment_manual_enabled"
                                                />
                                                <p class="text-[11px] text-muted-foreground">
                                                    Maksimal 2MB. Gambar akan ditampilkan agar pembeli bisa scan dari aplikasi bank / e-wallet.
                                                </p>
                                                <p v-if="paymentForm.errors.qris_image" class="text-[11px] text-destructive font-medium">
                                                    {{ paymentForm.errors.qris_image }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Midtrans Payment Gateway Card -->
                    <Card>
                        <CardHeader class="pb-3 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <Sparkles class="h-4 w-4 text-indigo-500" />
                                    <CardTitle class="text-base font-bold">Midtrans Payment Gateway (Otomatis)</CardTitle>
                                </div>
                                <CardDescription class="text-xs">
                                    Menerima pembayaran instan tanpa perlu cek mutasi manual (GoPay, OVO, ShopeePay, Virtual Account BCA/BRI/Mandiri/BNI, QRIS, & Kartu Kredit).
                                </CardDescription>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input 
                                    type="checkbox" 
                                    v-model="paymentForm.payment_midtrans_enabled" 
                                    class="sr-only peer"
                                >
                                <div class="w-11 h-6 bg-muted peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                <span class="ms-2 text-xs font-bold text-foreground">
                                    {{ paymentForm.payment_midtrans_enabled ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </label>
                        </CardHeader>

                        <CardContent class="space-y-5 pt-4">
                            <!-- Environment Mode Selector -->
                            <div class="space-y-2">
                                <Label class="text-xs font-semibold">Mode Lingkungan Midtrans</Label>
                                <div class="flex items-center gap-3">
                                    <label 
                                        class="flex items-center gap-2 p-3 border rounded-xl cursor-pointer text-xs transition-all w-48"
                                        :class="paymentForm.payment_midtrans_mode === 'sandbox' 
                                            ? 'border-amber-500 bg-amber-500/10 text-amber-700 dark:text-amber-400 font-bold' 
                                            : 'border-border text-muted-foreground hover:bg-muted/50'"
                                    >
                                        <input 
                                            type="radio" 
                                            value="sandbox" 
                                            v-model="paymentForm.payment_midtrans_mode" 
                                            class="text-amber-500 focus:ring-amber-500"
                                            :disabled="!paymentForm.payment_midtrans_enabled"
                                        />
                                        <div>
                                            <div class="font-bold">Sandbox (Uji Coba)</div>
                                            <div class="text-[10px] text-muted-foreground">Simulator & Testing</div>
                                        </div>
                                    </label>

                                    <label 
                                        class="flex items-center gap-2 p-3 border rounded-xl cursor-pointer text-xs transition-all w-48"
                                        :class="paymentForm.payment_midtrans_mode === 'production' 
                                            ? 'border-emerald-500 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-bold' 
                                            : 'border-border text-muted-foreground hover:bg-muted/50'"
                                    >
                                        <input 
                                            type="radio" 
                                            value="production" 
                                            v-model="paymentForm.payment_midtrans_mode" 
                                            class="text-emerald-500 focus:ring-emerald-500"
                                            :disabled="!paymentForm.payment_midtrans_enabled"
                                        />
                                        <div>
                                            <div class="font-bold">Production (Live)</div>
                                            <div class="text-[10px] text-muted-foreground">Transaksi Uang Nyata</div>
                                        </div>
                                    </label>
                                </div>
                                <p v-if="paymentForm.errors.payment_midtrans_mode" class="text-[11px] text-destructive font-medium">
                                    {{ paymentForm.errors.payment_midtrans_mode }}
                                </p>
                            </div>

                            <!-- Panduan Pemetaan Kunci Midtrans Versi Baru -->
                            <div class="p-4 rounded-xl border border-indigo-200 dark:border-indigo-900/60 bg-indigo-50/60 dark:bg-indigo-950/20 text-xs space-y-2">
                                <div class="font-bold text-indigo-900 dark:text-indigo-300 flex items-center gap-1.5">
                                    <Sparkles class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                                    <span>Panduan Kredensial Midtrans (Format Dashboard Terbaru)</span>
                                </div>
                                <div class="text-muted-foreground leading-relaxed space-y-1">
                                    <p>
                                        Midtrans memisahkan antara <strong>Client Key</strong>, <strong>Signature Secret Key</strong>, dan <strong>Server Keys</strong>:
                                    </p>
                                    <ul class="list-disc list-inside space-y-1 ps-1 text-[11px]">
                                        <li><strong>Client Key:</strong> Salin dari baris <em>Client Key</em> di Midtrans.</li>
                                        <li><strong>Server Key:</strong> Pada dashboard Midtrans Anda di bagian <strong>Server Keys</strong>, klik tombol biru <strong>"Generate server key"</strong> untuk membuat Server Key baru. Salin kunci tersebut ke kolom Server Key di bawah. <em>(Catatan: Jangan menyalin baris Signature Secret Key ke kolom Server Key).</em></li>
                                        <li><strong>Signature Secret Key (Opsional):</strong> Salin dari baris paling atas <em>Signature Secret Key</em> di Midtrans untuk verifikasi keamanan notifikasi webhook.</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- API Keys & Credentials: 4 Grid Columns -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="space-y-1.5">
                                    <Label for="merchant_id" class="text-xs font-semibold flex items-center gap-1.5">
                                        <Building2 class="h-3.5 w-3.5 text-muted-foreground" />
                                        <span>Merchant ID</span>
                                    </Label>
                                    <Input
                                        id="merchant_id"
                                        v-model="paymentForm.payment_midtrans_merchant_id"
                                        type="text"
                                        placeholder="G123456789 atau M123456"
                                        class="text-xs font-mono font-medium"
                                        :disabled="!paymentForm.payment_midtrans_enabled"
                                    />
                                    <p class="text-[11px] text-muted-foreground">
                                        ID unik di pojok kiri atas Midtrans.
                                    </p>
                                    <p v-if="paymentForm.errors.payment_midtrans_merchant_id" class="text-[11px] text-destructive font-medium">
                                        {{ paymentForm.errors.payment_midtrans_merchant_id }}
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="client_key" class="text-xs font-semibold flex items-center gap-1.5">
                                        <Key class="h-3.5 w-3.5 text-muted-foreground" />
                                        <span>Client Key</span>
                                    </Label>
                                    <Input
                                        id="client_key"
                                        v-model="paymentForm.payment_midtrans_client_key"
                                        type="text"
                                        placeholder="SB-Mid-client-xxx atau Mid-client-xxx"
                                        class="text-xs font-mono font-medium"
                                        :disabled="!paymentForm.payment_midtrans_enabled"
                                    />
                                    <p class="text-[11px] text-muted-foreground">
                                        Salin dari baris Client Key di Midtrans.
                                    </p>
                                    <p v-if="paymentForm.payment_midtrans_client_key && paymentForm.payment_midtrans_client_key.toLowerCase().includes('-server-')" class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold flex items-center gap-1">
                                        <AlertTriangle class="h-3 w-3 shrink-0" />
                                        <span>Perhatian: Kunci ini berformat Server Key. Seharusnya ditempel di kolom Server Key.</span>
                                    </p>
                                    <p v-if="paymentForm.errors.payment_midtrans_client_key" class="text-[11px] text-destructive font-medium">
                                        {{ paymentForm.errors.payment_midtrans_client_key }}
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="server_key" class="text-xs font-semibold flex items-center gap-1.5">
                                        <Key class="h-3.5 w-3.5 text-amber-500" />
                                        <span>Server Key (Wajib Transaksi)</span>
                                    </Label>
                                    <div class="relative">
                                        <Input
                                            id="server_key"
                                            v-model="paymentForm.payment_midtrans_server_key"
                                            :type="showServerKey ? 'text' : 'password'"
                                            placeholder="SB-Mid-server-xxx atau Mid-server-xxx"
                                            class="text-xs font-mono font-medium pr-10"
                                            :disabled="!paymentForm.payment_midtrans_enabled"
                                        />
                                        <button
                                            type="button"
                                            @click="showServerKey = !showServerKey"
                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                                            title="Tampilkan / sembunyikan key"
                                        >
                                            <component :is="showServerKey ? EyeOff : Eye" class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <p class="text-[11px] text-muted-foreground">
                                        Dihasilkan dari tombol <strong>"Generate server key"</strong> di Midtrans.
                                    </p>
                                    <p v-if="paymentForm.payment_midtrans_server_key && paymentForm.payment_midtrans_server_key.toLowerCase().includes('-client-')" class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold flex items-center gap-1">
                                        <AlertTriangle class="h-3 w-3 shrink-0" />
                                        <span>Perhatian: Kunci ini berformat Client Key. Seharusnya ditempel di kolom Client Key.</span>
                                    </p>
                                    <p v-if="paymentForm.errors.payment_midtrans_server_key" class="text-[11px] text-destructive font-medium">
                                        {{ paymentForm.errors.payment_midtrans_server_key }}
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="signature_key" class="text-xs font-semibold flex items-center gap-1.5">
                                        <ShieldCheck class="h-3.5 w-3.5 text-indigo-500" />
                                        <span>Signature Secret Key (Opsional)</span>
                                    </Label>
                                    <div class="relative">
                                        <Input
                                            id="signature_key"
                                            v-model="paymentForm.payment_midtrans_signature_key"
                                            :type="showSignatureKey ? 'text' : 'password'"
                                            placeholder="Salin dari baris Signature Secret Key"
                                            class="text-xs font-mono font-medium pr-10"
                                            :disabled="!paymentForm.payment_midtrans_enabled"
                                        />
                                        <button
                                            type="button"
                                            @click="showSignatureKey = !showSignatureKey"
                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                                            title="Tampilkan / sembunyikan key"
                                        >
                                            <component :is="showSignatureKey ? EyeOff : Eye" class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <p class="text-[11px] text-muted-foreground">
                                        Salin dari baris paling atas untuk verifikasi webhook.
                                    </p>
                                </div>
                            </div>

                            <!-- Test Connection Button & Result -->
                            <div class="p-3.5 rounded-xl border bg-muted/30 flex flex-col gap-2.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 text-xs gap-1.5 px-3 rounded-lg border-amber-300 dark:border-amber-700/80 hover:bg-amber-50 dark:hover:bg-amber-950/40 text-amber-800 dark:text-amber-300"
                                        :disabled="testingMidtrans || !paymentForm.payment_midtrans_server_key"
                                        @click="testMidtransConnection"
                                    >
                                        <Zap class="h-3.5 w-3.5" :class="{ 'animate-pulse': testingMidtrans }" />
                                        <span>{{ testingMidtrans ? 'Sedang Menguji...' : 'Tes Koneksi Midtrans' }}</span>
                                    </Button>
                                    <span class="text-[11px] text-muted-foreground">
                                        Menguji apakah Server Key dan Mode saat ini diterima oleh API Midtrans Snap.
                                    </span>
                                </div>

                                <div
                                    v-if="midtransTestResult"
                                    class="p-3 rounded-xl border text-xs flex items-start gap-2.5 animate-in fade-in"
                                    :class="midtransTestResult.success ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-800 dark:text-emerald-300' : 'bg-destructive/10 border-destructive/30 text-destructive'"
                                >
                                    <CheckCircle2 v-if="midtransTestResult.success" class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400 mt-0.5" />
                                    <AlertTriangle v-else class="h-4 w-4 shrink-0 text-destructive mt-0.5" />
                                    <span class="flex-1 font-medium leading-relaxed">{{ midtransTestResult.message }}</span>
                                </div>
                            </div>

                            <!-- Midtrans Configuration URLs (Settings > Configuration) -->
                            <div class="border border-border/80 rounded-2xl bg-muted/20 p-4 sm:p-5 space-y-4">
                                <div class="space-y-1">
                                    <div class="text-xs sm:text-sm font-bold text-foreground flex items-center gap-2">
                                        <AlertCircle class="h-4 w-4 text-indigo-500 shrink-0" />
                                        <span>Konfigurasi URL Callback & Notifikasi Midtrans</span>
                                    </div>
                                    <p class="text-xs text-muted-foreground leading-relaxed">
                                        Salin masing-masing URL di bawah ini lalu tempelkan ke akun Midtrans Anda pada menu <strong>Dashboard Midtrans > Settings > Configuration</strong>:
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 gap-3.5 pt-1">
                                    <div 
                                        v-for="item in midtransUrls" 
                                        :key="item.key"
                                        class="p-3.5 bg-card border rounded-xl shadow-2xs space-y-2 hover:border-indigo-500/40 transition-colors"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-xs font-bold text-foreground">{{ item.label }}</span>
                                                <Badge variant="outline" class="text-[10px] py-0 px-1.5 font-semibold text-muted-foreground">
                                                    {{ item.badge }}
                                                </Badge>
                                            </div>
                                            <Button 
                                                type="button" 
                                                variant="outline" 
                                                size="sm" 
                                                class="h-7 text-xs gap-1.5 px-3 rounded-lg border-indigo-200 dark:border-indigo-900/60 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 shrink-0"
                                                @click="copyUrl(item.url, item.key)"
                                            >
                                                <component 
                                                    :is="copiedField === item.key ? Check : Copy" 
                                                    class="h-3.5 w-3.5" 
                                                    :class="copiedField === item.key ? 'text-emerald-600' : 'text-indigo-600 dark:text-indigo-400'" 
                                                />
                                                <span :class="copiedField === item.key ? 'text-emerald-600 font-bold' : ''">
                                                    {{ copiedField === item.key ? 'Tersalin!' : 'Salin URL' }}
                                                </span>
                                            </Button>
                                        </div>
                                        
                                        <p class="text-[11px] text-muted-foreground leading-normal">
                                            {{ item.desc }}
                                        </p>

                                        <div class="flex items-center gap-2 bg-muted/50 border rounded-lg px-3 py-2 font-mono text-xs text-primary select-all break-all overflow-x-auto">
                                            <span class="flex-1">{{ item.url }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Submit Action Button -->
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <Button 
                            type="submit" 
                            class="gap-2 font-bold px-6 shadow-sm"
                            :disabled="paymentForm.processing"
                        >
                            <Save class="h-4 w-4" />
                            <span>{{ paymentForm.processing ? 'Menyimpan...' : 'Simpan Pengaturan Pembayaran' }}</span>
                        </Button>
                    </div>
                </form>
            </div>

            <!-- TAB 2: Kontak & Hubungi Kami -->
            <div v-else-if="currentTab === 'contact'" class="space-y-6">
                <Card>
                    <form @submit.prevent="saveSettings">
                        <CardHeader class="pb-3 border-b">
                            <CardTitle class="text-base font-bold">Informasi Halaman Hubungi Kami & Saluran Resmi</CardTitle>
                            <CardDescription class="text-xs">
                                Data ini tampil di halaman <a :href="route('contact')" target="_blank" class="text-primary underline">/hubungi-kami</a> dan footer website.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4 pt-4 pb-5">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-1.5">
                                    <Label for="whatsapp" class="flex items-center gap-1.5 text-xs font-semibold">
                                        <Phone class="h-3.5 w-3.5 text-emerald-600" />
                                        <span>Nomor WhatsApp Resmi (Format: 628xxx)</span>
                                    </Label>
                                    <Input
                                        id="whatsapp"
                                        v-model="settingsForm.support_whatsapp"
                                        type="text"
                                        placeholder="6281234567890"
                                        required
                                        class="text-xs font-medium tabular-nums"
                                        :class="{ 'border-destructive': settingsForm.errors.support_whatsapp }"
                                    />
                                    <p v-if="settingsForm.errors.support_whatsapp" class="text-[11px] text-destructive font-medium">
                                        {{ settingsForm.errors.support_whatsapp }}
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="email" class="flex items-center gap-1.5 text-xs font-semibold">
                                        <Mail class="h-3.5 w-3.5 text-primary" />
                                        <span>Alamat Email Dukungan Resmi</span>
                                    </Label>
                                    <Input
                                        id="email"
                                        v-model="settingsForm.support_email"
                                        type="email"
                                        placeholder="support@sirkelta.test"
                                        required
                                        class="text-xs"
                                        :class="{ 'border-destructive': settingsForm.errors.support_email }"
                                    />
                                    <p v-if="settingsForm.errors.support_email" class="text-[11px] text-destructive font-medium">
                                        {{ settingsForm.errors.support_email }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-1.5">
                                    <Label for="contact_hours" class="flex items-center gap-1.5 text-xs font-semibold">
                                        <Clock class="h-3.5 w-3.5 text-amber-500" />
                                        <span>Jam Kerja Operasional</span>
                                    </Label>
                                    <Input
                                        id="contact_hours"
                                        v-model="settingsForm.contact_hours"
                                        type="text"
                                        placeholder="Senin - Sabtu: 08.00 - 18.00 WIB"
                                        class="text-xs"
                                    />
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="contact_address" class="flex items-center gap-1.5 text-xs font-semibold">
                                        <MapPin class="h-3.5 w-3.5 text-rose-500" />
                                        <span>Lokasi / Alamat Redaksi</span>
                                    </Label>
                                    <Input
                                        id="contact_address"
                                        v-model="settingsForm.contact_address"
                                        type="text"
                                        placeholder="Indonesia"
                                        class="text-xs"
                                    />
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="contact_description" class="text-xs font-semibold">
                                    Kalimat Pengantar Halaman Hubungi Kami
                                </Label>
                                <textarea
                                    id="contact_description"
                                    v-model="settingsForm.contact_description"
                                    rows="2"
                                    class="w-full bg-muted/40 border rounded-xl p-3 text-xs focus:outline-hidden focus:ring-1 focus:ring-primary"
                                    placeholder="Punya pertanyaan, kendala, atau penawaran kerja sama? Jangan ragu untuk menghubungi kami."
                                ></textarea>
                            </div>

                            <div class="space-y-1.5 pt-2 border-t">
                                <Label for="referral_amount" class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Gift class="h-3.5 w-3.5 text-amber-500" />
                                    <span>Nominal Saldo Potongan Referral per Teman (Rupiah)</span>
                                </Label>
                                <Input
                                    id="referral_amount"
                                    v-model="settingsForm.referral_discount_amount"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    required
                                    class="text-xs max-w-xs"
                                />
                                <p class="text-[11px] text-muted-foreground">
                                    Nilai diskon yang diperoleh pengguna ketika berhasil mengundang teman untuk memotong biaya perpanjangan paket Pro.
                                </p>
                            </div>
                        </CardContent>
                        <CardFooter class="border-t pt-4 pb-4 flex justify-end">
                            <Button type="submit" size="sm" class="gap-1.5 text-xs font-bold" :disabled="settingsForm.processing">
                                <Save class="h-3.5 w-3.5" />
                                <span>Simpan Pengaturan Kontak</span>
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>

            <!-- TAB 3, 4, 5, 6: Dynamic Content Editors for Legal Pages -->
            <div v-else-if="legalPages[currentTab]" class="space-y-4">
                <Card>
                    <form @submit.prevent="savePageContent(currentTab)">
                        <CardHeader class="border-b pb-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div>
                                    <CardTitle class="text-base font-bold">
                                        Edit Konten Halaman {{ legalPages[currentTab].title }}
                                    </CardTitle>
                                    <CardDescription class="text-xs">
                                        {{ legalPages[currentTab].description }}
                                    </CardDescription>
                                </div>
                                <div v-if="legalPages[currentTab].updated_at" class="text-[11px] text-muted-foreground flex items-center gap-1">
                                    <Calendar class="h-3.5 w-3.5" />
                                    <span>Pembaruan: {{ legalPages[currentTab].updated_at }}</span>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4 pt-4 pb-5">
                            <div class="flex items-center justify-between">
                                <Label class="text-xs font-bold text-foreground">
                                    Editor Konten Visual & Teks Kaya (WYSIWYG)
                                </Label>
                                <span class="text-[11px] text-muted-foreground font-medium">
                                    Tampilan di editor ini persis dengan tampilan di halaman publik
                                </span>
                            </div>

                            <!-- Rich Text Editor with Full Plugins -->
                            <RichTextEditor
                                v-model="pageForms[currentTab].content"
                                :placeholder="`Tuliskan isi lengkap konten ${legalPages[currentTab].title} di sini...`"
                                :disabled="pageForms[currentTab].processing"
                                minHeight="420px"
                            />

                            <p v-if="pageForms[currentTab].errors.content" class="text-[11px] text-destructive font-medium">
                                {{ pageForms[currentTab].errors.content }}
                            </p>
                        </CardContent>
                        <CardFooter class="border-t pt-4 pb-4 flex items-center justify-between">
                            <a :href="getPublicUrl(currentTab)" target="_blank" class="text-xs text-primary font-semibold hover:underline inline-flex items-center gap-1">
                                <span>Buka halaman publik langsung</span>
                                <ExternalLink class="h-3 w-3" />
                            </a>
                            <Button type="submit" size="sm" class="gap-1.5 text-xs font-bold" :disabled="pageForms[currentTab].processing">
                                <Save class="h-3.5 w-3.5" />
                                <span>Simpan Konten {{ legalPages[currentTab].title }}</span>
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>

            <!-- FAQ Add/Edit Modal -->
            <div 
                v-if="isFaqModalOpen" 
                class="fixed inset-0 z-50 bg-background/80 backdrop-blur-xs flex items-center justify-center p-4"
            >
                <div class="bg-card border shadow-xl rounded-2xl max-w-lg w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                    <div class="flex items-center justify-between border-b pb-3">
                        <h3 class="font-bold text-base">
                            {{ editingFaq ? 'Edit Pertanyaan FAQ' : 'Tambah Pertanyaan FAQ Baru' }}
                        </h3>
                        <Button variant="ghost" size="icon" class="h-7 w-7" @click="closeFaqModal">
                            <X class="h-4 w-4" />
                        </Button>
                    </div>

                    <form @submit.prevent="saveFaq" class="space-y-3.5">
                        <div class="space-y-1.5">
                            <Label for="faq_category">Kategori</Label>
                            <Input
                                id="faq_category"
                                v-model="faqForm.category"
                                type="text"
                                placeholder="Contoh: Akun & Akses, Program Referral, dll."
                                required
                                class="text-xs"
                                :class="{ 'border-destructive': faqForm.errors.category }"
                            />
                            <p v-if="faqForm.errors.category" class="text-[11px] text-destructive font-medium">
                                {{ faqForm.errors.category }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="faq_question">Pertanyaan</Label>
                            <Input
                                id="faq_question"
                                v-model="faqForm.question"
                                type="text"
                                placeholder="Tuliskan pertanyaan yang sering diajukan..."
                                required
                                class="text-xs"
                                :class="{ 'border-destructive': faqForm.errors.question }"
                            />
                            <p v-if="faqForm.errors.question" class="text-[11px] text-destructive font-medium">
                                {{ faqForm.errors.question }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="faq_answer">Jawaban</Label>
                            <textarea
                                id="faq_answer"
                                v-model="faqForm.answer"
                                rows="4"
                                placeholder="Tuliskan jawaban yang jelas dan informatif..."
                                required
                                class="w-full bg-muted/40 border rounded-xl p-3 text-xs focus:outline-hidden focus:ring-1 focus:ring-primary"
                                :class="{ 'border-destructive': faqForm.errors.answer }"
                            ></textarea>
                            <p v-if="faqForm.errors.answer" class="text-[11px] text-destructive font-medium">
                                {{ faqForm.errors.answer }}
                            </p>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t">
                            <Button type="button" variant="outline" size="sm" class="text-xs" @click="closeFaqModal">Batal</Button>
                            <Button type="submit" size="sm" class="text-xs font-bold" :disabled="faqForm.processing">
                                {{ faqForm.processing ? 'Menyimpan...' : 'Simpan FAQ' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
