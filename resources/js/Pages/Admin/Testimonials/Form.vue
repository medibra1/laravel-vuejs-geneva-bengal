<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import LocaleTabs from '@/Components/LocaleTabs.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import ToggleSwitch from 'primevue/toggleswitch';
import type { Testimonial } from '@/types/models';

const props = defineProps<{
    testimonial?: Testimonial;
}>();

const form = useForm({
    author_name: props.testimonial?.author_name ?? '',
    quote: {
        fr: props.testimonial?.quote.fr ?? '',
        en: props.testimonial?.quote.en ?? '',
    },
    rating: props.testimonial?.rating ?? null,
    is_published: props.testimonial?.is_published ?? false,
    order: props.testimonial?.order ?? 0,
});

function submit(): void {
    if (props.testimonial) {
        form.put(route('admin.testimonials.update', props.testimonial.id));
    } else {
        form.post(route('admin.testimonials.store'));
    }
}
</script>

<template>
    <Head :title="testimonial ? 'Modifier le témoignage' : 'Nouveau témoignage'" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                {{ testimonial ? 'Modifier le témoignage' : 'Nouveau témoignage' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form class="space-y-6 bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <div>
                        <InputLabel for="author_name" value="Auteur" />
                        <InputText id="author_name" v-model="form.author_name" class="mt-1 w-full" />
                        <InputError :message="form.errors.author_name" />
                    </div>

                    <div>
                        <InputLabel value="Citation" />
                        <LocaleTabs
                            class="mt-1"
                            :fr-has-error="!!form.errors['quote.fr']"
                            :en-has-error="!!form.errors['quote.en']"
                        >
                            <template #fr>
                                <textarea v-model="form.quote.fr" rows="4" class="w-full rounded-md border-gray-300" />
                                <InputError :message="form.errors['quote.fr']" />
                            </template>
                            <template #en>
                                <textarea v-model="form.quote.en" rows="4" class="w-full rounded-md border-gray-300" />
                                <InputError :message="form.errors['quote.en']" />
                            </template>
                        </LocaleTabs>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel for="rating" value="Note (1-5, optionnel)" />
                            <InputNumber id="rating" v-model="form.rating" :min="1" :max="5" class="mt-1 w-full" />
                            <InputError :message="form.errors.rating" />
                        </div>

                        <div>
                            <InputLabel for="order" value="Ordre" />
                            <InputNumber id="order" v-model="form.order" class="mt-1 w-full" />
                            <InputError :message="form.errors.order" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2">
                        <ToggleSwitch v-model="form.is_published" />
                        Publié
                    </label>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">
                            {{ testimonial ? 'Enregistrer' : 'Créer' }}
                        </PrimaryButton>
                        <Button label="Annuler" severity="secondary" text @click="$inertia.get(route('admin.testimonials.index'))" />
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
