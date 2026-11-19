<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutDashboard } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
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
import type { NavGroup } from '@/types';

// Add new sections here; a group with a label renders an overline heading
// (e.g. { label: 'Vezetőként', items: [...] }).
const navGroups: NavGroup[] = [
    {
        items: [
            {
                title: 'Irányítópult',
                href: dashboard(),
                icon: LayoutDashboard,
            },
        ],
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <!-- Brand cell lines up with the topbar, like .kep-shell__brand -->
        <SidebarHeader
            class="h-(--kep-topbar-height) justify-center border-b border-sidebar-border px-3 py-0"
        >
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="h-10 hover:bg-transparent [&>svg]:size-7"
                    >
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-5 py-4">
            <NavMain
                v-for="(group, index) in navGroups"
                :key="group.label ?? index"
                :label="group.label"
                :items="group.items"
            />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border p-3">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
