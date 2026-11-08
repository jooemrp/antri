<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { cn } from '@/lib/utils';

// stacked: large logo with the name centred beneath it (sidebar header).
// In a collapsed icon sidebar it shrinks back to the 32px icon slot.
const props = defineProps<{
    stacked?: boolean;
}>();

const branding = computed(() => usePage().props.branding);

const appName = computed(() => {
    return branding.value?.app_name || 'Laravel Starter Kit';
});

const logoSize = computed(() =>
    props.stacked
        ? 'size-16 transition-[width,height] group-data-[collapsible=icon]:size-8'
        : 'size-8',
);
</script>

<template>
    <!-- An uploaded logo carries its own colours, so it skips the dark tile. -->
    <img
        v-if="branding?.logo_url"
        :src="branding.logo_url"
        alt=""
        :class="cn('shrink-0 rounded-md object-contain', logoSize)"
    />
    <div
        v-else
        :class="
            cn(
                'flex aspect-square shrink-0 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground',
                logoSize,
            )
        "
    >
        <AppLogoIcon
            :class="
                cn(
                    'fill-current text-white dark:text-black',
                    stacked
                        ? 'size-10 group-data-[collapsible=icon]:size-5'
                        : 'size-5',
                )
            "
        />
    </div>
    <span
        v-if="stacked"
        class="line-clamp-2 text-center text-xs leading-tight font-semibold group-data-[collapsible=icon]:sr-only"
    >
        {{ appName }}
    </span>
    <div v-else class="ml-1 grid flex-1 text-left text-xs">
        <span class="truncate leading-tight font-semibold">{{ appName }}</span>
    </div>
</template>
