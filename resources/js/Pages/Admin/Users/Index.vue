<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { 
    Users, 
    Search, 
    ShieldCheck, 
    AtSign, 
    Mail, 
    Gift, 
    Trash2, 
    Check, 
    X, 
    Key,
    Layers
} from 'lucide-vue-next';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', role: '' }),
    },
});

const page = usePage();
const search = ref(props.filters.search || '');
const selectedRoleFilter = ref(props.filters.role || '');

const handleFilter = () => {
    router.get(route('admin.users.index'), {
        search: search.value || undefined,
        role: selectedRoleFilter.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

// Role management modal
const activeUserModal = ref(null);
const roleForm = useForm({
    roles: [],
});

const openRoleModal = (user) => {
    roleForm.clearErrors();
    activeUserModal.value = user;
    roleForm.roles = user.roles?.map(r => r.name) || [];
};

const closeRoleModal = () => {
    roleForm.clearErrors();
    activeUserModal.value = null;
};

const toggleRole = (roleName) => {
    roleForm.clearErrors();
    const idx = roleForm.roles.indexOf(roleName);
    if (idx > -1) {
        roleForm.roles.splice(idx, 1);
    } else {
        roleForm.roles.push(roleName);
    }
};

const saveRoles = () => {
    if (!activeUserModal.value) return;
    roleForm.clearErrors();

    if (roleForm.roles.length === 0) {
        roleForm.setError('roles', 'Pilih minimal satu role akses untuk pengguna ini.');
        return;
    }

    roleForm.put(route('admin.users.roles', activeUserModal.value.id), {
        onSuccess: () => closeRoleModal(),
    });
};

const deleteUser = (user) => {
    if (confirm(`Apakah Anda yakin ingin menghapus akun ${user.name} (@${user.username})? Tindakan ini tidak dapat dibatalkan.`)) {
        router.delete(route('admin.users.destroy', user.id));
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};
</script>

<template>
    <DashboardLayout 
        title="Kelola Pengguna" 
        activeModule="admin" 
        :user="page.props.auth.user"
    >
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-foreground">Kelola Pengguna</h1>
                </div>
            </div>

            <!-- Filter & Search Bar -->
            <Card>
                <CardContent class="p-4">
                    <div class="flex flex-col sm:flex-row items-center gap-3">
                        <div class="relative flex-1 w-full">
                            <Search class="h-4 w-4 text-muted-foreground absolute left-3 top-3" />
                            <Input
                                v-model="search"
                                @keyup.enter="handleFilter"
                                placeholder="Cari berdasarkan nama, username, email, atau kode referral..."
                                class="pl-9 text-xs"
                            />
                        </div>

                        <select
                            v-model="selectedRoleFilter"
                            @change="handleFilter"
                            class="bg-card border rounded-xl px-3 py-2 text-xs font-medium focus:ring-1 focus:ring-primary w-full sm:w-48"
                        >
                            <option value="">Semua Role</option>
                            <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                        </select>

                        <Button size="sm" class="text-xs font-semibold shrink-0 w-full sm:w-auto" @click="handleFilter">
                            Filter
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Users Table -->
            <Card>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-muted/50 text-muted-foreground border-b font-semibold">
                                <tr>
                                    <th class="py-3 px-4">Nama & Email</th>
                                    <th class="py-3 px-4">Username</th>
                                    <th class="py-3 px-4">Role Akses</th>
                                    <th class="py-3 px-4">Referral</th>
                                    <th class="py-3 px-4">Bergabung</th>
                                    <th class="py-3 px-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="u in users.data" :key="u.id" class="hover:bg-muted/30">
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-foreground">{{ u.name }}</div>
                                        <div class="text-[11px] text-muted-foreground flex items-center gap-1 mt-0.5">
                                            <Mail class="h-3 w-3" />
                                            <span>{{ u.email }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-foreground">
                                        <span class="px-2 py-0.5 rounded-md bg-muted text-[11px]">@{{ u.username || '-' }}</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex flex-wrap gap-1">
                                            <Badge 
                                                v-for="r in u.roles" 
                                                :key="r.name"
                                                variant="secondary"
                                                class="text-[10px] font-semibold"
                                                :class="{
                                                    'bg-purple-500/10 text-purple-700': r.name === 'superadmin',
                                                    'bg-emerald-500/10 text-emerald-700': r.name.includes('wasender'),
                                                    'bg-primary/10 text-primary': r.name.includes('keuangan'),
                                                    'bg-amber-500/10 text-amber-700': r.name === 'admin_web',
                                                }"
                                            >
                                                {{ r.name }}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="text-xs text-primary font-bold tracking-wide">{{ u.referral_code || '-' }}</div>
                                        <div class="text-[10px] text-muted-foreground">{{ u.referees_count || 0 }} teman diundang</div>
                                    </td>
                                    <td class="py-3 px-4 text-muted-foreground">
                                        {{ formatDate(u.created_at) }}
                                    </td>
                                    <td class="py-3 px-4 text-right space-x-1">
                                        <Button 
                                            variant="outline" 
                                            size="sm" 
                                            class="h-7 text-[11px] px-2.5 font-semibold"
                                            @click="openRoleModal(u)"
                                        >
                                            <Key class="h-3 w-3 mr-1" />
                                            Role
                                        </Button>
                                        <Button 
                                            v-if="u.id !== page.props.auth.user.id"
                                            variant="ghost" 
                                            size="sm" 
                                            class="h-7 text-[11px] px-2 text-destructive hover:bg-destructive/10"
                                            @click="deleteUser(u)"
                                            title="Hapus Pengguna"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="users.data.length === 0">
                                    <td colspan="6" class="py-8 text-center text-muted-foreground">
                                        Tidak ada pengguna yang sesuai dengan kriteria pencarian.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Links -->
                    <div v-if="users.links && users.links.length > 3" class="p-4 border-t flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">
                            Menampilkan {{ users.from }} - {{ users.to }} dari {{ users.total }} pengguna
                        </span>
                        <div class="flex gap-1">
                            <Link
                                v-for="link in users.links"
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

            <!-- Role Edit Modal -->
            <div 
                v-if="activeUserModal" 
                class="fixed inset-0 z-50 bg-background/80 backdrop-blur-xs flex items-center justify-center p-4"
            >
                <div class="bg-card border shadow-xl rounded-2xl max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                    <div class="flex items-center justify-between border-b pb-3">
                        <div>
                            <h3 class="font-bold text-base">Ubah Role Pengguna</h3>
                            <p class="text-xs text-muted-foreground">{{ activeUserModal.name }} (@{{ activeUserModal.username }})</p>
                        </div>
                        <Button variant="ghost" size="icon" class="h-7 w-7" @click="closeRoleModal">
                            <X class="h-4 w-4" />
                        </Button>
                    </div>

                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-muted-foreground">Pilih role yang diberikan ke pengguna ini:</p>
                        <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                            <div 
                                v-for="r in roles" 
                                :key="r"
                                @click="toggleRole(r)"
                                class="flex items-center justify-between p-2.5 rounded-xl border cursor-pointer transition-all text-xs"
                                :class="roleForm.roles.includes(r) ? 'border-primary bg-primary/5 font-bold text-foreground' : 'border-border text-muted-foreground hover:bg-muted/40'"
                            >
                                <span>{{ r }}</span>
                                <Check v-if="roleForm.roles.includes(r)" class="h-4 w-4 text-primary" />
                            </div>
                        </div>
                        <p v-if="roleForm.errors.roles" class="text-[11px] text-destructive font-medium">
                            {{ roleForm.errors.roles }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t">
                        <Button variant="outline" size="sm" class="text-xs" @click="closeRoleModal">Batal</Button>
                        <Button size="sm" class="text-xs font-bold" :disabled="roleForm.processing" @click="saveRoles">
                            Simpan Perubahan
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

