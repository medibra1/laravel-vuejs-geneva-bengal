<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import type { Gallery, GalleryType, Paginated } from '@/types/models';

const props = defineProps<{
    galleries: Paginated<Gallery>;
    type: GalleryType;
}>();

const tabs: Array<{ value: GalleryType; label: string }> = [
    { value: 'gallery', label: 'Photos galerie' },
    { value: 'hero_slide', label: 'Slider accueil' },
    { value: 'social_tile', label: 'Tuiles réseaux sociaux' },
];

const titles: Record<GalleryType, string> = {
    gallery: 'Galerie — Photos galerie',
    hero_slide: 'Galerie — Slider accueil',
    social_tile: 'Galerie — Tuiles réseaux sociaux',
};

function goToPage(page: number): void {
    router.get(route('admin.galleries.index'), { type: props.type, page }, { preserveState: true, preserveScroll: true });
}

function destroy(gallery: Gallery): void {
    if (confirm('Supprimer cette photo ?')) {
        router.delete(route('admin.galleries.destroy', gallery.id));
    }
}
</script>

<template>
    <Head :title="titles[type]" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">Galerie</h2>
                <Link :href="route('admin.galleries.create', { type })">
                    <Button label="Ajouter une photo" icon="pi pi-plus" />
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap gap-1 border-b border-gray-200 dark:border-neutral-800">
                    <Link
                        v-for="tab in tabs"
                        :key="tab.value"
                        :href="route('admin.galleries.index', { type: tab.value })"
                        class="rounded-t-md px-4 py-2 text-sm font-medium transition"
                        :class="
                            tab.value === type
                                ? 'bg-emerald-700 text-white'
                                : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-white/5 dark:hover:text-white'
                        "
                    >
                        {{ tab.label }}
                    </Link>
                </div>

                <div class="overflow-hidden bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg">
                    <DataTable :value="galleries.data" data-key="id">
                        <Column header="Photo">
                            <template #body="{ data }">
                                <img
                                    v-if="data.image_url"
                                    :src="data.image_url"
                                    class="h-12 w-12 rounded object-cover"
                                />
                                <div v-else class="h-12 w-12 rounded bg-gray-100" />
                            </template>
                        </Column>
                        <Column field="caption" header="Légende" />
                        <Column field="position" header="Ordre" />
                        <Column header="Actions">
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Link :href="route('admin.galleries.edit', data.id)">
                                        <Button icon="pi pi-pencil" severity="secondary" size="small" text />
                                    </Link>
                                    <Button
                                        icon="pi pi-trash"
                                        severity="danger"
                                        size="small"
                                        text
                                        @click="destroy(data)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>

                    <div v-if="galleries.last_page > 1" class="mt-4 flex justify-center gap-2">
                        <Button
                            v-for="page in galleries.last_page"
                            :key="page"
                            :label="String(page)"
                            :severity="page === galleries.current_page ? 'primary' : 'secondary'"
                            size="small"
                            text
                            @click="goToPage(page)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
