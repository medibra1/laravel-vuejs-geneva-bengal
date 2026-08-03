<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import type { Paginated, Testimonial } from '@/types/models';

defineProps<{
    testimonials: Paginated<Testimonial>;
}>();

function goToPage(page: number): void {
    router.get(route('admin.testimonials.index'), { page }, { preserveState: true, preserveScroll: true });
}

function destroy(testimonial: Testimonial): void {
    if (confirm(`Supprimer le témoignage de ${testimonial.author_name} ?`)) {
        router.delete(route('admin.testimonials.destroy', testimonial.id));
    }
}
</script>

<template>
    <Head title="Témoignages" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Témoignages</h2>
                <Link :href="route('admin.testimonials.create')">
                    <Button label="Nouveau témoignage" icon="pi pi-plus" />
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <DataTable :value="testimonials.data" data-key="id">
                        <Column field="author_name" header="Auteur" />
                        <Column header="Citation">
                            <template #body="{ data }">{{ data.quote.fr }}</template>
                        </Column>
                        <Column field="rating" header="Note" />
                        <Column header="Statut">
                            <template #body="{ data }">
                                <Tag
                                    :value="data.is_published ? 'Publié' : 'Brouillon'"
                                    :severity="data.is_published ? 'success' : 'secondary'"
                                />
                            </template>
                        </Column>
                        <Column header="Actions">
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Link :href="route('admin.testimonials.edit', data.id)">
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

                    <div v-if="testimonials.last_page > 1" class="mt-4 flex justify-center gap-2">
                        <Button
                            v-for="page in testimonials.last_page"
                            :key="page"
                            :label="String(page)"
                            :severity="page === testimonials.current_page ? 'primary' : 'secondary'"
                            size="small"
                            text
                            @click="goToPage(page)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
