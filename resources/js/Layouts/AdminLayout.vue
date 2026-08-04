<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import logoLight from '../../images/admin/logo.png';
import logoDark from '../../images/admin/logo-dark-text.png';

const page = usePage<PageProps>();
const isSuperAdmin = computed(() => page.props.auth.roles.includes('super_admin'));
const userInitials = computed(() =>
    page.props.auth.user.name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

const sidebarOpen = ref(false); // mobile drawer
const sidebarDark = ref(false); // "aside" theme — light by default, see CLAUDE.md/feedback
const sidebarCollapsed = ref(false); // desktop icon-only rail

// localStorage doesn't exist during SSR (see resources/js/ssr.ts) — these
// three refs start at safe defaults above and only read/persist the
// user's actual preference once mounted in a real browser.
onMounted(() => {
    sidebarDark.value = localStorage.getItem('admin.sidebarDark') === '1';
    sidebarCollapsed.value = localStorage.getItem('admin.sidebarCollapsed') === '1';
});

watch(sidebarDark, (value) => localStorage.setItem('admin.sidebarDark', value ? '1' : '0'));
watch(sidebarCollapsed, (value) => localStorage.setItem('admin.sidebarCollapsed', value ? '1' : '0'));

const logo = computed(() => (sidebarDark.value ? logoLight : logoDark));

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

function linkClasses(active: boolean): string {
    if (active) {
        return 'bg-emerald-700 text-white';
    }

    return sidebarDark.value
        ? 'text-neutral-300 hover:bg-white/5 hover:text-white'
        : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900';
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
            class="fixed inset-y-0 left-0 z-40 flex shrink-0 flex-col border-r transition-all lg:static"
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                sidebarCollapsed ? 'w-16' : 'w-64',
                sidebarDark ? 'border-white/10 bg-neutral-900' : 'border-gray-200 bg-white',
            ]"
        >
            <div class="flex h-16 items-center gap-2 border-b px-3" :class="sidebarDark ? 'border-white/10' : 'border-gray-200'">
                <Link :href="route('dashboard')" class="flex min-w-0 flex-1 items-center justify-center">
                    <img :src="logo" alt="Geneva Bengal" class="h-9 w-auto" :class="{ 'max-w-none': !sidebarCollapsed }" />
                </Link>
                <button
                    type="button"
                    title="Réduire / agrandir le menu"
                    class="hidden shrink-0 rounded-md p-1.5 lg:block"
                    :class="sidebarDark ? 'text-neutral-400 hover:bg-white/5 hover:text-white' : 'text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700'"
                    @click="sidebarCollapsed = !sidebarCollapsed"
                >
                    <i class="pi" :class="sidebarCollapsed ? 'pi-angle-double-right' : 'pi-angle-double-left'" />
                </button>
            </div>

            <!-- Profile — top of the sidebar, not buried at the bottom -->
            <div class="border-b p-3" :class="sidebarDark ? 'border-white/10' : 'border-gray-200'">
                <Dropdown align="left" width="48">
                    <template #trigger>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-md p-1.5 text-left transition"
                            :class="sidebarDark ? 'hover:bg-white/5' : 'hover:bg-neutral-100'"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-xs font-semibold text-white"
                            >
                                {{ userInitials }}
                            </span>
                            <span v-if="!sidebarCollapsed" class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium" :class="sidebarDark ? 'text-white' : 'text-neutral-900'">
                                    {{ page.props.auth.user.name }}
                                </span>
                                <span class="block truncate text-xs" :class="sidebarDark ? 'text-neutral-400' : 'text-neutral-500'">
                                    {{ isSuperAdmin ? 'Super admin' : 'Admin' }}
                                </span>
                            </span>
                            <i v-if="!sidebarCollapsed" class="pi pi-angle-down text-xs" :class="sidebarDark ? 'text-neutral-400' : 'text-neutral-400'" />
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

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-6">
                <div class="space-y-1">
                    <Link
                        v-for="link in mainLinks"
                        :key="link.name"
                        :href="route(link.name)"
                        :title="link.label"
                        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                        :class="linkClasses(isActive(link.name))"
                    >
                        <i class="pi shrink-0" :class="link.icon" />
                        <span v-if="!sidebarCollapsed">{{ link.label }}</span>
                    </Link>
                </div>

                <div>
                    <p v-if="!sidebarCollapsed" class="px-3 text-xs font-semibold uppercase tracking-wider" :class="sidebarDark ? 'text-neutral-500' : 'text-neutral-400'">
                        Contenu
                    </p>
                    <div class="mt-2 space-y-1">
                        <Link
                            v-for="link in contentLinks"
                            :key="link.name"
                            :href="route(link.name)"
                            :title="link.label"
                            class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                            :class="linkClasses(isActive(link.name))"
                        >
                            <i class="pi shrink-0" :class="link.icon" />
                            <span v-if="!sidebarCollapsed">{{ link.label }}</span>
                        </Link>
                    </div>
                </div>

                <div>
                    <p v-if="!sidebarCollapsed" class="px-3 text-xs font-semibold uppercase tracking-wider" :class="sidebarDark ? 'text-neutral-500' : 'text-neutral-400'">
                        Paiements
                    </p>
                    <div class="mt-2 space-y-1">
                        <Link
                            v-for="link in paymentLinks"
                            :key="link.name"
                            :href="route(link.name)"
                            :title="link.label"
                            class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                            :class="linkClasses(isActive(link.name))"
                        >
                            <i class="pi shrink-0" :class="link.icon" />
                            <span v-if="!sidebarCollapsed">{{ link.label }}</span>
                        </Link>
                    </div>
                </div>

                <div v-if="isSuperAdmin">
                    <p v-if="!sidebarCollapsed" class="px-3 text-xs font-semibold uppercase tracking-wider" :class="sidebarDark ? 'text-neutral-500' : 'text-neutral-400'">
                        Administration
                    </p>
                    <div class="mt-2 space-y-1">
                        <Link
                            v-for="link in adminLinks"
                            :key="link.name"
                            :href="route(link.name)"
                            :title="link.label"
                            class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                            :class="linkClasses(isActive(link.name))"
                        >
                            <i class="pi shrink-0" :class="link.icon" />
                            <span v-if="!sidebarCollapsed">{{ link.label }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- Footer -->
            <div class="flex items-center gap-2 border-t p-3" :class="sidebarDark ? 'border-white/10' : 'border-gray-200'">
                <button
                    type="button"
                    title="Changer l'apparence du menu"
                    class="flex shrink-0 items-center justify-center rounded-md p-2"
                    :class="sidebarDark ? 'text-neutral-400 hover:bg-white/5 hover:text-white' : 'text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700'"
                    @click="sidebarDark = !sidebarDark"
                >
                    <i class="pi" :class="sidebarDark ? 'pi-sun' : 'pi-moon'" />
                </button>
                <p v-if="!sidebarCollapsed" class="truncate text-xs" :class="sidebarDark ? 'text-neutral-500' : 'text-neutral-400'">
                    © {{ new Date().getFullYear() }} Geneva Bengal
                </p>
            </div>
        </aside>

        <!-- Main column -->
        <div class="flex min-w-0 flex-1 flex-col">
            <div class="flex h-16 items-center gap-4 border-b border-gray-200 bg-white px-4 lg:hidden">
                <button type="button" class="text-gray-500" @click="sidebarOpen = true">
                    <i class="pi pi-bars text-xl" />
                </button>
                <img :src="logoDark" alt="Geneva Bengal" class="h-8 w-auto" />
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
