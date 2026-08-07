<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import type { NewsletterSubscriber, Paginated } from '@/types/models';

defineProps<{
    subscribers: Paginated<NewsletterSubscriber>;
}>();

function goToPage(page: number): void {
    router.get(route('admin.newsletter-subscribers.index'), { page }, { preserveState: true, preserveScroll: true });
}

function toggleUnsubscribed(subscriber: NewsletterSubscriber): void {
    const question = subscriber.unsubscribed_at
        ? `Réabonner ${subscriber.email} ?`
        : `Désabonner ${subscriber.email} ?`;

    if (confirm(question)) {
        router.patch(
            route('admin.newsletter-subscribers.toggle-unsubscribed', subscriber.id),
            {},
            { preserveScroll: true },
        );
    }
}
</script>

<template>

    <Head title="Newsletter" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                    Abonnés newsletter
                </h2>
                <a :href="route('admin.newsletter-subscribers.export')">
                    <Button icon="pi pi-download" label="Exporter CSV" severity="secondary" size="small" />
                </a>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg">
                    <DataTable :value="subscribers.data" data-key="id">
                        <Column field="email" header="E-mail" />
                        <Column header="Inscrit le">
                            <template #body="{ data }">{{ new Date(data.created_at).toLocaleDateString('fr-CH') }}</template>
                        </Column>
                        <Column header="Statut">
                            <template #body="{ data }">
                                <Tag
                                    :value="data.unsubscribed_at ? 'Désabonné' : 'Actif'"
                                    :severity="data.unsubscribed_at ? 'secondary' : 'success'"
                                />
                            </template>
                        </Column>
                        <Column header="Actions">
                            <template #body="{ data }">
                                <Button
                                    :label="data.unsubscribed_at ? 'Réabonner' : 'Désabonner'"
                                    :severity="data.unsubscribed_at ? 'success' : 'danger'"
                                    size="small"
                                    text
                                    @click="toggleUnsubscribed(data)"
                                />
                            </template>
                        </Column>
                    </DataTable>

                    <div v-if="subscribers.last_page > 1" class="mt-4 flex justify-center gap-2">
                        <Button v-for="page in subscribers.last_page" :key="page" :label="String(page)"
                            :severity="page === subscribers.current_page ? 'primary' : 'secondary'" size="small"
                            text @click="goToPage(page)" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
