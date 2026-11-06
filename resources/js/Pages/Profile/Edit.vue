<script setup>
import { ref, computed, watch, onMounted, nextTick } from "vue";
import { useForm, usePage, router } from "@inertiajs/vue3";
import DashboardLayout from "@/Layouts/DashboardLayout.vue";
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
    CardFooter,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    User,
    Lock,
    AtSign,
    Save,
    KeyRound,
    Loader2,
    Eye,
    EyeOff,
    Sparkles,
    Gift,
    Calendar,
    Clock,
    Check,
    Copy,
    Link2,
    Users,
    Tag,
    Wallet,
    Send,
    CheckCircle2,
    XCircle,
    Star,
    Flame,
    Plus,
    Layers,
    CreditCard,
    QrCode,
    Building2,
    ExternalLink,
    FileImage,
    Upload,
    X,
    AlertCircle,
    Phone,
    AlertTriangle,
    Trash2,
    PowerOff,
    Mail,
    ShieldAlert,
    ShieldCheck,
    Pencil,
    ChevronRight,
    Wrench,
} from "lucide-vue-next";

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    referral: {
        type: Object,
        default: () => ({
            code: "",
            url: "",
            total_referees: 0,
            available_discount: 0,
            total_earned_discount: 0,
            logs: [],
        }),
    },
    proApps: {
        type: Array,
        default: () => [],
    },
    packagePricing: {
        type: Object,
        default: () => ({}),
    },
    comparisonPlans: {
        type: Object,
        default: () => ({}),
    },
    paymentOrders: {
        type: Array,
        default: () => [],
    },
    paymentMethods: {
        type: Object,
        default: () => ({}),
    },
    status: {
        type: String,
        default: null,
    },
});

const page = usePage();

const activeSection = ref("account");
const activeTab = activeSection;

const scrollToSection = (sectionId) => {
    activeSection.value = sectionId;
    const el = document.getElementById(`section-${sectionId}`);
    if (el) {
        el.scrollIntoView({ behavior: "smooth", block: "start" });
        window.history.replaceState(null, "", `#${sectionId}`);
    }
};

onMounted(() => {
    const rawHash = window.location.hash.replace("#", "");
    if (["account", "pro", "referral", "tagihan", "paket"].includes(rawHash)) {
        const target = rawHash === "tagihan" || rawHash === "paket" ? "pro" : rawHash;
        activeSection.value = target;
        nextTick(() => {
            scrollToSection(target);
        });
    }

    if (typeof window !== "undefined" && "IntersectionObserver" in window) {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const id = entry.target.id.replace("section-", "");
                        if (["account", "pro", "referral"].includes(id)) {
                            activeSection.value = id;
                        }
                    }
                });
            },
            { threshold: 0.2, rootMargin: "-100px 0px -40% 0px" }
        );

        nextTick(() => {
            ["account", "pro", "referral"].forEach((id) => {
                const el = document.getElementById(`section-${id}`);
                if (el) observer.observe(el);
            });
        });
    }
});

// --- PROFILE SECTION ---
const isEditingUsername = ref(false);
const isCheckingUsername = ref(false);
const usernameStatus = ref("current"); // 'current' | 'available' | 'unavailable' | 'invalid' | null
const usernameMessage = ref("Username Anda saat ini.");
let usernameTimeout = null;

const enableEditUsername = () => {
    isEditingUsername.value = true;
    nextTick(() => {
        document.getElementById("profile-username")?.focus();
    });
};

const cancelEditUsername = () => {
    profileForm.username = props.user.username || "";
    profileForm.clearErrors("username");
    usernameStatus.value = "current";
    usernameMessage.value = "Username Anda saat ini.";
    isEditingUsername.value = false;
};

const isEditingEmail = ref(false);
const isSendingOtp = ref(false);
const otpSent = ref(false);
const otpMessage = ref("");
const resendCountdown = ref(0);

const enableEditEmail = () => {
    isEditingEmail.value = true;
    nextTick(() => {
        document.getElementById("profile-email")?.focus();
    });
};

const cancelEditEmail = () => {
    profileForm.email = props.user.email || "";
    profileForm.email_otp = "";
    otpSent.value = false;
    otpMessage.value = "";
    profileForm.clearErrors("email");
    profileForm.clearErrors("email_otp");
    isEditingEmail.value = false;
};

const profileForm = useForm({
    name: props.user.name || "",
    username: props.user.username || "",
    email: props.user.email || "",
    email_otp: "",
});

const isEmailChanged = computed(() => {
    return (
        (profileForm.email || "").trim().toLowerCase() !==
        (props.user.email || "").trim().toLowerCase()
    );
});

const onUsernameInput = () => {
    // Strip spaces immediately and enforce lowercase
    let cleaned = (profileForm.username || "")
        .replace(/\s+/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9_-]/g, "");
    profileForm.username = cleaned;
    profileForm.clearErrors("username");
    clearTimeout(usernameTimeout);

    if (!cleaned) {
        usernameStatus.value = "invalid";
        usernameMessage.value = "Username wajib diisi.";
        isCheckingUsername.value = false;
        return;
    }

    if (cleaned === (props.user.username || "").toLowerCase()) {
        usernameStatus.value = "current";
        usernameMessage.value = "Username Anda saat ini.";
        isCheckingUsername.value = false;
        return;
    }

    if (cleaned.length < 3) {
        usernameStatus.value = "invalid";
        usernameMessage.value = "Minimal 3 karakter tanpa spasi.";
        isCheckingUsername.value = false;
        return;
    }

    isCheckingUsername.value = true;
    usernameTimeout = setTimeout(async () => {
        try {
            const res = await fetch(
                `/check-username?username=${encodeURIComponent(cleaned)}`,
                {
                    headers: {
                        Accept: "application/json",
                        "X-Silent": "true",
                    },
                },
            );
            const data = await res.json();
            if (profileForm.username === cleaned) {
                usernameStatus.value = data.available
                    ? "available"
                    : "unavailable";
                usernameMessage.value = data.message;
            }
        } catch {
            // silent network fallback
        } finally {
            if (profileForm.username === cleaned) {
                isCheckingUsername.value = false;
            }
        }
    }, 350);
};

const sendEmailChangeOtp = async () => {
    if (!profileForm.email || !profileForm.email.includes("@")) {
        profileForm.setError(
            "email",
            "Masukkan alamat email baru yang valid terlebih dahulu.",
        );
        return;
    }
    profileForm.clearErrors("email_otp");
    isSendingOtp.value = true;
    otpMessage.value = "";

    try {
        const csrfToken =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content") || "";
        const res = await fetch(route("profile.email.send-otp"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken,
                "X-Silent": "true",
            },
            body: JSON.stringify({
                new_email: profileForm.email,
            }),
        });
        const data = await res.json();
        if (res.ok && data.success) {
            otpSent.value = true;
            otpMessage.value = data.message;
            resendCountdown.value = 60;
            const timer = setInterval(() => {
                resendCountdown.value--;
                if (resendCountdown.value <= 0) clearInterval(timer);
            }, 1000);
        } else {
            profileForm.setError(
                "email_otp",
                data.message || "Gagal mengirim kode verifikasi.",
            );
        }
    } catch {
        profileForm.setError(
            "email_otp",
            "Terjadi kesalahan jaringan saat mengirim kode.",
        );
    } finally {
        isSendingOtp.value = false;
    }
};

const updateProfile = () => {
    profileForm.clearErrors();

    if (!profileForm.name.trim()) {
        profileForm.setError("name", "Nama lengkap wajib diisi.");
        return;
    }

    if (isEditingUsername.value) {
        const cleanUser = (profileForm.username || "").trim();
        if (!cleanUser) {
            profileForm.setError("username", "Username wajib diisi.");
            return;
        }
        if (/\s/.test(cleanUser)) {
            profileForm.setError(
                "username",
                "Username tidak boleh mengandung spasi.",
            );
            return;
        }
        if (cleanUser.length < 3) {
            profileForm.setError("username", "Username minimal 3 karakter.");
            return;
        }
        if (usernameStatus.value === "unavailable") {
            profileForm.setError(
                "username",
                "Username sudah terpakai, silakan gunakan username lain.",
            );
            return;
        }
        if (usernameStatus.value === "invalid") {
            profileForm.setError(
                "username",
                usernameMessage.value || "Username tidak valid.",
            );
            return;
        }
    }

    if (isEditingEmail.value) {
        const cleanEmail = (profileForm.email || "").trim();
        if (!cleanEmail) {
            profileForm.setError("email", "Alamat email wajib diisi.");
            return;
        }
        if (!cleanEmail.includes("@") || !cleanEmail.includes(".")) {
            profileForm.setError("email", "Format alamat email tidak valid.");
            return;
        }
        if (isEmailChanged.value) {
            if (!profileForm.email_otp.trim()) {
                profileForm.setError(
                    "email_otp",
                    "Kode verifikasi 6 digit wajib diisi untuk mengonfirmasi perubahan email.",
                );
                return;
            }
        }
    }

    profileForm.put(route("profile.update"), {
        preserveScroll: true,
        onSuccess: () => {
            otpSent.value = false;
            otpMessage.value = "";
            usernameStatus.value = "current";
            usernameMessage.value = "Username Anda saat ini.";
            isEditingUsername.value = false;
            isEditingEmail.value = false;
        },
    });
};

// --- PASSWORD SECTION ---
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const passwordForm = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});

const updatePassword = () => {
    passwordForm.clearErrors();

    if (!passwordForm.current_password) {
        passwordForm.setError(
            "current_password",
            "Kata sandi saat ini wajib diisi.",
        );
        return;
    }
    if (!passwordForm.password || passwordForm.password.length < 8) {
        passwordForm.setError(
            "password",
            "Kata sandi baru minimal 8 karakter.",
        );
        return;
    }
    if (passwordForm.password !== passwordForm.password_confirmation) {
        passwordForm.setError(
            "password_confirmation",
            "Konfirmasi kata sandi baru tidak cocok.",
        );
        return;
    }

    passwordForm.put(route("profile.password"), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            showCurrentPassword.value = false;
            showNewPassword.value = false;
            showConfirmPassword.value = false;
        },
    });
};

// --- PRO UPGRADE & SUBSCRIPTION SECTION ---
const activeComparisonTab = ref(props.proApps?.[0]?.module || "keuangan");

const currentSelectedApp = computed(() => {
    return (
        props.proApps?.find((a) => a.module === activeComparisonTab.value) ||
        props.proApps?.[0] ||
        null
    );
});

const currentAppPricingTiers = computed(() => {
    if (!currentSelectedApp.value) return [];
    const moduleKey = currentSelectedApp.value.module;
    const pricingObj = props.packagePricing?.[moduleKey] || {};

    const standardDurations = [
        {
            months: 1,
            label: "1 Bulan",
            sublabel: "Fleksibel Bulanan",
            badge: "Standar",
            tagColor: "bg-muted text-muted-foreground border-border",
            isPopular: false,
            isBestValue: false,
        },
        {
            months: 3,
            label: "3 Bulan",
            sublabel: "Paket Kuartal",
            badge: "Hemat 10%",
            tagColor: "bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-500/20",
            isPopular: false,
            isBestValue: false,
        },
        {
            months: 6,
            label: "6 Bulan",
            sublabel: "Paling Populer",
            badge: "Paling Populer 🔥",
            tagColor: "bg-primary/10 text-primary border-primary/20",
            isPopular: true,
            isBestValue: false,
        },
        {
            months: 12,
            label: "12 Bulan (1 Thn)",
            sublabel: "Investasi Maksimal",
            badge: "Diskon Terbesar ⭐",
            tagColor: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20",
            isPopular: false,
            isBestValue: true,
        },
    ];

    return standardDurations.map((d) => {
        const tier = pricingObj[d.months] || getAppPricingTier(moduleKey, d.months);
        const normalPrice = tier.normal || (tier.price * (d.months === 1 ? 1 : 1.2));
        const finalPrice = tier.price;
        const discountPct = tier.discount_pct || 0;
        const monthlyRate = Math.round(finalPrice / d.months);
        const savings = Math.max(0, normalPrice - finalPrice);

        return {
            ...d,
            price: finalPrice,
            normal: normalPrice,
            discount_pct: discountPct,
            monthlyRate,
            savings,
        };
    });
});

const upgradeForm = useForm({
    module: "",
    duration_months: 6,
});

const activateForm = useForm({
    module: "",
});

const handleActivate = (moduleKey) => {
    activateForm.module = moduleKey;
    activateForm.post(route("profile.modules.activate"), {
        preserveScroll: true,
    });
};

const handleUpgrade = (moduleKey, months = 6) => {
    upgradeForm.module = moduleKey;
    upgradeForm.duration_months = months;
    upgradeForm.post(route("profile.pro.upgrade"), {
        preserveScroll: true,
        onSuccess: () => {
            closeCheckoutModal();
        },
    });
};

const getAppPricingTier = (moduleKey, months) => {
    const defaultBase = moduleKey === "wasender" ? 99000 : 75000;
    const basePrice =
        props.packagePricing?.[moduleKey]?.[1]?.price || defaultBase;

    return (
        props.packagePricing?.[moduleKey]?.[months] || {
            months,
            price: basePrice * months,
            normal: basePrice * months,
            discount_pct: 0,
            popular: false,
        }
    );
};

