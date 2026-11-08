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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';

interface ServiceOption {
    id: number;
    name: string;
}

interface Loket {
    id: number;
    service_id: number;
    name: string;
    officer_name: string | null;
    is_active: boolean;
    sort_order: number;
    service: ServiceOption & { color: string | null };
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Loket', href: '/admin/lokets' },
        ],
    },
});

defineProps<{
    lokets: Loket[];
    services: ServiceOption[];
}>();

const page = usePage();
const errors = computed(() => page.props.errors);

const createForm = reactive({
    service_id: '',
    name: '',
    officer_name: '',
    is_active: true,
    sort_order: 0,
});

function submitCreate() {
    if (!createForm.service_id) {
        return;
    }

    router.post(
        '/admin/lokets',
        { ...createForm, service_id: Number(createForm.service_id) },
        {
            preserveScroll: true,
            onSuccess: () => {
                createForm.service_id = '';
                createForm.name = '';
                createForm.officer_name = '';
                createForm.is_active = true;
                createForm.sort_order = 0;
            },
        },
    );
}

const editOpen = ref(false);
const editForm = reactive({
    id: 0,
    service_id: '',
    name: '',
    officer_name: '',
    is_active: true,
    sort_order: 0,
});

function openEdit(loket: Loket) {
    editForm.id = loket.id;
    editForm.service_id = String(loket.service_id);
    editForm.name = loket.name;
    editForm.officer_name = loket.officer_name ?? '';
    editForm.is_active = loket.is_active;
    editForm.sort_order = loket.sort_order;
    editOpen.value = true;
}

function submitEdit() {
    router.patch(
        `/admin/lokets/${editForm.id}`,
        {
            service_id: Number(editForm.service_id),
            name: editForm.name,
            officer_name: editForm.officer_name,
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
const deletingLoket = ref<Loket | null>(null);

function confirmDelete(loket: Loket) {
    deletingLoket.value = loket;
    deleteOpen.value = true;
}

function submitDelete() {
    if (!deletingLoket.value) {
        return;
    }

    router.delete(`/admin/lokets/${deletingLoket.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteOpen.value = false;
            deletingLoket.value = null;
        },
    });
}
</script>

<template>
    <Head title="Loket" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Card>
            <CardHeader>
                <CardTitle>Tambah Loket</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="submitCreate">
                    <div class="grid gap-2">
                        <Label for="create-service">Layanan</Label>
                        <Select v-model="createForm.service_id">
                            <SelectTrigger id="create-service" class="w-full">
                                <SelectValue placeholder="Pilih layanan..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="service in services"
                                    :key="service.id"
                                    :value="String(service.id)"
                                >
                                    {{ service.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.service_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-name">Nama Loket</Label>
                        <Input id="create-name" v-model="createForm.name" placeholder="Loket 1" />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-officer">Petugas</Label>
                        <Input id="create-officer" v-model="createForm.officer_name" placeholder="Nama petugas" />
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
                <CardTitle>Daftar Loket</CardTitle>
            </CardHeader>
            <CardContent>
                <p v-if="lokets.length === 0" class="text-sm text-muted-foreground">
                    Belum ada loket.
                </p>
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Loket</TableHead>
                            <TableHead>Layanan</TableHead>
                            <TableHead>Petugas</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Urutan</TableHead>
                            <TableHead class="text-right"><span class="sr-only">Aksi</span></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="loket in lokets" :key="loket.id">
                            <TableCell class="font-medium">{{ loket.name }}</TableCell>
                            <TableCell>
                                <Badge
                                    :style="loket.service.color ? { backgroundColor: loket.service.color, color: '#fff' } : undefined"
                                >
                                    {{ loket.service.name }}
                                </Badge>
                            </TableCell>
                            <TableCell :class="loket.officer_name ? undefined : 'text-muted-foreground'">{{ loket.officer_name ?? '—' }}</TableCell>
                            <TableCell>
                                <Badge :variant="loket.is_active ? 'default' : 'secondary'">
                                    {{ loket.is_active ? 'Aktif' : 'Nonaktif' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{ loket.sort_order }}</TableCell>
                            <TableCell>
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" @click="openEdit(loket)">Ubah</Button>
                                    <Button size="sm" variant="destructive" @click="confirmDelete(loket)">Hapus</Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Dialog v-model:open="editOpen">
            <DialogContent>
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <DialogHeader>
                        <DialogTitle>Ubah Loket</DialogTitle>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="edit-service">Layanan</Label>
                        <Select v-model="editForm.service_id">
                            <SelectTrigger id="edit-service" class="w-full">
                                <SelectValue placeholder="Pilih layanan..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="service in services"
                                    :key="service.id"
                                    :value="String(service.id)"
                                >
                                    {{ service.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-name">Nama Loket</Label>
                        <Input id="edit-name" v-model="editForm.name" />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-officer">Petugas</Label>
                        <Input id="edit-officer" v-model="editForm.officer_name" />
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
                    <DialogTitle>Hapus loket "{{ deletingLoket?.name }}"?</DialogTitle>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button variant="secondary" @click="deleteOpen = false">Batal</Button>
                    <Button variant="destructive" @click="submitDelete">Hapus</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
