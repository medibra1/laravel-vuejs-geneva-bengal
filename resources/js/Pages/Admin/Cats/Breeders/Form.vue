<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import LocaleTabs from '@/Components/LocaleTabs.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import ToggleSwitch from 'primevue/toggleswitch';
import type { Cat, Color } from '@/types/models';

const props = defineProps<{
    cat?: Cat;
    colors: Color[];
}>();

const sexOptions = [
    { label: 'Mâle', value: 'male' },
    { label: 'Femelle', value: 'femelle' },
];

const form = useForm({
    name: props.cat?.name ?? '',
    sex: props.cat?.sex ?? 'male',
    color_id: props.cat?.color_id ?? null,
    second_color_id: props.cat?.second_color_id ?? null,
    description: {
        fr: props.cat?.description.fr ?? '',
        en: props.cat?.description.en ?? '',
    },
    birth_date: props.cat?.birth_date ? new Date(props.cat.birth_date) : null,
    eye_color: props.cat?.eye_color ?? '',
    diet: props.cat?.diet ?? '',
    litter_trained: props.cat?.litter_trained ?? false,
    neutered: props.cat?.neutered ?? false,
    photos: [] as File[],
});

function onFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.photos = input.files ? Array.from(input.files) : [];
}

function submit(): void {
    if (props.cat) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            route('admin.cats.breeders.update', props.cat.id),
            { forceFormData: true },
        );
    } else {
        form.post(route('admin.cats.breeders.store'), { forceFormData: true });
    }
}

function deletePhoto(photoId: number): void {
    if (!props.cat) return;

    if (confirm('Supprimer cette photo ?')) {
        router.delete(route('admin.cats.breeders.photos.destroy', [props.cat.id, photoId]), { preserveScroll: true });
    }
}
</script>

<template>

    <Head :title="cat ? `Modifier ${cat.name}` : 'Nouveau reproducteur'" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                {{ cat ? `Modifier ${cat.name}` : 'Nouveau reproducteur' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <form class="space-y-6 bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel for="name" value="Nom" />
                            <InputText id="name" v-model="form.name" class="mt-1 w-full" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="sex" value="Sexe" />
                            <Select id="sex" v-model="form.sex" :options="sexOptions" option-label="label"
                                option-value="value" class="mt-1 w-full" />
                            <InputError :message="form.errors.sex" />
                        </div>

                        <div>
                            <InputLabel for="color_id" value="Couleur" />
                            <Select id="color_id" v-model="form.color_id" :options="colors" option-label="name"
                                option-value="id" class="mt-1 w-full" />
                            <InputError :message="form.errors.color_id" />
                        </div>

                        <div>
                            <InputLabel for="second_color_id" value="Deuxième couleur (optionnel)" />
                            <Select id="second_color_id" v-model="form.second_color_id" :options="colors"
                                option-label="name" option-value="id" show-clear class="mt-1 w-full" />
                            <InputError :message="form.errors.second_color_id" />
                        </div>

                        <div>
                            <InputLabel for="eye_color" value="Couleur des yeux" />
                            <InputText id="eye_color" v-model="form.eye_color" class="mt-1 w-full" />
                            <InputError :message="form.errors.eye_color" />
                        </div>

                        <div>
                            <InputLabel for="birth_date" value="Date de naissance" />
                            <DatePicker id="birth_date" v-model="form.birth_date" date-format="yy-mm-dd"
                                class="mt-1 w-full" />
                            <InputError :message="form.errors.birth_date" />
                        </div>

                        <div>
                            <InputLabel for="diet" value="Régime alimentaire" />
                            <InputText id="diet" v-model="form.diet" class="mt-1 w-full" />
                            <InputError :message="form.errors.diet" />
                        </div>
                    </div>

                    <div class="flex gap-8">
                        <label class="flex items-center gap-2">
                            <ToggleSwitch v-model="form.litter_trained" />
                            Formé à la litière
                        </label>
                        <label class="flex items-center gap-2">
                            <ToggleSwitch v-model="form.neutered" />
                            Castré/Stérilisé
                        </label>
                    </div>

                    <div>
                        <InputLabel value="Description" />
                        <LocaleTabs
                            class="mt-1"
                            :fr-has-error="!!form.errors['description.fr']"
                            :en-has-error="!!form.errors['description.en']"
                        >
                            <template #fr>
                                <textarea v-model="form.description.fr" rows="5"
                                    class="w-full rounded-md border-gray-300" />
                                <InputError :message="form.errors['description.fr']" />
                            </template>
                            <template #en>
                                <textarea v-model="form.description.en" rows="5"
                                    class="w-full rounded-md border-gray-300" />
                                <InputError :message="form.errors['description.en']" />
                            </template>
                        </LocaleTabs>
                    </div>

                    <div>
                        <InputLabel for="photos" value="Photos" />
                        <input id="photos" type="file" multiple accept="image/*" class="mt-1 block w-full text-sm"
                            @change="onFilesSelected" />
                        <InputError :message="form.errors.photos" />
                        <p class="mt-1 text-xs text-neutral-500">
                            Plusieurs photos peuvent être sélectionnées à la fois ; elles s'ajoutent aux photos
                            existantes ci-dessous.
                        </p>

                        <div v-if="cat?.photos.length" class="mt-3 flex flex-wrap gap-3">
                            <div v-for="photo in cat.photos" :key="photo.id" class="group relative">
                                <img :src="photo.url" class="h-20 w-20 rounded object-cover" />
                                <button
                                    type="button"
                                    title="Supprimer cette photo"
                                    class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-xs text-white opacity-0 shadow transition group-hover:opacity-100"
                                    @click="deletePhoto(photo.id)"
                                >
                                    <i class="pi pi-times" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">
                            {{ cat ? 'Enregistrer' : 'Créer' }}
                        </PrimaryButton>
                        <Button label="Annuler" severity="secondary" text
                            @click="$inertia.get(route('admin.cats.breeders.index'))" />
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
