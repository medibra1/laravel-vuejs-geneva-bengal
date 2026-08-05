<script setup lang="ts">
withDefaults(
    defineProps<{
        facebook?: string | null;
        instagram?: string | null;
        youtube?: string | null;
        pinterest?: string | null;
    }>(),
    {
        facebook: null,
        instagram: null,
        youtube: null,
        pinterest: null,
    },
);
</script>

<template>
    <ul class="flex items-center gap-4">
        <li v-if="facebook">
            <a :href="facebook" target="_blank" rel="noopener" title="Suivez-nous sur Facebook" aria-label="Facebook">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor">
                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
                </svg>
            </a>
        </li>
        <li v-if="instagram">
            <a :href="instagram" target="_blank" rel="noopener" title="Suivez-nous sur Instagram" aria-label="Instagram">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="5" ry="5" />
                    <circle cx="12" cy="12" r="4" />
                    <line x1="17.5" y1="6.5" x2="17.5" y2="6.5" />
                </svg>
            </a>
        </li>
        <li v-if="youtube">
            <a :href="youtube" target="_blank" rel="noopener" title="Suivez-nous sur YouTube" aria-label="YouTube">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path
                        d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"
                    />
                    <polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02" fill="currentColor" stroke="none" />
                </svg>
            </a>
        </li>
    </ul>
</template>
