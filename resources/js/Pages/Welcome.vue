<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    Wallet, 
    Send, 
    ArrowRight, 
    Sparkles,
    CheckCircle2
} from 'lucide-vue-next';
import SupportChatBubble from '@/Components/SupportChatBubble.vue';
import * as THREE from 'three';

defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
    canRegister: {
        type: Boolean,
        default: true,
    },
    auth: {
        type: Object,
        default: () => ({ user: null }),
    },
});

const canvasContainer = ref(null);
let scene, camera, renderer, animationFrameId;
let particles, particlePositions, particleInitialPositions;
let mouseX = 0, mouseY = 0;
let targetX = 0, targetY = 0;

// Dimensions of the 3D wave plane
const SEPARATION = 1.3;
const AMOUNTX = 65;
const AMOUNTY = 65;
const TOTAL_PARTICLES = AMOUNTX * AMOUNTY;

const onMouseMove = (event) => {
    const halfWidth = window.innerWidth / 2;
    const halfHeight = window.innerHeight / 2;
    mouseX = (event.clientX - halfWidth) * 0.0008;
    mouseY = (event.clientY - halfHeight) * 0.0008;
};

const onWindowResize = () => {
    if (!renderer || !camera || !canvasContainer.value) return;
    const width = canvasContainer.value.clientWidth;
    const height = canvasContainer.value.clientHeight;
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height, false);
};

onMounted(() => {
    if (!canvasContainer.value) return;

    const width = canvasContainer.value.clientWidth;
    const height = canvasContainer.value.clientHeight;

    // 1. Scene & Perspective Camera
    scene = new THREE.Scene();
    camera = new THREE.PerspectiveCamera(55, width / height, 1, 1000);
    camera.position.set(0, 22, 38);
    camera.lookAt(0, -2, 0);

    // 2. High-Performance Antialiased Renderer
    renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true, powerPreference: 'high-performance' });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.domElement.style.position = 'absolute';
    renderer.domElement.style.top = '0';
    renderer.domElement.style.left = '0';
    renderer.domElement.style.width = '100%';
    renderer.domElement.style.height = '100%';
    renderer.domElement.style.display = 'block';
    renderer.domElement.style.pointerEvents = 'none';
    canvasContainer.value.appendChild(renderer.domElement);

    // 3. Elegant Wave Geometry (Structured, Clean Particle Grid)
    const positions = new Float32Array(TOTAL_PARTICLES * 3);
    const scales = new Float32Array(TOTAL_PARTICLES);
    const colors = new Float32Array(TOTAL_PARTICLES * 3);

    particlePositions = positions;
    particleInitialPositions = new Float32Array(TOTAL_PARTICLES * 3);

    const baseColor = new THREE.Color(0x0265c2); // Sirkelta Royal Blue
    const highlightColor = new THREE.Color(0x38bdf8); // Sky Blue Glow

    let i = 0, j = 0;
    for (let ix = 0; ix < AMOUNTX; ix++) {
        for (let iy = 0; iy < AMOUNTY; iy++) {
            const posX = (ix * SEPARATION) - ((AMOUNTX * SEPARATION) / 2);
            const posZ = (iy * SEPARATION) - ((AMOUNTY * SEPARATION) / 2);
            const posY = 0;

            positions[i] = posX;
            positions[i + 1] = posY;
            positions[i + 2] = posZ;

            particleInitialPositions[i] = posX;
            particleInitialPositions[i + 1] = posY;
            particleInitialPositions[i + 2] = posZ;

            // Distance ratio from center for radial gradient
            const distFromCenter = Math.sqrt(posX * posX + posZ * posZ) / 45;
            const mixed = baseColor.clone().lerp(highlightColor, Math.max(0, 1 - distFromCenter));

            colors[i] = mixed.r;
            colors[i + 1] = mixed.g;
            colors[i + 2] = mixed.b;

            scales[j] = Math.max(0.6, 1.4 - distFromCenter * 0.7);

            i += 3;
            j++;
        }
    }

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));

    // Circle particle texture for smooth round dots
    const canvas = document.createElement('canvas');
    canvas.width = 64;
    canvas.height = 64;
    const ctx = canvas.getContext('2d');
    const gradient = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
    gradient.addColorStop(0, 'rgba(255, 255, 255, 1)');
    gradient.addColorStop(0.4, 'rgba(255, 255, 255, 0.85)');
    gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, 64, 64);
    const texture = new THREE.CanvasTexture(canvas);

    const material = new THREE.PointsMaterial({
        size: 0.65,
        map: texture,
        vertexColors: true,
        transparent: true,
        opacity: 0.85,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
    });

    particles = new THREE.Points(geometry, material);
    particles.position.y = -6; // Position gracefully at the bottom half
    scene.add(particles);

    // Event Listeners
    window.addEventListener('resize', onWindowResize);
    window.addEventListener('mousemove', onMouseMove);

    // 4. Smooth Animation Loop
    let count = 0;
    const animate = () => {
        animationFrameId = requestAnimationFrame(animate);
        count += 0.025;

        // Smooth camera drift based on mouse
        targetX += (mouseX - targetX) * 0.04;
        targetY += (mouseY - targetY) * 0.04;

        camera.position.x = targetX * 18;
        camera.position.y = 22 + (targetY * -8);
        camera.lookAt(0, -3, 0);

        // Undulating silky wave calculation
        let i = 0;
        for (let ix = 0; ix < AMOUNTX; ix++) {
            for (let iy = 0; iy < AMOUNTY; iy++) {
                // Harmonic fluid waves
                const wave1 = Math.sin((ix * 0.22) + count) * 1.5;
                const wave2 = Math.cos((iy * 0.22) + count * 0.8) * 1.5;
                const wave3 = Math.sin((ix * 0.1 + iy * 0.1) + count * 0.5) * 0.8;

                particlePositions[i + 1] = wave1 + wave2 + wave3;
                i += 3;
            }
        }

        particles.geometry.attributes.position.needsUpdate = true;
        renderer.render(scene, camera);
    };

    animate();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onWindowResize);
    window.removeEventListener('mousemove', onMouseMove);
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
    }
    if (renderer) {
        renderer.dispose();
    }
});
</script>

