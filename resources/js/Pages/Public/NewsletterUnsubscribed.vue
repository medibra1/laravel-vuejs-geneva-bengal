<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps<{
    found: boolean;
}>();
</script>

<template>
    <Head :title="$t('newsletterUnsubscribed.meta_title')" />

    <PublicLayout>
        <section class="mx-auto max-w-2xl px-6 py-24 text-center">
            <template v-if="found">
                <i class="pi pi-check-circle text-5xl text-emerald-600" />
                <h1 class="mt-6 text-2xl font-semibold text-neutral-900">{{ $t('newsletterUnsubscribed.found_heading') }}</h1>
                <p class="mt-4 text-neutral-600">
                    {{ $t('newsletterUnsubscribed.found_body') }}
                </p>
            </template>

            <template v-else>
                <i class="pi pi-times-circle text-5xl text-neutral-400" />
                <h1 class="mt-6 text-2xl font-semibold text-neutral-900">{{ $t('newsletterUnsubscribed.invalid_heading') }}</h1>
                <p class="mt-4 text-neutral-600">
                    {{ $t('newsletterUnsubscribed.invalid_body') }}
                </p>
            </template>

            <Link :href="route('home')" class="mt-8 inline-flex items-center gap-2 text-emerald-700 hover:text-emerald-800">
                <i class="pi pi-arrow-left" />
                {{ $t('newsletterUnsubscribed.back_link') }}
            </Link>
        </section>
    </PublicLayout>
</template>
