<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import type { Litter, LitterCatOption } from '@/types/models';

const props = defineProps<{
    litter?: Litter;
    sires: LitterCatOption[];
    dams: LitterCatOption[];
}>();

const form = useForm({
    sire_cat_id: props.litter?.sire_cat_id ?? null,
    dam_cat_id: props.litter?.dam_cat_id ?? null,
    expected_date: props.litter?.expected_date ? new Date(props.litter.expected_date) : null,
    notes: props.litter?.notes ?? '',
});

function submit(): void {
    if (props.litter) {
        form.put(route('admin.litters.update', props.litter.id));
    } else {
        form.post(route('admin.litters.store'));
    }
}
</script>

<template>
    <Head :title="litter ? 'Modifier la portée' : 'Nouvelle portée'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ litter ? 'Modifier la portée' : 'Nouvelle portée' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <form class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel for="sire_cat_id" value="Père (sire)" />
                            <Select
                                id="sire_cat_id"
                                v-model="form.sire_cat_id"
                                :options="sires"
                                option-label="name"
                                option-value="id"
                                show-clear
                                class="mt-1 w-full"
                            />
                            <InputError :message="form.errors.sire_cat_id" />
                        </div>

                        <div>
                            <InputLabel for="dam_cat_id" value="Mère (dam)" />
                            <Select
                                id="dam_cat_id"
                                v-model="form.dam_cat_id"
                                :options="dams"
                                option-label="name"
                                option-value="id"
                                show-clear
                                class="mt-1 w-full"
                            />
                            <InputError :message="form.errors.dam_cat_id" />
                        </div>

                        <div>
                            <InputLabel for="expected_date" value="Date prévue" />
                            <DatePicker
                                id="expected_date"
                                v-model="form.expected_date"
                                date-format="yy-mm-dd"
                                class="mt-1 w-full"
                            />
                            <InputError :message="form.errors.expected_date" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="notes" value="Notes" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="4"
                            class="mt-1 w-full rounded-md border-gray-300"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">
                            {{ litter ? 'Enregistrer' : 'Créer' }}
                        </PrimaryButton>
                        <Button
                            label="Annuler"
                            severity="secondary"
                            text
                            @click="$inertia.get(route('admin.litters.index'))"
                        />
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
