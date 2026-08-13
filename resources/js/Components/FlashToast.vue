<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";
import { watch } from "vue";
import type { PageProps } from "@/types";

// Renders session flash messages (set by controllers via
// ->with('success'|'error', ...), shared globally through
// HandleInertiaRequests::share()) as a PrimeVue toast. Mounted once in
// both AdminLayout.vue and PublicLayout.vue.
//
// Watches page.props.flash rather than reading it once: Inertia reuses the
// same mounted layout across visits (no remount), so a plain onMounted read
// would only ever fire for the very first page load.
const page = usePage<PageProps>();
const toast = useToast();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            toast.add({ severity: "success", summary: flash.success, life: 4000 });
        }
        if (flash?.error) {
            toast.add({ severity: "error", summary: flash.error, life: 6000 });
        }
    },
    { deep: true },
);
</script>

<template>
    <Toast position="top-right" />
</template>
