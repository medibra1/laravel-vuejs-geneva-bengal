<script setup lang="ts">
import chatonMobile from '../../images/shared/chaton-mobile.png';
import chatonMobileWhite from '../../images/shared/chaton-mobile-white.png';

withDefaults(
    defineProps<{
        /** The script-font ("Sacramento") line, e.g. "Faits avec amour". */
        script: string;
        /** The bold uppercase subtitle underneath, e.g. "Nous avons des chatons !". */
        title?: string;
        /** Show the small decorative kitten mark above the title. */
        icon?: boolean;
        /** White text/icon variant for dark section backgrounds. */
        inverted?: boolean;
        /** Center the subtitle too (the script title is always centered). */
        center?: boolean;
    }>(),
    {
        title: undefined,
        icon: true,
        inverted: false,
        center: false,
    },
);
</script>

<template>
    <div>
        <img v-if="icon" :src="inverted ? chatonMobileWhite : chatonMobile" alt="" class="mx-auto h-[75px] w-[90px] object-contain" />
        <h2 class="font-script text-center text-5xl sm:text-6xl" :class="inverted ? 'text-white' : 'text-brand-gray'">
            {{ script }}<span class="text-brand-green">_</span>
        </h2>
        <h3
            v-if="title"
            class="font-heading text-brand-tan mt-2 text-xl font-bold tracking-wide uppercase"
            :class="center ? 'text-center' : ''"
        >
            {{ title }}
        </h3>
    </div>
</template>
