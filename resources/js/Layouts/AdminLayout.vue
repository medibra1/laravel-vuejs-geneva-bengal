<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NotificationBell from '@/Components/Admin/NotificationBell.vue';
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
const themeDark = ref(false); // whole admin panel, not just the sidebar
const sidebarCollapsed = ref(false); // desktop icon-only rail

// localStorage/document don't exist during SSR (see resources/js/ssr.ts)
// — these start at safe defaults above and only read/apply the user's
// actual preference once mounted in a real browser. app.blade.php also
// applies the .dark class synchronously pre-hydration to avoid a flash
// of the wrong theme; this just keeps the ref itself in sync with that.
onMounted(() => {
    themeDark.value = document.documentElement.classList.contains('dark');
    sidebarCollapsed.value = localStorage.getItem('admin.sidebarCollapsed') === '1';
});

watch(themeDark, (value) => {
    document.documentElement.classList.toggle('dark', value);
    localStorage.setItem('admin.themeDark', value ? '1' : '0');
});
watch(sidebarCollapsed, (value) => localStorage.setItem('admin.sidebarCollapsed', value ? '1' : '0'));

interface NavItem {
    name: string;
    label: string;
    icon: string;
    // Only set when the link needs query params the plain route(name) call
    // wouldn't add (e.g. the waiting-list filter below) — falls back to
    // route(name) otherwise.
    href?: string;
    // Only set when route().current(name) isn't precise enough — e.g. two
    // links sharing the same route name but distinguished by a query
    // param (see paymentLinks below).
    isActive?: () => boolean;
}

const mainLinks: NavItem[] = [{ name: 'dashboard', label: 'Tableau de bord', icon: 'pi-home' }];

const contentLinks: NavItem[] = [
    { name: 'admin.cats.adoption.index', label: 'Chatons (adoption)', icon: 'pi-heart' },
    { name: 'admin.cats.breeders.index', label: 'Reproducteurs', icon: 'pi-star' },
    { name: 'admin.owners.index', label: 'Adoptants', icon: 'pi-users' },
    { name: 'admin.litters.index', label: 'Portées', icon: 'pi-sitemap' },
    { name: 'admin.galleries.index', label: 'Galerie', icon: 'pi-images' },
    { name: 'admin.pages.index', label: 'Pages', icon: 'pi-file' },
    { name: 'admin.faq-items.index', label: 'FAQ', icon: 'pi-question-circle' },
    { name: 'admin.testimonials.index', label: 'Témoignages', icon: 'pi-comment' },
    { name: 'admin.contact-requests.index', label: 'Demandes de contact', icon: 'pi-envelope' },
    { name: 'admin.newsletter-subscribers.index', label: 'Newsletter', icon: 'pi-send' },
];

const paymentLinks: NavItem[] = [
    {
        name: 'admin.deposits.index',
        label: 'Réservations',
        icon: 'pi-wallet',
        isActive: () => route().current('admin.deposits.index') && !window.location.search.includes('waiting_list'),
    },
    {
        name: 'admin.deposits.index',
        label: "Liste d'attente",
        icon: 'pi-list',
        href: route('admin.deposits.index', { filter: { waiting_list: 1 } }),
        isActive: () => route().current('admin.deposits.index') && window.location.search.includes('waiting_list'),
    },
];

const adminLinks: NavItem[] = [
    { name: 'admin.users.index', label: 'Comptes admin', icon: 'pi-shield' },
    { name: 'admin.settings.edit', label: 'Réglages du site', icon: 'pi-cog' },
];

