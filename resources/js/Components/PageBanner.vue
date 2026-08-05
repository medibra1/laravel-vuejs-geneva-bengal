<script setup lang="ts">
import banner from '../../images/shared/page-banner.jpg';

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
</script>

<template>
    <section class="relative flex h-64 items-center justify-center overflow-hidden bg-neutral-900 text-white sm:h-80">
        <div class="absolute inset-0 bg-cover bg-top" :style="{ backgroundImage: `url(${banner})` }" />
        <div class="absolute inset-0 bg-neutral-900/50" />
        <div class="relative px-6 text-center">
            <p class="font-script text-4xl sm:text-5xl">{{ script }}</p>
            <p v-if="subtitle" class="font-heading mt-2 text-sm tracking-widest uppercase">{{ subtitle }}</p>
        </div>
    </section>
</template>