const calculateFinalPrice = (moduleKey, months) => {
    const tier = getAppPricingTier(moduleKey, months);
    const availableDiscount = props.referral?.available_discount || 0;
    const discountApplied = Math.min(availableDiscount, tier.price);
    const finalAmount = Math.max(0, tier.price - discountApplied);

    return {
        ...tier,
        discountApplied,
        finalAmount,
    };
};

const scrollToComparison = (moduleKey) => {
    activeComparisonTab.value = moduleKey;
    nextTick(() => {
        const el = document.getElementById("feature-comparison-section");
        if (el) {
            el.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    });
};

// --- CHECKOUT & PAYMENT MODAL SECTION ---
const isCheckoutModalOpen = ref(false);
const checkoutApp = ref(null);
const checkoutDuration = ref(6);
const proofPreview = ref(null);
const copiedBankNumber = ref(false);

const checkoutCalculation = computed(() => {
    if (!checkoutApp.value)
        return {
            normal: 0,
            price: 0,
            discount_pct: 0,
            discountApplied: 0,
            finalAmount: 0,
        };
    return calculateFinalPrice(
        checkoutApp.value.module,
        checkoutDuration.value,
    );
});

const checkoutForm = useForm({
    module: "",
    duration_months: 6,
    payment_method: "manual_bank",
    payment_proof: null,
});

const handleProofChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        checkoutForm.payment_proof = file;
        proofPreview.value = URL.createObjectURL(file);
    }
};

const copyBankNumber = (number) => {
    if (!number) return;
    navigator.clipboard.writeText(number);
    copiedBankNumber.value = true;
    setTimeout(() => {
        copiedBankNumber.value = false;
    }, 2000);
};

const isAllPaymentsDisabled = computed(() => {
    return !props.paymentMethods?.midtrans_enabled && !props.paymentMethods?.manual_enabled;
});

const openCheckoutModal = (app, months) => {
    checkoutApp.value = app;
    checkoutDuration.value = months;
    checkoutForm.module = app.module;
    checkoutForm.duration_months = months;
    checkoutForm.payment_proof = null;
    proofPreview.value = null;

    // Pick default payment method
    if (props.paymentMethods?.midtrans_enabled) {
        checkoutForm.payment_method = "midtrans";
    } else if (props.paymentMethods?.manual_enabled) {
        if (props.paymentMethods?.bank_name) {
            checkoutForm.payment_method = "manual_bank";
        } else {
            checkoutForm.payment_method = "manual_qris";
        }
    } else {
        checkoutForm.payment_method = "";
    }

    isCheckoutModalOpen.value = true;
};

const setCheckoutDuration = (months) => {
    checkoutDuration.value = months;
    checkoutForm.duration_months = months;
};

const closeCheckoutModal = () => {
    isCheckoutModalOpen.value = false;
    checkoutApp.value = null;
    checkoutForm.reset();
    proofPreview.value = null;
};

const submitCheckout = () => {
    checkoutForm.clearErrors();

    const finalAmt = checkoutApp.value 
        ? calculateFinalAmount(checkoutApp.value.module, checkoutDuration.value) 
        : 1;

    // Cegah transaksi berbayar jika semua metode pembayaran dinonaktifkan
    if (finalAmt > 0 && isAllPaymentsDisabled.value) {
        showToast("Layanan transaksi pembayaran sedang ditutup sementara karena sedang ada perbaikan.", "error");
        return;
    }

    checkoutForm.post(route("profile.payment.order"), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: (response) => {
            const token = response?.props?.flash?.midtrans_snap_token || page.props.flash?.midtrans_snap_token;
            if (token) {
                triggerSnapPayment(token);
            } else if (checkoutForm.payment_method !== "midtrans") {
                closeCheckoutModal();
            }
        },
    });
};

const getWhatsAppConfirmationUrl = () => {
    const phone = props.paymentMethods?.support_whatsapp || "6281234567890";
    const appName = checkoutApp.value?.name || "Sirkelta Pro";
    const text = encodeURIComponent(
        `Halo Admin Sirkelta, saya mau konfirmasi pembayaran ${appName} paket ${checkoutDuration.value} bulan.`,
    );
    return `https://wa.me/${phone}?text=${text}`;
};

const loadMidtransScript = () => {
    if (typeof window === "undefined") return;
    if (document.getElementById("midtrans-snap-script")) return;
    if (
        !props.paymentMethods?.midtrans_enabled ||
        !props.paymentMethods?.midtrans_client_key
    )
        return;

    const isProduction = props.paymentMethods.midtrans_mode === "production";
    const script = document.createElement("script");
    script.id = "midtrans-snap-script";
    script.src = isProduction
        ? "https://app.midtrans.com/snap/snap.js"
        : "https://app.sandbox.midtrans.com/snap/snap.js";
    script.setAttribute(
        "data-client-key",
        props.paymentMethods.midtrans_client_key,
    );
    document.head.appendChild(script);
};

const triggerSnapPayment = (snapToken) => {
    if (!snapToken || typeof window === "undefined") return;

    // Tutup modal checkout agar tidak menutupi tampilan popup Midtrans Snap
    closeCheckoutModal();

    const executePay = () => {
        if (window.snap && typeof window.snap.pay === "function") {
            window.snap.pay(snapToken, {
                onSuccess: () => {
                    showToast("Pembayaran berhasil! Paket Pro Anda sedang diaktifkan.", "success");
                    router.reload({ preserveScroll: true });
                },
                onPending: () => {
                    showToast("Pembayaran menunggu verifikasi. Silakan selesaikan pembayaran tagihan Anda.", "info");
                    router.reload({ preserveScroll: true });
                },
                onError: () => {
                    showToast("Pembayaran Midtrans gagal atau dibatalkan.", "error");
                },
                onClose: () => {
                    // Popup ditutup pengguna
                },
            });
        }
    };

    if (window.snap && typeof window.snap.pay === "function") {
        executePay();
    } else {
        loadMidtransScript();
        const script = document.getElementById("midtrans-snap-script");
        if (script) {
            script.addEventListener("load", executePay, { once: true });
        }
        let attempts = 0;
        const interval = setInterval(() => {
            attempts++;
            if (window.snap && typeof window.snap.pay === "function") {
                clearInterval(interval);
                executePay();
            } else if (attempts > 30) {
                clearInterval(interval);
                showToast("Jendela pembayaran Midtrans belum dapat dimuat. Pastikan koneksi internet stabil atau muat ulang halaman.", "error");
            }
        }, 200);
    }
};

onMounted(() => {
    loadMidtransScript();
});

watch(
    () => page.props.flash?.midtrans_snap_token,
    (snapToken) => {
        if (snapToken) {
            triggerSnapPayment(snapToken);
        }
    },
    { immediate: true },
);

// --- REFERRAL SECTION ---
const copiedLink = ref(false);

const copyToClipboard = async (text) => {
    const stringToCopy = String(text || "").trim();
    if (!stringToCopy) return;

    let success = false;
    if (typeof navigator !== "undefined" && navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(stringToCopy);
            success = true;
        } catch {
            success = false;
        }
    }

    if (!success && typeof document !== "undefined") {
        try {
            const textArea = document.createElement("textarea");
            textArea.value = stringToCopy;
            textArea.setAttribute("readonly", "");
            textArea.style.position = "fixed";
            textArea.style.opacity = "0";
            document.body.appendChild(textArea);
            textArea.select();
            success = document.execCommand("copy");
            document.body.removeChild(textArea);
        } catch {
            success = false;
        }
    }

    if (success) {
        copiedLink.value = true;
        setTimeout(() => {
            copiedLink.value = false;
        }, 2000);
    }
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(val || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return "-";
    return new Date(dateStr).toLocaleDateString("id-ID", {
        day: "numeric",
        month: "short",
        year: "numeric",
    });
};

// --- DEACTIVATE APPLICATION SECTION (2-STEP VALIDATION) ---
const isDeactivateModalOpen = ref(false);
const deactivateStep = ref(1);
const selectedAppToDeactivate = ref(null);
const deactivateForm = useForm({
    module: "",
    confirmation_text: "",
});

const openDeactivateModal = (app) => {
    selectedAppToDeactivate.value = app;
    deactivateStep.value = 1;
    deactivateForm.reset();
    deactivateForm.clearErrors();
    deactivateForm.module = app.module;
    isDeactivateModalOpen.value = true;
};

const proceedToDeactivateStep2 = () => {
    deactivateStep.value = 2;
};

const closeDeactivateModal = () => {
    isDeactivateModalOpen.value = false;
    selectedAppToDeactivate.value = null;
    deactivateStep.value = 1;
    deactivateForm.reset();
};

const submitDeactivateModule = () => {
    if (!selectedAppToDeactivate.value) return;
    deactivateForm.post(route("profile.modules.deactivate"), {
        preserveScroll: true,
        onSuccess: () => {
            closeDeactivateModal();
        },
    });
};

// --- DELETE ACCOUNT SECTION (2-STEP VALIDATION) ---
const isDeleteAccountModalOpen = ref(false);
const deleteAccountStep = ref(1);
const showDeleteAccountPassword = ref(false);
const deleteAccountForm = useForm({
    current_password: "",
    confirmation_text: "",
});

const openDeleteAccountModal = () => {
    deleteAccountStep.value = 1;
    showDeleteAccountPassword.value = false;
    deleteAccountForm.reset();
    deleteAccountForm.clearErrors();
    isDeleteAccountModalOpen.value = true;
};

const proceedToDeleteAccountStep2 = () => {
    deleteAccountStep.value = 2;
};

const closeDeleteAccountModal = () => {
    isDeleteAccountModalOpen.value = false;
    deleteAccountStep.value = 1;
    deleteAccountForm.reset();
};

const submitDeleteAccount = () => {
    deleteAccountForm.delete(route("profile.destroy"), {
        onSuccess: () => {
            closeDeleteAccountModal();
        },
    });
};
</script>

