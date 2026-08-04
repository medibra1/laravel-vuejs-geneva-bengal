<script setup lang="ts">
import { computed, ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import logo from '../../images/admin/logo.png';

const page = usePage<PageProps>();
const isSuperAdmin = computed(() => page.props.auth.roles.includes('super_admin'));
const sidebarOpen = ref(false);

interface NavItem {
    name: string;
    label: string;
    icon: string;
}

const mainLinks: NavItem[] = [{ name: 'dashboard', label: 'Tableau de bord', icon: 'pi-home' }];

const contentLinks: NavItem[] = [
    { name: 'admin.cats.index', label: 'Chats', icon: 'pi-heart' },
    { name: 'admin.owners.index', label: 'Adoptants', icon: 'pi-users' },
    { name: 'admin.litters.index', label: 'Portées', icon: 'pi-sitemap' },
    { name: 'admin.galleries.index', label: 'Galerie', icon: 'pi-images' },
    { name: 'admin.pages.index', label: 'Pages', icon: 'pi-file' },
    { name: 'admin.faq-items.index', label: 'FAQ', icon: 'pi-question-circle' },
    { name: 'admin.testimonials.index', label: 'Témoignages', icon: 'pi-comment' },
    { name: 'admin.contact-requests.index', label: 'Demandes de contact', icon: 'pi-envelope' },
];

const paymentLinks: NavItem[] = [{ name: 'admin.deposits.index', label: 'Acomptes', icon: 'pi-wallet' }];

const adminLinks: NavItem[] = [
    { name: 'admin.users.index', label: 'Comptes admin', icon: 'pi-shield' },
    { name: 'admin.settings.edit', label: 'Réglages du site', icon: 'pi-cog' },
];

function isActive(name: string): boolean {
    return route().current(name) || route().current(`${name}.*`);
}
</script>

<template>
    <div class="flex min-h-screen bg-gray-100">
        <!-- Mobile overlay -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-black/50 lg:hidden"
            @click="sidebarOpen = false"
        />

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col bg-neutral-900 transition-transform lg:static lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 items-center justify-center border-b border-white/10 px-4">
                <Link :href="route('dashboard')">
                    <img :src="logo" alt="Geneva Bengal" class="h-10 w-auto" />
                </Link>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-6">
                <div class="space-y-1">
                    <Link
                        v-for="link in mainLinks"
                        :key="link.name"
                        :href="route(link.name)"
                        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                        :class="
                            isActive(link.name)
                                ? 'bg-emerald-700 text-white'
                                : 'text-neutral-300 hover:bg-white/5 hover:text-white'
                        "
                    >
                        <i class="pi" :class="link.icon" />
                        {{ link.label }}
                    </Link>
                </div>

                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-neutral-500">Contenu</p>
                    <div class="mt-2 space-y-1">
                        <Link
                            v-for="link in contentLinks"
                            :key="link.name"
                            :href="route(link.name)"
                            class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                            :class="
                                isActive(link.name)
                                    ? 'bg-emerald-700 text-white'
                                    : 'text-neutral-300 hover:bg-white/5 hover:text-white'
                            "
                        >
                            <i class="pi" :class="link.icon" />
                            {{ link.label }}
                        </Link>
                    </div>
                </div>

                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-neutral-500">Paiements</p>
                    <div class="mt-2 space-y-1">
                        <Link
                            v-for="link in paymentLinks"
                            :key="link.name"
                            :href="route(link.name)"
                            class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                            :class="
                                isActive(link.name)
                                    ? 'bg-emerald-700 text-white'
                                    : 'text-neutral-300 hover:bg-white/5 hover:text-white'
                            "
                        >
                            <i class="pi" :class="link.icon" />
                            {{ link.label }}
                        </Link>
                    </div>
                </div>

                <div v-if="isSuperAdmin">
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-neutral-500">Administration</p>
                    <div class="mt-2 space-y-1">
                        <Link
                            v-for="link in adminLinks"
                            :key="link.name"
                            :href="route(link.name)"
                            class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                            :class="
                                isActive(link.name)
                                    ? 'bg-emerald-700 text-white'
                                    : 'text-neutral-300 hover:bg-white/5 hover:text-white'
                            "
                        >
                            <i class="pi" :class="link.icon" />
                            {{ link.label }}
                        </Link>
                    </div>
                </div>
            </nav>

            <div class="border-t border-white/10 p-3">
                <Dropdown align="left" width="48">
                    <template #trigger>
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-md px-3 py-2 text-sm font-medium text-neutral-300 hover:bg-white/5 hover:text-white"
                        >
                            <span class="truncate">{{ page.props.auth.user.name }}</span>
                            <i class="pi pi-angle-up" />
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('profile.edit')">Profil</DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">
                            Se déconnecter
                        </DropdownLink>
                    </template>
                </Dropdown>
            </div>
        </aside>

        <!-- Main column -->
        <div class="flex min-w-0 flex-1 flex-col">
            <div class="flex h-16 items-center gap-4 border-b border-gray-200 bg-white px-4 lg:hidden">
                <button type="button" class="text-gray-500" @click="sidebarOpen = true">
                    <i class="pi pi-bars text-xl" />
                </button>
                <img :src="logo" alt="Geneva Bengal" class="h-8 w-auto invert" />
            </div>

            <header class="border-b border-gray-200 bg-white" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main class="flex-1">
                <slot />
            </main>
        </div>
    </div>
</template>
