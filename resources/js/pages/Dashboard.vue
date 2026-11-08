<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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

defineProps<{
    stats: Stats;
}>();
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="grid auto-rows-min gap-4 md:grid-cols-4">
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Total Hari Ini
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ stats.total_today }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Menunggu
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ stats.waiting }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Selesai
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ stats.done }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Loket Aktif
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ stats.active_lokets }}</p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Per Layanan</CardTitle>
            </CardHeader>
            <CardContent>
                <p
                    v-if="stats.per_service.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Belum ada layanan.
                </p>
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Layanan</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                            <TableHead class="text-right">Menunggu</TableHead>
                            <TableHead class="text-right">Selesai</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="service in stats.per_service"
                            :key="service.id"
                        >
                            <TableCell>
                                <div class="flex items-center gap-3">
                                    <Badge
                                        class="min-w-9 justify-center font-mono"
                                        :style="service.color ? { backgroundColor: service.color, color: '#fff' } : undefined"
                                    >
                                        {{ service.prefix }}
                                    </Badge>
                                    <span class="font-medium">{{ service.name }}</span>
                                </div>
                            </TableCell>
                            <TableCell class="text-right font-semibold tabular-nums">{{ service.total }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ service.waiting }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ service.done }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Tautan Cepat</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-3">
                <Button as-child variant="outline">
                    <a href="/kiosk" target="_blank" rel="noopener noreferrer">Kiosk</a>
                </Button>
                <Button as-child variant="outline">
                    <a href="/display" target="_blank" rel="noopener noreferrer">Display</a>
                </Button>
                <Button as-child variant="outline">
                    <Link href="/operator">Operator</Link>
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
