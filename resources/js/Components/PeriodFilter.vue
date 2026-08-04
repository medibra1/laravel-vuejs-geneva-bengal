<script setup lang="ts">
import { ref, watch } from 'vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';

const emit = defineEmits<{ change: [from: string, to: string] }>();

type Preset = 'today' | '7d' | '30d' | 'month' | 'year';
type PresetKey = Preset | 'custom';

const presets: { key: Preset; label: string }[] = [
    { key: 'today', label: "Aujourd'hui" },
    { key: '7d', label: '7 jours' },
    { key: '30d', label: '30 jours' },
    { key: 'month', label: 'Ce mois' },
    { key: 'year', label: 'Cette année' },
];

const active = ref<PresetKey>('month');
const customRange = ref<Date[] | null>(null);

function toDateString(date: Date): string {
    return date.toLocaleDateString('en-CA'); // yyyy-mm-dd, no timezone surprises
}

function applyPreset(key: Preset): void {
    active.value = key;
    customRange.value = null;

    const now = new Date();
    let from = new Date(now);

    if (key === 'today') {
        // from stays "now"
    } else if (key === '7d') {
        from.setDate(from.getDate() - 6);
    } else if (key === '30d') {
        from.setDate(from.getDate() - 29);
    } else if (key === 'month') {
        from = new Date(now.getFullYear(), now.getMonth(), 1);
    } else if (key === 'year') {
        from = new Date(now.getFullYear(), 0, 1);
    }

    emit('change', toDateString(from), toDateString(now));
}

watch(customRange, (range) => {
    if (range && range[0] && range[1]) {
        active.value = 'custom';
        emit('change', toDateString(range[0]), toDateString(range[1]));
    }
});

applyPreset('month');
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button
            v-for="preset in presets"
            :key="preset.key"
            :label="preset.label"
            :severity="active === preset.key ? undefined : 'secondary'"
            size="small"
            @click="applyPreset(preset.key)"
        />
        <DatePicker
            v-model="customRange"
            selection-mode="range"
            placeholder="Plage personnalisée"
            date-format="dd/mm/yy"
            size="small"
            show-icon
            :class="active === 'custom' ? 'ring-2 ring-emerald-600 rounded-md' : ''"
        />
    </div>
</template>
