<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { readableTextColor } from '@/lib/color';
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

const props = defineProps<{
    lokets: Loket[];
    services: ServiceOption[];
}>();

// --- Filter by service -------------------------------------------------

const filter = ref<number | 'all'>('all');

const countByService = computed(() => {
    const counts = new Map<number, number>();

    for (const loket of props.lokets) {
        counts.set(loket.service_id, (counts.get(loket.service_id) ?? 0) + 1);
    }

    return counts;
});

const visibleLokets = computed(() =>
    filter.value === 'all'
        ? props.lokets
        : props.lokets.filter((loket) => loket.service_id === filter.value),
);

const activeCount = computed(
    () => props.lokets.filter((loket) => loket.is_active).length,
);

function badgeStyle(color: string | null) {
    const background = color ?? '#64748b';

    return {
        backgroundColor: background,
        color: readableTextColor(background),
    };
}

// --- Create / edit (one dialog, scoped errors) -------------------------

const formOpen = ref(false);
const editing = ref<Loket | null>(null);

const form = useForm({
    service_id: '',
    name: '',
    officer_name: '',
    is_active: true,
    sort_order: 0,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    // Pre-fill from context: the filtered service, next sort position.
    form.service_id =
        filter.value !== 'all'
            ? String(filter.value)
            : String(props.services[0]?.id ?? '');
    form.sort_order =
        props.lokets.reduce(
            (max, loket) => Math.max(max, loket.sort_order),
            0,
        ) + 1;
    formOpen.value = true;
}

function openEdit(loket: Loket) {
    editing.value = loket;
    form.clearErrors();
    form.service_id = String(loket.service_id);
    form.name = loket.name;
    form.officer_name = loket.officer_name ?? '';
    form.is_active = loket.is_active;
    form.sort_order = loket.sort_order;
    formOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            formOpen.value = false;
        },
    };

    form.transform((data) => ({
        ...data,
        service_id: Number(data.service_id),
    }));

    if (editing.value) {
        form.patch(`/admin/lokets/${editing.value.id}`, options);
    } else {
        form.post('/admin/lokets', options);
    }
}

// --- Inline active toggle ----------------------------------------------

const togglingId = ref<number | null>(null);

function setActive(loket: Loket, isActive: boolean, onDone?: () => void) {
    togglingId.value = loket.id;

    router.patch(
        `/admin/lokets/${loket.id}`,
        {
            service_id: loket.service_id,
            name: loket.name,
            officer_name: loket.officer_name,
            is_active: isActive,
            sort_order: loket.sort_order,
        },
        {
            preserveScroll: true,
            onSuccess: () => onDone?.(),
            onFinish: () => {
                togglingId.value = null;
            },
        },
    );
}

// --- Delete --------------------------------------------------------------

const deleteOpen = ref(false);
const deleting = ref<Loket | null>(null);
const deleteProcessing = ref(false);

function confirmDelete(loket: Loket) {
    deleting.value = loket;
    deleteOpen.value = true;
}

function closeDelete() {
    deleteOpen.value = false;
}