<template>
    <DashboardLayout
        :user="user"
        :active-module="user.accessible_modules?.[0] || 'keuangan'"
        title="Profil & Keamanan"
    >
        <div class="space-y-6 max-w-5xl mx-auto pb-12">
            <!-- Header Bar & Quick Navigation -->
            <div class="space-y-4 pb-2 border-b">
                <div
                    class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
                >
                    <div>
                        <h1
                            class="text-xl sm:text-2xl font-extrabold tracking-tight text-foreground"
                        >
                            Pengaturan & Layanan Akun
                        </h1>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Kelola profil, keamanan, paket langganan Pro, serta program referral Anda dalam satu halaman.
                        </p>
                    </div>
                </div>

                <!-- Sticky Quick Section Jump Navigation -->
                <div
                    class="sticky top-16 z-20 -mx-1 px-1 py-1.5 bg-background/90 backdrop-blur-md"
                >
                    <div class="flex items-center gap-1.5 p-1 bg-muted/60 dark:bg-muted/30 rounded-2xl border border-border/60 max-w-fit overflow-x-auto">
                        <button
                            type="button"
                            @click="scrollToSection('account')"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
                            :class="
                                activeSection === 'account'
                                    ? 'bg-card text-foreground shadow-xs border border-border/80 ring-1 ring-primary/20'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            <User class="w-3.5 h-3.5 text-primary" />
                            <span>Akun & Keamanan</span>
                        </button>
                        <button
                            type="button"
                            @click="scrollToSection('pro')"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
                            :class="
                                activeSection === 'pro'
                                    ? 'bg-card text-foreground shadow-xs border border-border/80 ring-1 ring-primary/20'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            <Sparkles class="w-3.5 h-3.5 text-amber-500" />
                            <span>Paket Pro & Tagihan</span>
                        </button>
                        <button
                            type="button"
                            @click="scrollToSection('referral')"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
                            :class="
                                activeSection === 'referral'
                                    ? 'bg-card text-foreground shadow-xs border border-border/80 ring-1 ring-primary/20'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            <Gift class="w-3.5 h-3.5 text-primary" />
                            <span>Program Referral</span>
                            <Badge
                                v-if="referral.available_discount > 0"
                                class="text-[9px] py-0 px-1 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/20"
                            >
                                Ada Saldo
                            </Badge>
                        </button>
                    </div>
                </div>
            </div>

            <!-- BAGIAN 1: AKUN & KEAMANAN -->
            <div id="section-account" class="space-y-8 scroll-mt-28"
            >
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                    <!-- Profile Information Card -->
                    <Card class="shadow-2xs">
                        <CardHeader class="pb-3 border-b">
                            <CardTitle class="text-sm font-bold"
                                >Informasi Akun</CardTitle
                            >
                            <CardDescription class="text-xs"
                                >Perbarui nama lengkap, username unik, dan email
                                Anda.</CardDescription
                            >
                        </CardHeader>
                        <form @submit.prevent="updateProfile">
                            <CardContent class="pt-4 pb-5 space-y-4">
                                <div class="space-y-1.5">
                                    <Label for="profile-name"
                                        >Nama Lengkap</Label
                                    >
                                    <Input
                                        id="profile-name"
                                        v-model="profileForm.name"
                                        type="text"
                                        placeholder="Nama Lengkap Anda"
                                        required
                                        :class="{
                                            'border-destructive':
                                                profileForm.errors.name,
                                        }"
                                    />
                                    <p
                                        v-if="profileForm.errors.name"
                                        class="text-[11px] text-destructive font-medium"
                                    >
                                        {{ profileForm.errors.name }}
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <Label
                                            for="profile-username"
                                            class="flex items-center gap-1.5"
                                        >
                                            <AtSign
                                                class="w-3.5 h-3.5 text-muted-foreground"
                                            />
                                            <span>Username</span>
                                        </Label>
                                        <span
                                            v-if="!isEditingUsername"
                                            class="inline-flex items-center gap-1 text-[10px] text-muted-foreground font-semibold px-2 py-0.5 rounded bg-muted/80"
                                        >
                                            <Lock class="w-2.5 h-2.5" />
                                            Terkunci
                                        </span>
                                        <span
                                            v-else-if="isCheckingUsername"
                                            class="text-[10px] text-muted-foreground flex items-center gap-1 font-medium"
                                        >
                                            <Loader2
                                                class="w-2.5 h-2.5 animate-spin"
                                            />
                                            Memeriksa...
                                        </span>
                                        <span
                                            v-else-if="
                                                usernameStatus === 'available'
                                            "
                                            class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-0.5"
                                        >
                                            <Check class="w-3 h-3" /> Dapat
                                            Digunakan
                                        </span>
                                        <span
                                            v-else-if="
                                                usernameStatus === 'current'
                                            "
                                            class="text-[10px] text-muted-foreground font-medium flex items-center gap-0.5"
                                        >
                                            <Check
                                                class="w-3 h-3 text-emerald-500"
                                            />
                                            Saat Ini
                                        </span>
                                        <span
                                            v-else-if="
                                                usernameStatus === 'unavailable'
                                            "
                                            class="text-[10px] text-destructive font-bold flex items-center gap-0.5"
                                        >
                                            <XCircle class="w-3 h-3" /> Sudah
                                            Digunakan
                                        </span>
                                    </div>
                                    <div class="flex gap-2 items-center">
                                        <div class="relative flex-1">
                                            <Input
                                                id="profile-username"
                                                v-model="profileForm.username"
                                                type="text"
                                                placeholder="username_anda"
                                                required
                                                :disabled="!isEditingUsername"
                                                autocomplete="username"
                                                class="pr-8 font-mono text-xs transition-colors"
                                                :class="{
                                                    'bg-muted/40 cursor-not-allowed text-muted-foreground':
                                                        !isEditingUsername,
                                                    'border-destructive focus-visible:ring-destructive':
                                                        isEditingUsername &&
                                                        (profileForm.errors
                                                            .username ||
                                                            usernameStatus ===
                                                                'unavailable'),
                                                    'border-emerald-500 focus-visible:ring-emerald-500':
                                                        isEditingUsername &&
                                                        (usernameStatus ===
                                                            'available' ||
                                                            usernameStatus ===
                                                                'current') &&
                                                        !profileForm.errors
                                                            .username,
                                                }"
                                                @input="onUsernameInput"
                                            />
                                            <div
                                                class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none"
                                            >
                                                <Lock
                                                    v-if="!isEditingUsername"
                                                    class="w-3.5 h-3.5 text-muted-foreground/60"
                                                />
                                                <Loader2
                                                    v-else-if="
                                                        isCheckingUsername
                                                    "
                                                    class="w-3.5 h-3.5 text-muted-foreground animate-spin"
                                                />
                                                <CheckCircle2
                                                    v-else-if="
                                                        usernameStatus ===
                                                            'available' ||
                                                        usernameStatus ===
                                                            'current'
                                                    "
                                                    class="w-4 h-4 text-emerald-500"
                                                />
                                                <XCircle
                                                    v-else-if="
                                                        usernameStatus ===
                                                        'unavailable'
                                                    "
                                                    class="w-4 h-4 text-destructive"
                                                />
                                            </div>
                                        </div>
                                        <Button
                                            v-if="!isEditingUsername"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="enableEditUsername"
                                            class="shrink-0 text-xs font-semibold cursor-pointer gap-1.5 h-9"
                                        >
                                            <Pencil class="w-3.5 h-3.5" /> Ganti
                                        </Button>
                                        <Button
                                            v-else
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            @click="cancelEditUsername"
                                            class="shrink-0 text-xs text-muted-foreground hover:text-foreground cursor-pointer h-9 px-2.5"
                                        >
                                            Batal
                                        </Button>
                                    </div>
                                    <p
                                        v-if="profileForm.errors.username"
                                        class="text-[11px] text-destructive font-medium"
                                    >
                                        {{ profileForm.errors.username }}
                                    </p>
                                    <p
                                        v-else-if="
                                            isEditingUsername &&
                                            usernameStatus === 'available'
                                        "
                                        class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1"
                                    >
                                        <Check class="w-3 h-3" />
                                        {{ usernameMessage }}
                                    </p>
                                    <p
                                        v-else-if="
                                            isEditingUsername &&
                                            usernameStatus === 'unavailable'
                                        "
                                        class="text-[11px] text-destructive font-medium flex items-center gap-1"
                                    >
                                        <XCircle class="w-3 h-3" />
                                        {{ usernameMessage }}
                                    </p>
                                    <p
                                        v-else-if="isEditingUsername"
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Gunakan huruf kecil & angka tanpa spasi.
                                        Mengubah username akan otomatis
                                        menyinkronkan tautan referral Anda.
                                    </p>
                                    <p
                                        v-else
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Username terikat dengan tautan referral
                                        publik Anda. Klik tombol "Ganti" untuk
                                        mengubah.
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <Label for="profile-email"
                                            >Alamat Email</Label
                                        >
                                        <span
                                            v-if="!isEditingEmail"
                                            class="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1"
                                        >
                                            <CheckCircle2 class="w-3 h-3" />
                                            Terverifikasi
                                        </span>
                                        <span
                                            v-else-if="isEmailChanged"
                                            class="text-[10px] text-amber-600 dark:text-amber-400 font-bold flex items-center gap-1"
                                        >
                                            <ShieldAlert class="w-3 h-3" />
                                            Perlu Kode OTP
                                        </span>
                                        <span
                                            v-else
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            Sedang diedit
                                        </span>
                                    </div>
                                    <div class="flex gap-2 items-center">
                                        <div class="relative flex-1">
                                            <Input
                                                id="profile-email"
                                                v-model="profileForm.email"
                                                type="email"
                                                placeholder="nama@email.com"
                                                required
                                                :disabled="!isEditingEmail"
                                                class="pr-8 text-xs transition-colors"
                                                :class="{
                                                    'bg-muted/40 cursor-not-allowed text-muted-foreground':
                                                        !isEditingEmail,
                                                    'border-destructive':
                                                        profileForm.errors
                                                            .email,
                                                    'border-amber-500':
                                                        isEditingEmail &&
                                                        isEmailChanged,
                                                }"
                                            />
                                            <div
                                                v-if="!isEditingEmail"
                                                class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none"
                                            >
                                                <Lock
                                                    class="w-3.5 h-3.5 text-muted-foreground/60"
                                                />
                                            </div>
                                        </div>
                                        <Button
                                            v-if="!isEditingEmail"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="enableEditEmail"
                                            class="shrink-0 text-xs font-semibold cursor-pointer gap-1.5 h-9"
                                        >
                                            <Pencil class="w-3.5 h-3.5" /> Ganti
                                        </Button>
                                        <Button
                                            v-else
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            @click="cancelEditEmail"
                                            class="shrink-0 text-xs text-muted-foreground hover:text-foreground cursor-pointer h-9 px-2.5"
                                        >
                                            Batal
                                        </Button>
                                    </div>
                                    <p
                                        v-if="profileForm.errors.email"
                                        class="text-[11px] text-destructive font-medium"
                                    >
                                        {{ profileForm.errors.email }}
                                    </p>
                                    <p
                                        v-else-if="!isEditingEmail"
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Alamat email terkunci untuk keamanan.
                                        Klik tombol "Ganti" untuk memperbarui
                                        email.
                                    </p>
                                    <p
                                        v-else-if="!isEmailChanged"
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Ketikkan alamat email baru Anda. Kode
                                        verifikasi OTP akan dikirimkan ke email
                                        saat ini.
                                    </p>
                                </div>

                                <!-- Verification Box when Email is Changed -->
                                <div
                                    v-if="isEditingEmail && isEmailChanged"
                                    class="p-3.5 rounded-xl border border-amber-200 dark:border-amber-800/80 bg-amber-50/70 dark:bg-amber-950/20 space-y-3 animate-in fade-in-50 duration-200"
                                >
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <div class="flex items-start gap-2">
                                            <ShieldAlert
                                                class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5"
                                            />
                                            <div>
                                                <p
                                                    class="text-xs font-bold text-amber-900 dark:text-amber-300"
                                                >
                                                    Verifikasi Penggantian Email
                                                </p>
                                                <p
                                                    class="text-[11px] text-amber-800/90 dark:text-amber-400/90 leading-relaxed mt-0.5"
                                                >
                                                    Demi keamanan akun, sistem
                                                    akan mengirimkan 6 digit
                                                    kode ke email aktif saat
                                                    ini:
                                                    <strong
                                                        class="font-semibold"
                                                        >{{
                                                            user.email
                                                        }}</strong
                                                    >.
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="xs"
                                            @click="cancelEditEmail"
                                            class="text-[11px] text-muted-foreground hover:text-foreground cursor-pointer h-7 px-2"
                                        >
                                            Batal
                                        </Button>
                                    </div>

                                    <div
                                        class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 pt-1"
                                    >
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            @click="sendEmailChangeOtp"
                                            :disabled="
                                                isSendingOtp ||
                                                resendCountdown > 0
                                            "
                                            class="text-xs cursor-pointer font-medium bg-card"
                                        >
                                            <Loader2
                                                v-if="isSendingOtp"
                                                class="w-3.5 h-3.5 animate-spin mr-1.5"
                                            />
                                            <Mail
                                                v-else
                                                class="w-3.5 h-3.5 mr-1.5 text-primary"
                                            />
                                            <span v-if="resendCountdown > 0"
                                                >Kirim Ulang ({{
                                                    resendCountdown
                                                }}s)</span
                                            >
                                            <span v-else-if="otpSent"
                                                >Kirim Ulang Kode</span
                                            >
                                            <span v-else
                                                >Kirim Kode ke
                                                {{ user.email }}</span
                                            >
                                        </Button>
                                    </div>

                                    <div
                                        v-if="otpMessage"
                                        class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1"
                                    >
                                        <CheckCircle2
                                            class="w-3.5 h-3.5 shrink-0"
                                        />
                                        <span>{{ otpMessage }}</span>
                                    </div>

                                    <div class="space-y-1.5 pt-1">
                                        <Label
                                            for="profile-email-otp"
                                            class="text-xs"
                                            >Kode Verifikasi (6 Digit)</Label
                                        >
                                        <Input
                                            id="profile-email-otp"
                                            v-model="profileForm.email_otp"
                                            type="text"
                                            maxlength="6"
                                            placeholder="123456"
                                            class="font-mono text-sm tracking-widest uppercase max-w-[180px]"
                                            :class="{
                                                'border-destructive':
                                                    profileForm.errors
                                                        .email_otp,
                                            }"
                                        />
                                        <p
                                            v-if="profileForm.errors.email_otp"
                                            class="text-[11px] text-destructive font-medium"
                                        >
                                            {{ profileForm.errors.email_otp }}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                            <CardFooter class="pt-4 pb-4 border-t flex justify-end">
                                <Button
                                    type="submit"
                                    size="sm"
                                    :disabled="
                                        profileForm.processing ||
                                        (isEditingUsername &&
                                            (isCheckingUsername ||
                                                usernameStatus ===
                                                    'unavailable'))
                                    "
                                    class="gap-1.5 text-xs font-bold shadow-xs cursor-pointer"
                                >
                                    <Loader2
                                        v-if="profileForm.processing"
                                        class="w-3.5 h-3.5 animate-spin"
                                    />
                                    <Save v-else class="w-3.5 h-3.5" />
                                    <span>{{
                                        profileForm.processing
                                            ? "Menyimpan..."
                                            : "Simpan Profil"
                                    }}</span>
                                </Button>
                            </CardFooter>
                        </form>
                    </Card>

                    <!-- Change Password Card -->
                    <Card class="shadow-2xs">
                        <CardHeader class="pb-3 border-b">
                            <CardTitle class="text-sm font-bold"
                                >Ubah Kata Sandi</CardTitle
                            >
                            <CardDescription class="text-xs"
                                >Gunakan minimal 8 karakter untuk mengamankan
                                akun.</CardDescription
                            >
                        </CardHeader>
                        <form @submit.prevent="updatePassword">
                            <CardContent class="pt-4 pb-5 space-y-4">
                                <div class="space-y-1.5">
                                    <Label for="current-password"
                                        >Kata Sandi Saat Ini</Label
                                    >
                                    <div class="relative">
                                        <Input
                                            id="current-password"
                                            v-model="
                                                passwordForm.current_password
                                            "
                                            :type="
                                                showCurrentPassword
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            placeholder="Masukkan sandi saat ini"
                                            required
                                            autocomplete="current-password"
                                            class="pr-9"
                                            :class="{
                                                'border-destructive':
                                                    passwordForm.errors
                                                        .current_password,
                                            }"
                                        />
                                        <button
                                            type="button"
                                            @click="
                                                showCurrentPassword =
                                                    !showCurrentPassword
                                            "
                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                            tabindex="-1"
                                            :title="
                                                showCurrentPassword
                                                    ? 'Sembunyikan sandi'
                                                    : 'Lihat sandi'
                                            "
                                        >
                                            <EyeOff
                                                v-if="showCurrentPassword"
                                                class="w-4 h-4"
                                            />
                                            <Eye v-else class="w-4 h-4" />
                                        </button>
                                    </div>
                                    <p
                                        v-if="
                                            passwordForm.errors.current_password
                                        "
                                        class="text-[11px] text-destructive font-medium"
                                    >
                                        {{
                                            passwordForm.errors.current_password
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="new-password"
                                        >Kata Sandi Baru</Label
                                    >
                                    <div class="relative">
                                        <Input
                                            id="new-password"
                                            v-model="passwordForm.password"
                                            :type="
                                                showNewPassword
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            placeholder="Minimal 8 karakter"
                                            required
                                            autocomplete="new-password"
                                            class="pr-9"
                                            :class="{
                                                'border-destructive':
                                                    passwordForm.errors
                                                        .password,
                                            }"
                                        />
                                        <button
                                            type="button"
                                            @click="
                                                showNewPassword =
                                                    !showNewPassword
                                            "
                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                            tabindex="-1"
                                            :title="
                                                showNewPassword
                                                    ? 'Sembunyikan sandi'
                                                    : 'Lihat sandi'
                                            "
                                        >
                                            <EyeOff
                                                v-if="showNewPassword"
                                                class="w-4 h-4"
                                            />
                                            <Eye v-else class="w-4 h-4" />
                                        </button>
                                    </div>
                                    <p
                                        v-if="passwordForm.errors.password"
                                        class="text-[11px] text-destructive font-medium"
                                    >
                                        {{ passwordForm.errors.password }}
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="confirm-password"
                                        >Konfirmasi Sandi Baru</Label
                                    >
                                    <div class="relative">
                                        <Input
                                            id="confirm-password"
                                            v-model="
                                                passwordForm.password_confirmation
                                            "
                                            :type="
                                                showConfirmPassword
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            placeholder="Ketik ulang sandi baru"
                                            required
                                            autocomplete="new-password"
                                            class="pr-9"
                                            :class="{
                                                'border-destructive':
                                                    passwordForm.errors
                                                        .password_confirmation,
                                            }"
                                        />
                                        <button
                                            type="button"
                                            @click="
                                                showConfirmPassword =
                                                    !showConfirmPassword
                                            "
                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                            tabindex="-1"
                                            :title="
                                                showConfirmPassword
                                                    ? 'Sembunyikan sandi'
                                                    : 'Lihat sandi'
                                            "
                                        >
                                            <EyeOff
                                                v-if="showConfirmPassword"
                                                class="w-4 h-4"
                                            />
                                            <Eye v-else class="w-4 h-4" />
                                        </button>
                                    </div>
                                    <p
                                        v-if="
                                            passwordForm.errors
                                                .password_confirmation
                                        "
                                        class="text-[11px] text-destructive font-medium"
                                    >
                                        {{
                                            passwordForm.errors
                                                .password_confirmation
                                        }}
                                    </p>
                                </div>
                            </CardContent>
                            <CardFooter class="pt-4 pb-4 border-t flex justify-end">
                                <Button
                                    type="submit"
                                    size="sm"
                                    :disabled="passwordForm.processing"
                                    class="gap-1.5 text-xs font-bold shadow-xs cursor-pointer"
                                >
                                    <Loader2
                                        v-if="passwordForm.processing"
                                        class="w-3.5 h-3.5 animate-spin"
                                    />
                                    <KeyRound v-else class="w-3.5 h-3.5" />
                                    <span>{{
                                        passwordForm.processing
                                            ? "Memperbarui..."
                                            : "Perbarui Kata Sandi"
                                    }}</span>
                                </Button>
                            </CardFooter>
                        </form>
                    </Card>
                </div>

                <!-- ZONA BAHAYA (DANGER ZONE) -->
                <section class="space-y-4 pt-6 border-t border-destructive/20">
                    <div class="flex items-center gap-2">
                        <div
                            class="p-2 rounded-xl bg-destructive/10 text-destructive"
                        >
                            <AlertTriangle class="h-4 w-4" />
                        </div>
                        <div>
                            <h2
                                class="text-base sm:text-lg font-bold text-destructive"
                            >
                                Zona Bahaya (Danger Zone)
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Tindakan berisiko tinggi: menonaktifkan aplikasi
                                beserta seluruh datanya atau menghapus akun
                                secara permanen.
                            </p>
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch"
                    >
                        <!-- Kartu 1: Menonaktifkan Aplikasi -->
                        <Card
                            class="border-destructive/30 bg-destructive/5 shadow-2xs flex flex-col justify-between"
                        >
                            <CardHeader
                                class="pb-3 border-b border-destructive/15"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="h-8 w-8 rounded-lg bg-destructive/10 text-destructive flex items-center justify-center shrink-0"
                                    >
                                        <PowerOff class="h-4 w-4" />
                                    </div>
                                    <div>
                                        <CardTitle
                                            class="text-base font-bold text-destructive"
                                            >Nonaktifkan Aplikasi</CardTitle
                                        >
                                        <CardDescription
                                            class="text-xs text-muted-foreground"
                                        >
                                            Lepas modul dari akun Anda dan hapus
                                            seluruh datanya
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="pt-4 pb-5 space-y-3 flex-1">
                                <p
                                    class="text-xs text-muted-foreground leading-relaxed"
                                >
                                    Menonaktifkan aplikasi akan
                                    <strong
                                        >menghapus bersih seluruh data
                                        transaksi, dompet, perangkat, atau
                                        kontak</strong
                                    >
                                    yang terhubung pada aplikasi tersebut.
                                </p>
                                <div class="space-y-2 pt-1">
                                    <div
                                        v-for="app in proApps"
                                        :key="app.module"
                                        class="p-3 rounded-xl border bg-card flex items-center justify-between gap-3 text-xs shadow-2xs"
                                    >
                                        <div class="flex items-center gap-2">
                                            <Wallet
                                                v-if="app.module === 'keuangan'"
                                                class="w-4 h-4 text-primary"
                                            />
                                            <Send
                                                v-else
                                                class="w-4 h-4 text-emerald-600"
                                            />
                                            <div>
                                                <span
                                                    class="font-bold text-foreground block"
                                                    >{{ app.name }}</span
                                                >
                                                <span
                                                    class="text-[10px] text-muted-foreground"
                                                >
                                                    {{
                                                        app.is_accessible
                                                            ? "Terpasang di akun"
                                                            : "Belum aktif"
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                        <Button
                                            v-if="app.is_accessible"
                                            size="sm"
                                            variant="outline"
                                            class="h-7 px-2.5 text-xs font-bold text-destructive hover:bg-destructive/10 hover:text-destructive border-destructive/30 cursor-pointer"
                                            @click="openDeactivateModal(app)"
                                        >
                                            <PowerOff class="w-3 h-3 mr-1" />
                                            <span>Nonaktifkan</span>
                                        </Button>
                                        <Badge
                                            v-else
                                            variant="outline"
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            Nonaktif
                                        </Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Kartu 2: Hapus Akun Permanen -->
                        <Card
                            class="border-destructive/30 bg-destructive/5 shadow-2xs flex flex-col justify-between"
                        >
                            <CardHeader
                                class="pb-3 border-b border-destructive/15"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="h-8 w-8 rounded-lg bg-destructive/10 text-destructive flex items-center justify-center shrink-0"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </div>
                                    <div>
                                        <CardTitle
                                            class="text-base font-bold text-destructive"
                                            >Hapus Akun Permanen</CardTitle
                                        >
                                        <CardDescription
                                            class="text-xs text-muted-foreground"
                                        >
                                            Hapus seluruh profil, login, dan
                                            data semua aplikasi
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="pt-4 pb-5 space-y-3 flex-1">
                                <p
                                    class="text-xs text-muted-foreground leading-relaxed"
                                >
                                    Setelah akun dihapus,
                                    <strong
                                        >seluruh data di semua modul aplikasi,
                                        langganan aktif, tiket aduan, dan
                                        riwayat referral Anda akan musnah secara
                                        permanen</strong
                                    >. Tindakan ini tidak dapat dibatalkan.
                                </p>
                                <div
                                    class="p-3 rounded-xl border border-destructive/20 bg-destructive/10 text-[11px] text-destructive leading-tight flex items-start gap-2"
                                >
                                    <AlertTriangle
                                        class="w-4 h-4 shrink-0 mt-0.5"
                                    />
                                    <span
                                        >Anda akan langsung dikeluarkan dari
                                        sistem dan tidak bisa login kembali
                                        dengan akun ini.</span
                                    >
                                </div>
                            </CardContent>
                            <CardFooter
                                class="pt-4 pb-4 border-t border-destructive/15 flex justify-end"
                            >
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    class="text-xs font-bold gap-1.5 shadow-2xs cursor-pointer"
                                    @click="openDeleteAccountModal"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                    <span>Hapus Akun Saya</span>
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>
                </section>
            </div>
            <!-- /BAGIAN 1: AKUN & KEAMANAN -->

            <!-- BAGIAN 2: PAKET PRO & TAGIHAN -->
            <div id="section-pro" class="space-y-8 scroll-mt-28 pt-8 border-t"
            >
                <!-- 1. EKOSISTEM & RINGKASAN LANGGANAN -->
                <section class="space-y-4">
                    <div
                        class="flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <Sparkles class="h-5 w-5 text-amber-500" />
                                <h2
                                    class="text-lg sm:text-xl font-extrabold text-foreground tracking-tight"
                                >
                                    Paket Pro & Langganan Aplikasi
                                </h2>
                            </div>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                Pusat pengelolaan langganan, pilihan durasi hemat, serta perbandingan fitur & spesifikasi keuangan tiap aplikasi Sirkelta.
                            </p>
                        </div>

                        <div
                            v-if="referral.available_discount > 0"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-800 dark:text-emerald-300 font-bold shrink-0"
                        >
                            <Tag
                                class="w-3.5 h-3.5 text-emerald-600 shrink-0"
                            />
                            <span
                                >Diskon Referral:
                                {{
                                    formatRupiah(referral.available_discount)
                                }}</span
                            >
                        </div>
                    </div>

                    <!-- 3 Ringkasan Metrik Akun -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div
                            class="p-4 rounded-2xl bg-card border shadow-2xs flex items-center gap-3.5"
                        >
                            <div
                                class="h-10 w-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0"
                            >
                                <Layers class="h-5 w-5" />
                            </div>
                            <div>
                                <span
                                    class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground"
                                    >Aplikasi Terpasang</span
                                >
                                <div class="text-lg font-black text-foreground">
                                    {{
                                        proApps.filter((a) => a.is_accessible)
                                            .length
                                    }}
                                    / {{ proApps.length }} Modul
                                </div>
                                <span class="text-[10px] text-muted-foreground"
                                    >Tersedia di Pindah Ruang Kerja</span
                                >
                            </div>
                        </div>

                        <div
                            class="p-4 rounded-2xl bg-card border shadow-2xs flex items-center gap-3.5"
                        >
                            <div
                                class="h-10 w-10 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center shrink-0"
                            >
                                <Sparkles class="h-5 w-5" />
                            </div>
                            <div>
                                <span
                                    class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground"
                                    >Status Keanggotaan</span
                                >
                                <div class="text-lg font-black text-foreground">
                                    {{
                                        proApps.some((a) => a.is_pro)
                                            ? "Member Pro Aktif"
                                            : "Paket Free (Gratis)"
                                    }}
                                </div>
                                <span
                                    class="text-[10px]"
                                    :class="
                                        proApps.some((a) => a.is_pro)
                                            ? 'text-emerald-600 font-semibold'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ proApps.filter((a) => a.is_pro).length }}
                                    Aplikasi Versi Pro
                                </span>
                            </div>
                        </div>

                        <div
                            class="p-4 rounded-2xl bg-card border shadow-2xs flex items-center gap-3.5"
                            :class="
                                referral.available_discount > 0
                                    ? 'bg-emerald-500/5 border-emerald-500/20'
                                    : ''
                            "
                        >
                            <div
                                class="h-10 w-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0"
                            >
                                <Gift class="h-5 w-5" />
                            </div>
                            <div>
                                <span
                                    class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground"
                                    >Saldo Potongan Referral</span
                                >
                                <div
                                    class="text-lg font-black text-foreground"
                                    :class="
                                        referral.available_discount > 0
                                            ? 'text-emerald-600'
                                            : ''
                                    "
                                >
                                    {{
                                        formatRupiah(
                                            referral.available_discount,
                                        )
                                    }}
                                </div>
                                <span class="text-[10px] text-muted-foreground"
                                    >Otomatis memotong tagihan checkout</span
                                >
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 2. PEMILIH APLIKASI, STATUS AKTIF & TABEL LENGKAP -->
                <section class="space-y-6">
                    <!-- Tab Selector Modul Aplikasi (Dinamis & Skalavel untuk Aplikasi Baru) -->
                    <div class="space-y-2">
                        <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                            Pilih Modul Aplikasi Bisnis:
                        </Label>
                        <div class="flex items-center gap-2 p-1.5 bg-muted/50 rounded-2xl border overflow-x-auto">
                            <button
                                v-for="app in proApps"
                                :key="app.module"
                                type="button"
                                @click="activeComparisonTab = app.module"
                                class="flex-1 min-w-[200px] flex items-center justify-between gap-3 px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                                :class="
                                    activeComparisonTab === app.module
                                        ? 'bg-card text-foreground shadow-sm border border-border/80 ring-1 ring-primary/20'
                                        : 'text-muted-foreground hover:text-foreground hover:bg-card/50'
                                "
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="h-8 w-8 rounded-lg flex items-center justify-center shrink-0"
                                        :class="
                                            app.module === 'keuangan'
                                                ? 'bg-primary/10 text-primary'
                                                : app.module === 'wasender'
                                                  ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                  : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        <Wallet v-if="app.module === 'keuangan'" class="h-4 w-4" />
                                        <Send v-else-if="app.module === 'wasender'" class="h-4 w-4" />
                                        <Layers v-else class="h-4 w-4" />
                                    </div>
                                    <span class="text-sm font-bold tracking-tight">{{ app.name }}</span>
                                </div>

                                <Badge
                                    v-if="app.is_superadmin"
                                    class="text-[9px] font-black uppercase bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-500/20"
                                >
                                    SUPERADMIN
                                </Badge>
                                <Badge
                                    v-else-if="app.is_pro"
                                    class="text-[9px] font-black uppercase bg-emerald-600 text-white flex items-center gap-1 shadow-2xs"
                                >
                                    <Sparkles class="w-2.5 h-2.5 text-amber-300 fill-amber-300" />
                                    PRO
                                </Badge>
                                <Badge
                                    v-else-if="app.is_accessible"
                                    class="text-[9px] font-bold bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/20"
                                >
                                    FREE
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="text-[9px] font-semibold text-muted-foreground bg-muted/40"
                                >
                                    BELUM AKTIF
                                </Badge>
                            </button>
                        </div>
                    </div>

                    <!-- Selected App Status & Summary Banner -->
                    <div
                        v-if="currentSelectedApp"
                        class="p-4 sm:p-5 rounded-2xl border bg-card shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4"
                        :class="currentSelectedApp.is_pro ? 'border-primary/40 bg-gradient-to-r from-card to-primary/5' : ''"
                    >
                        <div class="flex items-start sm:items-center gap-3.5">
                            <div
                                class="h-12 w-12 rounded-2xl flex items-center justify-center shrink-0 shadow-2xs"
                                :class="
                                    currentSelectedApp.module === 'keuangan'
                                        ? 'bg-primary/15 text-primary'
                                        : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                "
                            >
                                <Wallet v-if="currentSelectedApp.module === 'keuangan'" class="h-6 w-6" />
                                <Send v-else-if="currentSelectedApp.module === 'wasender'" class="h-6 w-6" />
                                <Layers v-else class="h-6 w-6" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base sm:text-lg font-black text-foreground">
                                        {{ currentSelectedApp.name }}
                                    </h3>
                                    <Badge
                                        v-if="currentSelectedApp.is_pro"
                                        class="text-[10px] font-extrabold uppercase bg-emerald-600 text-white"
                                    >
                                        PRO AKTIF
                                    </Badge>
                                    <Badge
                                        v-else-if="currentSelectedApp.is_accessible"
                                        class="text-[10px] font-bold bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/20"
                                    >
                                        PAKET FREE
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="outline"
                                        class="text-[10px] font-bold text-muted-foreground"
                                    >
                                        BELUM TERPASANG
                                    </Badge>
                                </div>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    {{ currentSelectedApp.description }}
                                </p>
                            </div>
                        </div>

                        <!-- Right Status Details & Quick Action -->
                        <div class="flex items-center gap-3 shrink-0">
                            <!-- If Pro: Remaining Days -->
                            <div
                                v-if="currentSelectedApp.subscription"
                                class="text-right px-3.5 py-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20"
                            >
                                <div class="text-[11px] font-semibold text-emerald-800 dark:text-emerald-300 flex items-center justify-end gap-1">
                                    <Clock class="w-3.5 h-3.5 text-emerald-600" />
                                    <span>Masa Aktif Pro:</span>
                                    <strong class="font-extrabold text-emerald-700 dark:text-emerald-400">
                                        Sisa {{ currentSelectedApp.subscription.remaining_days }} Hari
                                    </strong>
                                </div>
                                <div class="text-[10px] text-muted-foreground mt-0.5">
                                    Berlaku s/d {{ currentSelectedApp.subscription.expires_at }}
                                </div>
                            </div>

                            <!-- If Not Accessible: Quick Activate Free -->
                            <Button
                                v-else-if="!currentSelectedApp.is_accessible"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="text-xs font-bold h-9 cursor-pointer gap-1.5"
                                :disabled="activateForm.processing && activateForm.module === currentSelectedApp.module"
                                @click="handleActivate(currentSelectedApp.module)"
                            >
                                <Plus class="w-3.5 h-3.5 text-primary" />
                                <span>{{
                                    activateForm.processing && activateForm.module === currentSelectedApp.module
                                        ? "Memasang..."
                                        : "Aktifkan Versi Free"
                                }}</span>
                            </Button>
                        </div>
                    </div>

                    <!-- TABEL TERPADU: RINCIAN FITUR, KEUANGAN & PILIHAN DURASI PAKET PRO -->
                    <Card class="shadow-2xs overflow-hidden border">
                        <!-- Card Header -->
                        <CardHeader class="pb-3 border-b bg-muted/20">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <CardTitle class="text-sm sm:text-base font-extrabold flex items-center gap-2">
                                        <Wallet v-if="activeComparisonTab === 'keuangan'" class="h-4 w-4 text-primary" />
                                        <Send v-else-if="activeComparisonTab === 'wasender'" class="h-4 w-4 text-emerald-600" />
                                        <Layers v-else class="h-4 w-4 text-primary" />
                                        <span>
                                            Rincian Fitur, Keuangan & Pilihan Durasi Paket:
                                            {{ comparisonPlans[activeComparisonTab]?.name || currentSelectedApp?.name }}
                                        </span>
                                    </CardTitle>
                                    <CardDescription class="text-xs mt-0.5">
                                        Pilihan durasi hemat 1 s/d 12 bulan dan matriks perbandingan fitur lengkap versi Free vs Pro.
                                    </CardDescription>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <Badge
                                        variant="outline"
                                        class="font-bold text-[10px]"
                                    >
                                        Mulai {{ formatRupiah(props.packagePricing?.[activeComparisonTab]?.[1]?.price || 75000) }}/bln
                                    </Badge>
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2.5 py-0.5 rounded-full border border-emerald-500/20">
                                        <Sparkles class="w-3 h-3" />
                                        <span>Hemat s/d 25%</span>
                                    </div>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent class="p-0 divide-y">
                            <!-- 1. BAGIAN ATAS: 4 KARTU PILIHAN DURASI PRO -->
                            <div class="p-4 sm:p-5 bg-card space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <CreditCard class="h-4 w-4 text-primary" />
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-foreground">
                                            Pilihan Durasi & Harga Paket Pro
                                        </h4>
                                    </div>
                                    <span class="text-[11px] text-muted-foreground hidden sm:inline">
                                        Semua durasi mencakup akses penuh seluruh fitur Pro tanpa batasan
                                    </span>
                                </div>

                                <!-- Notice jika semua metode pembayaran ditutup karena perbaikan -->
                                <div 
                                    v-if="isAllPaymentsDisabled"
                                    class="p-3.5 bg-amber-500/10 border border-amber-500/30 rounded-xl flex items-start sm:items-center gap-2.5 text-xs text-amber-900 dark:text-amber-200"
                                >
                                    <Wrench class="h-4 w-4 text-amber-600 shrink-0 mt-0.5 sm:mt-0" />
                                    <span class="text-xs leading-relaxed">
                                        <strong>Pemberitahuan:</strong> Seluruh saluran transaksi pembayaran saat ini sedang ditutup sementara karena sedang ada pemeliharaan sistem.
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                    <div
                                        v-for="tier in currentAppPricingTiers"
                                        :key="tier.months"
                                        class="rounded-xl border p-3.5 flex flex-col justify-between transition-all relative overflow-hidden"
                                        :class="tier.isPopular ? 'border-primary/60 ring-1 ring-primary/20 bg-primary/5 shadow-2xs' : 'bg-muted/10 hover:bg-muted/20 border-border'"
                                    >
                                        <!-- Top Popular Ribbon -->
                                        <div v-if="tier.isPopular" class="absolute top-0 right-0">
                                            <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-bl-lg bg-primary text-primary-foreground shadow-2xs">
                                                🔥 POPULER
                                            </span>
                                        </div>

                                        <div>
                                            <div class="flex items-center gap-1.5 mb-1">
                                                <span class="font-extrabold text-sm text-foreground">{{ tier.label }}</span>
                                                <Badge
                                                    v-if="!tier.isPopular"
                                                    variant="outline"
                                                    class="text-[9px] font-bold px-1.5 py-0"
                                                    :class="tier.tagColor"
                                                >
                                                    {{ tier.badge }}
                                                </Badge>
                                            </div>
                                            <p class="text-[10px] text-muted-foreground line-clamp-1">
                                                {{ tier.sublabel }}
                                            </p>

                                            <!-- Price tag -->
                                            <div class="mt-2.5">
                                                <div v-if="tier.savings > 0" class="flex items-center gap-1.5 h-4">
                                                    <span class="line-through text-muted-foreground text-[10px]">
                                                        {{ formatRupiah(tier.normal) }}
                                                    </span>
                                                    <span class="text-[9px] font-extrabold text-emerald-600 dark:text-emerald-400">
                                                        Hemat {{ tier.discount_pct }}%
                                                    </span>
                                                </div>
                                                <div v-else class="h-4"></div>

                                                <div
                                                    class="text-lg font-black tracking-tight"
                                                    :class="tier.isPopular ? 'text-primary' : 'text-foreground'"
                                                >
                                                    {{ formatRupiah(tier.price) }}
                                                </div>
                                                <div class="text-[10px] text-muted-foreground mt-0.5">
                                                    ~{{ formatRupiah(tier.monthlyRate) }} / bulan
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Button Action -->
                                        <div class="pt-3 mt-3 border-t border-border/60">
                                            <Button
                                                type="button"
                                                size="sm"
                                                class="w-full text-xs font-bold h-8 cursor-pointer gap-1 shadow-2xs"
                                                :variant="tier.isPopular ? 'default' : 'outline'"
                                                @click="openCheckoutModal(currentSelectedApp, tier.months)"
                                            >
                                                <Sparkles v-if="tier.isPopular" class="w-3 h-3 text-amber-300" />
                                                <span>{{
                                                    currentSelectedApp?.is_pro
                                                        ? `Perpanjang (${tier.months} Bln)`
                                                        : `Pilih (${tier.months} Bln)`
                                                }}</span>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. BAGIAN BAWAH: MATRIKS PERBANDINGAN FITUR (FREE VS PRO) -->
                            <div class="bg-card">
                                <div class="px-4 sm:px-5 py-3 border-b bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Layers class="h-4 w-4 text-emerald-600" />
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-foreground">
                                            Matriks Fitur & Spesifikasi: Free (Gratis) vs Pro (Maksimal)
                                        </h4>
                                    </div>
                                    <span class="text-[11px] text-muted-foreground hidden sm:inline">
                                        {{ activeComparisonTab === 'wasender' ? 'Rincian kapabilitas broadcast dan fitur ekstensi WhatsApp' : 'Rincian kapabilitas operasional dan fitur akuntansi' }}
                                    </span>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs text-left border-collapse min-w-[640px]">
                                        <thead>
                                            <tr class="border-b bg-muted/40 text-muted-foreground uppercase text-[10px] font-bold tracking-wider">
                                                <th class="py-3 px-4 w-1/2 font-bold">Fitur & Spesifikasi Operasional</th>
                                                <th class="py-3 px-4 w-1/4 font-bold text-center">Paket Free (Gratis)</th>
                                                <th class="py-3 px-4 w-1/4 font-bold text-center bg-primary/5 text-primary font-black">
                                                    Paket Pro (Maksimal)
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            <tr
                                                v-for="(row, idx) in comparisonPlans[activeComparisonTab]?.items || []"
                                                :key="idx"
                                                class="hover:bg-muted/30 transition-colors"
                                            >
                                                <td class="py-3 px-4 font-semibold text-foreground">
                                                    {{ row.feature }}
                                                </td>
                                                <td class="py-3 px-4 text-center text-muted-foreground">
                                                    <span
                                                        v-if="row.free === 'Tidak Tersedia'"
                                                        class="inline-flex items-center gap-1 text-muted-foreground/60 italic text-[11px]"
                                                    >
                                                        <XCircle class="h-3.5 w-3.5 text-muted-foreground/40" />
                                                        <span>Tidak Tersedia</span>
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="font-medium text-[11px]"
                                                    >
                                                        {{ row.free }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-center font-bold text-foreground bg-primary/5">
                                                    <span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 text-[11px]">
                                                        <CheckCircle2 class="h-3.5 w-3.5 text-emerald-600 shrink-0" />
                                                        <span>{{ row.pro }}</span>
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <!-- Bottom Action Row inside Comparison Table -->
                                        <tfoot class="border-t bg-muted/30 font-medium">
                                            <tr>
                                                <td class="py-3.5 px-4 font-bold text-foreground">
                                                    Status Akses Akun Anda Saat Ini
                                                </td>
                                                <td class="py-3.5 px-4 text-center">
                                                    <Badge
                                                        v-if="!currentSelectedApp?.is_pro"
                                                        class="text-[10px] font-bold bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/20"
                                                    >
                                                        Aktif Digunakan (Free)
                                                    </Badge>
                                                    <span v-else class="text-xs text-muted-foreground">-</span>
                                                </td>
                                                <td class="py-3.5 px-4 text-center bg-primary/5">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        class="w-full sm:w-auto font-bold text-xs h-8 cursor-pointer gap-1.5 shadow-xs"
                                                        @click="openCheckoutModal(currentSelectedApp, 6)"
                                                    >
                                                        <Sparkles class="w-3.5 h-3.5 text-amber-300 fill-amber-300" />
                                                        <span>{{
                                                            currentSelectedApp?.is_pro
                                                                ? "Perpanjang Masa Aktif Pro"
                                                                : "Upgrade ke Pro Sekarang"
                                                        }}</span>
                                                    </Button>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <!-- 3. RIWAYAT PESANAN LANGGANAN USER -->
                <section v-if="paymentOrders.length > 0" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <CreditCard class="h-4 w-4 text-primary" />
                            <h3
                                class="text-sm sm:text-base font-bold text-foreground"
                            >
                                Riwayat Pesanan Langganan
                            </h3>
                        </div>
                        <span class="text-xs text-muted-foreground"
                            >{{ paymentOrders.length }} pesanan terakhir</span
                        >
                    </div>

                    <Card class="shadow-2xs overflow-hidden">
                        <CardContent class="p-0">
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs text-left">
                                    <thead
                                        class="bg-muted/50 text-muted-foreground border-b font-semibold"
                                    >
                                        <tr>
                                            <th class="py-2.5 px-4">
                                                No. Order & Tanggal
                                            </th>
                                            <th class="py-2.5 px-4">
                                                Aplikasi & Paket
                                            </th>
                                            <th class="py-2.5 px-4">
                                                Metode Bayar
                                            </th>
                                            <th class="py-2.5 px-4 text-right">
                                                Total Tagihan
                                            </th>
                                            <th class="py-2.5 px-4 text-center">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr
                                            v-for="order in paymentOrders"
                                            :key="order.id"
                                            class="hover:bg-muted/30"
                                        >
                                            <td class="py-2.5 px-4">
                                                <div
                                                    class="font-mono font-bold text-foreground"
                                                >
                                                    {{ order.order_number }}
                                                </div>
                                                <div
                                                    class="text-[10px] text-muted-foreground"
                                                >
                                                    {{ order.created_at }}
                                                </div>
                                            </td>
                                            <td class="py-2.5 px-4">
                                                <div
                                                    class="font-semibold text-foreground"
                                                >
                                                    {{ order.app_name }}
                                                </div>
                                                <div
                                                    class="text-[10px] text-muted-foreground"
                                                >
                                                    {{ order.duration_months }}
                                                    Bulan
                                                </div>
                                            </td>
                                            <td
                                                class="py-2.5 px-4 text-muted-foreground"
                                            >
                                                {{ order.payment_method_label }}
                                            </td>
                                            <td
                                                class="py-2.5 px-4 text-right font-mono font-bold text-foreground"
                                            >
                                                {{
                                                    formatRupiah(
                                                        order.final_amount,
                                                    )
                                                }}
                                            </td>
                                            <td class="py-2.5 px-4 text-center">
                                                <Badge
                                                    :variant="
                                                        order.status === 'paid'
                                                            ? 'default'
                                                            : order.status ===
                                                                'pending'
                                                              ? 'secondary'
                                                              : 'destructive'
                                                    "
                                                    class="text-[10px] font-bold"
                                                >
                                                    {{
                                                        order.status === "paid"
                                                            ? "Lunas"
                                                            : order.status ===
                                                                "pending"
                                                              ? "Menunggu Verifikasi"
                                                              : "Ditolak"
                                                    }}
                                                </Badge>

                                                <div 
                                                    v-if="order.status === 'pending' && order.midtrans_snap_token" 
                                                    class="mt-1.5 flex justify-center"
                                                >
                                                    <Button 
                                                        type="button" 
                                                        size="sm" 
                                                        variant="outline" 
                                                        class="h-6 text-[10px] px-2.5 gap-1 rounded-lg border-indigo-200 dark:border-indigo-800 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 font-bold"
                                                        @click="triggerSnapPayment(order.midtrans_snap_token)"
                                                    >
                                                        <Sparkles class="h-3 w-3" />
                                                        <span>Bayar Sekarang</span>
                                                    </Button>
                                                </div>
                                                <div
                                                    v-if="
                                                        order.status ===
                                                            'rejected' &&
                                                        order.rejection_reason
                                                    "
                                                    class="text-[10px] text-destructive mt-0.5 max-w-xs truncate"
                                                    :title="
                                                        order.rejection_reason
                                                    "
                                                >
                                                    "{{
                                                        order.rejection_reason
                                                    }}"
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </section>

            </div>
            <!-- /BAGIAN 2: PAKET PRO & TAGIHAN -->

            <!-- BAGIAN 3: PROGRAM REFERRAL -->
            <div id="section-referral" class="space-y-6 scroll-mt-28 pt-8 border-t"
            >
                <!-- BAGIAN 2: PROGRAM REFERRAL & SALDO POTONGAN -->
                <section class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Gift class="h-4 w-4 text-primary" />
                            <h2 class="text-base font-bold text-foreground">
                                Program Referral & Saldo Undangan
                            </h2>
                        </div>
                        <Badge
                            v-if="referral.available_discount > 0"
                            class="text-[10px] font-bold bg-emerald-500/10 text-emerald-700 border-emerald-500/20"
                        >
                            Saldo Aktif:
                            {{ formatRupiah(referral.available_discount) }}
                        </Badge>
                    </div>

                    <!-- Referral Card -->
                    <Card class="shadow-2xs">
                        <CardContent class="p-4 sm:p-5">
                            <div
                                class="flex flex-col md:flex-row md:items-center justify-between gap-4"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-10 w-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-base shrink-0"
                                    >
                                        @
                                    </div>
                                    <div>
                                        <span
                                            class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider"
                                            >Username Referral Anda</span
                                        >
                                        <div
                                            class="text-lg font-extrabold text-foreground tracking-tight"
                                        >
                                            @{{ referral.code }}
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center gap-2 flex-1 max-w-lg"
                                >
                                    <div class="relative flex-1">
                                        <Link2
                                            class="h-4 w-4 text-muted-foreground absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
                                        />
                                        <input
                                            type="text"
                                            readonly
                                            :value="referral.url"
                                            @click="
                                                copyToClipboard(referral.url)
                                            "
                                            class="w-full bg-muted/40 hover:bg-muted/60 border rounded-xl pl-9 pr-3 py-2 text-xs font-medium text-foreground select-all focus:outline-hidden cursor-pointer transition-colors"
                                            title="Klik untuk menyalin tautan pendaftaran"
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        class="gap-1.5 shrink-0 text-xs font-semibold h-9 px-3.5 cursor-pointer"
                                        @click="copyToClipboard(referral.url)"
                                    >
                                        <Check
                                            v-if="copiedLink"
                                            class="h-3.5 w-3.5 text-emerald-300"
                                        />
                                        <Copy v-else class="h-3.5 w-3.5" />
                                        <span>{{
                                            copiedLink
                                                ? "Tersalin"
                                                : "Salin Tautan"
                                        }}</span>
                                    </Button>
                                </div>
                            </div>

                            <!-- 3 Summary Metrics -->
                            <div
                                class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-4 mt-4 border-t"
                            >
                                <div class="p-3 rounded-xl bg-muted/30 border">
                                    <span
                                        class="text-[10px] uppercase font-semibold text-muted-foreground"
                                        >Teman Terdaftar</span
                                    >
                                    <div
                                        class="text-lg font-extrabold tabular-nums text-foreground"
                                    >
                                        {{ referral.total_referees }} Orang
                                    </div>
                                </div>
                                <div
                                    class="p-3 rounded-xl bg-muted/30 border"
                                    :class="
                                        referral.available_discount > 0
                                            ? 'bg-emerald-500/5 border-emerald-500/30'
                                            : ''
                                    "
                                >
                                    <span
                                        class="text-[10px] uppercase font-semibold text-muted-foreground"
                                        :class="
                                            referral.available_discount > 0
                                                ? 'text-emerald-700'
                                                : ''
                                        "
                                        >Saldo Potongan Aktif</span
                                    >
                                    <div
                                        class="text-lg font-extrabold tabular-nums"
                                        :class="
                                            referral.available_discount > 0
                                                ? 'text-emerald-700'
                                                : 'text-foreground'
                                        "
                                    >
                                        {{
                                            formatRupiah(
                                                referral.available_discount,
                                            )
                                        }}
                                    </div>
                                </div>
                                <div class="p-3 rounded-xl bg-muted/30 border">
                                    <span
                                        class="text-[10px] uppercase font-semibold text-muted-foreground"
                                        >Total Reward Terkumpul</span
                                    >
                                    <div
                                        class="text-lg font-extrabold tabular-nums text-foreground"
                                    >
                                        {{
                                            formatRupiah(
                                                referral.total_earned_discount,
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </section>
            </div>
            <!-- /BAGIAN 3: PROGRAM REFERRAL -->

            <!-- CHECKOUT & PAYMENT MODAL -->
            <div
                v-if="isCheckoutModalOpen && checkoutApp"
                class="fixed inset-0 z-50 bg-background/80 backdrop-blur-xs flex items-center justify-center p-4"
            >
                <div
                    class="bg-card border shadow-2xl rounded-2xl max-w-lg w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150 max-h-[92vh] overflow-y-auto"
                >
                    <!-- Modal Header -->
                    <div
                        class="flex items-center justify-between border-b pb-3"
                    >
                        <div>
                            <h3 class="font-bold text-base text-foreground">
                                Selesaikan Pembayaran & Aktivasi Pro
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                {{ checkoutApp.name }} - Durasi
                                {{ checkoutDuration }} Bulan
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7"
                            @click="closeCheckoutModal"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>

                    <form @submit.prevent="submitCheckout" class="space-y-4">
                        <!-- Pilihan Durasi di Modal Checkout -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <Label class="text-xs font-bold text-foreground"
                                    >Pilih Durasi Paket:</Label
                                >
                                <span
                                    class="text-[10px] text-emerald-600 font-semibold"
                                    >Tersedia diskon hemat s/d 25%</span
                                >
                            </div>
                            <div class="grid grid-cols-4 gap-1.5 sm:gap-2">
                                <button
                                    v-for="opt in [
                                        { m: 1, label: '1 Bln', tag: '' },
                                        { m: 3, label: '3 Bln', tag: '-10%' },
                                        {
                                            m: 6,
                                            label: '6 Bln',
                                            tag: 'POPULER',
                                            isPopular: true,
                                        },
                                        { m: 12, label: '1 Thn', tag: '-25%' },
                                    ]"
                                    :key="opt.m"
                                    type="button"
                                    class="relative flex flex-col items-center justify-center py-2 px-1 rounded-xl text-xs font-bold border transition-all cursor-pointer text-center"
                                    :class="[
                                        checkoutDuration === opt.m
                                            ? 'border-primary bg-primary text-primary-foreground shadow-xs'
                                            : 'border-input hover:bg-muted text-foreground',
                                        opt.isPopular &&
                                        checkoutDuration !== opt.m
                                            ? 'border-amber-500/50 bg-amber-500/5'
                                            : '',
                                    ]"
                                    @click="setCheckoutDuration(opt.m)"
                                >
                                    <span
                                        v-if="opt.tag"
                                        class="text-[8px] font-black uppercase px-1 rounded-sm mb-0.5"
                                        :class="
                                            opt.isPopular
                                                ? checkoutDuration === opt.m
                                                    ? 'bg-amber-300 text-amber-950'
                                                    : 'bg-amber-500 text-white'
                                                : checkoutDuration === opt.m
                                                  ? 'bg-primary-foreground/20 text-primary-foreground'
                                                  : 'bg-emerald-500/10 text-emerald-700'
                                        "
                                    >
                                        {{ opt.tag }}
                                    </span>
                                    <span>{{ opt.label }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Invoice Breakdown Card -->
                        <div
                            class="p-3.5 bg-muted/40 border rounded-xl space-y-1.5 text-xs"
                        >
                            <div
                                class="flex items-center justify-between text-muted-foreground"
                            >
                                <span>Paket & Masa Aktif:</span>
                                <span class="font-bold text-foreground"
                                    >{{ checkoutApp.name }} ({{
                                        checkoutDuration
                                    }}
                                    Bulan)</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between text-muted-foreground"
                            >
                                <span>Harga Normal:</span>
                                <span
                                    :class="
                                        checkoutCalculation.discount_pct > 0
                                            ? 'line-through text-muted-foreground'
                                            : 'font-medium text-foreground'
                                    "
                                >
                                    {{
                                        formatRupiah(checkoutCalculation.normal)
                                    }}
                                </span>
                            </div>
                            <div
                                v-if="checkoutCalculation.discount_pct > 0"
                                class="flex items-center justify-between text-amber-600 font-bold"
                            >
                                <span
                                    >Diskon Durasi ({{
                                        checkoutCalculation.discount_pct
                                    }}%):</span
                                >
                                <span
                                    >-
                                    {{
                                        formatRupiah(
                                            checkoutCalculation.normal -
                                                checkoutCalculation.price,
                                        )
                                    }}</span
                                >
                            </div>
                            <div
                                v-if="checkoutCalculation.discountApplied > 0"
                                class="flex items-center justify-between text-emerald-600 font-bold"
                            >
                                <span>Potongan Saldo Referral:</span>
                                <span
                                    >-
                                    {{
                                        formatRupiah(
                                            checkoutCalculation.discountApplied,
                                        )
                                    }}</span
                                >
                            </div>
                            <div
                                class="border-t pt-2 flex items-center justify-between text-sm font-black"
                            >
                                <span class="text-foreground"
                                    >Total yang Harus Dibayar:</span
                                >
                                <span
                                    class="text-primary text-base tabular-nums font-mono"
                                >
                                    {{
                                        formatRupiah(
                                            checkoutCalculation.finalAmount,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Alert jika semua pembayaran dinonaktifkan -->
                        <div 
                            v-if="isAllPaymentsDisabled && checkoutCalculation.finalAmount > 0"
                            class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-2xl text-amber-900 dark:text-amber-200 space-y-2 text-xs"
                        >
                            <div class="flex items-center gap-2 font-bold text-sm text-amber-700 dark:text-amber-300">
                                <Wrench class="h-4 w-4 shrink-0 text-amber-600" />
                                <span>Layanan Pembayaran Sedang Dalam Perbaikan</span>
                            </div>
                            <p class="leading-relaxed text-[11px] text-muted-foreground">
                                Mohon maaf, saat ini seluruh metode pembayaran sedang dinonaktifkan sementara karena sedang ada perbaikan & pemeliharaan sistem. Anda belum dapat melanjutkan transaksi saat ini. Silakan coba kembali beberapa saat lagi.
                            </p>
                        </div>

                        <!-- Payment Method Selector -->
                        <div v-else-if="!isAllPaymentsDisabled && checkoutCalculation.finalAmount > 0" class="space-y-2">
                            <Label class="text-xs font-bold text-foreground"
                                >Pilih Metode Pembayaran:</Label
                            >
                            <div
                                class="grid grid-cols-1 sm:grid-cols-2 gap-2.5"
                            >
                                <!-- Option 1: Midtrans Gateway (if enabled) -->
                                <label
                                    v-if="paymentMethods.midtrans_enabled"
                                    class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer text-xs transition-all"
                                    :class="
                                        checkoutForm.payment_method ===
                                        'midtrans'
                                            ? 'border-indigo-500 bg-indigo-500/10 font-bold text-indigo-950 dark:text-indigo-200 shadow-xs'
                                            : 'border-border text-muted-foreground hover:bg-muted/50'
                                    "
                                >
                                    <input
                                        type="radio"
                                        value="midtrans"
                                        v-model="checkoutForm.payment_method"
                                        class="sr-only"
                                    />
                                    <div
                                        class="h-8 w-8 rounded-lg bg-indigo-500/20 text-indigo-600 flex items-center justify-center shrink-0"
                                    >
                                        <Sparkles class="h-4 w-4" />
                                    </div>
                                    <div class="flex-1">
                                        <div
                                            class="font-bold flex items-center gap-1"
                                        >
                                            <span>Midtrans Otomatis</span>
                                            <Badge
                                                variant="outline"
                                                class="text-[9px] py-0 px-1 bg-indigo-500/10 text-indigo-700"
                                                >Instan</Badge
                                            >
                                        </div>
                                        <div
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            GoPay, QRIS, Virtual Account, Kartu
                                        </div>
                                    </div>
                                </label>

                                <!-- Option 2: Transfer Bank Manual (if enabled) -->
                                <label
                                    v-if="
                                        paymentMethods.manual_enabled &&
                                        paymentMethods.bank_name
                                    "
                                    class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer text-xs transition-all"
                                    :class="
                                        checkoutForm.payment_method ===
                                        'manual_bank'
                                            ? 'border-primary bg-primary/10 font-bold text-foreground shadow-xs'
                                            : 'border-border text-muted-foreground hover:bg-muted/50'
                                    "
                                >
                                    <input
                                        type="radio"
                                        value="manual_bank"
                                        v-model="checkoutForm.payment_method"
                                        class="sr-only"
                                    />
                                    <div
                                        class="h-8 w-8 rounded-lg bg-primary/20 text-primary flex items-center justify-center shrink-0"
                                    >
                                        <Building2 class="h-4 w-4" />
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-bold">
                                            Transfer Bank Manual
                                        </div>
                                        <div
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            {{ paymentMethods.bank_name }}
                                        </div>
                                    </div>
                                </label>

                                <!-- Option 3: QRIS Manual (if enabled) -->
                                <label
                                    v-if="
                                        paymentMethods.manual_enabled &&
                                        (paymentMethods.qris_image_path ||
                                            paymentMethods.qris_merchant_name)
                                    "
                                    class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer text-xs transition-all"
                                    :class="
                                        checkoutForm.payment_method ===
                                        'manual_qris'
                                            ? 'border-primary bg-primary/10 font-bold text-foreground shadow-xs'
                                            : 'border-border text-muted-foreground hover:bg-muted/50'
                                    "
                                >
                                    <input
                                        type="radio"
                                        value="manual_qris"
                                        v-model="checkoutForm.payment_method"
                                        class="sr-only"
                                    />
                                    <div
                                        class="h-8 w-8 rounded-lg bg-emerald-500/20 text-emerald-600 flex items-center justify-center shrink-0"
                                    >
                                        <QrCode class="h-4 w-4" />
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-bold">
                                            Scan QRIS Manual
                                        </div>
                                        <div
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            E-Wallet & Mobile Banking
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Instructions Panel: Midtrans -->
                        <div
                            v-if="checkoutForm.payment_method === 'midtrans'"
                            class="p-3.5 bg-indigo-500/5 border border-indigo-500/20 rounded-xl space-y-2 text-xs"
                        >
                            <div
                                class="font-bold text-foreground flex items-center gap-1.5"
                            >
                                <Sparkles class="h-3.5 w-3.5 text-indigo-500" />
                                <span>Verifikasi Otomatis Real-Time</span>
                            </div>
                            <p
                                class="text-[11px] text-muted-foreground leading-relaxed"
                            >
                                Setelah menekan tombol
                                <strong>Bayar Sekarang</strong>, jendela
                                pembayaran Midtrans akan terbuka untuk memilih
                                metode transfer (BCA, Mandiri, BRI, BNI Virtual
                                Account, GoPay, ShopeePay, atau QRIS). Begitu
                                pembayaran selesai, paket Pro langsung aktif
                                otomatis tanpa perlu verifikasi admin.
                            </p>
                        </div>

                        <!-- Instructions Panel: Transfer Bank Manual -->
                        <div
                            v-else-if="
                                checkoutForm.payment_method === 'manual_bank'
                            "
                            class="p-3.5 bg-muted/40 border rounded-xl space-y-3 text-xs"
                        >
                            <div
                                class="font-bold text-foreground flex items-center gap-1.5"
                            >
                                <Building2 class="h-3.5 w-3.5 text-primary" />
                                <span>Rekening Tujuan Transfer:</span>
                            </div>
                            <div
                                class="p-3 bg-card border rounded-lg space-y-1.5"
                            >
                                <div class="text-xs text-muted-foreground">
                                    Bank:
                                    <strong class="text-foreground">{{
                                        paymentMethods.bank_name
                                    }}</strong>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div
                                        class="font-mono text-base font-black text-foreground"
                                    >
                                        {{ paymentMethods.bank_account_number }}
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-7 text-[11px] gap-1 px-2.5 cursor-pointer"
                                        @click="
                                            copyBankNumber(
                                                paymentMethods.bank_account_number,
                                            )
                                        "
                                    >
                                        <component
                                            :is="
                                                copiedBankNumber ? Check : Copy
                                            "
                                            class="h-3 w-3 text-emerald-600"
                                        />
                                        <span>{{
                                            copiedBankNumber
                                                ? "Tersalin"
                                                : "Salin Rekening"
                                        }}</span>
                                    </Button>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    Atas Nama:
                                    <strong class="text-foreground">{{
                                        paymentMethods.bank_account_holder
                                    }}</strong>
                                </div>
                            </div>

                            <!-- Upload Proof File -->
                            <div class="space-y-1.5 pt-1">
                                <Label class="text-xs font-semibold"
                                    >Unggah Bukti Transfer</Label
                                >
                                <div class="flex items-center gap-3">
                                    <div
                                        v-if="proofPreview"
                                        class="h-14 w-14 shrink-0 border rounded-lg overflow-hidden bg-muted/40 p-1 flex items-center justify-center"
                                    >
                                        <img
                                            :src="proofPreview"
                                            alt="Bukti Transfer"
                                            class="h-full w-full object-contain"
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <input
                                            type="file"
                                            id="transfer_proof_file"
                                            accept="image/*"
                                            @change="handleProofChange"
                                            class="block w-full text-xs text-muted-foreground file:mr-2.5 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer"
                                        />
                                    </div>
                                </div>
                                <p
                                    v-if="checkoutForm.errors.payment_proof"
                                    class="text-[11px] text-destructive font-medium"
                                >
                                    {{ checkoutForm.errors.payment_proof }}
                                </p>
                            </div>
                        </div>

                        <!-- Instructions Panel: QRIS Manual -->
                        <div
                            v-else-if="
                                checkoutForm.payment_method === 'manual_qris'
                            "
                            class="p-3.5 bg-muted/40 border rounded-xl space-y-3 text-xs"
                        >
                            <div
                                class="font-bold text-foreground flex items-center gap-1.5"
                            >
                                <QrCode class="h-3.5 w-3.5 text-primary" />
                                <span>Pindai Barcode QRIS:</span>
                            </div>

                            <div
                                class="flex flex-col items-center justify-center p-3 bg-card border rounded-lg space-y-2"
                            >
                                <img
                                    v-if="paymentMethods.qris_image_path"
                                    :src="paymentMethods.qris_image_path"
                                    alt="QRIS Barcode"
                                    class="h-44 w-auto object-contain rounded-lg shadow-2xs"
                                />
                                <div class="text-center">
                                    <div class="font-bold text-foreground">
                                        {{ paymentMethods.qris_merchant_name }}
                                    </div>
                                    <div
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Scan dengan m-Banking BCA, Mandiri, BRI,
                                        BNI atau GoPay, OVO, ShopeePay
                                    </div>
                                </div>
                            </div>

                            <!-- Upload Proof File -->
                            <div class="space-y-1.5 pt-1">
                                <Label class="text-xs font-semibold"
                                    >Unggah Bukti Pembayaran QRIS</Label
                                >
                                <div class="flex items-center gap-3">
                                    <div
                                        v-if="proofPreview"
                                        class="h-14 w-14 shrink-0 border rounded-lg overflow-hidden bg-muted/40 p-1 flex items-center justify-center"
                                    >
                                        <img
                                            :src="proofPreview"
                                            alt="Bukti Transfer"
                                            class="h-full w-full object-contain"
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <input
                                            type="file"
                                            id="qris_proof_file"
                                            accept="image/*"
                                            @change="handleProofChange"
                                            class="block w-full text-xs text-muted-foreground file:mr-2.5 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer"
                                        />
                                    </div>
                                </div>
                                <p
                                    v-if="checkoutForm.errors.payment_proof"
                                    class="text-[11px] text-destructive font-medium"
                                >
                                    {{ checkoutForm.errors.payment_proof }}
                                </p>
                            </div>
                        </div>

                        <!-- WhatsApp Confirmation & Support Link -->
                        <div
                            class="flex items-center justify-between text-xs pt-1"
                        >
                            <span class="text-muted-foreground text-[11px]"
                                >Butuh bantuan pembayaran?</span
                            >
                            <a
                                :href="getWhatsAppConfirmationUrl()"
                                target="_blank"
                                class="text-[11px] text-emerald-600 hover:underline font-semibold flex items-center gap-1"
                            >
                                <Phone class="h-3 w-3" />
                                <span>Hubungi CS WhatsApp</span>
                            </a>
                        </div>

                        <!-- Footer Actions -->
                        <div
                            class="flex items-center justify-end gap-2 pt-3 border-t"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="text-xs cursor-pointer"
                                @click="closeCheckoutModal"
                            >
                                Batal
                            </Button>
                            <Button
                                v-if="checkoutCalculation.finalAmount <= 0"
                                type="button"
                                size="sm"
                                class="text-xs font-bold gap-1.5 px-5 cursor-pointer bg-emerald-600 hover:bg-emerald-700 text-white"
                                :disabled="upgradeForm.processing"
                                @click="
                                    handleUpgrade(
                                        checkoutApp.module,
                                        checkoutDuration,
                                    )
                                "
                            >
                                <Gift class="h-3.5 w-3.5" />
                                <span>{{
                                    upgradeForm.processing
                                        ? "Mengaktifkan..."
                                        : "Aktivasi Gratis dengan Saldo Referral"
                                }}</span>
                            </Button>
                            <Button
                                v-else-if="isAllPaymentsDisabled"
                                type="button"
                                size="sm"
                                disabled
                                class="text-xs font-bold gap-1.5 px-5 bg-muted text-muted-foreground cursor-not-allowed opacity-75 shadow-xs"
                            >
                                <Wrench class="h-3.5 w-3.5 text-amber-500" />
                                <span>Tidak Bisa Lanjut (Sedang Ada Perbaikan)</span>
                            </Button>
                            <Button
                                v-else
                                type="submit"
                                size="sm"
                                class="text-xs font-bold gap-1.5 px-5 cursor-pointer"
                                :disabled="checkoutForm.processing"
                            >
                                <Sparkles
                                    v-if="
                                        checkoutForm.payment_method ===
                                        'midtrans'
                                    "
                                    class="h-3.5 w-3.5 text-amber-300"
                                />
                                <Check v-else class="h-3.5 w-3.5" />
                                <span>
                                    {{
                                        checkoutForm.processing
                                            ? "Memproses..."
                                            : checkoutForm.payment_method ===
                                                "midtrans"
                                              ? "Bayar Sekarang via Midtrans"
                                              : "Konfirmasi & Kirim Bukti"
                                    }}
                                </span>
                            </Button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL 1: NONAKTIFKAN APLIKASI (2 TAHAP VALIDASI) -->
            <div
                v-if="isDeactivateModalOpen && selectedAppToDeactivate"
                class="fixed inset-0 z-50 bg-background/80 backdrop-blur-xs flex items-center justify-center p-4 select-none"
                @click.self="closeDeactivateModal"
            >
                <div
                    class="bg-card border shadow-2xl rounded-2xl max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150"
                >
                    <!-- Modal Header -->
                    <div
                        class="flex items-center justify-between border-b pb-3"
                    >
                        <div class="flex items-center gap-2 text-destructive">
                            <AlertTriangle class="h-5 w-5" />
                            <div>
                                <h3
                                    class="font-extrabold text-base text-foreground leading-tight"
                                >
                                    Nonaktifkan
                                    {{ selectedAppToDeactivate.name }}
                                </h3>
                                <span
                                    class="text-[11px] text-muted-foreground font-semibold"
                                >
                                    Tahap {{ deactivateStep }} dari 2 Konfirmasi
                                </span>
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 rounded-lg cursor-pointer"
                            @click="closeDeactivateModal"
                        >
                            <X class="w-4 h-4" />
                        </Button>
                    </div>

                    <!-- TAHAP 1: PERINGATAN KONSEKUENSI PENGHAPUSAN DATA -->
                    <div v-if="deactivateStep === 1" class="space-y-4">
                        <div
                            class="p-3.5 rounded-xl border border-destructive/20 bg-destructive/10 text-xs text-destructive space-y-2"
                        >
                            <span class="font-bold block"
                                >Peringatan: Seluruh data aplikasi ini akan
                                dihapus permanen!</span
                            >
                            <p
                                class="text-[11px] text-foreground/80 leading-relaxed"
                            >
                                Tindakan ini akan menghapus modul
                                {{ selectedAppToDeactivate.name }} dari akun
                                Anda beserta data berikut:
                            </p>
                            <ul
                                v-if="
                                    selectedAppToDeactivate.module ===
                                    'keuangan'
                                "
                                class="list-disc pl-4 space-y-1 text-[11px] text-foreground font-medium"
                            >
                                <li>
                                    Seluruh dompet kas dan buku kas mandiri.
                                </li>
                                <li>
                                    Seluruh riwayat mutasi pemasukan,
                                    pengeluaran, dan transfer.
                                </li>
                                <li>
                                    Seluruh catatan radar utang dan piutang.
                                </li>
                                <li>
                                    Seluruh relasi kolaborasi/berbagi dompet
                                    bersama.
                                </li>
                            </ul>
                            <ul
                                v-else
                                class="list-disc pl-4 space-y-1 text-[11px] text-foreground font-medium"
                            >
                                <li>
                                    Seluruh perangkat sesi WhatsApp yang
                                    terhubung.
                                </li>
                                <li>
                                    Seluruh buku kontak dan grup kontak
                                    pelanggan.
                                </li>
                                <li>Seluruh template pesan siap kirim.</li>
                            </ul>
                        </div>

                        <div
                            class="flex items-center justify-end gap-2 pt-2 border-t"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="text-xs cursor-pointer"
                                @click="closeDeactivateModal"
                            >
                                Batal
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                class="text-xs font-bold gap-1 cursor-pointer"
                                @click="proceedToDeactivateStep2"
                            >
                                <span>Saya Paham, Lanjut ke Tahap 2</span>
                                <span>→</span>
                            </Button>
                        </div>
                    </div>

                    <!-- TAHAP 2: KETIK TEKS KONFIRMASI -->
                    <div v-else class="space-y-4">
                        <div class="space-y-2 text-xs">
                            <p class="text-muted-foreground leading-relaxed">
                                Untuk mengonfirmasi penghapusan seluruh data dan
                                penonaktifan modul, harap ketik:
                            </p>
                            <div
                                class="p-2 rounded-lg bg-muted text-center font-mono font-black text-sm text-destructive tracking-wider border select-all"
                            >
                                HAPUS
                                {{
                                    selectedAppToDeactivate.module.toUpperCase()
                                }}
                            </div>
                            <div class="space-y-1.5 pt-1">
                                <Label class="text-xs font-semibold"
                                    >Ketik teks konfirmasi di sini:</Label
                                >
                                <Input
                                    v-model="deactivateForm.confirmation_text"
                                    type="text"
                                    :placeholder="
                                        'HAPUS ' +
                                        selectedAppToDeactivate.module.toUpperCase()
                                    "
                                    class="text-xs font-bold uppercase tracking-wider"
                                    autofocus
                                />
                                <span
                                    v-if="
                                        deactivateForm.errors.confirmation_text
                                    "
                                    class="text-[11px] text-destructive block"
                                >
                                    {{
                                        deactivateForm.errors.confirmation_text
                                    }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between gap-2 pt-3 border-t"
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-xs text-muted-foreground cursor-pointer"
                                @click="deactivateStep = 1"
                            >
                                ← Kembali
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                class="text-xs font-bold gap-1.5 cursor-pointer"
                                :disabled="
                                    deactivateForm.confirmation_text
                                        .trim()
                                        .toUpperCase() !==
                                        'HAPUS ' +
                                            selectedAppToDeactivate.module.toUpperCase() ||
                                    deactivateForm.processing
                                "
                                @click="submitDeactivateModule"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                                <span>{{
                                    deactivateForm.processing
                                        ? "Menghapus Data..."
                                        : "Ya, Hapus Data & Nonaktifkan"
                                }}</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL 2: HAPUS AKUN PERMANEN (2 TAHAP VALIDASI) -->
            <div
                v-if="isDeleteAccountModalOpen"
                class="fixed inset-0 z-50 bg-background/80 backdrop-blur-xs flex items-center justify-center p-4 select-none"
                @click.self="closeDeleteAccountModal"
            >
                <div
                    class="bg-card border shadow-2xl rounded-2xl max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150"
                >
                    <!-- Modal Header -->
                    <div
                        class="flex items-center justify-between border-b pb-3"
                    >
                        <div class="flex items-center gap-2 text-destructive">
                            <Trash2 class="h-5 w-5" />
                            <div>
                                <h3
                                    class="font-extrabold text-base text-foreground leading-tight"
                                >
                                    Hapus Akun Secara Permanen
                                </h3>
                                <span
                                    class="text-[11px] text-muted-foreground font-semibold"
                                >
                                    Tahap {{ deleteAccountStep }} dari 2
                                    Konfirmasi Keamanan
                                </span>
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 rounded-lg cursor-pointer"
                            @click="closeDeleteAccountModal"
                        >
                            <X class="w-4 h-4" />
                        </Button>
                    </div>

                    <!-- TAHAP 1: PERINGATAN KRITIS PENUTUPAN AKUN TOTAL -->
                    <div v-if="deleteAccountStep === 1" class="space-y-4">
                        <div
                            class="p-3.5 rounded-xl border border-destructive/20 bg-destructive/10 text-xs text-destructive space-y-2"
                        >
                            <span class="font-bold block"
                                >Peringatan Terakhir: Akun dan Seluruh Data Akan
                                Musnah!</span
                            >
                            <p
                                class="text-[11px] text-foreground/80 leading-relaxed"
                            >
                                Tindakan ini bersifat permanen dan tidak dapat
                                dibatalkan. Menghapus akun Anda (<strong
                                    >@{{ user.username }}</strong
                                >) akan berakibat:
                            </p>
                            <ul
                                class="list-disc pl-4 space-y-1 text-[11px] text-foreground font-medium"
                            >
                                <li>
                                    Seluruh data di Sirkelta Keuangan dan
                                    Sirkelta WaSender terhapus bersih.
                                </li>
                                <li>
                                    Seluruh sisa saldo reward diskon referral
                                    akan hangus.
                                </li>
                                <li>
                                    Seluruh riwayat transaksi pembayaran dan
                                    langganan aktif akan dihapus.
                                </li>
                                <li>
                                    Semua tiket aduan bantuan dan pesan chat
                                    akan dibersihkan.
                                </li>
                                <li>
                                    Anda akan langsung dikeluarkan dan tidak
                                    bisa login kembali.
                                </li>
                            </ul>
                        </div>

                        <div
                            class="flex items-center justify-end gap-2 pt-2 border-t"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="text-xs cursor-pointer"
                                @click="closeDeleteAccountModal"
                            >
                                Batal
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                class="text-xs font-bold gap-1 cursor-pointer"
                                @click="proceedToDeleteAccountStep2"
                            >
                                <span>Saya Paham, Lanjut ke Verifikasi</span>
                                <span>→</span>
                            </Button>
                        </div>
                    </div>

                    <!-- TAHAP 2: VERIFIKASI KATA SANDI & TEKS KONFIRMASI -->
                    <div v-else class="space-y-4">
                        <div class="space-y-3 text-xs">
                            <p class="text-muted-foreground leading-relaxed">
                                Untuk memastikan bahwa ini adalah tindakan yang
                                Anda inginkan, masukkan kata sandi Anda dan
                                ketik teks konfirmasi.
                            </p>

                            <!-- Password Input -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold"
                                    >Kata Sandi Saat Ini</Label
                                >
                                <div class="relative">
                                    <Input
                                        v-model="
                                            deleteAccountForm.current_password
                                        "
                                        :type="
                                            showDeleteAccountPassword
                                                ? 'text'
                                                : 'password'
                                        "
                                        placeholder="Masukkan kata sandi akun Anda"
                                        class="pr-9 text-xs"
                                        autofocus
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-2.5 top-2.5 text-muted-foreground hover:text-foreground cursor-pointer"
                                        @click="
                                            showDeleteAccountPassword =
                                                !showDeleteAccountPassword
                                        "
                                    >
                                        <EyeOff
                                            v-if="showDeleteAccountPassword"
                                            class="w-3.5 h-3.5"
                                        />
                                        <Eye v-else class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                                <span
                                    v-if="
                                        deleteAccountForm.errors
                                            .current_password
                                    "
                                    class="text-[11px] text-destructive block"
                                >
                                    {{
                                        deleteAccountForm.errors
                                            .current_password
                                    }}
                                </span>
                            </div>

                            <!-- Confirmation Text Input -->
                            <div class="space-y-1.5 pt-1">
                                <Label class="text-xs font-semibold">
                                    Ketik
                                    <strong class="text-destructive font-mono"
                                        >HAPUS AKUN SAYA</strong
                                    >
                                    di bawah ini:
                                </Label>
                                <Input
                                    v-model="
                                        deleteAccountForm.confirmation_text
                                    "
                                    type="text"
                                    placeholder="HAPUS AKUN SAYA"
                                    class="text-xs font-bold uppercase tracking-wider"
                                />
                                <span
                                    v-if="
                                        deleteAccountForm.errors
                                            .confirmation_text
                                    "
                                    class="text-[11px] text-destructive block"
                                >
                                    {{
                                        deleteAccountForm.errors
                                            .confirmation_text
                                    }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between gap-2 pt-3 border-t"
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-xs text-muted-foreground cursor-pointer"
                                @click="deleteAccountStep = 1"
                            >
                                ← Kembali
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                class="text-xs font-bold gap-1.5 cursor-pointer"
                                :disabled="
                                    deleteAccountForm.confirmation_text
                                        .trim()
                                        .toUpperCase() !== 'HAPUS AKUN SAYA' ||
                                    !deleteAccountForm.current_password ||
                                    deleteAccountForm.processing
                                "
                                @click="submitDeleteAccount"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                                <span>{{
                                    deleteAccountForm.processing
                                        ? "Menghapus Akun..."
                                        : "Hapus Akun Saya Selamanya"
                                }}</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
