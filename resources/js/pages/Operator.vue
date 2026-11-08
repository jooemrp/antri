<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Check,
    ChevronRight,
    Megaphone,
    SkipForward,
    Volume2,
    WifiOff,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { readableTextColor } from '@/lib/color';

interface OperatorLoket {
    id: number;
    service_id: number;
    name: string;
    officer_name: string | null;
    service: {
        id: number;
        name: string;
        color: string | null;
    };
}

interface CurrentTicket {
    id: number;
    code: string;
    status: string;
    called_at: string | null;
}

interface WaitingTicket {
    id: number;
    code: string;
    created_at: string;
}

type Action = 'next' | 'recall' | 'skip' | 'finish';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Operator',
                href: '/operator',
            },
        ],
    },
});

const props = defineProps<{
    lokets: OperatorLoket[];
}>();

const STORAGE_KEY = 'operator.loket_id';

const selectedLoketId = ref<string | undefined>(undefined);
const current = ref<CurrentTicket | null>(null);
const waiting = ref<WaitingTicket[]>([]);
const waitingCount = ref(0);
const loaded = ref(false);
const connectionLost = ref(false);
const busy = ref<Action | null>(null);
const now = ref(Date.now());

const selectedLoket = computed(() =>
    props.lokets.find((loket) => String(loket.id) === selectedLoketId.value),
);

const serviceColor = computed(
    () => selectedLoket.value?.service.color ?? '#64748b',
);

const nextTicket = computed(() => waiting.value[0] ?? null);
const hiddenWaiting = computed(() =>
    Math.max(0, waitingCount.value - waiting.value.length),
);

let isFetching = false;
let pollTimer: ReturnType<typeof setInterval> | undefined;
let clockTimer: ReturnType<typeof setInterval> | undefined;

function splitCode(code: string): { prefix: string; number: string } {
    const dash = code.indexOf('-');

    return dash === -1
        ? { prefix: '', number: code }
        : { prefix: code.slice(0, dash), number: code.slice(dash + 1) };
}

/** "0:45" / "12:03" — time since the current number was (re)called. */
function elapsedClock(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const seconds = Math.max(
        0,
        Math.floor((now.value - new Date(iso).getTime()) / 1000),
    );

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

/** "< 1 mnt" / "12 mnt" / "1 j 5 mnt" — how long a ticket has waited. */
function waitedLabel(iso: string): string {
    const minutes = Math.floor((now.value - new Date(iso).getTime()) / 60000);

    if (minutes < 1) {
        return '< 1 mnt';
    }

    if (minutes < 60) {
        return `${minutes} mnt`;
    }

    return `${Math.floor(minutes / 60)} j ${minutes % 60} mnt`;
}

async function fetchState() {
    if (isFetching || !selectedLoketId.value) {
        return;
    }

    isFetching = true;

    try {
        const response = await fetch(
            `/operator/state?loket_id=${selectedLoketId.value}`,
        );

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        current.value = data.current;
        waiting.value = data.waiting;
        waitingCount.value = data.waiting_count;
        loaded.value = true;
        connectionLost.value = false;
    } catch {
        // Keep the last-known state on screen, but say it may be stale.
        connectionLost.value = true;
    } finally {
        isFetching = false;
    }
}

watch(selectedLoketId, (value) => {
    if (value) {
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch {
            // Storage unavailable — the choice just won't persist.
        }
    }

    current.value = null;
    waiting.value = [];
    waitingCount.value = 0;
    loaded.value = false;

    fetchState();
});

function selectLoket(loket: OperatorLoket) {
    selectedLoketId.value = String(loket.id);
}

function run(action: Action, url: string) {
    if (busy.value !== null) {
        return;
    }

    busy.value = action;

    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => fetchState(),
            onFinish: () => {
                busy.value = null;
            },
        },
    );
}

const canCallNext = computed(
    () => !!selectedLoketId.value && waitingCount.value > 0,
);

function callNext() {
    if (canCallNext.value) {
        run('next', `/operator/${selectedLoketId.value}/call`);
    }
}

function recall() {
    if (current.value) {
        run('recall', `/operator/ticket/${current.value.id}/recall`);
    }
}

function skip() {
    if (current.value) {
        run('skip', `/operator/ticket/${current.value.id}/skip`);
    }
}

function finish() {
    if (current.value) {
        run('finish', `/operator/ticket/${current.value.id}/finish`);
    }
}

// Single-letter shortcuts named after the Indonesian labels:
// B = Berikutnya, U = Ulang, L = Lewati, S = Selesai.
const shortcuts: Record<string, () => void> = {
    b: callNext,
    u: recall,
    l: skip,
    s: finish,
};

