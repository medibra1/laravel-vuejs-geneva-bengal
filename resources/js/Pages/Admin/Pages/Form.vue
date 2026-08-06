<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import LocaleTabs from '@/Components/LocaleTabs.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import ToggleSwitch from 'primevue/toggleswitch';
import type { CmsPage } from '@/types/models';

const props = defineProps<{
    page?: CmsPage & { id: number };
}>();

const form = useForm({
    menu_group: props.page?.menu_group ?? '',
    order: props.page?.order ?? 0,
    title: {
        fr: props.page?.title.fr ?? '',
        en: props.page?.title.en ?? '',
    },
    body: {
        fr: props.page?.body?.fr ?? '',
        en: props.page?.body?.en ?? '',
    },
    meta_title: {
        fr: props.page?.meta_title?.fr ?? '',
        en: props.page?.meta_title?.en ?? '',
    },
    meta_description: {
        fr: props.page?.meta_description?.fr ?? '',
        en: props.page?.meta_description?.en ?? '',
    },
    is_published: props.page?.is_published ?? false,
});

function submit(): void {
    if (props.page) {
        form.put(route('admin.pages.update', props.page.id));
    } else {
        form.post(route('admin.pages.store'));
    }
}
</script>

<template>
    <Head :title="page ? `Modifier ${page.title.fr}` : 'Nouvelle page'" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                {{ page ? `Modifier ${page.title.fr}` : 'Nouvelle page' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form class="space-y-6 bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel for="menu_group" value="Groupe de menu (optionnel)" />
                            <InputText id="menu_group" v-model="form.menu_group" class="mt-1 w-full" />
                            <InputError :message="form.errors.menu_group" />
                        </div>

                        <div>
                            <InputLabel for="order" value="Ordre" />
                            <InputNumber id="order" v-model="form.order" class="mt-1 w-full" />
                            <InputError :message="form.errors.order" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Titre" />
                        <LocaleTabs
                            class="mt-1"
                            :fr-has-error="!!form.errors['title.fr']"
                            :en-has-error="!!form.errors['title.en']"
                        >
                            <template #fr>
                                <InputText v-model="form.title.fr" class="w-full" />
                                <InputError :message="form.errors['title.fr']" />
                            </template>
                            <template #en>
                                <InputText v-model="form.title.en" class="w-full" />
                                <InputError :message="form.errors['title.en']" />
                            </template>
                        </LocaleTabs>
                    </div>

                    <div>
                        <InputLabel value="Contenu" />
                        <LocaleTabs
                            class="mt-1"
                            :fr-has-error="!!form.errors['body.fr']"
                            :en-has-error="!!form.errors['body.en']"
                        >
                            <template #fr>
                                <RichTextEditor v-model="form.body.fr" placeholder="Contenu de la page…" :error="form.errors['body.fr']" />
                            </template>
                            <template #en>
                                <RichTextEditor v-model="form.body.en" placeholder="Page content…" :error="form.errors['body.en']" />
                            </template>
                        </LocaleTabs>
                    </div>

                    <div>
                        <InputLabel value="Méta SEO" />
                        <LocaleTabs
                            class="mt-1"
                            :fr-has-error="!!form.errors['meta_title.fr'] || !!form.errors['meta_description.fr']"
                            :en-has-error="!!form.errors['meta_title.en'] || !!form.errors['meta_description.en']"
                        >
                            <template #fr>
                                <InputLabel for="meta_title_fr" value="Titre SEO" class="text-xs" />
                                <InputText id="meta_title_fr" v-model="form.meta_title.fr" class="w-full" />
                                <InputError :message="form.errors['meta_title.fr']" />
                                <InputLabel for="meta_description_fr" value="Description SEO" class="mt-3 text-xs" />
                                <textarea id="meta_description_fr" v-model="form.meta_description.fr" rows="3" class="w-full rounded-md border-gray-300" />
                                <InputError :message="form.errors['meta_description.fr']" />
                            </template>
                            <template #en>
                                <InputLabel for="meta_title_en" value="SEO title" class="text-xs" />
                                <InputText id="meta_title_en" v-model="form.meta_title.en" class="w-full" />
                                <InputError :message="form.errors['meta_title.en']" />
                                <InputLabel for="meta_description_en" value="SEO description" class="mt-3 text-xs" />
                                <textarea id="meta_description_en" v-model="form.meta_description.en" rows="3" class="w-full rounded-md border-gray-300" />
                                <InputError :message="form.errors['meta_description.en']" />
                            </template>
                        </LocaleTabs>
                    </div>

                    <label class="flex items-center gap-2">
                        <ToggleSwitch v-model="form.is_published" />
                        Publiée
                    </label>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">
                            {{ page ? 'Enregistrer' : 'Créer' }}
                        </PrimaryButton>
                        <Button label="Annuler" severity="secondary" text @click="$inertia.get(route('admin.pages.index'))" />
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
