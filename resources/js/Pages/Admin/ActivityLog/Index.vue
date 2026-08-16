<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useTableQuery } from '@/Composables/useTableQuery';
import type { ActivityLogCauser, ActivityLogEntry, Paginated } from '@/types/models';

const props = defineProps<{
    activities: Paginated<ActivityLogEntry>;
    logNames: (string | null)[];
    events: (string | null)[];
    causers: ActivityLogCauser[];
}>();

const eventLabels: Record<string, string> = {
    created: 'Créé',
    updated: 'Modifié',
    deleted: 'Supprimé',
};

function eventSeverity(event: string | null): 'success' | 'warn' | 'danger' | 'secondary' {
    if (event === 'created') return 'success';
    if (event === 'updated') return 'warn';
    if (event === 'deleted') return 'danger';

    return 'secondary';
}

function subjectLabel(activity: ActivityLogEntry): string {
    if (!activity.subject_type) return '—';

    return `${activity.subject_type.replace('App\\Models\\', '')} #${activity.subject_id}`;
}

interface FieldChange {
    field: string;
    before: unknown;
    after: unknown;
}

// Formats a single before/after value for display — objects/arrays (e.g.
// Deposit's translatable-adjacent columns) fall back to compact JSON so
// nothing ever renders as the unhelpful literal "[object Object]".
function formatValue(value: unknown): string {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'boolean') return value ? 'Oui' : 'Non';
    if (typeof value === 'object') return JSON.stringify(value);

    return String(value);
}

// Merges properties.attributes ("after") and properties.old ("before")
// into one row per changed field — a created/deleted entry only has one
// side populated (LogOptions::logOnlyDirty() never submits an empty log,
// see Cat::getActivitylogOptions() etc., so at least one side is always
// present).
function fieldChanges(activity: ActivityLogEntry): FieldChange[] {
    const after = activity.properties?.attributes ?? {};
    const before = activity.properties?.old ?? {};
    const fields = new Set([...Object.keys(after), ...Object.keys(before)]);

    return Array.from(fields).map((field) => ({ field, before: before[field], after: after[field] }));
}

const { filters, applyFilters, goToPage } = useTableQuery({
    routeName: 'admin.activity-log.index',
    filterDefaults: {
        log_name: null as string | null,
        event: null as string | null,
        causer_id: null as number | null,
        from: null as string | null,
        to: null as string | null,
    },
    numericFilterKeys: ['causer_id'],
});

const detailsDialogVisible = ref(false);
const selectedActivity = ref<ActivityLogEntry | null>(null);

function showDetails(activity: ActivityLogEntry): void {
    selectedActivity.value = activity;
    detailsDialogVisible.value = true;
}

// DatePicker works with real Date objects — the composable stores/sends
// plain 'YYYY-MM-DD' strings (what the backend's from/to filters expect).
function onDateChange(key: 'from' | 'to', value: Date | null): void {
    filters[key] = value ? value.toISOString().slice(0, 10) : null;
    applyFilters();
}
</script>

<template>
    <Head title="Journal d'activité" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">Journal d'activité</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap items-end gap-4 bg-white dark:bg-neutral-800 p-4 shadow-sm sm:rounded-lg">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">Module</label>
                        <Select
                            v-model="filters.log_name"
                            :options="props.logNames"
                            show-clear
                            placeholder="Tous les modules"
                            class="w-48"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">Action</label>
                        <Select
                            v-model="filters.event"
                            :options="props.events"
                            show-clear
                            placeholder="Toutes les actions"
                            class="w-40"
                            @update:model-value="applyFilters"
                        >
                            <template #option="{ option }">{{ eventLabels[option] ?? option }}</template>
                        </Select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">Auteur</label>
                        <Select
                            v-model="filters.causer_id"
                            :options="props.causers"
                            option-label="name"
                            option-value="id"
                            show-clear
                            placeholder="Tous les auteurs"
                            class="w-48"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">Du</label>
                        <DatePicker
                            date-format="dd/mm/yy"
                            show-icon
                            icon-display="input"
                            class="w-36"
                            @update:model-value="(value) => onDateChange('from', value as Date | null)"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">Au</label>
                        <DatePicker
                            date-format="dd/mm/yy"
                            show-icon
                            icon-display="input"
                            class="w-36"
                            @update:model-value="(value) => onDateChange('to', value as Date | null)"
                        />
                    </div>
                </div>

                <div class="overflow-hidden bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg">
                    <DataTable :value="activities.data" data-key="id">
                        <Column header="Date">
                            <template #body="{ data }">{{ new Date(data.created_at).toLocaleString('fr-CH') }}</template>
                        </Column>
                        <Column field="log_name" header="Module" />
                        <Column header="Action">
                            <template #body="{ data }">
                                <Tag
                                    v-if="data.event"
                                    :value="eventLabels[data.event] ?? data.event"
                                    :severity="eventSeverity(data.event)"
                                />
                                <span v-else>—</span>
                            </template>
                        </Column>
                        <Column header="Concerné">
                            <template #body="{ data }">{{ subjectLabel(data) }}</template>
                        </Column>
                        <Column header="Auteur">
                            <template #body="{ data }">{{ data.causer?.name ?? 'Système' }}</template>
                        </Column>
                        <Column field="description" header="Description" />
                        <Column header="Détails">
                            <template #body="{ data }">
                                <Button
                                    v-if="data.properties && (data.properties.attributes || data.properties.old)"
                                    icon="pi pi-eye"
                                    severity="secondary"
                                    size="small"
                                    text
                                    @click="showDetails(data)"
                                />
                            </template>
                        </Column>
                    </DataTable>

                    <div v-if="activities.data.length === 0" class="py-8 text-center text-sm text-neutral-500">
                        Aucune activité trouvée pour ces filtres.
                    </div>

                    <div v-if="activities.last_page > 1" class="mt-4 flex justify-center gap-2">
                        <Button
                            v-for="page in activities.last_page"
                            :key="page"
                            :label="String(page)"
                            :severity="page === activities.current_page ? 'primary' : 'secondary'"
                            size="small"
                            text
                            @click="goToPage(page)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:visible="detailsDialogVisible" header="Détail de l'activité" modal class="w-full max-w-lg">
            <div v-if="selectedActivity" class="text-sm">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="border-b border-neutral-200 text-xs text-neutral-500 dark:border-neutral-700">
                            <th class="py-1.5 pr-2 font-medium">Champ</th>
                            <th class="py-1.5 px-2 font-medium">Avant</th>
                            <th class="py-1.5 pl-2 font-medium">Après</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="change in fieldChanges(selectedActivity)"
                            :key="change.field"
                            class="border-b border-neutral-100 last:border-0 dark:border-neutral-800"
                        >
                            <td class="py-1.5 pr-2 font-mono text-xs text-neutral-500">{{ change.field }}</td>
                            <td class="py-1.5 px-2">{{ formatValue(change.before) }}</td>
                            <td class="py-1.5 pl-2 font-medium">{{ formatValue(change.after) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Dialog>
    </AdminLayout>
</template>