<template>
    <Head title="Sirkelta - Ekosistem Aplikasi Terpadu" />

    <div class="min-h-screen min-h-[100dvh] bg-background text-foreground flex flex-col relative overflow-x-hidden select-none">
        <!-- 3D Three.js Fluid Digital Waves Background -->
        <div 
            ref="canvasContainer" 
            class="absolute inset-0 z-0 pointer-events-none overflow-hidden opacity-70 dark:opacity-85 [mask-image:radial-gradient(ellipse_80%_65%_at_50%_60%,#000_40%,transparent_100%)]"
        ></div>

        <!-- Soft Ambient Accent Glow -->
        <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-full max-w-[700px] h-[450px] bg-primary/10 rounded-full blur-[120px] pointer-events-none"></div>

        <!-- Sticky Clean Navbar with Safe Area -->
        <header class="relative z-20 w-full border-b bg-background/80 backdrop-blur-md pt-safe">
            <div class="container mx-auto flex h-16 items-center justify-between px-4 sm:px-8 max-w-6xl">
                <!-- Brand -->
                <Link :href="route('home')" class="flex items-center gap-3 hover:opacity-90 transition-opacity">
                    <img src="/images/logo.png" alt="Sirkelta Logo" class="h-9 w-9 rounded-xl object-contain shadow-xs" />
                    <span class="text-xl font-bold tracking-tight">Sirkelta</span>
                </Link>

                <!-- Navigation Actions -->
                <div class="flex items-center gap-2.5">
                    <template v-if="auth?.user">
                        <Link :href="route('portal')">
                            <Button class="gap-2 font-semibold shadow-xs">
                                <span>Buka Portal</span>
                                <ArrowRight class="h-4 w-4" />
                            </Button>
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')">
                            <Button variant="ghost" class="text-sm font-semibold">
                                Masuk
                            </Button>
                        </Link>
                        <Link :href="route('register')">
                            <Button class="text-sm font-semibold shadow-xs">
                                Daftar
                            </Button>
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Main Hero Section (Crisp, High Impact, No Clutter) -->
        <main class="relative z-10 flex-1 flex flex-col justify-center items-center px-4 sm:px-8 py-16 sm:py-24 text-center max-w-4xl mx-auto">
            <!-- Badge -->
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border bg-card/80 backdrop-blur-md text-xs font-semibold text-foreground mb-6 shadow-2xs">
                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Satu Akun, Seluruh Ruang Kerja</span>
            </div>

            <!-- Main Headline -->
            <h1 class="text-4xl sm:text-6xl md:text-7xl font-extrabold tracking-tight mb-4 leading-[1.12]">
                Satu Portal, <br />
                <span class="bg-gradient-to-r from-primary via-blue-500 to-sky-400 bg-clip-text text-transparent">
                    Seluruh Solusi Bisnis.
                </span>
            </h1>

            <!-- Subhead -->
            <p class="text-sm sm:text-base md:text-lg text-muted-foreground mb-10 max-w-lg leading-relaxed">
                Kelola mutasi keuangan dan automasi WhatsApp dalam satu ekosistem terpadu.
            </p>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row items-center gap-3 mb-14 w-full sm:w-auto">
                <template v-if="auth?.user">
                    <Link :href="route('portal')" class="w-full sm:w-auto">
                        <Button size="lg" class="w-full sm:w-auto h-12 px-8 font-bold gap-2 shadow-sm text-base">
                            <span>Masuk ke Portal Aplikasi</span>
                            <ArrowRight class="h-4 w-4" />
                        </Button>
                    </Link>
                </template>
                <template v-else>
                    <Link :href="route('login')" class="w-full sm:w-auto">
                        <Button size="lg" class="w-full sm:w-auto h-12 px-8 font-bold gap-2 shadow-sm text-base">
                            <span>Mulai Sekarang</span>
                            <ArrowRight class="h-4 w-4" />
                        </Button>
                    </Link>
                    <Link :href="route('register')" class="w-full sm:w-auto">
                        <Button size="lg" variant="outline" class="w-full sm:w-auto h-12 px-6 font-semibold bg-background/60 backdrop-blur-md">
                            Daftar Akun Baru
                        </Button>
                    </Link>
                </template>
            </div>

            <!-- Quick Clean Application Cards (Minimalist & Direct) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full max-w-xl text-left">
                <!-- Card Keuangan -->
                <Link 
                    :href="auth?.user ? route('keuangan.dashboard') : route('login')"
                    class="group p-5 rounded-2xl border bg-card/85 backdrop-blur-md hover:border-primary/50 hover:bg-card transition-all duration-300 shadow-2xs flex flex-col justify-between"
                >
                    <div class="flex items-center justify-between mb-3">
                        <div class="h-10 w-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                            <Wallet class="h-5 w-5" />
                        </div>
                        <Badge variant="secondary" class="text-[10px] bg-primary/10 text-primary font-bold">
                            Keuangan
                        </Badge>
                    </div>
                    <div>
                        <h3 class="font-bold text-base group-hover:text-primary transition-colors">Sirkelta Keuangan</h3>
                        <p class="text-xs text-muted-foreground mt-1">Multi-dompet kas, mutasi transaksi, dan laporan laba rugi.</p>
                    </div>
                    <div class="mt-4 pt-3 border-t flex items-center justify-between text-xs font-semibold text-primary">
                        <span>Buka Aplikasi</span>
                        <ArrowRight class="h-3.5 w-3.5 group-hover:translate-x-1 transition-transform" />
                    </div>
                </Link>

                <!-- Card WaSender -->
                <Link 
                    :href="auth?.user ? route('wasender.dashboard') : route('login')"
                    class="group p-5 rounded-2xl border bg-card/85 backdrop-blur-md hover:border-emerald-500/50 hover:bg-card transition-all duration-300 shadow-2xs flex flex-col justify-between"
                >
                    <div class="flex items-center justify-between mb-3">
                        <div class="h-10 w-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                            <Send class="h-5 w-5" />
                        </div>
                        <Badge variant="secondary" class="text-[10px] bg-emerald-500/10 text-emerald-600 font-bold">
                            WA Sender
                        </Badge>
                    </div>
                    <div>
                        <h3 class="font-bold text-base group-hover:text-emerald-600 transition-colors">Sirkelta WaSender</h3>
                        <p class="text-xs text-muted-foreground mt-1">Broadcast WhatsApp terarah, buku kontak, dan template pesan.</p>
                    </div>
                    <div class="mt-4 pt-3 border-t flex items-center justify-between text-xs font-semibold text-emerald-600">
                        <span>Buka Aplikasi</span>
                        <ArrowRight class="h-3.5 w-3.5 group-hover:translate-x-1 transition-transform" />
                    </div>
                </Link>
            </div>
        </main>

        <!-- Footer with Safe Area -->
        <footer class="relative z-10 py-8 pb-[calc(2rem+env(safe-area-inset-bottom,0px))] text-center text-xs text-muted-foreground border-t bg-background/80 backdrop-blur-md">
            <div class="max-w-6xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <img src="/images/logo.png" alt="Logo" class="h-5 w-5 rounded-sm object-contain" />
                    <span>&copy; {{ new Date().getFullYear() }} Sirkelta. All rights reserved.</span>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs font-medium">
                    <Link :href="route('about')" class="hover:text-foreground transition-colors">Tentang Kami</Link>
                    <Link :href="route('faq')" class="hover:text-foreground transition-colors">FAQ</Link>
                    <Link :href="route('contact')" class="hover:text-foreground transition-colors">Hubungi Kami</Link>
                    <Link :href="route('privacy')" class="hover:text-foreground transition-colors">Kebijakan Privasi</Link>
                    <Link :href="route('terms')" class="hover:text-foreground transition-colors">Syarat & Ketentuan</Link>
                    <Link :href="route('refund')" class="hover:text-foreground transition-colors">Pengembalian Dana</Link>
                </div>
            </div>
        </footer>

        <!-- Floating Live Support Chat Bubble -->
        <SupportChatBubble />
    </div>
</template>
