<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

interface Service {
    id: number;
    name: string;
    prefix: string;
    color: string | null;
    is_active: boolean;
    sort_order: number;
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Layanan', href: '/admin/services' },
        ],
    },
});

defineProps<{
    services: Service[];
}>();

const page = usePage();
const errors = computed(() => page.props.errors);

const createForm = reactive({
    name: '',
    prefix: '',
    color: '#2563eb',
    is_active: true,
    sort_order: 0,
});

function submitCreate() {
    router.post('/admin/services', { ...createForm }, {
        preserveScroll: true,
        onSuccess: () => {
            createForm.name = '';
            createForm.prefix = '';
            createForm.color = '#2563eb';
            createForm.is_active = true;
            createForm.sort_order = 0;
        },
    });
}

const editOpen = ref(false);
const editForm = reactive({
    id: 0,
    name: '',
    prefix: '',
    color: '#2563eb',
    is_active: true,
    sort_order: 0,
});

function openEdit(service: Service) {
    editForm.id = service.id;
    editForm.name = service.name;
    editForm.prefix = service.prefix;
    editForm.color = service.color ?? '#2563eb';
    editForm.is_active = service.is_active;
    editForm.sort_order = service.sort_order;
    editOpen.value = true;
}

function submitEdit() {
    router.patch(
        `/admin/services/${editForm.id}`,
        {
            name: editForm.name,
            prefix: editForm.prefix,
            color: editForm.color,
            is_active: editForm.is_active,
            sort_order: editForm.sort_order,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                editOpen.value = false;
            },
        },
    );
}

const deleteOpen = ref(false);
const deletingService = ref<Service | null>(null);

function confirmDelete(service: Service) {
    deletingService.value = service;
    deleteOpen.value = true;
}

function submitDelete() {
    if (!deletingService.value) {
        return;
    }

    router.delete(`/admin/services/${deletingService.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteOpen.value = false;
            deletingService.value = null;
        },
    });
}
</script>

<template>
    <Head title="Layanan" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Card>
            <CardHeader>
                <CardTitle>Tambah Layanan</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="submitCreate">
                    <div class="grid gap-2">
                        <Label for="create-name">Nama</Label>
                        <Input id="create-name" v-model="createForm.name" placeholder="Contoh: KTP" />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-prefix">Prefix</Label>
                        <Input id="create-prefix" v-model="createForm.prefix" maxlength="3" placeholder="A" />
                        <InputError :message="errors.prefix" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-color">Warna</Label>
                        <input id="create-color" v-model="createForm.color" type="color" class="h-9 w-full rounded-md border border-input" />
                        <InputError :message="errors.color" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-sort">Urutan</Label>
                        <Input id="create-sort" v-model.number="createForm.sort_order" type="number" />
                    </div>
                    <div class="flex items-end gap-2">
                        <Label class="flex items-center gap-2">
                            <Checkbox v-model="createForm.is_active" />
                            Aktif
                        </Label>
                        <Button type="submit">Tambah</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Daftar Layanan</CardTitle>
            </CardHeader>
            <CardContent>
                <p v-if="services.length === 0" class="text-sm text-muted-foreground">
                    Belum ada layanan.
                </p>
                <table v-else class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-muted-foreground">
                            <th class="py-2 pr-4 font-medium">Nama</th>
                            <th class="py-2 pr-4 font-medium">Prefix</th>
                            <th class="py-2 pr-4 font-medium">Warna</th>
                            <th class="py-2 pr-4 font-medium">Status</th>
                            <th class="py-2 pr-4 font-medium">Urutan</th>
                            <th class="py-2 pr-4 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="service in services" :key="service.id" class="border-b last:border-0">
                            <td class="py-2 pr-4">{{ service.name }}</td>
                            <td class="py-2 pr-4">{{ service.prefix }}</td>
                            <td class="py-2 pr-4">
                                <span
                                    class="inline-block size-4 rounded-full border"
                                    :style="{ backgroundColor: service.color ?? 'transparent' }"
                                />
                            </td>
                            <td class="py-2 pr-4">
                                <Badge :variant="service.is_active ? 'default' : 'secondary'">
                                    {{ service.is_active ? 'Aktif' : 'Nonaktif' }}
                                </Badge>
                            </td>
                            <td class="py-2 pr-4">{{ service.sort_order }}</td>
                            <td class="flex gap-2 py-2 pr-4">
                                <Button size="sm" variant="outline" @click="openEdit(service)">Ubah</Button>
                                <Button size="sm" variant="destructive" @click="confirmDelete(service)">Hapus</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <Dialog v-model:open="editOpen">
            <DialogContent>
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <DialogHeader>
                        <DialogTitle>Ubah Layanan</DialogTitle>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="edit-name">Nama</Label>
                        <Input id="edit-name" v-model="editForm.name" />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-prefix">Prefix</Label>
                        <Input id="edit-prefix" v-model="editForm.prefix" maxlength="3" />
                        <InputError :message="errors.prefix" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-color">Warna</Label>
                        <input id="edit-color" v-model="editForm.color" type="color" class="h-9 w-full rounded-md border border-input" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-sort">Urutan</Label>
                        <Input id="edit-sort" v-model.number="editForm.sort_order" type="number" />
                    </div>
                    <Label class="flex items-center gap-2">
                        <Checkbox v-model="editForm.is_active" />
                        Aktif
                    </Label>

                    <DialogFooter>
                        <Button type="submit">Simpan</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hapus layanan "{{ deletingService?.name }}"?</DialogTitle>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button variant="secondary" @click="deleteOpen = false">Batal</Button>
                    <Button variant="destructive" @click="submitDelete">Hapus</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
