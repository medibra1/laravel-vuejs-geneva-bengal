<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const currentLocale = computed(() => page.props.locale);

function switchLocaleHref(locale: string): string {
    const segments = window.location.pathname.split('/').filter(Boolean);

    if (segments[0] === 'fr' || segments[0] === 'en') {
        segments[0] = locale;
    } else {
        segments.unshift(locale);
    }

    return '/' + segments.join('/');
}

// TODO: these become real routes as their phases land (À propos/Contact/
// Galerie: Phase 3-4; info & adoption sub-pages: Phase 3 CMS pages).
const placeholderHref = '#';
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <header class="bg-neutral-900 text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-end gap-6 px-6 py-2 text-sm">
                <ul class="flex gap-4">
                    <li><a :href="placeholderHref">À propos</a></li>
                    <li><a :href="placeholderHref">Galerie photo</a></li>
                    <li><a :href="placeholderHref">Contact</a></li>
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
                    <li><a :href="placeholderHref">Information sur le bengal</a></li>
                    <li><a :href="placeholderHref">Nos chats reproducteurs</a></li>
                    <li><a :href="placeholderHref">Portées prévues</a></li>
                    <li><a :href="placeholderHref">Adoption et prix</a></li>
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
                        <li><a :href="placeholderHref">Notre histoire</a></li>
                        <li><a :href="placeholderHref">Témoignages</a></li>
                        <li><a :href="placeholderHref">Contactez-nous</a></li>
                    </ul>
                </div>
                <div>
                    <h5 class="font-semibold text-white">Information sur le chat Bengal</h5>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li><a :href="placeholderHref">Race</a></li>
                        <li><a :href="placeholderHref">Motifs et Couleurs</a></li>
                        <li><a :href="placeholderHref">Santé</a></li>
                    </ul>
                </div>
                <div>
                    <h5 class="font-semibold text-white">Guide d'adoption</h5>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li><a :href="placeholderHref">Étapes pour adopter un chaton Bengal</a></li>
                        <li><a :href="placeholderHref">FAQ</a></li>
                    </ul>
                </div>
            </div>
            <p class="mt-10 text-center text-xs text-neutral-500">
                © {{ new Date().getFullYear() }} Geneva Bengal — Tous droits réservés.
            </p>
        </footer>
    </div>
</template>
