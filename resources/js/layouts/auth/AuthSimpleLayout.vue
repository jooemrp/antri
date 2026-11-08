<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
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
const year = new Date().getFullYear();

// A same-hue gradient: the brand colour deepening toward black when the
// text is white, or lightening toward white when the text is dark, so the
// far end only ever increases the text's contrast.
const bandGradient = computed(() => {
    const base = branding.value.primary_color;
    const toward = brandText.value === '#ffffff' ? 'black' : 'white';

    return `linear-gradient(135deg, ${base} 0%, color-mix(in oklab, ${base} 70%, ${toward}) 100%)`;
});
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-muted/40">
        <!-- Identity band: brand-colour gradient, logo on a white tile so any
             logo reads cleanly on any brand colour, and the name beneath it.
             backgroundColor stays as the fallback if color-mix is unsupported. -->
        <header
            class="px-6 pt-14 pb-32 md:pt-20"
            :style="{
                backgroundColor: branding.primary_color,
                backgroundImage: bandGradient,
                color: brandText,
            }"
        >
            <Link
                :href="home()"
                class="mx-auto flex w-fit flex-col items-center gap-5 rounded-2xl text-center focus-visible:ring-[3px] focus-visible:ring-current focus-visible:outline-none"
            >
                <span
                    class="flex size-28 items-center justify-center rounded-3xl bg-white p-4 shadow-lg ring-1 ring-black/5"
                >
                    <img
                        v-if="branding.logo_url"
                        :src="branding.logo_url"
                        alt=""
                        class="size-full object-contain"
                    />
                    <AppLogoIcon
                        v-else
                        class="size-full fill-current text-slate-900"
                    />
                </span>
                <span>
                    <span class="block text-3xl leading-tight font-bold">
                        {{ branding.app_name }}
                    </span>
                    <span class="mt-1 block opacity-80">Sistem Antrian</span>
                </span>
            </Link>
        </header>

        <!-- The card overlaps the band's lower edge. -->
        <main class="-mt-20 flex flex-1 justify-center px-6 pb-10">
            <div class="flex w-full max-w-sm flex-col gap-6">
                <div class="rounded-2xl border bg-card p-6 shadow-xl sm:p-8">
                    <div
                        v-if="title || description"
                        class="mb-6 space-y-1 text-center"
                    >
                        <h1 v-if="title" class="text-xl font-semibold">
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>

                <p class="text-center text-sm text-muted-foreground">
                    © {{ year }} {{ branding.app_name }}
                </p>
            </div>
        </main>
    </div>
</template>
