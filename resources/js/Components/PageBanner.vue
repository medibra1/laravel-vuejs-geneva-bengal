<script setup lang="ts">
import { useImageSlider } from '@/Composables/useImageSlider';
import type { Gallery } from '@/types/models';

const props = withDefaults(
    defineProps<{
        /** Script-font ("Sacramento") line, e.g. "Chatons Bengal". */
        script: string;
        /** Optional bold line underneath, e.g. a cat's name or a tagline. */
        subtitle?: string;
        /**
         * Same hero_slide Gallery rows as the homepage's HeroSlider.vue —
         * every page using PageBanner wants the same ambient rotation, none
         * pick their own image. Passed in (not fetched here) since sharing
         * it globally via HandleInertiaRequests would run the query on
         * every request, admin included.
         */
        slides: Gallery[];
    }>(),
    {
        subtitle: undefined,
    },
);

const { currentSlide } = useImageSlider(() => props.slides.length);

// Real <img> with srcset — see HeroSlider.vue for the same reasoning.
// This banner is never the page's LCP element (it's a secondary strip,
// the page heading/body text above the fold usually wins that), so no
// fetchpriority override — plain eager/lazy per slide is enough.
function srcsetFor(slide: Gallery): string | undefined {
    const widths: Array<[string | null | undefined, number]> = [
        [slide.image_sm_url, 480],
        [slide.image_md_url, 800],
        [slide.image_lg_url, 1400],
    ];

    const set = widths
        .filter((entry): entry is [string, number] => Boolean(entry[0]))
        .map(([url, width]) => `${url} ${width}w`)
        .join(', ');

    return set || undefined;
}
</script>

<template>
    <section
        v-if="slides.length"
        class="relative flex h-64 items-center justify-center overflow-hidden bg-neutral-900 text-white sm:h-80"
    >
        <Transition name="fade">
            <img
                :key="currentSlide"
                :src="slides[currentSlide].image_lg_url ?? slides[currentSlide].image_url ?? ''"
                :srcset="srcsetFor(slides[currentSlide])"
                sizes="100vw"
                :loading="currentSlide === 0 ? 'eager' : 'lazy'"
                alt=""
                class="absolute inset-0 h-full w-full object-cover object-center"
            />
        </Transition>
        <div class="absolute inset-0 bg-neutral-900/50" />
        <div class="relative px-6 text-center">
            <p class="font-script text-4xl sm:text-5xl">{{ script }}</p>
            <p v-if="subtitle" class="font-heading mt-2 text-sm tracking-widest uppercase">{{ subtitle }}</p>
        </div>
    </section>
</template>

<style scoped>
.fade-enter-active {
    transition: opacity 1.2s ease-in-out;
}
.fade-leave-active {
    transition: opacity 1.2s ease-in-out;
    position: absolute;
    inset: 0;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
