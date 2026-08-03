<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import type { FaqItem, Paginated } from '@/types/models';

defineProps<{
    faqItems: Paginated<FaqItem>;
}>();

function goToPage(page: number): void {
    router.get(route('admin.faq-items.index'), { page }, { preserveState: true, preserveScroll: true });
}

function destroy(faqItem: FaqItem): void {
    if (confirm('Supprimer cette question ?')) {
        router.delete(route('admin.faq-items.destroy', faqItem.id));
    }
}
</script>

<template>
    <Head title="FAQ" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">FAQ</h2>
                <Link :href="route('admin.faq-items.create')">
                    <Button label="Nouvelle question" icon="pi pi-plus" />
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <DataTable :value="faqItems.data" data-key="id">
                        <Column header="Question">
                            <template #body="{ data }">{{ data.question.fr }}</template>
                        </Column>
                        <Column field="order" header="Ordre" />
                        <Column header="Actions">
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Link :href="route('admin.faq-items.edit', data.id)">
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

                    <div v-if="faqItems.last_page > 1" class="mt-4 flex justify-center gap-2">
                        <Button
                            v-for="page in faqItems.last_page"
                            :key="page"
                            :label="String(page)"
                            :severity="page === faqItems.current_page ? 'primary' : 'secondary'"
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
