<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';

const visible = ref(false);

function onScroll(): void {
    visible.value = window.scrollY > 400;
}

function scrollToTop(): void {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }));
onUnmounted(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <Transition name="pop">
        <button
            v-if="visible"
            type="button"
            class="bg-brand-ink hover:bg-brand-green fixed right-6 bottom-6 z-40 flex h-11 w-11 items-center justify-center rounded-full text-white shadow-lg transition-colors"
            :aria-label="$t('scrollTop.aria_label')"
            @click="scrollToTop"
        >
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="18 15 12 9 6 15" />
            </svg>
        </button>
    </Transition>
</template>

<style scoped>
.pop-enter-active,
.pop-leave-active {
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}
.pop-enter-from,
.pop-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>
