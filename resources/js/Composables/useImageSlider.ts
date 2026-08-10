import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Auto-advancing crossfade slide index, shared by HeroSlider.vue and
 * PageBanner.vue — both just render a different background/overlay around
 * the same `currentSlide` index. `slideCount` is a getter (not a plain
 * number) so it stays correct even if the caller's slide list length can
 * change after mount.
 */
export function useImageSlider(slideCount: () => number, intervalMs = 6500) {
    const currentSlide = ref(0);
    let timer: ReturnType<typeof setInterval> | undefined;

    function restart(): void {
        clearInterval(timer);
        if (slideCount() > 1) timer = setInterval(next, intervalMs);
    }

    function next(): void {
        currentSlide.value = (currentSlide.value + 1) % slideCount();
    }

    function previous(): void {
        currentSlide.value = (currentSlide.value - 1 + slideCount()) % slideCount();
    }

    function goTo(index: number): void {
        currentSlide.value = index;
        restart();
    }

    onMounted(restart);
    onUnmounted(() => clearInterval(timer));

    return { currentSlide, next, previous, goTo, restart };
}
