<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Info, Pencil, Plus, Trash2, TriangleAlert } from '@lucide/vue';
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
import { readableTextColor, relativeLuminance } from '@/lib/color';
import { dashboard } from '@/routes';

interface Service {
    id: number;
    name: string;
    prefix: string;
    color: string | null;
    is_active: boolean;
    sort_order: number;
    lokets_count: number;
    tickets_count: number;
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Layanan', href: '/admin/services' },
        ],
    },
});

const props = defineProps<{
    services: Service[];
}>();

// Equal-luminance set (~0.12-0.17): white text clears 4.5:1 on each, and
// each clears 3:1 against both the dark and the light display background.
const PRESET_COLORS = [
    { value: '#2563eb', name: 'Biru' },
    { value: '#047857', name: 'Hijau' },
    { value: '#b45309', name: 'Oranye' },
    { value: '#7c3aed', name: 'Ungu' },
    { value: '#be123c', name: 'Merah' },
    { value: '#0e7490', name: 'Toska' },
    { value: '#a21caf', name: 'Magenta' },
];

const activeCount = computed(
    () => props.services.filter((service) => service.is_active).length,
);

function prefixStyle(color: string | null) {
    const background = color ?? '#64748b';

    return {
        backgroundColor: background,
        color: readableTextColor(background),
    };
}

// --- Create / edit -------------------------------------------------------

const formOpen = ref(false);
const editing = ref<Service | null>(null);

const form = useForm({
    name: '',
    prefix: '',
    color: PRESET_COLORS[0].value,
    is_active: true,
    sort_order: 0,
});

/** Guidance for a custom colour that won't read well on the display. */
const colorNote = computed(() => {
    const luminance = relativeLuminance(form.color);

    if (luminance === null) {
        return null;
    }

    if (luminance < 0.12) {
        return 'Warna ini sangat gelap dan kurang terlihat di layar Display bertema gelap.';
    }

    if (readableTextColor(form.color) !== '#ffffff') {
        return 'Warna ini terang, jadi huruf antrian akan ditampilkan hitam.';
    }

    return null;
});

const previewPrefix = computed(() => form.prefix.trim().toUpperCase() || 'A');

function nextFreePrefix(): string {
    const used = new Set(
        props.services.map((service) => service.prefix.toUpperCase()),
    );

    return (
        [...'ABCDEFGHIJKLMNOPQRSTUVWXYZ'].find((letter) => !used.has(letter)) ??
        ''
    );
}

function nextFreeColor(): string {
    const used = new Set(
        props.services.map((service) => service.color?.toLowerCase()),
    );

    return (
        PRESET_COLORS.find((preset) => !used.has(preset.value)) ??
        PRESET_COLORS[0]
    ).value;
}

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    // Suggest the next unused letter and colour so a new service is distinct.
    form.prefix = nextFreePrefix();
    form.color = nextFreeColor();
    form.sort_order =
        props.services.reduce(
            (max, service) => Math.max(max, service.sort_order),
            0,
        ) + 1;
    formOpen.value = true;
}

function openEdit(service: Service) {
    editing.value = service;
    form.clearErrors();
    form.name = service.name;
    form.prefix = service.prefix;
    form.color = service.color ?? PRESET_COLORS[0].value;
    form.is_active = service.is_active;
    form.sort_order = service.sort_order;
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
        prefix: data.prefix.trim().toUpperCase(),
    }));

    if (editing.value) {
        form.patch(`/admin/services/${editing.value.id}`, options);
    } else {
        form.post('/admin/services', options);
    }
}

// --- Inline active toggle ------------------------------------------------

const togglingId = ref<number | null>(null);

