<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const currentLocale = computed(() => page.props.locale);
const menuPages = computed(() => page.props.menuPages);
const alternateUrls = computed(() => page.props.alternateUrls);

const raceInfoPages = computed(() => menuPages.value.filter((p) => p.menu_group === 'race_info'));
const adoptionPages = computed(() => menuPages.value.filter((p) => p.menu_group === 'adoption'));

function switchLocaleHref(locale: string): string {
    if (alternateUrls.value[locale]) {
        return new URL(alternateUrls.value[locale]).pathname;
    }

    const segments = window.location.pathname.split('/').filter(Boolean);

    if (segments[0] === 'fr' || segments[0] === 'en') {
        segments[0] = locale;
    } else {
        segments.unshift(locale);
    }

    return '/' + segments.join('/');
}

// TODO: still no dedicated route for these (Phase 4/Galerie).
const placeholderHref = '#';
</script>

<template>
    <Head>
        <link
            v-for="(url, locale) in alternateUrls"
            :key="locale"
            rel="alternate"
            :hreflang="locale"
            :href="url"
        />
        <link v-if="alternateUrls.fr" rel="alternate" hreflang="x-default" :href="alternateUrls.fr" />
    </Head>

    <div class="flex min-h-screen flex-col">
        <header class="bg-neutral-900 text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-end gap-6 px-6 py-2 text-sm">
                <ul class="flex gap-4">
                    <li><Link :href="route('pages.a-propos')">À propos</Link></li>
                    <li><a :href="placeholderHref">Galerie photo</a></li>
                    <li><Link :href="route('pages.contact')">Contact</Link></li>
                    <li>
                        <Link :href="switchLocaleHref('fr')" :class="{ underline: currentLocale === 'fr' }">FR</Link>
                        /
                        <Link :href="switchLocaleHref('en')" :class="{ underline: currentLocale === 'en' }">EN</Link>
                    </li>
                </ul>
            </div>
        </header>

        <nav class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <Link href="/" class="text-2xl font-semibold text-emerald-700">
                    Geneva Bengal
                </Link>
                <ul class="hidden items-center gap-8 text-sm font-medium md:flex">
                    <li v-if="raceInfoPages.length" class="group relative">
                        <span class="cursor-default">Information sur le bengal</span>
                        <ul class="absolute left-0 hidden min-w-48 border bg-white py-2 shadow-lg group-hover:block">
                            <li v-for="item in raceInfoPages" :key="item.id">
                                <Link :href="route('pages.show', item.slug)" class="block px-4 py-2 hover:bg-gray-50">
                                    {{ item.title }}
                                </Link>
                            </li>
                        </ul>
                    </li>
                    <li><a :href="placeholderHref">Nos chats reproducteurs</a></li>
                    <li><a :href="placeholderHref">Portées prévues</a></li>
                    <li v-if="adoptionPages.length" class="group relative">
                        <span class="cursor-default">Adoption et prix</span>
                        <ul class="absolute left-0 hidden min-w-48 border bg-white py-2 shadow-lg group-hover:block">
                            <li v-for="item in adoptionPages" :key="item.id">
                                <Link :href="route('pages.show', item.slug)" class="block px-4 py-2 hover:bg-gray-50">
                                    {{ item.title }}
                                </Link>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <Link :href="route('cats.index')" class="text-emerald-700">
                            Chaton Bengal Disponible
                        </Link>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="bg-neutral-900 py-12 text-neutral-300">
            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-6 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-lg font-semibold text-white">Geneva Bengal</p>
                    <p class="mt-2 text-sm">1209 Genève, Suisse</p>
                </div>
                <div>
                    <h5 class="font-semibold text-white">À propos de nous</h5>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li><Link :href="route('pages.a-propos')">Notre histoire</Link></li>
                        <li><Link :href="`${route('pages.a-propos')}#temoignages`">Témoignages</Link></li>
                        <li><Link :href="route('pages.contact')">Contactez-nous</Link></li>
                    </ul>
                </div>
                <div>
                    <h5 class="font-semibold text-white">Information sur le chat Bengal</h5>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li v-for="item in raceInfoPages" :key="item.id">
                            <Link :href="route('pages.show', item.slug)">{{ item.title }}</Link>
                        </li>
                    </ul>
                </div>
                <div>
                    <h5 class="font-semibold text-white">Guide d'adoption</h5>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li v-for="item in adoptionPages" :key="item.id">
                            <Link :href="route('pages.show', item.slug)">{{ item.title }}</Link>
                        </li>
                    </ul>
                </div>
            </div>
            <p class="mt-10 text-center text-xs text-neutral-500">
                © {{ new Date().getFullYear() }} Geneva Bengal — Tous droits réservés.
            </p>
        </footer>
    </div>
</template>
