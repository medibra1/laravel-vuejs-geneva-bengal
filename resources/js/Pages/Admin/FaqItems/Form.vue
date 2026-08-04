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
import type { FaqItem } from '@/types/models';

const props = defineProps<{
    faqItem?: FaqItem;
}>();

const form = useForm({
    question: {
        fr: props.faqItem?.question.fr ?? '',
        en: props.faqItem?.question.en ?? '',
    },
    answer: {
        fr: props.faqItem?.answer.fr ?? '',
        en: props.faqItem?.answer.en ?? '',
    },
    order: props.faqItem?.order ?? 0,
});

function submit(): void {
    if (props.faqItem) {
        form.put(route('admin.faq-items.update', props.faqItem.id));
    } else {
        form.post(route('admin.faq-items.store'));
    }
}
</script>

<template>

    <Head :title="faqItem ? 'Modifier la question' : 'Nouvelle question'" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                {{ faqItem ? 'Modifier la question' : 'Nouvelle question' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form class="space-y-6 bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <div>
                        <InputLabel value="Question" />
                        <LocaleTabs
                            class="mt-1"
                            :fr-has-error="!!form.errors['question.fr']"
                            :en-has-error="!!form.errors['question.en']"
                        >
                            <template #fr>
                                <InputText v-model="form.question.fr" class="w-full" />
                                <InputError :message="form.errors['question.fr']" />
                            </template>
                            <template #en>
                                <InputText v-model="form.question.en" class="w-full" />
                                <InputError :message="form.errors['question.en']" />
                            </template>
                        </LocaleTabs>
                    </div>

                    <div>
                        <InputLabel value="Réponse" />
                        <LocaleTabs
                            class="mt-1"
                            :fr-has-error="!!form.errors['answer.fr']"
                            :en-has-error="!!form.errors['answer.en']"
                        >
                            <template #fr>
                                <textarea v-model="form.answer.fr" rows="5" class="w-full rounded-md border-gray-300" />
                                <InputError :message="form.errors['answer.fr']" />
                            </template>
                            <template #en>
                                <textarea v-model="form.answer.en" rows="5" class="w-full rounded-md border-gray-300" />
                                <InputError :message="form.errors['answer.en']" />
                            </template>
                        </LocaleTabs>
                    </div>

                    <div>
                        <InputLabel for="order" value="Ordre" />
                        <InputNumber id="order" v-model="form.order" class="mt-1 w-full" />
                        <InputError :message="form.errors.order" />
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">
                            {{ faqItem ? 'Enregistrer' : 'Créer' }}
                        </PrimaryButton>
                        <Button label="Annuler" severity="secondary" text
                            @click="$inertia.get(route('admin.faq-items.index'))" />
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
