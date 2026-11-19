import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    /** Count shown at the end of the row, e.g. overdue items. */
    badge?: string | number;
};

export type NavGroup = {
    /** Overline heading; omit for the first, unlabeled group. */
    label?: string;
    items: NavItem[];
};