function onKeydown(event: KeyboardEvent) {
    if (
        event.repeat ||
        event.ctrlKey ||
        event.metaKey ||
        event.altKey ||
        !selectedLoketId.value
    ) {
        return;
    }

    const target = event.target as HTMLElement | null;

    // Never steal keys from inputs, the loket picker, or open dialogs.
    if (
        target?.closest(
            'input, textarea, select, [contenteditable], [role="combobox"], [role="listbox"], [role="dialog"], [role="menu"]',
        )
    ) {
        return;
    }

    const handler = shortcuts[event.key.toLowerCase()];

    if (handler) {
        event.preventDefault();
        handler();
    }
}

onMounted(() => {
    let stored: string | null = null;

    try {
        stored = localStorage.getItem(STORAGE_KEY);
    } catch {
        // Storage unavailable — fall through to the loket chooser.
    }

    // Ignore a remembered loket that has since been removed or deactivated.
    if (stored && props.lokets.some((loket) => String(loket.id) === stored)) {
        selectedLoketId.value = stored;
    }

    pollTimer = setInterval(fetchState, 3000);
    clockTimer = setInterval(() => {
        now.value = Date.now();
    }, 1000);
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }

    if (clockTimer) {
        clearInterval(clockTimer);
    }

    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Head title="Operator" />

    <!-- First visit: choose the loket you are staffing. -->
    <div
        v-if="!selectedLoket"
        class="flex flex-1 items-center justify-center p-6"
    >
        <div class="w-full max-w-3xl space-y-6">
            <div class="text-center">
                <h1 class="text-3xl font-bold">Pilih loket Anda</h1>
                <p class="mt-2 text-muted-foreground">
                    Pilihan ini diingat di perangkat ini dan bisa diganti kapan
                    saja.
                </p>
            </div>

            <p
                v-if="lokets.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                Belum ada loket aktif. Tambahkan di menu Administrasi → Loket.
            </p>

            <div v-else class="grid gap-4 sm:grid-cols-2">
                <button
                    v-for="loket in lokets"
                    :key="loket.id"
                    type="button"
                    class="group flex items-center gap-4 rounded-xl border bg-card p-5 text-left shadow-xs transition hover:border-foreground/30 hover:shadow-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none motion-reduce:transition-none"
                    @click="selectLoket(loket)"
                >
                    <span
                        aria-hidden="true"
                        class="size-3 shrink-0 rounded-full"
                        :style="{
                            backgroundColor: loket.service.color ?? '#64748b',
                        }"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-lg font-semibold">
                            {{ loket.name }}
                        </span>
                        <span
                            class="block truncate text-sm text-muted-foreground"
                        >
                            {{ loket.service.name }}
                            <template v-if="loket.officer_name">
                                · {{ loket.officer_name }}
                            </template>
                        </span>
                    </span>
                    <ChevronRight
                        class="size-5 text-muted-foreground transition group-hover:translate-x-0.5 motion-reduce:transition-none"
                        aria-hidden="true"
                    />
                </button>
            </div>
        </div>
    </div>

    <div v-else class="flex flex-1 flex-col gap-4 p-4">
        <!-- Which desk am I? Compact, since it rarely changes mid-shift. -->
        <header
            class="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-card px-5 py-4 shadow-xs"
        >
            <div class="flex min-w-0 items-center gap-4">
                <div class="min-w-0">
                    <h1 class="truncate text-xl font-bold">
                        {{ selectedLoket.name }}
                    </h1>
                    <p
                        class="flex items-center gap-2 truncate text-sm text-muted-foreground"
                    >
                        <span
                            aria-hidden="true"
                            class="size-2.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: serviceColor }"
                        />
                        Layanan {{ selectedLoket.service.name }}
                        <template v-if="selectedLoket.officer_name">
                            · Petugas {{ selectedLoket.officer_name }}
                        </template>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <p
                    v-if="connectionLost"
                    role="status"
                    class="flex items-center gap-2 rounded-lg bg-amber-500/15 px-3 py-1.5 text-sm font-medium text-amber-700 dark:text-amber-400"
                >
                    <WifiOff class="size-4" aria-hidden="true" />
                    Koneksi terputus — mencoba lagi
                </p>
                <!-- Labelled and full-size so it reads as "change desk here". -->
                <div class="grid gap-1.5">
                    <Label
                        for="switch-loket"
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Ganti loket
                    </Label>
                    <Select v-model="selectedLoketId">
                        <SelectTrigger
                            id="switch-loket"
                            class="w-72 gap-3 border-2 border-foreground/15 px-4 text-base font-semibold transition-colors hover:border-foreground/40 data-[size=default]:h-12 [&>svg:last-child]:size-5 [&>svg:last-child]:opacity-70"
                        >
                            <ArrowLeftRight
                                class="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <SelectValue placeholder="Pilih loket" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="loket in lokets"
                                :key="loket.id"
                                :value="String(loket.id)"
                                class="py-2.5 text-base"
                            >
                                <span
                                    aria-hidden="true"
                                    class="size-2.5 shrink-0 rounded-full"
                                    :style="{
                                        backgroundColor:
                                            loket.service.color ?? '#64748b',
                                    }"
                                />
                                {{ loket.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </header>

        <div class="grid flex-1 gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <!-- The one thing that matters: who is at my desk, what next. -->
            <section
                aria-labelledby="current-title"
                class="flex flex-col rounded-xl border bg-card p-6 shadow-xs"
            >
                <div class="flex items-center justify-between gap-4">
                    <h2
                        id="current-title"
                        class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Nomor saat ini
                    </h2>
                    <p
                        v-if="current"
                        class="flex items-center gap-2 rounded-full bg-muted px-3 py-1 text-sm font-medium tabular-nums"
                    >
                        <Volume2 class="size-4" aria-hidden="true" />
                        Dipanggil {{ elapsedClock(current.called_at) }} lalu
                    </p>
                </div>

                <div
                    class="flex flex-1 flex-col items-center justify-center py-10"
                >
                    <p
                        v-if="current"
                        class="flex items-center gap-4 text-8xl font-extrabold tracking-tight tabular-nums"
                    >
                        <span
                            v-if="splitCode(current.code).prefix"
                            class="flex h-[1.1em] min-w-[1.1em] items-center justify-center rounded-2xl px-3 text-[0.7em]"
                            :style="{
                                backgroundColor: serviceColor,
                                color: readableTextColor(serviceColor),
                            }"
                        >
                            {{ splitCode(current.code).prefix }}
                        </span>
                        <span>{{ splitCode(current.code).number }}</span>
                    </p>
                    <template v-else-if="loaded">
                        <Megaphone
                            class="size-14 text-muted-foreground/60"
                            aria-hidden="true"
                        />
                        <p class="mt-4 text-2xl font-semibold">
                            Belum ada nomor dipanggil
                        </p>
                        <p class="mt-1 text-muted-foreground">
                            {{
                                waitingCount > 0
                                    ? 'Panggil nomor berikutnya untuk mulai melayani.'
                                    : 'Antrian kosong. Nomor baru akan muncul otomatis.'
                            }}
                        </p>
                    </template>
                    <Spinner v-else class="size-8 text-muted-foreground" />
                </div>

                <div class="space-y-3">
                    <!-- The primary action: service-coloured so it is the one
                         saturated thing on the page, with real button feedback.
                         It pulses only when the desk is idle and people wait. -->
                    <button
                        type="button"
                        class="group relative flex w-full items-center justify-center gap-5 rounded-2xl px-6 py-6 shadow-lg transition duration-150 hover:-translate-y-0.5 hover:shadow-xl hover:brightness-110 focus-visible:ring-4 focus-visible:ring-ring/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none active:translate-y-0 active:scale-[0.99] active:shadow-md disabled:pointer-events-none disabled:bg-muted! disabled:text-muted-foreground! disabled:shadow-none motion-reduce:transition-none motion-reduce:hover:translate-y-0 motion-reduce:active:scale-100"
                        :class="{
                            'call-invite':
                                canCallNext && !current && busy === null,
                        }"
                        :style="{
                            backgroundColor: serviceColor,
                            color: readableTextColor(serviceColor),
                            '--invite': serviceColor,
                        }"
                        :disabled="!canCallNext || busy !== null"
                        @click="callNext"
                    >
                        <Spinner v-if="busy === 'next'" class="size-8" />
                        <Megaphone
                            v-else
                            class="size-8 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="text-left">
                            <span class="flex flex-wrap items-center gap-3">
                                <span class="text-2xl font-extrabold">
                                    {{
                                        canCallNext
                                            ? 'Panggil Berikutnya'
                                            : 'Tidak ada antrian menunggu'
                                    }}
                                </span>
                                <span
                                    v-if="canCallNext && nextTicket"
                                    class="rounded-lg bg-current/15 px-3 py-0.5 text-2xl font-extrabold tabular-nums"
                                >
                                    {{ nextTicket.code }}
                                </span>
                            </span>
                            <span
                                v-if="canCallNext"
                                class="mt-1 block text-sm font-medium opacity-85"
                            >
                                <template v-if="current"
                                    >{{ current.code }} otomatis selesai · </template
                                >Klik atau tekan
                                <kbd
                                    class="rounded border border-current/40 px-1.5 font-mono"
                                    >B</kbd
                                >
                            </span>
                        </span>
                        <ChevronRight
                            v-if="canCallNext"
                            class="ml-auto size-8 shrink-0 transition-transform group-hover:translate-x-1 motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </button>

                    <div class="grid grid-cols-3 gap-3">
                        <Button
                            variant="outline"
                            class="h-14 justify-between px-4 text-base"
                            :disabled="!current || busy !== null"
                            @click="recall"
                        >
                            <span class="flex items-center gap-2">
                                <Spinner
                                    v-if="busy === 'recall'"
                                    class="size-5"
                                />
                                <Volume2
                                    v-else
                                    class="size-5"
                                    aria-hidden="true"
                                />
                                Panggil Ulang
                            </span>
                            <kbd class="font-mono text-xs text-muted-foreground"
                                >U</kbd
                            >
                        </Button>
                        <Button
                            variant="outline"
                            class="h-14 justify-between px-4 text-base text-amber-700 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300"
                            :disabled="!current || busy !== null"
                            @click="skip"
                        >
                            <span class="flex items-center gap-2">
                                <Spinner
                                    v-if="busy === 'skip'"
                                    class="size-5"
                                />
                                <SkipForward
                                    v-else
                                    class="size-5"
                                    aria-hidden="true"
                                />
                                Lewati
                            </span>
                            <kbd class="font-mono text-xs text-muted-foreground"
                                >L</kbd
                            >
                        </Button>
                        <Button
                            variant="outline"
                            class="h-14 justify-between px-4 text-base"
                            :disabled="!current || busy !== null"
                            @click="finish"
                        >
                            <span class="flex items-center gap-2">
                                <Spinner
                                    v-if="busy === 'finish'"
                                    class="size-5"
                                />
                                <Check
                                    v-else
                                    class="size-5"
                                    aria-hidden="true"
                                />
                                Selesai
                            </span>
                            <kbd class="font-mono text-xs text-muted-foreground"
                                >S</kbd
                            >
                        </Button>
                    </div>

                    <p class="text-center text-sm text-muted-foreground">
                        Lewati jika warga tidak datang setelah dipanggil ulang ·
                        Selesai jika ingin jeda tanpa memanggil nomor baru
                    </p>
                </div>
            </section>

            <!-- Who is still waiting, and for how long. -->
            <section
                aria-labelledby="waiting-title"
                class="flex flex-col rounded-xl border bg-card shadow-xs"
            >
                <div
                    class="flex items-baseline justify-between border-b px-5 py-4"
                >
                    <h2
                        id="waiting-title"
                        class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Antrian menunggu
                    </h2>
                    <p class="text-3xl font-bold tabular-nums">
                        {{ waitingCount }}
                    </p>
                </div>

                <p
                    v-if="loaded && waitingCount === 0"
                    class="flex flex-1 items-center justify-center p-8 text-center text-muted-foreground"
                >
                    Tidak ada yang menunggu saat ini.
                </p>

                <ol v-else class="flex-1 divide-y">
                    <li
                        v-for="(ticket, index) in waiting"
                        :key="ticket.id"
                        class="flex items-center gap-4 px-5 py-3"
                        :class="{ 'bg-muted/60': index === 0 }"
                    >
                        <span
                            class="w-6 text-right text-sm text-muted-foreground tabular-nums"
                        >
                            {{ index + 1 }}
                        </span>
                        <span class="flex-1 text-xl font-bold tabular-nums">
                            {{ ticket.code }}
                        </span>
                        <span
                            v-if="index === 0"
                            class="rounded-full bg-foreground px-2.5 py-0.5 text-xs font-semibold text-background"
                        >
                            Berikutnya
                        </span>
                        <span
                            class="text-sm text-muted-foreground tabular-nums"
                            :title="`Menunggu sejak ${new Date(ticket.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`"
                        >
                            {{ waitedLabel(ticket.created_at) }}
                        </span>
                    </li>
                </ol>

                <p
                    v-if="hiddenWaiting > 0"
                    class="border-t px-5 py-3 text-center text-sm text-muted-foreground"
                >
                    +{{ hiddenWaiting }} nomor lainnya
                </p>
            </section>
        </div>
    </div>
</template>

<style scoped>
/* Idle desk with people waiting: a soft ring invites the call. */
.call-invite {
    animation: call-invite 1.8s ease-out infinite;
}

@keyframes call-invite {
    0% {
        box-shadow: 0 0 0 0 color-mix(in srgb, var(--invite) 55%, transparent);
    }

    70% {
        box-shadow: 0 0 0 16px color-mix(in srgb, var(--invite) 0%, transparent);
    }

    100% {
        box-shadow: 0 0 0 0 transparent;
    }
}

@media (prefers-reduced-motion: reduce) {
    .call-invite {
        animation: none;
    }
}
</style>
