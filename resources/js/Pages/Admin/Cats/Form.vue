<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import LocaleTabs from '@/Components/LocaleTabs.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import ToggleSwitch from 'primevue/toggleswitch';
import type { Cat, Color } from '@/types/models';

const props = defineProps<{
    cat?: Cat;
    colors: Color[];
}>();

const typeOptions = [
    { label: 'Chaton', value: 'chaton' },
    { label: 'Chat', value: 'chat' },
    { label: 'Reproducteur', value: 'reproducteur' },
];

const sexOptions = [
    { label: 'Mâle', value: 'male' },
    { label: 'Femelle', value: 'femelle' },
];

const statusOptions = [
    { label: 'Disponible', value: 'disponible' },
    { label: 'En attente', value: 'en_attente' },
    { label: 'Adopté', value: 'adopte' },
];

const form = useForm({
    name: props.cat?.name ?? '',
    type: props.cat?.type ?? 'chaton',
    sex: props.cat?.sex ?? 'male',
    color_id: props.cat?.color_id ?? null,
    second_color_id: props.cat?.second_color_id ?? null,
    description: {
        fr: props.cat?.description.fr ?? '',
        en: props.cat?.description.en ?? '',
    },
    price: props.cat?.price ?? null,
    birth_date: props.cat?.birth_date ? new Date(props.cat.birth_date) : null,
    eye_color: props.cat?.eye_color ?? '',
    available_at: props.cat?.available_at ? new Date(props.cat.available_at) : null,
    diet: props.cat?.diet ?? '',
    litter_trained: props.cat?.litter_trained ?? false,
    neutered: props.cat?.neutered ?? false,
    status: props.cat?.status ?? 'disponible',
    photos: [] as File[],
});

// form.price stays in centimes (what's stored and validated) —
// InputNumber's currency mode reads/writes whole CHF, so binding it
// directly would silently divide every price by 100 on save (500 CHF
// entered -> 500 saved -> "5.00 CHF" shown on the public site).
const priceChf = computed({
    get: () => (form.price !== null ? form.price / 100 : null),
    set: (value: number | null) => {
        form.price = value !== null ? Math.round(value * 100) : null;
    },
});

function onFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.photos = input.files ? Array.from(input.files) : [];
}

function submit(): void {
    if (props.cat) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            route('admin.cats.update', props.cat.id),
            { forceFormData: true },
        );
    } else {
        form.post(route('admin.cats.store'), { forceFormData: true });
    }
}

function deletePhoto(photoId: number): void {
    if (!props.cat) return;

    if (confirm('Supprimer cette photo ?')) {
        router.delete(route('admin.cats.photos.destroy', [props.cat.id, photoId]), { preserveScroll: true });
    }
}
</script>

<template>

    <Head :title="cat ? `Modifier ${cat.name}` : 'Nouveau chat'" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                {{ cat ? `Modifier ${cat.name}` : 'Nouveau chat' }}
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
                            <InputLabel for="type" value="Type" />
                            <Select id="type" v-model="form.type" :options="typeOptions" option-label="label"
                                option-value="value" class="mt-1 w-full" />
                            <InputError :message="form.errors.type" />
                        </div>

                        <div>
                            <InputLabel for="sex" value="Sexe" />
                            <Select id="sex" v-model="form.sex" :options="sexOptions" option-label="label"
                                option-value="value" class="mt-1 w-full" />
                            <InputError :message="form.errors.sex" />
                        </div>

                        <div>
                            <InputLabel for="status" value="Statut" />
                            <Select id="status" v-model="form.status" :options="statusOptions" option-label="label"
                                option-value="value" class="mt-1 w-full" />
                            <InputError :message="form.errors.status" />
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
                            <InputLabel for="price" value="Prix (CHF)" />
                            <InputNumber id="price" v-model="priceChf" mode="currency" currency="CHF" locale="fr-CH"
                                class="mt-1 w-full" />
                            <InputError :message="form.errors.price" />
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
                            <InputLabel for="available_at" value="Disponible à partir de" />
                            <DatePicker id="available_at" v-model="form.available_at" date-format="yy-mm-dd"
                                class="mt-1 w-full" />
                            <InputError :message="form.errors.available_at" />
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
                            @click="$inertia.get(route('admin.cats.index'))" />
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
