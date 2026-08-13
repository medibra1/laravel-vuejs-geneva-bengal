<script setup lang="ts">
import { computed } from 'vue';
import catHead from '../../images/home/cat-head.png';
import { useImageSlider } from '@/Composables/useImageSlider';
import type { Gallery } from '@/types/models';

const props = defineProps<{
    slides: Gallery[];
}>();

const { currentSlide, next, previous, goTo, restart } = useImageSlider(() => props.slides.length);

// No srcset possible on a CSS background-image — the hero is always
// full-bleed, so the largest conversion is the correct choice regardless
// of viewport (unlike an <img>, there's no smaller-viewport case where a
// smaller file would still look right here).
const backgroundUrls = computed(() => props.slides.map((slide) => slide.image_lg_url ?? slide.image_url ?? ''));
</script>

<template>
    <section
        v-if="slides.length"
        class="relative flex h-[70vh] min-h-[520px] items-center overflow-hidden bg-neutral-900 text-white sm:h-[85vh]"
    >
        <Transition name="fade">
            <div :key="currentSlide" class="kenburns absolute inset-0 bg-cover bg-top" :style="{ backgroundImage: `url(${backgroundUrls[currentSlide]})` }" />
        </Transition>
        <!-- Matches bengal-client's .slider-image:before exactly: a dark
             vignette behind the text card, fading bottom-to-top on mobile
             (card sits below the image) and right-to-left on larger
             screens (card floats on the right). -->
        <div
            class="absolute inset-0 bg-gradient-to-t from-black/80 from-45% to-transparent to-65% sm:bg-gradient-to-l sm:from-black/90 sm:from-15% sm:via-black/50 sm:via-35% sm:to-transparent sm:to-60%"
        />

        <!-- Text card, paired with the decorative kitten-vignette mark
             behind it — mirrors bengal-client's slider-description-content,
             modernized into a floating right-aligned card. -->
        <div class="relative mx-auto flex w-full max-w-7xl justify-center px-6 sm:justify-end sm:pr-0 sm:mr-10">
            <div
                class="hero-cat-head relative flex min-h-[30rem] max-w-lg min-w-[22rem] flex-col items-center justify-center bg-contain bg-center bg-no-repeat px-12 pt-32 pb-16 text-center sm:min-h-[36rem] sm:min-w-[32rem] sm:px-16 sm:py-20"
                :style="{ backgroundImage: `url(${catHead})` }"
            >
                <p class="font-script text-4xl sm:text-6xl">{{ $t('hero.tagline_script') }}</p>
                <h1 class="font-round mt-2 text-2xl tracking-[0.1em] uppercase sm:text-5xl">{{ $t('hero.tagline_title') }}</h1>
                <p class="font-heading mt-5 text-lg font-semibold sm:text-xl">{{ $t('hero.tagline_subtitle') }}</p>
                <p class="mt-5 max-w-[15rem] text-xs font-semibold text-neutral-200 sm:max-w-[20rem] sm:text-base">
                    {{ $t('hero.tagline_body') }}
                </p>
            </div>
        </div>

        <!-- Arrows -->
        <button
            type="button"
            class="absolute top-1/2 left-4 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 backdrop-blur transition hover:bg-white/25"
            :aria-label="$t('common.prev_photo')"
            @click="previous(); restart();"
        >
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6" />
            </svg>
        </button>
        <button
            type="button"
            class="absolute top-1/2 right-4 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 backdrop-blur transition hover:bg-white/25"
            :aria-label="$t('common.next_photo')"
            @click="next(); restart();"
        >
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6" />
            </svg>
        </button>

        <!-- Dots -->
        <div class="absolute bottom-6 left-1/2 flex -translate-x-1/2 gap-2">
            <button
                v-for="(slide, index) in slides"
                :key="index"
                type="button"
                class="h-2 rounded-full transition-all"
                :class="index === currentSlide ? 'bg-brand-green w-8' : 'w-2 bg-white/50 hover:bg-white/80'"
                :aria-label="$t('hero.go_to_photo', { n: index + 1 })"
                @click="goTo(index)"
            />
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

.kenburns {
    animation: kenburns 6.5s ease-out forwards;
}
@keyframes kenburns {
    from {
        transform: scale(1);
    }
    to {
        transform: scale(1.08);
    }
}

@media (prefers-reduced-motion: reduce) {
    .kenburns {
        animation: none;
    }
}

/* Cat-head vignette is a desktop/tablet flourish only — matches
   bengal-client, which drops it below its "normal" breakpoint entirely
   rather than shrinking it. Needs !important: the image itself is set via
   an inline :style binding (dynamic Vite asset URL, can't be a Tailwind
   class), which otherwise outranks a plain scoped-CSS override. */
@media (max-width: 639px) {
    .hero-cat-head {
        background-image: none !important;
    }
}
</style>
