<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

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

interface ServiceState {
    id: number;
    name: string;
    color: string | null;
    last_issued: string | null;
    last_called: string | null;
}

const serviceStates = ref<ServiceState[]>([]);

let isFetching = false;
let pollTimer: ReturnType<typeof setInterval> | undefined;

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
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
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

function issue(service: KioskService) {
    router.post(`/kiosk/${service.id}`, {}, {
        preserveScroll: true,
        onSuccess: () => fetchState(),
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

    <div class="min-h-screen bg-slate-50 p-8 dark:bg-slate-950">
        <header class="mb-10 flex flex-col items-center gap-3 text-center">
            <img
                v-if="branding.logo_url"
                :src="branding.logo_url"
                :alt="branding.app_name"
                class="h-16 w-auto"
            />
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">
                {{ branding.app_name }}
            </h1>
        </header>

        <main class="mx-auto flex max-w-4xl flex-col items-center gap-8">
            <h2 class="text-center text-3xl font-bold text-slate-900 dark:text-slate-100">
                Silakan pilih layanan
            </h2>

            <div class="grid w-full grid-cols-1 gap-6 sm:grid-cols-2">
                <button
                    v-for="service in services"
                    :key="service.id"
                    type="button"
                    :style="{
                        borderColor: service.color ?? undefined,
                        backgroundColor: service.color ? `${service.color}1a` : undefined,
                    }"
                    class="flex min-h-[200px] flex-col items-center justify-center gap-3 rounded-2xl border-4 bg-white p-6 text-center shadow-sm transition hover:scale-[1.02] hover:shadow-md active:scale-[0.98] dark:bg-slate-900"
                    @click="issue(service)"
                >
                    <span class="text-lg font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                        Ambil Nomor Antrian
                    </span>
                    <span class="text-4xl font-extrabold text-slate-900 dark:text-slate-100">
                        {{ service.name }}
                    </span>
                </button>
            </div>

            <div v-if="serviceStates.length" class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2 print:hidden">
                <div
                    v-for="state in serviceStates"
                    :key="state.id"
                    class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                >
                    <p
                        class="text-sm font-semibold tracking-wide uppercase"
                        :style="{ color: state.color ?? undefined }"
                    >
                        {{ state.name }}
                    </p>
                    <dl class="mt-3 grid gap-2">
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Terakhir diambil</dt>
                            <dd class="text-2xl font-bold text-slate-900 tabular-nums dark:text-slate-100">
                                {{ state.last_issued ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Sedang dipanggil</dt>
                            <dd class="text-2xl font-bold text-slate-900 tabular-nums dark:text-slate-100">
                                {{ state.last_called ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </main>

        <div
            v-if="issuedTicket"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 print:bg-transparent"
        >
            <div
                class="ticket-print flex w-full max-w-lg flex-col items-center gap-6 rounded-2xl bg-white p-10 text-center shadow-xl dark:bg-slate-900"
            >
                <p class="text-xl font-medium text-slate-600 dark:text-slate-300">
                    {{ issuedTicket.service_name }}
                </p>
                <p class="text-7xl font-black tracking-wider text-slate-900 dark:text-slate-100">
                    {{ issuedTicket.code }}
                </p>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ issuedAtLabel }}
                </p>
                <p class="text-lg text-slate-700 dark:text-slate-200">
                    Silakan menunggu nomor Anda dipanggil
                </p>

                <div class="flex w-full gap-3 print:hidden">
                    <Button class="flex-1" size="lg" @click="print">Cetak</Button>
                    <Button class="flex-1" size="lg" variant="outline" @click="reset">
                        Selesai / Ambil Lagi
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
}
</style>
