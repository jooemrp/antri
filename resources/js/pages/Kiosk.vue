<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronRight, Printer } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { readableTextColor } from '@/lib/color';

interface KioskService {
    id: number;
    name: string;
    prefix: string;
    color: string | null;
}

interface IssuedTicket {
    code: string;
    service_name: string;
    issued_at: string;
}

const props = defineProps<{
    services: KioskService[];
    issuedTicket?: IssuedTicket | null;
}>();

const branding = computed(() => usePage().props.branding);
const brandText = computed(() =>
    readableTextColor(branding.value.primary_color),
);

function serviceColor(service: KioskService): string {
    return service.color ?? branding.value.primary_color;
}

interface ServiceState {
    id: number;
    name: string;
    color: string | null;
    last_issued: string | null;
    last_called: string | null;
}

const serviceStates = ref<ServiceState[]>([]);

const stateById = computed(
    () => new Map(serviceStates.value.map((state) => [state.id, state])),
);

const now = ref(new Date());

const dateLabel = computed(() =>
    now.value.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

const clockLabel = computed(() =>
    now.value.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    }),
);

let isFetching = false;
let pollTimer: ReturnType<typeof setInterval> | undefined;
let clockTimer: ReturnType<typeof setInterval> | undefined;

async function fetchState() {
    if (isFetching) {
        return;
    }

    isFetching = true;

    try {
        const response = await fetch('/kiosk/state');

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        serviceStates.value = data.services;
    } catch {
        // Network or parsing failure — keep the last-known state on screen.
    } finally {
        isFetching = false;
    }
}

onMounted(() => {
    fetchState();
    pollTimer = setInterval(fetchState, 3000);
    clockTimer = setInterval(() => {
        now.value = new Date();
    }, 1000);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }

    if (clockTimer) {
        clearInterval(clockTimer);
    }
});

const issuedAtLabel = computed(() => {
    if (!props.issuedTicket) {
        return '';
    }

    return new Date(props.issuedTicket.issued_at).toLocaleString('id-ID', {
        dateStyle: 'full',
        timeStyle: 'short',
    });
});

// A touchscreen double-tap would otherwise issue two tickets.
const issuingId = ref<number | null>(null);

function issue(service: KioskService) {
    if (issuingId.value !== null) {
        return;
    }

    issuingId.value = service.id;

    router.post(`/kiosk/${service.id}`, {}, {
        preserveScroll: true,
        onSuccess: () => fetchState(),
        onFinish: () => {
            issuingId.value = null;
        },
    });
}

function reset() {
    router.get('/kiosk');
}

function print() {
    window.print();
}
</script>

