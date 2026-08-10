<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PageProps } from '@/types';
import SocialLinks from '@/Components/SocialLinks.vue';
import ScrollToTop from '@/Components/ScrollToTop.vue';
import logo from '../../images/shared/logo-gb.png';
import logoFooter from '../../images/shared/logo-footer.png';

const page = usePage<PageProps>();
const currentLocale = computed(() => page.props.locale);

// Inertia visits swap page.props reactively without remounting app.ts, so
// vue-i18n's active locale (fixed at mount time otherwise) needs to be kept
// in sync here — this layout wraps every public page.
const { locale: i18nLocale } = useI18n();
watch(currentLocale, (value) => (i18nLocale.value = value), { immediate: true });
const menuPages = computed(() => page.props.menuPages);
const colors = computed(() => page.props.colors);
const alternateUrls = computed(() => page.props.alternateUrls);
const socialLinks = computed(() => page.props.socialLinks);
const address = computed(() => page.props.address);

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

// Mobile off-canvas menu: closed on every navigation (Inertia doesn't
// unmount this layout between page visits), with its own nested
// accordions for the two CMS-driven submenus.
const mobileMenuOpen = ref(false);
const mobileOpenSection = ref<'race_info' | 'adoption' | 'colors' | null>(null);

function toggleMobileSection(section: 'race_info' | 'adoption' | 'colors'): void {
    mobileOpenSection.value = mobileOpenSection.value === section ? null : section;
}

