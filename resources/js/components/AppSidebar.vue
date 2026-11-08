<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Building2,
    Layers,
    LayoutGrid,
    MonitorSpeaker,
    Palette,
    Ticket,
    Tv,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const operationalNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Loket / Operator',
        href: '/operator',
        icon: MonitorSpeaker,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Loket',
        href: '/admin/lokets',
        icon: Building2,
    },
    {
        title: 'Layanan',
        href: '/admin/services',
        icon: Layers,
    },
    {
        title: 'Tampilan',
        href: '/admin/branding',
        icon: Palette,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Kiosk',
        href: '/kiosk',
        icon: Ticket,
    },
    {
        title: 'Display',
        href: '/display',
        icon: Tv,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain label="Operasional" :items="operationalNavItems" />
            <NavMain label="Administrasi" :items="adminNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter label="Layar Publik" :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
