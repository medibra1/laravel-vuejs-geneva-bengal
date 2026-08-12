<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        sm?: string | null;
        md?: string | null;
        lg?: string | null;
        fallback: string;
        sizes: string;
        alt: string;
        class?: string;
        /** Skip loading="lazy"/decoding="async" for above-the-fold images. */
        eager?: boolean;
    }>(),
    {
        sm: null,
        md: null,
        lg: null,
        class: undefined,
        eager: false,
    },
);

const src = computed(() => props.md ?? props.fallback);
// `class` alone is a reserved word in a template expression (parsed as a
// class declaration), so the prop can't be read as `:class="class"` —
// exposed as `imgClass` instead.
const imgClass = computed(() => props.class);

const srcset = computed(() => {
    const widths: Array<[string | null, number]> = [
        [props.sm, 480],
        [props.md, 800],
        [props.lg, 1400],
    ];

    return widths
        .filter((entry): entry is [string, number] => entry[0] !== null)
        .map(([url, width]) => `${url} ${width}w`)
        .join(', ');
});
</script>

<template>
    <img
        :src="src"
        :srcset="srcset || undefined"
        :sizes="sizes"
        :alt="alt"
        :class="imgClass"
        :loading="eager ? undefined : 'lazy'"
        :decoding="eager ? undefined : 'async'"
    />
</template>
