<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import NewsletterForm from '@/Components/NewsletterForm.vue';
import SectionHeading from '@/Components/SectionHeading.vue';
import HeroSlider from '@/Components/HeroSlider.vue';
import SocialLinks from '@/Components/SocialLinks.vue';
import type { PageProps } from '@/types';
// import slider1 from '../../../images/home/slider-1.jpg';
// import slider2 from '../../../images/home/slider-2.jpg';
import slider3 from '../../../images/home/slider-3.jpg';
import slider4 from '../../../images/home/slider-4.jpg';
import kittensMontage from '../../../images/home/kittens-montage.png';
import newsletterKitten from '../../../images/home/newsletter-kitten.png';
import internationalKitten from '../../../images/home/international-kitten.png';
import social1 from '../../../images/home/social/social-1.jpg';
import social2 from '../../../images/home/social/social-2.jpg';
import social3 from '../../../images/home/social/social-3.jpg';
import social4 from '../../../images/home/social/social-4.jpg';
import social5 from '../../../images/home/social/social-5.jpg';
import social6 from '../../../images/home/social/social-6.jpg';

defineProps<{
    seo: { title: string; description: string };
}>();

const socialTiles = [social1, social2, social3, social4, social5, social6];
const socialLinks = usePage<PageProps>().props.socialLinks;

// No dedicated page/route yet for international shipping info — placeholder.
// The social feed tiles/CTA below use the real socialLinks.facebook URL.
const placeholderHref = '#';

const slides = [
    // slider1,
    // slider2,
    slider3, slider4];
</script>

<template>

    <Head :title="seo.title">
        <meta head-key="description" name="description" :content="seo.description" />
    </Head>

    <PublicLayout>
        <HeroSlider :slides="slides" />

        <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
                <div>
                    <SectionHeading :script="$t('home.intro_script')" :title="$t('home.intro_title')" />
                    <p class="mt-4 text-neutral-600">
                        {{ $t('home.intro_body') }}
                    </p>
                    <Link :href="route('cats.index')" class="btn-outline-brand mt-6">
                        {{ $t('home.intro_cta') }}
                        <span aria-hidden="true">→</span>
                    </Link>

                    <div class="mt-10 space-y-6">
                        <div>
                            <h4 class="font-heading text-brand-green text-sm font-light">{{ $t('home.quality_title') }}</h4>
                            <p class="mt-1 text-neutral-600">
                                {{ $t('home.quality_body') }}
                            </p>
                        </div>
                        <div>
                            <h4 class="font-heading text-brand-green text-sm font-light">{{ $t('home.health_title') }}</h4>
                            <p class="mt-1 text-neutral-600">
                                {{ $t('home.health_body') }}
                            </p>
                        </div>
                        <div>
                            <h4 class="font-heading text-brand-green text-sm font-light">{{ $t('home.temperament_title') }}</h4>
                            <p class="mt-1 text-neutral-600">
                                {{ $t('home.temperament_body') }}
                            </p>
                        </div>
                    </div>
                </div>
                <div>
                    <img :src="kittensMontage" :alt="$t('home.montage_alt')"
                        class="w-full rounded-lg" />
                </div>
            </div>
        </section>

        <section class="bg-brand-ink py-16 text-white sm:py-24">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <SectionHeading :script="$t('home.testimonials_script')" :title="$t('home.testimonials_title')" inverted center />
                <p class="mt-4 text-neutral-300">
                    {{ $t('home.testimonials_body') }}
                </p>
                <Link :href="`${route('pages.a-propos')}#temoignages`"
                    class="btn-outline-brand mt-6 !border-white !text-white hover:!border-brand-green">
                    {{ $t('home.testimonials_cta') }}
                    <span aria-hidden="true">→</span>
                </Link>
            </div>
        </section>

        <section class="bg-brand-canvas border-b border-gray-200 py-16 sm:py-24">
            <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-12 px-6 lg:grid-cols-2">
                <div>
                    <SectionHeading :script="$t('newsletter.section_script')" :title="$t('newsletter.section_title')" />
                    <p class="mt-4 text-neutral-600">
                        {{ $t('newsletter.home_body') }}
                    </p>
                    <NewsletterForm class="mt-6 max-w-md" />
                </div>
                <div>
                    <img :src="newsletterKitten" alt="" class="mx-auto w-full max-w-sm rounded-full" />
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
                <div>
                    <SectionHeading :script="$t('home.shipping_script')"
                        :title="$t('home.shipping_title')" />
                    <p class="mt-4 text-neutral-600">
                        {{ $t('home.shipping_body') }}
                    </p>
                    <a :href="placeholderHref" class="btn-outline-brand mt-6">
                        {{ $t('home.shipping_cta') }}
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
                <div>
                    <img :src="internationalKitten" alt="" class="mx-auto w-full max-w-sm rounded-full" />
                </div>
            </div>
        </section>

        <section class="bg-brand-green py-10 text-white">
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-center gap-4 px-6 text-center sm:flex-row sm:justify-between sm:text-left">
                <div>
                    <h2 class="font-script text-3xl">{{ $t('home.social_script') }}</h2>
                    <h3 class="font-heading text-lg font-semibold tracking-wide uppercase">{{ $t('home.social_title') }}</h3>
                </div>
                <SocialLinks v-bind="socialLinks" class="text-2xl [&_svg]:h-7 [&_svg]:w-7" />
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
                <div class="grid grid-cols-3 gap-2">
                    <a v-for="(tile, index) in socialTiles" :key="index" :href="socialLinks.facebook ?? placeholderHref"
                        target="_blank" rel="noopener" class="aspect-square overflow-hidden rounded">
                        <img :src="tile" alt="" class="h-full w-full object-cover transition hover:scale-105" />
                    </a>
                </div>
                <div>
                    <SectionHeading :script="$t('home.facebook_script')" :title="$t('home.facebook_title')" />
                    <p class="mt-4 text-neutral-600">
                        {{ $t('home.facebook_body') }}
                    </p>
                    <a v-if="socialLinks.facebook" :href="socialLinks.facebook" target="_blank" rel="noopener"
                        class="btn-outline-brand mt-6">
                        {{ $t('home.facebook_cta') }}
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
