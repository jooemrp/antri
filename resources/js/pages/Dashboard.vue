<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
                <table v-else class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-muted-foreground">
                            <th class="py-2 pr-4 font-medium">Layanan</th>
                            <th class="py-2 pr-4 font-medium">Total</th>
                            <th class="py-2 pr-4 font-medium">Menunggu</th>
                            <th class="py-2 pr-4 font-medium">Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="service in stats.per_service"
                            :key="service.id"
                            class="border-b last:border-0"
                        >
                            <td class="py-2 pr-4">
                                <Badge
                                    :style="service.color ? { backgroundColor: service.color, color: '#fff' } : undefined"
                                >
                                    {{ service.prefix }}
                                </Badge>
                                {{ service.name }}
                            </td>
                            <td class="py-2 pr-4">{{ service.total }}</td>
                            <td class="py-2 pr-4">{{ service.waiting }}</td>
                            <td class="py-2 pr-4">{{ service.done }}</td>
                        </tr>
                    </tbody>
                </table>
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
