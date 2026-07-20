<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';

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

defineProps<{
    lokets: OperatorLoket[];
}>();

const STORAGE_KEY = 'operator.loket_id';

const selectedLoketId = ref<string | undefined>(undefined);
const current = ref<CurrentTicket | null>(null);
const waiting = ref<WaitingTicket[]>([]);
const waitingCount = ref(0);

let isFetching = false;
let pollTimer: ReturnType<typeof setInterval> | undefined;

async function fetchState() {
    if (isFetching || !selectedLoketId.value) {
        return;
    }

    isFetching = true;

    try {
        const response = await fetch(`/operator/state?loket_id=${selectedLoketId.value}`);
        const data = await response.json();

        current.value = data.current;
        waiting.value = data.waiting;
        waitingCount.value = data.waiting_count;
    } finally {
        isFetching = false;
    }
}

watch(selectedLoketId, (value) => {
    if (value) {
        localStorage.setItem(STORAGE_KEY, value);
    }

    current.value = null;
    waiting.value = [];
    waitingCount.value = 0;

    fetchState();
});

onMounted(() => {
    const stored = localStorage.getItem(STORAGE_KEY);

    if (stored) {
        selectedLoketId.value = stored;
    }

    fetchState();
    pollTimer = setInterval(fetchState, 3000);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});

function callNext() {
    if (!selectedLoketId.value) {
        return;
    }

    router.post(
        `/operator/${selectedLoketId.value}/call`,
        {},
        { preserveScroll: true, onSuccess: () => fetchState() },
    );
}

function recall() {
    if (!current.value) {
        return;
    }

    router.post(
        `/operator/ticket/${current.value.id}/recall`,
        {},
        { preserveScroll: true, onSuccess: () => fetchState() },
    );
}

function skip() {
    if (!current.value) {
        return;
    }

    router.post(
        `/operator/ticket/${current.value.id}/skip`,
        {},
        { preserveScroll: true, onSuccess: () => fetchState() },
    );
}

function finish() {
    if (!current.value) {
        return;
    }

    router.post(
        `/operator/ticket/${current.value.id}/finish`,
        {},
        { preserveScroll: true, onSuccess: () => fetchState() },
    );
}
</script>

<template>
    <Head title="Operator" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Card>
            <CardHeader>
                <CardTitle>Pilih Loket</CardTitle>
            </CardHeader>
            <CardContent>
                <Select v-model="selectedLoketId">
                    <SelectTrigger class="w-full sm:w-80">
                        <SelectValue placeholder="Pilih loket..." />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="loket in lokets"
                            :key="loket.id"
                            :value="String(loket.id)"
                        >
                            {{ loket.name }}
                            <template v-if="loket.officer_name">
                                — {{ loket.officer_name }}
                            </template>
                        </SelectItem>
                    </SelectContent>
                </Select>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Panggilan Saat Ini</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col items-center gap-6">
                <p
                    v-if="current"
                    class="text-7xl font-black tracking-wider text-foreground"
                >
                    {{ current.code }}
                </p>
                <p v-else class="text-2xl font-medium text-muted-foreground">
                    Belum ada panggilan
                </p>

                <div class="flex flex-wrap justify-center gap-3">
                    <Button :disabled="!selectedLoketId" @click="callNext">
                        Panggil Berikutnya
                    </Button>
                    <Button variant="outline" :disabled="!current" @click="recall">
                        Panggil Ulang
                    </Button>
                    <Button variant="outline" :disabled="!current" @click="skip">
                        Lewati
                    </Button>
                    <Button variant="outline" :disabled="!current" @click="finish">
                        Selesai
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    Antrian Menunggu
                    <Badge variant="secondary">{{ waitingCount }}</Badge>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p v-if="waiting.length === 0" class="text-sm text-muted-foreground">
                    Tidak ada antrian menunggu.
                </p>
                <ul v-else class="flex flex-wrap gap-2">
                    <template v-for="(ticket, index) in waiting" :key="ticket.id">
                        <li class="text-lg font-semibold">{{ ticket.code }}</li>
                        <Separator
                            v-if="index < waiting.length - 1"
                            orientation="vertical"
                            class="h-6"
                        />
                    </template>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
