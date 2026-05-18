<script setup lang="ts">
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
import { home, expenses, income, savings, transactions, categories } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Banknote, PiggyBank, Euro, Wallet } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: home(),
        icon: LayoutGrid,
        color: 'white',
    },
    {
        title: 'Uitgaven',
        href: expenses(),
        icon: Wallet,
        color: 'white',
    },
    {
        title: 'Inkomsten',
        href: income(),
        icon: Euro,
        color: 'white',
    },
    {
        title: 'Sparen',
        href: savings(),
        icon: PiggyBank,
        color: 'white',
    },
    {
        title: 'Categorieën',
        href: categories(),
        icon: Folder,
        color: 'white',
    },
    {
        title: 'Transacties',
        href: transactions(),
        icon: Banknote,
        color: 'white',
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Frontend components',
        href: 'https://www.shadcn-vue.com',
        icon: Folder,
        color: 'white',
    },
    {
        title: 'What To Build',
        href: 'https://preview.themeforest.net/item/evank-tailwind-css-nextjs-admin-template/full_screen_preview/57881050',
        icon: BookOpen,
        color: 'white',
    },
    {
        title: 'Icons',
        href: 'https://lucide.dev/icons/',
        icon: BookOpen,
        color: 'white',
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="home()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