<template>
    <Head title="Kiosk" />

    <div class="flex min-h-dvh flex-col bg-slate-50 dark:bg-slate-950">
        <header
            class="flex items-center justify-between gap-6 px-8 py-5 shadow-sm print:hidden"
            :style="{ backgroundColor: branding.primary_color, color: brandText }"
        >
            <div class="flex min-w-0 items-center gap-4">
                <img
                    v-if="branding.logo_url"
                    :src="branding.logo_url"
                    :alt="branding.app_name"
                    class="h-14 w-auto"
                />
                <h1 class="truncate text-2xl font-bold">
                    {{ branding.app_name }}
                </h1>
            </div>
            <div class="shrink-0 text-right">
                <p class="text-3xl font-bold tabular-nums">{{ clockLabel }}</p>
                <p class="text-base opacity-80">{{ dateLabel }}</p>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-10 px-6 py-12 print:hidden">
            <div class="text-center">
                <h2 class="text-4xl font-bold text-slate-900 dark:text-white">
                    Silakan pilih layanan
                </h2>
                <p class="mt-3 text-xl text-slate-600 dark:text-slate-400">
                    Sentuh salah satu layanan untuk mengambil nomor antrian
                </p>
            </div>

            <p
                v-if="services.length === 0"
                class="rounded-3xl border-2 border-dashed border-slate-300 p-12 text-center text-xl text-slate-500 dark:border-slate-700 dark:text-slate-400"
            >
                Belum ada layanan yang tersedia. Silakan hubungi petugas.
            </p>

            <div v-else class="grid gap-6 md:grid-cols-2">
                <button
                    v-for="service in services"
                    :key="service.id"
                    type="button"
                    :disabled="issuingId !== null"
                    class="group flex flex-col overflow-hidden rounded-3xl border-2 border-slate-200 bg-white text-left shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg focus-visible:ring-4 focus-visible:ring-slate-900 focus-visible:ring-offset-2 focus-visible:outline-none active:translate-y-0 active:scale-[0.99] disabled:cursor-wait motion-reduce:transition-none motion-reduce:hover:translate-y-0 motion-reduce:active:scale-100 dark:border-slate-800 dark:bg-slate-900 dark:focus-visible:ring-white dark:focus-visible:ring-offset-slate-950"
                    :class="{ 'opacity-60': issuingId !== null && issuingId !== service.id }"
                    @click="issue(service)"
                >
                    <span class="flex flex-1 items-center gap-6 p-7">
                        <span
                            class="flex size-20 shrink-0 items-center justify-center rounded-2xl text-4xl font-extrabold"
                            :style="{
                                backgroundColor: serviceColor(service),
                                color: readableTextColor(serviceColor(service)),
                            }"
                        >
                            {{ service.prefix }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-3xl leading-tight font-bold text-slate-900 dark:text-white">
                                {{ service.name }}
                            </span>
                            <span class="mt-1 block text-lg text-slate-500 dark:text-slate-400">
                                Ambil nomor antrian
                            </span>
                        </span>
                        <Spinner v-if="issuingId === service.id" class="size-8 text-slate-400" />
                        <ChevronRight
                            v-else
                            class="size-8 shrink-0 text-slate-400 transition group-hover:translate-x-1 motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="grid grid-cols-2 divide-x divide-slate-200 border-t border-slate-200 bg-slate-50 dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-950/50">
                        <span class="block px-7 py-4">
                            <span class="block text-sm font-medium text-slate-500 dark:text-slate-400">
                                Antrian saat ini
                            </span>
                            <span class="block text-2xl font-bold text-slate-900 tabular-nums dark:text-white">
                                {{ stateById.get(service.id)?.last_called ?? '—' }}
                            </span>
                        </span>
                        <span class="block px-7 py-4">
                            <span class="block text-sm font-medium text-slate-500 dark:text-slate-400">
                                Terakhir diambil
                            </span>
                            <span class="block text-2xl font-bold text-slate-900 tabular-nums dark:text-white">
                                {{ stateById.get(service.id)?.last_issued ?? '—' }}
                            </span>
                        </span>
                    </span>
                </button>
            </div>
        </main>

        <div
            v-if="issuedTicket"
            role="dialog"
            aria-modal="true"
            aria-labelledby="ticket-title"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm print:bg-transparent print:backdrop-blur-none"
        >
            <div class="ticket-print w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
                <p
                    class="px-8 py-4 text-center text-base font-semibold tracking-widest uppercase"
                    :style="{ backgroundColor: branding.primary_color, color: brandText }"
                >
                    {{ branding.app_name }}
                </p>

                <div class="flex flex-col items-center px-8 pt-8 pb-6 text-center">
                    <p id="ticket-title" class="text-lg font-medium text-slate-500 dark:text-slate-400">
                        Nomor Antrian Anda
                    </p>
                    <p class="mt-2 text-8xl font-extrabold tracking-wide text-slate-900 tabular-nums dark:text-white">
                        {{ issuedTicket.code }}
                    </p>
                    <p class="mt-4 text-2xl font-semibold text-slate-800 dark:text-slate-100">
                        {{ issuedTicket.service_name }}
                    </p>
                    <p class="mt-1 text-base text-slate-500 dark:text-slate-400">
                        {{ issuedAtLabel }}
                    </p>
                </div>

                <div aria-hidden="true" class="mx-8 border-t-2 border-dashed border-slate-300 dark:border-slate-700" />

                <p class="px-8 py-6 text-center text-lg text-slate-700 dark:text-slate-200">
                    Silakan menunggu nomor Anda dipanggil
                </p>

                <div class="flex gap-3 px-8 pb-8 print:hidden">
                    <Button class="h-14 flex-1 text-lg" size="lg" @click="print">
                        <Printer class="size-5" aria-hidden="true" />
                        Cetak
                    </Button>
                    <Button class="h-14 flex-1 text-lg" size="lg" variant="outline" @click="reset">
                        <Check class="size-5" aria-hidden="true" />
                        Selesai
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }

    .ticket-print,
    .ticket-print * {
        visibility: visible;
    }

    .ticket-print {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
    }

    /* Printers drop backgrounds by default; force black-on-white so a dark
       theme or a light brand colour can't print invisible text. */
    .ticket-print,
    .ticket-print * {
        color: #000 !important;
        background: transparent !important;
        border-color: #000 !important;
        box-shadow: none !important;
    }
}
</style>
