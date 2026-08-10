<script setup lang="ts">
import { useImageSlider } from '@/Composables/useImageSlider';
import banner1 from '../../images/home/slider-1.jpg';
import banner2 from '../../images/home/slider-2.jpg';

withDefaults(
    defineProps<{
        /** Script-font ("Sacramento") line, e.g. "Chatons Bengal". */
        script: string;
        /** Optional bold line underneath, e.g. a cat's name or a tagline. */
        subtitle?: string;
    }>(),
    {
        subtitle: undefined,
    },
);

// Same curated 1920x1275 photo set as HeroSlider.vue's homepage hero — kept
// local to this component (no `images` prop) since every page using
// PageBanner wants the same ambient rotation, none pick their own image.
const images = [banner1, banner2];

const { currentSlide } = useImageSlider(() => images.length);
</script>

<template>
    <section class="relative flex h-64 items-center justify-center overflow-hidden bg-neutral-900 text-white sm:h-80">
        <Transition name="fade">
            <div
                :key="currentSlide"
                class="absolute inset-0 bg-cover bg-center"
                :style="{ backgroundImage: `url(${images[currentSlide]})` }"
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