function setActive(service: Service, isActive: boolean, onDone?: () => void) {
    togglingId.value = service.id;

    router.patch(
        `/admin/services/${service.id}`,
        {
            name: service.name,
            prefix: service.prefix,
            color: service.color,
            is_active: isActive,
            sort_order: service.sort_order,
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

// --- Delete (cascades to lokets and tickets in the database) -------------

const deleteOpen = ref(false);
const deleting = ref<Service | null>(null);
const deleteConfirmText = ref('');
const deleteProcessing = ref(false);

const deleteHasData = computed(
    () =>
        !!deleting.value &&
        (deleting.value.lokets_count > 0 || deleting.value.tickets_count > 0),
);

const deleteConfirmed = computed(
    () =>
        !deleteHasData.value ||
        deleteConfirmText.value.trim().toUpperCase() ===
            deleting.value?.prefix.toUpperCase(),
);

function confirmDelete(service: Service) {
    deleting.value = service;
    deleteConfirmText.value = '';
    deleteOpen.value = true;
}

function closeDelete() {
    deleteOpen.value = false;
}

function submitDelete() {
    if (!deleting.value || !deleteConfirmed.value) {
        return;
    }

    deleteProcessing.value = true;

    router.delete(`/admin/services/${deleting.value.id}`, {
        preserveScroll: true,
        onSuccess: closeDelete,
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
}
</script>

<template>
    <Head title="Layanan" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">Layanan</h1>
                <p class="mt-1 text-muted-foreground">
                    {{ services.length }} layanan · {{ activeCount }} tampil di
                    kiosk
                </p>
            </div>
            <Button @click="openCreate">
                <Plus class="size-4" aria-hidden="true" />
                Tambah Layanan
            </Button>
        </header>

        <div
            v-if="services.length === 0"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center text-muted-foreground"
        >
            <p>
                Belum ada layanan. Layanan menentukan tombol di kiosk dan huruf
                nomor antrian.
            </p>
            <Button variant="outline" @click="openCreate">
                <Plus class="size-4" aria-hidden="true" />
                Tambah Layanan
            </Button>
        </div>

        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead>Layanan</TableHead>
                    <TableHead class="text-right">Loket</TableHead>
                    <TableHead>Tampil di kiosk</TableHead>
                    <TableHead class="text-right">Urutan</TableHead>
                    <TableHead class="text-right"
                        ><span class="sr-only">Aksi</span></TableHead
                    >
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow
                    v-for="service in services"
                    :key="service.id"
                    :class="{ 'text-muted-foreground': !service.is_active }"
                >
                    <TableCell>
                        <div class="flex items-center gap-3">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg text-lg font-bold"
                                :style="prefixStyle(service.color)"
                            >
                                {{ service.prefix }}
                            </span>
                            <div>
                                <p class="font-medium">{{ service.name }}</p>
                                <p
                                    class="text-sm text-muted-foreground tabular-nums"
                                >
                                    Nomor {{ service.prefix }}-001,
                                    {{ service.prefix }}-002, …
                                </p>
                            </div>
                        </div>
                    </TableCell>
                    <TableCell class="text-right tabular-nums">{{
                        service.lokets_count
                    }}</TableCell>
                    <TableCell>
                        <label
                            class="flex w-fit cursor-pointer items-center gap-3"
                        >
                            <Switch
                                :model-value="service.is_active"
                                :disabled="togglingId === service.id"
                                @update:model-value="
                                    (value: boolean) =>
                                        setActive(service, value)
                                "
                            />
                            <span class="text-sm">{{
                                service.is_active ? 'Aktif' : 'Nonaktif'
                            }}</span>
                        </label>
                    </TableCell>
                    <TableCell class="text-right tabular-nums">{{
                        service.sort_order
                    }}</TableCell>
                    <TableCell>
                        <div class="flex justify-end gap-1">
                            <Button
                                size="sm"
                                variant="ghost"
                                @click="openEdit(service)"
                            >
                                <Pencil class="size-4" aria-hidden="true" />
                                Ubah
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-destructive hover:text-destructive"
                                @click="confirmDelete(service)"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                                Hapus
                            </Button>
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <Dialog v-model:open="formOpen">
            <DialogContent class="sm:max-w-xl">
                <form class="space-y-5" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{
                            editing ? `Ubah ${editing.name}` : 'Tambah Layanan'
                        }}</DialogTitle>
                        <DialogDescription>
                            Layanan aktif tampil sebagai tombol di kiosk.
                        </DialogDescription>
                    </DialogHeader>

                    <!-- Live preview: exactly what visitors will tap. -->
                    <div
                        class="flex items-center gap-4 rounded-xl border bg-muted/40 p-4"
                    >
                        <span
                            class="flex size-14 shrink-0 items-center justify-center rounded-xl text-2xl font-extrabold"
                            :style="prefixStyle(form.color)"
                        >
                            {{ previewPrefix }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-lg font-bold">
                                {{ form.name || 'Nama layanan' }}
                            </p>
                            <p
                                class="text-sm text-muted-foreground tabular-nums"
                            >
                                Pratinjau kiosk · nomor pertama
                                {{ previewPrefix }}-001
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
                        <div class="grid gap-2">
                            <Label for="service-name">Nama layanan</Label>
                            <Input
                                id="service-name"
                                v-model="form.name"
                                placeholder="Contoh: KTP"
                                required
                            />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="service-prefix">Huruf antrian</Label>
                            <Input
                                id="service-prefix"
                                v-model="form.prefix"
                                maxlength="3"
                                placeholder="A"
                                class="uppercase"
                                required
                            />
                            <InputError :message="form.errors.prefix" />
                        </div>
                    </div>

                    <fieldset class="grid gap-3">
                        <legend class="mb-2 text-sm leading-none font-medium">
                            Warna
                        </legend>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-for="preset in PRESET_COLORS"
                                :key="preset.value"
                                type="button"
                                class="flex size-9 items-center justify-center rounded-full ring-offset-2 ring-offset-background transition focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none motion-reduce:transition-none"
                                :class="{
                                    'ring-2 ring-foreground':
                                        form.color.toLowerCase() ===
                                        preset.value,
                                }"
                                :style="{ backgroundColor: preset.value }"
                                :aria-label="preset.name"
                                :aria-pressed="
                                    form.color.toLowerCase() === preset.value
                                "
                                @click="form.color = preset.value"
                            >
                                <Check
                                    v-if="
                                        form.color.toLowerCase() ===
                                        preset.value
                                    "
                                    class="size-4 text-white"
                                    aria-hidden="true"
                                />
                            </button>
                            <label
                                class="flex h-9 cursor-pointer items-center gap-2 rounded-full border px-3 text-sm"
                            >
                                <input
                                    v-model="form.color"
                                    type="color"
                                    class="size-5 cursor-pointer rounded-full border-0 bg-transparent p-0"
                                    aria-label="Warna lain"
                                />
                                <span class="font-mono uppercase">{{
                                    form.color
                                }}</span>
                            </label>
                        </div>
                        <p
                            v-if="colorNote"
                            class="flex items-start gap-2 text-sm text-amber-700 dark:text-amber-400"
                        >
                            <Info
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            {{ colorNote }}
                        </p>
                        <InputError :message="form.errors.color" />
                    </fieldset>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="service-sort">Urutan di kiosk</Label>
                            <Input
                                id="service-sort"
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                            />
                            <InputError :message="form.errors.sort_order" />
                        </div>
                        <div class="grid gap-2">
                            <span class="text-sm leading-none font-medium"
                                >Tampil di kiosk</span
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
                            {{
                                editing ? 'Simpan Perubahan' : 'Tambah Layanan'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteOpen">
            <DialogContent>
                <form class="space-y-5" @submit.prevent="submitDelete">
                    <DialogHeader>
                        <DialogTitle
                            >Hapus layanan {{ deleting?.name }}?</DialogTitle
                        >
                        <DialogDescription v-if="!deleteHasData">
                            Layanan ini belum punya loket maupun nomor antrian.
                        </DialogDescription>
                    </DialogHeader>

                    <!-- The database cascades: lokets and every ticket go too. -->
                    <div
                        v-if="deleteHasData && deleting"
                        class="flex gap-3 rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm"
                    >
                        <TriangleAlert
                            class="mt-0.5 size-5 shrink-0 text-destructive"
                            aria-hidden="true"
                        />
                        <div class="space-y-1">
                            <p class="font-semibold">
                                Ini juga menghapus permanen:
                            </p>
                            <ul class="list-inside list-disc">
                                <li>
                                    {{ deleting.lokets_count }} loket milik
                                    layanan ini
                                </li>
                                <li>
                                    {{ deleting.tickets_count }} nomor antrian,
                                    termasuk riwayatnya
                                </li>
                            </ul>
                            <p>Tindakan ini tidak bisa dibatalkan.</p>
                        </div>
                    </div>

                    <div v-if="deleteHasData && deleting" class="grid gap-2">
                        <Label for="delete-confirm">
                            Ketik
                            <span class="font-mono font-bold">{{
                                deleting.prefix
                            }}</span>
                            untuk konfirmasi
                        </Label>
                        <Input
                            id="delete-confirm"
                            v-model="deleteConfirmText"
                            autocomplete="off"
                            class="uppercase"
                        />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeDelete"
                            >Batal</Button
                        >
                        <Button
                            v-if="deleting?.is_active"
                            type="button"
                            variant="secondary"
                            :disabled="togglingId !== null"
                            @click="
                                deleting &&
                                setActive(deleting, false, closeDelete)
                            "
                        >
                            Nonaktifkan saja
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="!deleteConfirmed || deleteProcessing"
                        >
                            <Spinner v-if="deleteProcessing" />
                            Hapus permanen
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
