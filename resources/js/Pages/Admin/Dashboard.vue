<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PeriodFilter from '@/Components/PeriodFilter.vue';
import Chart from 'primevue/chart';
import type { DashboardStats, DashboardPeriod } from '@/types/models';

const props = defineProps<{
    stats: DashboardStats;
    period: DashboardPeriod;
}>();

const stats = ref<DashboardStats>(props.stats);
const period = ref<DashboardPeriod>(props.period);
const loading = ref(false);

const emerald = '#047857';
const emeraldLight = 'rgba(4, 120, 87, 0.15)';
const palette = ['#047857', '#0d9488', '#65a30d', '#d97706', '#dc2626', '#7c3aed', '#0369a1'];

const lineOptions = {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
};

const doughnutOptions = {
    plugins: { legend: { position: 'bottom' as const } },
};

const barOptions = {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
};

function lineData(chart: DashboardStats['charts']['adoptionsByMonth']) {
    return {
        labels: chart.labels,
        datasets: chart.datasets.map((dataset) => ({
            ...dataset,
            borderColor: emerald,
            backgroundColor: emeraldLight,
            fill: true,
            tension: 0.3,
        })),
    };
}

function doughnutData(chart: DashboardStats['charts']['catsByStatus']) {
    return {
        labels: chart.labels,
        datasets: chart.datasets.map((dataset) => ({ ...dataset, backgroundColor: palette })),
    };
}

function barData(chart: DashboardStats['charts']['catsByColor']) {
    return {
        labels: chart.labels,
        datasets: chart.datasets.map((dataset) => ({ ...dataset, backgroundColor: emerald })),
    };
}

function formatAmount(cents: number): string {
    return new Intl.NumberFormat('fr-CH', { style: 'currency', currency: 'CHF' }).format(cents / 100);
}

async function onPeriodChange(from: string, to: string): Promise<void> {
    loading.value = true;

    try {
        const response = await fetch(route('dashboard.stats', { from, to }), {
            headers: { Accept: 'application/json' },
        });
        const json = await response.json();
        stats.value = json.stats;
        period.value = json.period;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Head title="Tableau de bord" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">Tableau de bord</h2>
                <PeriodFilter @change="onPeriodChange" />
            </div>
        </template>

        <div class="py-12" :class="{ 'opacity-60': loading }">
            <div class="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="bg-white dark:bg-neutral-800 rounded-lg p-6 shadow-sm">
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">Chats disponibles</p>
                        <p class="mt-2 text-3xl font-semibold text-neutral-900 dark:text-white">{{ stats.kpis.available_cats }}</p>
                    </div>
                    <div class="bg-white dark:bg-neutral-800 rounded-lg p-6 shadow-sm">
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">Adoptions sur la période</p>
                        <p class="mt-2 text-3xl font-semibold text-neutral-900 dark:text-white">{{ stats.kpis.adoptions_in_period }}</p>
                    </div>
                    <div class="bg-white dark:bg-neutral-800 rounded-lg p-6 shadow-sm">
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">Acomptes encaissés</p>
                        <p class="mt-2 text-3xl font-semibold text-neutral-900 dark:text-white">
                            {{ formatAmount(stats.kpis.deposit_revenue_in_period) }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-neutral-800 rounded-lg p-6 shadow-sm">
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">Demandes de contact en attente</p>
                        <p class="mt-2 text-3xl font-semibold text-neutral-900 dark:text-white">
                            {{ stats.kpis.pending_contact_requests }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="bg-white dark:bg-neutral-800 rounded-lg p-6 shadow-sm">
                        <h3 class="font-semibold text-neutral-900 dark:text-white">Adoptions par mois</h3>
                        <Chart
                            key="adoptions-by-month"
                            type="line"
                            :data="lineData(stats.charts.adoptionsByMonth)"
                            :options="lineOptions"
                            class="mt-4 h-64"
                        />
                    </div>
                    <div class="bg-white dark:bg-neutral-800 rounded-lg p-6 shadow-sm">
                        <h3 class="font-semibold text-neutral-900 dark:text-white">Revenus des acomptes par mois</h3>
                        <Chart
                            key="deposit-revenue-by-month"
                            type="line"
                            :data="lineData(stats.charts.depositRevenueByMonth)"
                            :options="lineOptions"
                            class="mt-4 h-64"
                        />
                    </div>
                    <div class="bg-white dark:bg-neutral-800 rounded-lg p-6 shadow-sm">
                        <h3 class="font-semibold text-neutral-900 dark:text-white">Répartition des chats par statut</h3>
                        <Chart
                            key="cats-by-status"
                            type="doughnut"
                            :data="doughnutData(stats.charts.catsByStatus)"
                            :options="doughnutOptions"
                            class="mt-4 h-64"
                        />
                    </div>
                    <div class="bg-white dark:bg-neutral-800 rounded-lg p-6 shadow-sm">
                        <h3 class="font-semibold text-neutral-900 dark:text-white">Répartition des chats par couleur</h3>
                        <Chart
                            key="cats-by-color"
                            type="bar"
                            :data="barData(stats.charts.catsByColor)"
                            :options="barOptions"
                            class="mt-4 h-64"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
