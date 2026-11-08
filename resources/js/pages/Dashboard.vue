<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CheckCheck,
    ExternalLink,
    Hourglass,
    Megaphone,
    MonitorSpeaker,
    Ticket,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
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

interface ServiceStat {
    id: number;
    name: string;
    prefix: string;
    color: string | null;
    total: number;
    waiting: number;
    done: number;
}

interface Stats {
    total_today: number;
    waiting: number;
    done: number;
    active_lokets: number;
    per_service: ServiceStat[];
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const props = defineProps<{
    stats: Stats;
}>();

const REFRESH_MS = 10_000;

const lastUpdated = ref(new Date());
let refreshTimer: ReturnType<typeof setInterval> | undefined;

const todayLabel = new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const updatedLabel = computed(() =>
    lastUpdated.value.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }),
);

const donePercent = computed(() =>
    props.stats.total_today === 0
        ? 0
        : Math.round((props.stats.done / props.stats.total_today) * 100),
);

function percent(part: number, whole: number): number {
    return whole === 0 ? 0 : Math.round((part / whole) * 100);
}

function prefixStyle(color: string | null) {
    const background = color ?? '#64748b';

    return {
        backgroundColor: background,
        color: readableTextColor(background),
    };
}

// Keep the numbers live without a manual reload; skip while the tab is
// hidden so an idle dashboard tab doesn't poll all day.
function refresh() {
    if (document.hidden) {
        return;
    }

    router.reload({
        only: ['stats'],
        onSuccess: () => {
            lastUpdated.value = new Date();
        },
    });
}

onMounted(() => {
    refreshTimer = setInterval(refresh, REFRESH_MS);
});

onUnmounted(() => {
    if (refreshTimer) {
        clearInterval(refreshTimer);
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">Ringkasan hari ini</h1>
                <p class="mt-1 flex items-center gap-2 text-muted-foreground">
                    {{ todayLabel }}
                    <span aria-hidden="true">·</span>
                    <span class="flex items-center gap-1.5 tabular-nums">
                        <span
                            aria-hidden="true"
                            class="size-2 rounded-full bg-emerald-500"
                        />
                        Diperbarui {{ updatedLabel }}
                    </span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button as-child>
                    <Link href="/operator">
                        <Megaphone class="size-4" aria-hidden="true" />
                        Mulai Melayani
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <a href="/kiosk" target="_blank" rel="noopener noreferrer">
                        Kiosk
                        <ExternalLink class="size-4" aria-hidden="true" />
                    </a>
                </Button>
                <Button as-child variant="outline">
                    <a
                        href="/display"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Display
                        <ExternalLink class="size-4" aria-hidden="true" />
                    </a>
                </Button>
            </div>
        </header>

        <section
            aria-label="Statistik hari ini"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <!-- The number that needs attention comes first. -->
            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="font-medium text-muted-foreground">Menunggu</p>
                    <Hourglass
                        class="size-5 text-amber-600 dark:text-amber-400"
                        aria-hidden="true"
                    />
                </div>
                <p class="mt-3 text-4xl font-bold tabular-nums">
                    {{ stats.waiting }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    orang masih dalam antrian
                </p>
            </div>
            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="font-medium text-muted-foreground">Selesai</p>
                    <CheckCheck
                        class="size-5 text-emerald-600 dark:text-emerald-400"
                        aria-hidden="true"
                    />
                </div>
                <p class="mt-3 text-4xl font-bold tabular-nums">
                    {{ stats.done }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ donePercent }}% dari nomor hari ini
                </p>
            </div>
            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="font-medium text-muted-foreground">
                        Total nomor diambil
                    </p>
                    <Ticket
                        class="size-5 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <p class="mt-3 text-4xl font-bold tabular-nums">
                    {{ stats.total_today }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">sejak pagi ini</p>
            </div>
            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <p class="font-medium text-muted-foreground">Loket aktif</p>
                    <MonitorSpeaker
                        class="size-5 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <p class="mt-3 text-4xl font-bold tabular-nums">
                    {{ stats.active_lokets }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    <Link
                        href="/admin/lokets"
                        class="underline-offset-4 hover:text-foreground hover:underline"
                    >
                        Kelola loket
                    </Link>
                </p>
            </div>
        </section>

        <section aria-labelledby="per-service-title" class="space-y-3">
            <h2 id="per-service-title" class="text-lg font-semibold">
                Per layanan
            </h2>

            <div
                v-if="stats.per_service.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                Belum ada layanan.
                <Link
                    href="/admin/services"
                    class="font-medium text-foreground underline underline-offset-4"
                >
                    Tambah layanan
                </Link>
            </div>

            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>Layanan</TableHead>
                        <TableHead class="text-right">Menunggu</TableHead>
                        <TableHead class="text-right">Selesai</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                        <TableHead class="w-1/4">Progres</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="service in stats.per_service"
                        :key="service.id"
                    >
                        <TableCell>
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg font-bold"
                                    :style="prefixStyle(service.color)"
                                >
                                    {{ service.prefix }}
                                </span>
                                <span class="font-medium">{{
                                    service.name
                                }}</span>
                            </div>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            <span
                                :class="
                                    service.waiting > 0
                                        ? 'font-bold text-amber-700 dark:text-amber-400'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ service.waiting }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ service.done }}
                        </TableCell>
                        <TableCell
                            class="text-right font-semibold tabular-nums"
                        >
                            {{ service.total }}
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center gap-3">
                                <div
                                    class="h-2 flex-1 overflow-hidden rounded-full bg-muted"
                                    role="progressbar"
                                    :aria-valuenow="
                                        percent(service.done, service.total)
                                    "
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    :aria-label="`Progres ${service.name}`"
                                >
                                    <div
                                        class="h-full rounded-full transition-[width] duration-500 motion-reduce:transition-none"
                                        :style="{
                                            width: `${percent(service.done, service.total)}%`,
                                            backgroundColor:
                                                service.color ?? '#64748b',
                                        }"
                                    />
                                </div>
                                <span
                                    class="w-10 text-right text-sm text-muted-foreground tabular-nums"
                                >
                                    {{ percent(service.done, service.total) }}%
                                </span>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>
    </div>
</template>
