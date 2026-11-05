<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { 
    DropdownMenu, 
    DropdownMenuTrigger, 
    DropdownMenuContent, 
    DropdownMenuItem, 
    DropdownMenuLabel, 
    DropdownMenuSeparator 
} from '@/components/ui/dropdown-menu';
import { 
    Wallet, 
    Send, 
    LogOut, 
    ArrowRight, 
    Lock, 
    ChevronDown
} from 'lucide-vue-next';

const props = defineProps({
    apps: {
        type: Array,
        required: true,
    },
    user: {
        type: Object,
        required: true,
    },
});

const logout = () => {
    router.post(route('logout'));
};

const getInitials = (name) => {
    return name
        ?.split(' ')
        .map((n) => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase() || 'SK';
};
</script>

<template>
    <Head title="Pilih Aplikasi - Sirkelta" />

    <div class="min-h-screen bg-muted/20 text-foreground flex flex-col font-sans selection:bg-primary selection:text-primary-foreground">
        <!-- Top Navbar -->
        <header class="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="container mx-auto flex h-16 items-center justify-between px-4 sm:px-8 max-w-5xl">
                <!-- Brand -->
                <div class="flex items-center gap-3">
                    <img src="/images/logo.png" alt="Sirkelta Logo" class="h-9 w-9 rounded-xl shadow-xs object-contain" />
                    <span class="text-xl font-extrabold tracking-tight">Sirkelta</span>
                </div>

                <!-- User Account Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" class="flex items-center gap-2.5 h-10 px-2 sm:px-3 rounded-full hover:bg-muted">
                            <Avatar class="h-8 w-8 border">
                                <AvatarFallback class="bg-primary/10 text-primary font-bold text-xs">
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="hidden sm:flex flex-col text-left">
                                <span class="text-xs font-semibold leading-none">{{ user.name }}</span>
                                <span class="text-[10px] text-muted-foreground mt-0.5">{{ user.email }}</span>
                            </div>
                            <ChevronDown class="h-4 w-4 text-muted-foreground" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="w-56" align="end">
                        <DropdownMenuLabel>
                            <p class="text-xs font-semibold">{{ user.name }}</p>
                            <p class="text-[11px] text-muted-foreground font-normal">{{ user.email }}</p>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="logout" class="text-destructive cursor-pointer">
                            <LogOut class="h-4 w-4 mr-2" />
                            <span>Keluar</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>

        <!-- Main Body -->
        <main class="flex-1 flex flex-col justify-center py-12 sm:py-20">
            <div class="container mx-auto px-4 sm:px-8 max-w-4xl">
                <!-- Clean Page Header -->
                <div class="mb-8 text-center sm:text-left">
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                        Pilih Aplikasi
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Pilih ruang kerja yang ingin Anda buka.
                    </p>
                </div>

                <!-- Apps Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <Card 
                        v-for="app in apps" 
                        :key="app.id"
                        class="border shadow-xs hover:border-primary/50 transition-all flex flex-col justify-between"
                        :class="{ 'opacity-60 bg-muted/40': !app.has_access }"
                    >
                        <div>
                            <CardHeader class="pb-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div 
                                        class="h-11 w-11 rounded-xl flex items-center justify-center shrink-0"
                                        :class="app.id === 'keuangan' ? 'bg-primary/10 text-primary' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'"
                                    >
                                        <Wallet v-if="app.id === 'keuangan'" class="h-5 w-5" />
                                        <Send v-else class="h-5 w-5" />
                                    </div>
                                    <Badge 
                                        :variant="app.has_access ? 'secondary' : 'outline'"
                                        class="text-[11px] font-medium"
                                    >
                                        <span v-if="app.has_access">{{ app.role_label }}</span>
                                        <span v-else class="flex items-center gap-1">
                                            <Lock class="h-3 w-3" />
                                            Perlu Akses
                                        </span>
                                    </Badge>
                                </div>
                                <CardTitle class="text-xl font-bold">{{ app.name }}</CardTitle>
                                <CardDescription class="text-xs sm:text-sm mt-1 leading-relaxed">
                                    {{ app.description }}
                                </CardDescription>
                            </CardHeader>
                        </div>

                        <CardFooter class="pt-4 pb-5">
                            <template v-if="app.has_access">
                                <Link :href="app.route" class="w-full">
                                    <Button class="w-full gap-2 font-semibold shadow-xs">
                                        <span>Buka Aplikasi</span>
                                        <ArrowRight class="h-4 w-4" />
                                    </Button>
                                </Link>
                            </template>
                            <template v-else>
                                <Button disabled variant="outline" class="w-full gap-2 text-xs">
                                    <Lock class="h-3.5 w-3.5" />
                                    <span>Terkunci</span>
                                </Button>
                            </template>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </main>
    </div>
</template>
