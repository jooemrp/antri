<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { MonitorSpeaker, Ticket, Tv } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { readableTextColor } from '@/lib/color';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const branding = computed(() => usePage().props.branding);
const brandText = computed(() =>
    readableTextColor(branding.value.primary_color),
);

const highlights = [
    { icon: Ticket, label: 'Ambil nomor antrian lewat kiosk' },
    { icon: Tv, label: 'Panggilan tampil & bersuara di layar' },
    {
        icon: MonitorSpeaker,
        label: 'Operator loket memanggil dengan satu klik',
    },
];
</script>

<template>
    <div class="grid min-h-dvh bg-background lg:grid-cols-2">
        <aside
            class="relative hidden flex-col justify-between overflow-hidden p-12 lg:flex"
            :style="{
                backgroundColor: branding.primary_color,
                color: brandText,
            }"
        >
            <!-- Decorative rings; purely visual. -->
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -right-32 -bottom-32 size-[28rem] rounded-full border-[3rem] border-current opacity-10"
            />
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -top-24 -left-24 size-72 rounded-full bg-current opacity-5"
            />

            <Link
                :href="home()"
                class="relative flex items-center gap-5 self-start rounded-lg text-[2.5rem] leading-tight font-bold focus-visible:ring-2 focus-visible:ring-current focus-visible:outline-none"
            >
                <img
                    v-if="branding.logo_url"
                    :src="branding.logo_url"
                    alt=""
                    class="h-20 w-auto"
                />
                <AppLogoIcon v-else class="size-18 shrink-0 fill-current" />
                {{ branding.app_name }}
            </Link>

            <div class="relative max-w-md space-y-8">
                <div class="space-y-3">
                    <p
                        class="text-sm font-semibold tracking-widest uppercase opacity-80"
                    >
                        Sistem Antrian
                    </p>
                    <h2 class="text-4xl leading-tight font-bold">
                        Layanan lebih tertib, menunggu lebih nyaman.
                    </h2>
                </div>
                <ul class="space-y-4">
                    <li
                        v-for="item in highlights"
                        :key="item.label"
                        class="flex items-center gap-4 text-lg"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-current/10"
                        >
                            <component
                                :is="item.icon"
                                class="size-5"
                                aria-hidden="true"
                            />
                        </span>
                        {{ item.label }}
                    </li>
                </ul>
            </div>

            <p class="relative text-sm opacity-70">
                © {{ new Date().getFullYear() }} {{ branding.app_name }}
            </p>
        </aside>

        <main class="flex items-center justify-center p-6 sm:p-10">
            <div class="flex w-full max-w-sm flex-col gap-8">
                <Link
                    :href="home()"
                    class="flex flex-col items-center gap-4 self-center rounded-lg text-center text-[2rem] leading-tight font-bold lg:hidden"
                >
                    <img
                        v-if="branding.logo_url"
                        :src="branding.logo_url"
                        alt=""
                        class="h-20 w-auto"
                    />
                    <AppLogoIcon v-else class="size-18 fill-current" />
                    {{ branding.app_name }}
                </Link>

                <div class="space-y-2">
                    <h1 v-if="title" class="text-3xl font-bold tracking-tight">
                        {{ title }}
                    </h1>
                    <p v-if="description" class="text-muted-foreground">
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>
        </main>
    </div>
</template>