watch(
    () => page.url,
    () => {
        mobileMenuOpen.value = false;
        mobileOpenSection.value = null;
    },
);
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

    <div class="bg-brand-canvas flex min-h-screen flex-col">
        <header class="bg-brand-ink font-heading text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-2 text-xs tracking-wide uppercase sm:justify-end">
                <SocialLinks
                    class="[&_a]:hover:text-brand-green"
                    v-bind="socialLinks"
                />
                <ul class="flex items-center gap-4">
                    <!-- Duplicated in the mobile drawer already — hidden
                         below `sm` to stop this bar from wrapping. -->
                    <li class="hidden sm:block"><Link :href="route('pages.a-propos')" class="hover:text-brand-green">{{ $t('nav.about') }}</Link></li>
                    <li class="hidden sm:block"><Link :href="route('galleries.index')" class="hover:text-brand-green">{{ $t('nav.gallery') }}</Link></li>
                    <li class="hidden sm:block"><Link :href="route('pages.contact')" class="hover:text-brand-green">{{ $t('nav.contact') }}</Link></li>
                    <li>
                        <!-- Plain <a>, not <Link>: switching locale must be a
                             full browser navigation, not an Inertia SPA visit.
                             window.Ziggy (Ziggy's route() table) is injected
                             once by the `@routes` Blade directive and never
                             refreshed on SPA visits, so every route()-built
                             link on the page would keep resolving against
                             whichever locale was active on the very first
                             load — a hard reload is what actually gets a
                             fresh, correctly-prefixed window.Ziggy. -->
                        <a :href="switchLocaleHref('fr')" :class="{ underline: currentLocale === 'fr' }">FR</a>
                        /
                        <a :href="switchLocaleHref('en')" :class="{ underline: currentLocale === 'en' }">EN</a>
                    </li>
                </ul>
            </div>
        </header>

        <nav class="sticky top-0 z-50 border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-3 md:gap-10">
                <Link href="/">
                    <img :src="logo" alt="Geneva Bengal" class="h-14 w-auto sm:h-16" />
                </Link>
                <ul class="font-heading hidden items-center gap-8 text-sm font-semibold tracking-wide uppercase md:flex">
                    <li v-if="raceInfoPages.length" class="group relative py-3">
                        <span class="hover:text-brand-tan flex cursor-default items-center gap-1">
                            {{ $t('nav.race_info') }}
                            <svg viewBox="0 0 24 24" class="h-3 w-3 transition group-hover:rotate-180" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </span>
                        <ul
                            class="invisible absolute left-0 z-50 min-w-56 -translate-y-1 rounded-xl bg-white py-2 text-brand-gray! opacity-0 shadow-xl ring-1 ring-black/5 transition duration-200 normal-case group-hover:visible group-hover:translate-y-0 group-hover:opacity-100"
                        >
                            <li v-for="item in raceInfoPages" :key="item.id">
                                <Link :href="route('pages.show', item.slug)" class="hover:bg-brand-tan block px-5 py-2.5 transition hover:text-white">
                                    {{ item.title }}
                                </Link>
                            </li>
                        </ul>
                    </li>
                    <li><Link :href="route('cats.breeders')" class="hover:text-brand-tan">{{ $t('nav.breeders') }}</Link></li>
                    <li><Link :href="route('litters.index')" class="hover:text-brand-tan">{{ $t('nav.litters') }}</Link></li>
                    <li v-if="adoptionPages.length" class="group relative py-3">
                        <span class="hover:text-brand-tan flex cursor-default items-center gap-1">
                            {{ $t('nav.adoption') }}
                            <svg viewBox="0 0 24 24" class="h-3 w-3 transition group-hover:rotate-180" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </span>
                        <ul
                            class="invisible absolute left-0 z-50 min-w-56 -translate-y-1 rounded-xl bg-white py-2 text-brand-gray! opacity-0 shadow-xl ring-1 ring-black/5 transition duration-200 normal-case group-hover:visible group-hover:translate-y-0 group-hover:opacity-100"
                        >
                            <li v-for="item in adoptionPages" :key="item.id">
                                <Link :href="route('pages.show', item.slug)" class="hover:bg-brand-tan block px-5 py-2.5 transition hover:text-white">
                                    {{ item.title }}
                                </Link>
                            </li>
                        </ul>
                    </li>
                    <li v-if="colors.length" class="group relative py-3">
                        <Link :href="route('cats.index')" class="btn-outline-brand !px-5 !py-2 !text-xs">
                            {{ $t('nav.kittens_cta') }}
                            <svg viewBox="0 0 24 24" class="h-3 w-3 transition group-hover:rotate-180" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </Link>
                        <ul
                            class="invisible absolute right-0 z-50 min-w-56 -translate-y-1 rounded-xl bg-white py-2 text-brand-gray! opacity-0 shadow-xl ring-1 ring-black/5 transition duration-200 normal-case group-hover:visible group-hover:translate-y-0 group-hover:opacity-100"
                        >
                            <li v-for="color in colors" :key="color.id">
                                <Link
                                    :href="route('cats.index.color', color.slug)"
                                    class="hover:bg-brand-tan flex items-center gap-2 px-5 py-2.5 transition hover:text-white"
                                >
                                    <span class="h-3 w-3 shrink-0 rounded-full ring-1 ring-black/10" :style="{ backgroundColor: color.hex_code }" />
                                    {{ color.name }}
                                </Link>
                            </li>
                        </ul>
                    </li>
                    <li v-else>
                        <Link :href="route('cats.index')" class="btn-outline-brand !px-5 !py-2 !text-xs">
                            {{ $t('nav.kittens_cta') }}
                        </Link>
                    </li>
                </ul>

                <button
                    type="button"
                    class="text-brand-ink flex h-10 w-10 items-center justify-center md:hidden"
                    :aria-label="$t('nav.open_menu')"
                    @click="mobileMenuOpen = true"
                >
                    <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <line x1="4" y1="7" x2="20" y2="7" />
                        <line x1="4" y1="12" x2="20" y2="12" />
                        <line x1="4" y1="17" x2="20" y2="17" />
                    </svg>
                </button>
            </div>
        </nav>

        <!-- Mobile off-canvas menu -->
        <Transition name="backdrop-fade">
            <div v-if="mobileMenuOpen" class="fixed inset-0 z-50 bg-black/50 md:hidden" @click="mobileMenuOpen = false" />
        </Transition>
        <Transition name="panel-slide">
            <div v-if="mobileMenuOpen" class="bg-brand-ink fixed inset-y-0 left-0 z-50 w-[85vw] max-w-sm overflow-y-auto text-white md:hidden">
                <div class="flex items-center justify-between px-6 py-4">
                    <img :src="logo" alt="Geneva Bengal" class="h-12 w-auto" />
                    <button type="button" class="flex h-10 w-10 items-center justify-center" :aria-label="$t('nav.close_menu')" @click="mobileMenuOpen = false">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <line x1="6" y1="6" x2="18" y2="18" />
                            <line x1="18" y1="6" x2="6" y2="18" />
                        </svg>
                    </button>
                </div>
                <ul class="font-heading px-6 pb-10 text-sm font-semibold tracking-wide uppercase">
                    <li class="border-b border-white/10">
                        <Link href="/" class="block py-4">{{ $t('nav.home') }}</Link>
                    </li>
                    <li class="border-b border-white/10">
                        <div class="flex items-center justify-between">
                            <Link :href="route('cats.index')" class="text-brand-green block py-4">{{ $t('nav.kittens_cta') }}</Link>
                            <button
                                v-if="colors.length"
                                type="button"
                                class="flex h-10 w-10 items-center justify-center"
                                :aria-label="$t('nav.filter_by_color')"
                                @click="toggleMobileSection('colors')"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="h-3 w-3 transition"
                                    :class="{ 'rotate-180': mobileOpenSection === 'colors' }"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="3"
                                >
                                    <polyline points="6 9 12 15 18 9" />
                                </svg>
                            </button>
                        </div>
                        <ul v-if="colors.length" v-show="mobileOpenSection === 'colors'" class="text-brand-tan space-y-3 pb-4 pl-4 text-xs normal-case">
                            <li v-for="color in colors" :key="color.id">
                                <Link :href="route('cats.index.color', color.slug)" class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full ring-1 ring-white/30" :style="{ backgroundColor: color.hex_code }" />
                                    {{ color.name }}
                                </Link>
                            </li>
                        </ul>
                    </li>
                    <li v-if="raceInfoPages.length" class="border-b border-white/10">
                        <button type="button" class="flex w-full items-center justify-between py-4" @click="toggleMobileSection('race_info')">
                            {{ $t('nav.race_info') }}
                            <svg
                                viewBox="0 0 24 24"
                                class="h-3 w-3 transition"
                                :class="{ 'rotate-180': mobileOpenSection === 'race_info' }"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3"
                            >
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <ul v-show="mobileOpenSection === 'race_info'" class="text-brand-tan space-y-3 pb-4 pl-4 text-xs normal-case">
                            <li v-for="item in raceInfoPages" :key="item.id">
                                <Link :href="route('pages.show', item.slug)" class="block">{{ item.title }}</Link>
                            </li>
                        </ul>
                    </li>
                    <li class="border-b border-white/10">
                        <Link :href="route('cats.breeders')" class="block py-4">{{ $t('nav.breeders') }}</Link>
                    </li>
                    <li class="border-b border-white/10">
                        <Link :href="route('litters.index')" class="block py-4">{{ $t('nav.litters') }}</Link>
                    </li>
                    <li v-if="adoptionPages.length" class="border-b border-white/10">
                        <button type="button" class="flex w-full items-center justify-between py-4" @click="toggleMobileSection('adoption')">
                            {{ $t('nav.adoption') }}
                            <svg
                                viewBox="0 0 24 24"
                                class="h-3 w-3 transition"
                                :class="{ 'rotate-180': mobileOpenSection === 'adoption' }"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3"
                            >
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <ul v-show="mobileOpenSection === 'adoption'" class="text-brand-tan space-y-3 pb-4 pl-4 text-xs normal-case">
                            <li v-for="item in adoptionPages" :key="item.id">
                                <Link :href="route('pages.show', item.slug)" class="block">{{ item.title }}</Link>
                            </li>
                        </ul>
                    </li>
                    <li class="border-b border-white/10">
                        <Link :href="route('galleries.index')" class="block py-4">{{ $t('nav.gallery') }}</Link>
                    </li>
                    <li class="border-b border-white/10">
                        <Link :href="route('pages.a-propos')" class="block py-4">{{ $t('nav.about') }}</Link>
                    </li>
                    <li>
                        <Link :href="route('pages.contact')" class="block py-4">{{ $t('nav.contact_us') }}</Link>
                    </li>
                </ul>
                <div class="px-6 pb-10">
                    <SocialLinks v-bind="socialLinks" class="[&_a]:hover:text-brand-green" />
                </div>
            </div>
        </Transition>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="bg-brand-ink text-neutral-300">
            <div class="mx-auto max-w-7xl px-6 pt-16 pb-10">
                <div class="grid grid-cols-1 gap-x-12 gap-y-12 text-center sm:grid-cols-2 sm:text-left lg:grid-cols-4">
                    <div>
                        <img :src="logoFooter" alt="Geneva Bengal" class="mx-auto h-24 w-auto rounded-full sm:mx-0" />
                        <p v-if="address" class="my-8 text-sm text-neutral-200">{{ address }}</p>
                        <SocialLinks v-bind="socialLinks" class="[&_a]:hover:text-brand-green justify-center sm:justify-start" />
                    </div>
                    <div>
                        <h5 class="font-heading flex min-h-14 items-end justify-center text-sm font-bold tracking-wide text-white uppercase sm:justify-start">
                            {{ $t('footer.about_heading') }}
                        </h5>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li><Link :href="route('pages.a-propos')" class="hover:text-brand-green">{{ $t('footer.our_story') }}</Link></li>
                            <li><Link :href="`${route('pages.a-propos')}#temoignages`" class="hover:text-brand-green">{{ $t('footer.testimonials') }}</Link></li>
                            <li><Link :href="route('pages.contact')" class="hover:text-brand-green">{{ $t('nav.contact_us') }}</Link></li>
                        </ul>
                    </div>
                    <div>
                        <h5 class="font-heading flex min-h-14 items-end justify-center text-sm font-bold tracking-wide text-white uppercase sm:justify-start">
                            {{ $t('footer.race_info_heading') }}
                        </h5>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li v-for="item in raceInfoPages" :key="item.id">
                                <Link :href="route('pages.show', item.slug)" class="hover:text-brand-green">{{ item.title }}</Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h5 class="font-heading flex min-h-14 items-end justify-center text-sm font-bold tracking-wide text-white uppercase sm:justify-start">
                            {{ $t('footer.adoption_heading') }}
                        </h5>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li v-for="item in adoptionPages" :key="item.id">
                                <Link :href="route('pages.show', item.slug)" class="hover:text-brand-green">{{ item.title }}</Link>
                            </li>
                        </ul>
                    </div>
                </div>

                <p class="mt-12 border-t border-white/90 pt-6 text-center text-sm">
                    © {{ new Date().getFullYear() }} Geneva Bengal — {{ $t('footer.rights_reserved') }}
                </p>
            </div>
        </footer>

        <ScrollToTop />
    </div>
</template>

<style scoped>
.backdrop-fade-enter-active,
.backdrop-fade-leave-active {
    transition: opacity 0.3s ease;
}
.backdrop-fade-enter-from,
.backdrop-fade-leave-to {
    opacity: 0;
}
.panel-slide-enter-active,
.panel-slide-leave-active {
    transition: transform 0.3s ease-in-out;
}
.panel-slide-enter-from,
.panel-slide-leave-to {
    transform: translateX(-100%);
}
</style>