function isActive(link: NavItem): boolean {
    if (link.isActive) return link.isActive();

    return route().current(link.name) || route().current(`${link.name}.*`);
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-gray-100 dark:bg-neutral-900">
        <div class="flex flex-1">
            <!-- Mobile overlay -->
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-black/50 lg:hidden"
                @click="sidebarOpen = false"
            />

            <!-- Sidebar — navigation only, nothing global lives in here -->
            <aside
                class="fixed inset-y-0 left-0 z-40 flex shrink-0 flex-col border-r border-gray-200 bg-white transition-all dark:border-neutral-800 dark:bg-neutral-900 lg:static"
                :class="[
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                    sidebarCollapsed ? 'w-16' : 'w-64',
                ]"
            >
                <div class="flex h-16 items-center gap-2 border-b border-gray-200 px-3 dark:border-neutral-800">
                    <Link :href="route('dashboard')" class="flex min-w-0 flex-1 items-center justify-center">
                        <img :src="logoDark" alt="Geneva Bengal" class="h-9 w-auto dark:hidden" />
                        <img :src="logoLight" alt="Geneva Bengal" class="hidden h-9 w-auto dark:block" />
                    </Link>
                    <button
                        type="button"
                        title="Réduire / agrandir le menu"
                        class="hidden shrink-0 rounded-md p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-white/5 dark:hover:text-white lg:block"
                        @click="sidebarCollapsed = !sidebarCollapsed"
                    >
                        <i class="pi" :class="sidebarCollapsed ? 'pi-angle-double-right' : 'pi-angle-double-left'" />
                    </button>
                </div>

                <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-6">
                    <div class="space-y-1">
                        <Link
                            v-for="link in mainLinks"
                            :key="link.label"
                            :href="link.href ?? route(link.name)"
                            :title="link.label"
                            class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                            :class="
                                isActive(link)
                                    ? 'bg-emerald-700 text-white'
                                    : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-white/5 dark:hover:text-white'
                            "
                        >
                            <i class="pi shrink-0" :class="link.icon" />
                            <span v-if="!sidebarCollapsed">{{ link.label }}</span>
                        </Link>
                    </div>

                    <div>
                        <p v-if="!sidebarCollapsed" class="px-3 text-xs font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">
                            Contenu
                        </p>
                        <div class="mt-2 space-y-1">
                            <Link
                                v-for="link in contentLinks"
                                :key="link.label"
                                :href="link.href ?? route(link.name)"
                                :title="link.label"
                                class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                                :class="
                                    isActive(link)
                                        ? 'bg-emerald-700 text-white'
                                        : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-white/5 dark:hover:text-white'
                                "
                            >
                                <i class="pi shrink-0" :class="link.icon" />
                                <span v-if="!sidebarCollapsed">{{ link.label }}</span>
                            </Link>
                        </div>
                    </div>

                    <div>
                        <p v-if="!sidebarCollapsed" class="px-3 text-xs font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">
                            Paiements
                        </p>
                        <div class="mt-2 space-y-1">
                            <Link
                                v-for="link in paymentLinks"
                                :key="link.label"
                                :href="link.href ?? route(link.name)"
                                :title="link.label"
                                class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                                :class="
                                    isActive(link)
                                        ? 'bg-emerald-700 text-white'
                                        : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-white/5 dark:hover:text-white'
                                "
                            >
                                <i class="pi shrink-0" :class="link.icon" />
                                <span v-if="!sidebarCollapsed">{{ link.label }}</span>
                            </Link>
                        </div>
                    </div>

                    <div v-if="isSuperAdmin">
                        <p v-if="!sidebarCollapsed" class="px-3 text-xs font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">
                            Administration
                        </p>
                        <div class="mt-2 space-y-1">
                            <Link
                                v-for="link in adminLinks"
                                :key="link.label"
                                :href="link.href ?? route(link.name)"
                                :title="link.label"
                                class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                                :class="
                                    isActive(link)
                                        ? 'bg-emerald-700 text-white'
                                        : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-white/5 dark:hover:text-white'
                                "
                            >
                                <i class="pi shrink-0" :class="link.icon" />
                                <span v-if="!sidebarCollapsed">{{ link.label }}</span>
                            </Link>
                        </div>
                    </div>
                </nav>
            </aside>

            <!-- Main column -->
            <div class="flex min-w-0 flex-1 flex-col">
                <!-- Topbar — global controls only: mobile nav toggle on the
                     left, theme switcher + profile on the right. Page
                     title/actions stay in the #header slot below, which
                     every Admin/*.vue page already owns the full width of. -->
                <div class="flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 dark:border-neutral-800 dark:bg-neutral-900 sm:px-6">
                    <button type="button" class="text-gray-500 dark:text-neutral-400 lg:hidden" @click="sidebarOpen = true">
                        <i class="pi pi-bars text-xl" />
                    </button>
                    <div class="hidden lg:block" />

                    <div class="flex items-center gap-2">
                        <NotificationBell />

                        <button
                            type="button"
                            title="Changer l'apparence"
                            class="flex h-9 w-9 items-center justify-center rounded-full text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 dark:text-neutral-400 dark:hover:bg-white/5 dark:hover:text-white"
                            @click="themeDark = !themeDark"
                        >
                            <i class="pi" :class="themeDark ? 'pi-sun' : 'pi-moon'" />
                        </button>

                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 transition hover:bg-neutral-100 dark:hover:bg-white/5"
                                >
                                    <span
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-xs font-semibold text-white"
                                    >
                                        {{ userInitials }}
                                    </span>
                                    <span class="hidden text-left sm:block">
                                        <span class="block text-sm font-medium leading-tight text-neutral-900 dark:text-white">
                                            {{ page.props.auth.user.name }}
                                        </span>
                                        <span class="block text-xs leading-tight text-neutral-500 dark:text-neutral-400">
                                            {{ isSuperAdmin ? 'Super admin' : 'Admin' }}
                                        </span>
                                    </span>
                                    <i class="pi pi-angle-down hidden text-xs text-neutral-400 sm:block" />
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
                </div>

                <header class="border-b border-gray-200 bg-white dark:border-neutral-800 dark:bg-neutral-900" v-if="$slots.header">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        <slot name="header" />
                    </div>
                </header>

                <main class="flex-1">
                    <slot />
                </main>

                <footer class="border-t border-gray-200 bg-white px-4 py-4 text-center text-xs text-neutral-400 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-500 sm:px-6">
                    © {{ new Date().getFullYear() }} Geneva Bengal — Back-office
                </footer>
            </div>
        </div>
    </div>
</template>
