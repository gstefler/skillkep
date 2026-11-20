<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Building2,
    GraduationCap,
    LayoutDashboard,
    ShieldCheck,
} from '@lucide/vue';
import { computed } from 'vue';
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
import { index as admins } from '@/routes/admins';
import { index as companies } from '@/routes/companies';
import { index as courses } from '@/routes/courses';
import { index as learners } from '@/routes/learners';
import { index as manageCourses } from '@/routes/manage/courses';
import type { NavGroup } from '@/types';

// Navigation depends on the role: learners only see their course list,
// admins manage the learners of their company, superadmins also manage
// companies and admins. A group with a label renders an overline heading.
const user = computed(() => usePage().props.auth.user);

const navGroups = computed<NavGroup[]>(() => {
    if (user.value.role === 'learner') {
        return [
            {
                items: [
                    { title: 'Képzéseim', href: courses(), icon: BookOpen },
                ],
            },
        ];
    }

    const groups: NavGroup[] = [
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

    const manage: NavGroup = {
        label: 'Szervezet',
        items: [
            { title: 'Képzések', href: manageCourses(), icon: BookOpen },
            { title: 'Tanulók', href: learners(), icon: GraduationCap },
        ],
    };

    if (user.value.role === 'superadmin') {
        manage.items.unshift(
            { title: 'Cégek', href: companies(), icon: Building2 },
            { title: 'Adminisztrátorok', href: admins(), icon: ShieldCheck },
        );
    }

    groups.push(manage);

    return groups;
});
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
