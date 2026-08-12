<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import type { Gallery, GalleryType } from '@/types/models';

const props = defineProps<{
    gallery?: Gallery;
    type: GalleryType;
}>();

const typeLabels: Record<GalleryType, string> = {
    gallery: 'Photos galerie',
    hero_slide: 'Slider accueil',
    social_tile: 'Tuiles réseaux sociaux',
};

// The gallery's own type (edit) always wins over the `type` query-string
// prop (create) — it never changes on update, no field exposed for it.
const activeType = props.gallery?.type ?? props.type;

const form = useForm({
    caption: props.gallery?.caption ?? '',
    position: props.gallery?.position ?? 0,
    type: activeType,
    image: null as File | null,
});

function onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.image = input.files?.[0] ?? null;
}

function submit(): void {
    if (props.gallery) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            route('admin.galleries.update', props.gallery.id),
            { forceFormData: true },
        );
    } else {
        form.post(route('admin.galleries.store'), { forceFormData: true });
    }
}
</script>

<template>
    <Head :title="gallery ? 'Modifier la photo' : 'Ajouter une photo'" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                {{ gallery ? 'Modifier la photo' : 'Ajouter une photo' }} — {{ typeLabels[activeType] }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <form class="space-y-6 bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <InputError :message="form.errors.type" />

                    <div>
                        <InputLabel for="caption" value="Légende" />
                        <InputText id="caption" v-model="form.caption" class="mt-1 w-full" />
                        <InputError :message="form.errors.caption" />
                    </div>

                    <div>
                        <InputLabel for="position" value="Ordre d'affichage" />
                        <InputNumber id="position" v-model="form.position" class="mt-1 w-full" />
                        <InputError :message="form.errors.position" />
                    </div>

                    <div>
                        <InputLabel for="image" value="Photo" />
                        <input
                            id="image"
                            type="file"
                            accept="image/*"
                            class="mt-1 block w-full text-sm"
                            @change="onFileSelected"
                        />
                        <InputError :message="form.errors.image" />

                        <img
                            v-if="gallery?.image_url"
                            :src="gallery.image_url"
                            class="mt-3 h-24 w-24 rounded object-cover"
                        />
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">
                            {{ gallery ? 'Enregistrer' : 'Ajouter' }}
                        </PrimaryButton>
                        <Link :href="route('admin.galleries.index', { type: activeType })">
                            <Button label="Annuler" severity="secondary" text />
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
