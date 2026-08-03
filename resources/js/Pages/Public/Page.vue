<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

interface PublicTestimonial {
    id: number;
    author_name: string;
    quote: string;
    rating: number | null;
}

interface PublicSettings {
    address: string | null;
    social_facebook: string | null;
    social_instagram: string | null;
    social_youtube: string | null;
    social_pinterest: string | null;
}

defineProps<{
    page: {
        slug: string;
        title: string;
        body: string | null;
        meta_title: string | null;
        meta_description: string | null;
    };
    testimonials?: PublicTestimonial[];
    settings?: PublicSettings;
}>();
</script>

<template>
    <Head :title="page.meta_title ?? page.title">
        <meta v-if="page.meta_description" head-key="description" name="description" :content="page.meta_description" />
    </Head>

    <PublicLayout>
        <section class="mx-auto max-w-4xl px-6 py-16">
            <h1 class="text-3xl font-semibold text-neutral-900">{{ page.title }}</h1>
            <div v-if="page.body" class="prose mt-6 max-w-none whitespace-pre-line text-neutral-700">
                {{ page.body }}
            </div>
        </section>

        <section v-if="testimonials" id="temoignages" class="border-t border-gray-200 bg-gray-50 py-16">
            <div class="mx-auto max-w-4xl px-6">
                <h2 class="text-2xl font-semibold text-neutral-900">Témoignages</h2>
                <div v-if="testimonials.length" class="mt-8 space-y-8">
                    <blockquote v-for="testimonial in testimonials" :key="testimonial.id">
                        <p class="text-lg italic text-neutral-700">&ldquo;{{ testimonial.quote }}&rdquo;</p>
                        <footer class="mt-2 text-sm text-neutral-500">
                            — {{ testimonial.author_name }}
                            <span v-if="testimonial.rating"> ({{ testimonial.rating }}/5)</span>
                        </footer>
                    </blockquote>
                </div>
                <p v-else class="mt-4 text-neutral-500">Aucun témoignage pour le moment.</p>
            </div>
        </section>

        <section v-if="settings" class="border-t border-gray-200 py-16">
            <div class="mx-auto max-w-4xl px-6">
                <h2 class="text-2xl font-semibold text-neutral-900">Nous contacter</h2>
                <p v-if="settings.address" class="mt-4 text-neutral-700">{{ settings.address }}</p>
                <ul class="mt-4 flex gap-4 text-sm text-emerald-700">
                    <li v-if="settings.social_facebook"><a :href="settings.social_facebook" target="_blank">Facebook</a></li>
                    <li v-if="settings.social_instagram"><a :href="settings.social_instagram" target="_blank">Instagram</a></li>
                    <li v-if="settings.social_youtube"><a :href="settings.social_youtube" target="_blank">YouTube</a></li>
                    <li v-if="settings.social_pinterest"><a :href="settings.social_pinterest" target="_blank">Pinterest</a></li>
                </ul>
                <!-- Le formulaire de contact lui-même arrive en Phase 4. -->
            </div>
        </section>
    </PublicLayout>
</template>