function submitDelete() {
    if (!deleting.value) {
        return;
    }

    deleteProcessing.value = true;

    router.delete(`/admin/lokets/${deleting.value.id}`, {
        preserveScroll: true,
        onSuccess: closeDelete,
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
}
</script>

<template>
    <Head title="Loket" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">Loket</h1>
                <p class="mt-1 text-muted-foreground">
                    {{ lokets.length }} loket · {{ activeCount }} aktif melayani
                </p>
            </div>
            <Button :disabled="services.length === 0" @click="openCreate">
                <Plus class="size-4" aria-hidden="true" />
                Tambah Loket
            </Button>
        </header>

        <p
            v-if="services.length === 0"
            class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
        >
            Buat layanan terlebih dahulu di menu
            <a
                href="/admin/services"
                class="font-medium text-foreground underline underline-offset-4"
                >Layanan</a
            >, lalu tambahkan loket untuk melayaninya.
        </p>

        <template v-else>
            <!-- Filter chips: jump straight to one service's desks. -->
            <div
                v-if="services.length > 1"
                role="group"
                aria-label="Saring berdasarkan layanan"
                class="flex flex-wrap gap-2"
            >
                <Button
                    size="sm"
                    :variant="filter === 'all' ? 'default' : 'outline'"
                    :aria-pressed="filter === 'all'"
                    @click="filter = 'all'"
                >
                    Semua · {{ lokets.length }}
                </Button>
                <Button
                    v-for="service in services"
                    :key="service.id"
                    size="sm"
                    :variant="filter === service.id ? 'default' : 'outline'"
                    :aria-pressed="filter === service.id"
                    @click="filter = service.id"
                >
                    {{ service.name }} ·
                    {{ countByService.get(service.id) ?? 0 }}
                </Button>
            </div>

            <div
                v-if="visibleLokets.length === 0"
                class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                <p>
                    Belum ada loket{{
                        filter === 'all' ? '' : ' untuk layanan ini'
                    }}.
                </p>
                <Button variant="outline" @click="openCreate">
                    <Plus class="size-4" aria-hidden="true" />
                    Tambah Loket
                </Button>
            </div>

            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>Loket</TableHead>
                        <TableHead>Layanan</TableHead>
                        <TableHead>Petugas</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Urutan</TableHead>
                        <TableHead class="text-right"
                            ><span class="sr-only">Aksi</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="loket in visibleLokets"
                        :key="loket.id"
                        :class="{ 'text-muted-foreground': !loket.is_active }"
                    >
                        <TableCell class="font-medium">{{
                            loket.name
                        }}</TableCell>
                        <TableCell>
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold"
                                :style="badgeStyle(loket.service.color)"
                            >
                                {{ loket.service.name }}
                            </span>
                        </TableCell>
                        <TableCell>
                            <span v-if="loket.officer_name">{{
                                loket.officer_name
                            }}</span>
                            <span v-else class="text-muted-foreground"
                                >Belum ditentukan</span
                            >
                        </TableCell>
                        <TableCell>
                            <label
                                class="flex w-fit cursor-pointer items-center gap-3"
                            >
                                <Switch
                                    :model-value="loket.is_active"
                                    :disabled="togglingId === loket.id"
                                    @update:model-value="
                                        (value: boolean) =>
                                            setActive(loket, value)
                                    "
                                />
                                <span class="text-sm">{{
                                    loket.is_active ? 'Aktif' : 'Nonaktif'
                                }}</span>
                            </label>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            loket.sort_order
                        }}</TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-1">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    @click="openEdit(loket)"
                                >
                                    <Pencil class="size-4" aria-hidden="true" />
                                    Ubah
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="text-destructive hover:text-destructive"
                                    @click="confirmDelete(loket)"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                    Hapus
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </template>

        <Dialog v-model:open="formOpen">
            <DialogContent>
                <form class="space-y-5" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{
                            editing ? `Ubah ${editing.name}` : 'Tambah Loket'
                        }}</DialogTitle>
                        <DialogDescription>
                            Loket aktif muncul di pilihan halaman Operator dan
                            di layar Display.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="loket-service">Layanan</Label>
                        <Select v-model="form.service_id">
                            <SelectTrigger id="loket-service" class="w-full">
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
                        <InputError :message="form.errors.service_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="loket-name">Nama loket</Label>
                        <Input
                            id="loket-name"
                            v-model="form.name"
                            placeholder="Contoh: Loket KTP 3"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="loket-officer"
                            >Petugas
                            <span class="font-normal text-muted-foreground"
                                >(opsional)</span
                            ></Label
                        >
                        <Input
                            id="loket-officer"
                            v-model="form.officer_name"
                            placeholder="Nama petugas yang bertugas"
                        />
                        <InputError :message="form.errors.officer_name" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="loket-sort">Urutan tampil</Label>
                            <Input
                                id="loket-sort"
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                            />
                            <InputError :message="form.errors.sort_order" />
                        </div>
                        <div class="grid gap-2">
                            <span class="text-sm leading-none font-medium"
                                >Status</span
                            >
                            <label
                                class="flex h-9 cursor-pointer items-center gap-3"
                            >
                                <Switch v-model="form.is_active" />
                                <span class="text-sm">{{
                                    form.is_active ? 'Aktif' : 'Nonaktif'
                                }}</span>
                            </label>
                        </div>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="formOpen = false"
                            >Batal</Button
                        >
                        <Button type="submit" :disabled="form.processing">
                            <Spinner v-if="form.processing" />
                            {{ editing ? 'Simpan Perubahan' : 'Tambah Loket' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hapus {{ deleting?.name }}?</DialogTitle>
                    <DialogDescription>
                        Riwayat nomor antrian yang pernah dilayani loket ini
                        tetap tersimpan.
                        <template v-if="deleting?.is_active">
                            Jika loket hanya tidak dipakai sementara, cukup
                            nonaktifkan.
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="closeDelete"
                        >Batal</Button
                    >
                    <Button
                        v-if="deleting?.is_active"
                        variant="secondary"
                        :disabled="togglingId !== null"
                        @click="
                            deleting && setActive(deleting, false, closeDelete)
                        "
                    >
                        Nonaktifkan saja
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="deleteProcessing"
                        @click="submitDelete"
                    >
                        <Spinner v-if="deleteProcessing" />
                        Hapus
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
