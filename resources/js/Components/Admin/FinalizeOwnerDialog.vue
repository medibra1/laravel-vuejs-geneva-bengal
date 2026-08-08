<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import RadioButton from 'primevue/radiobutton';
import Select from 'primevue/select';
import type { FinalizeFormData } from '@/Composables/useDepositActions';
import type { OwnerOption } from '@/types/models';

/**
 * Owner picker shown by useDepositActions().finalize() when a deposit has
 * no owner yet — shared by Admin/Deposits/Index.vue and CatAdoptionPanel.vue
 * so the two don't carry their own copy of the same markup.
 */

defineProps<{
    owners: OwnerOption[];
    form: InertiaForm<FinalizeFormData>;
}>();

const visible = defineModel<boolean>('visible', { required: true });
const ownerMode = defineModel<'existing' | 'new'>('ownerMode', { required: true });

const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <Dialog v-model:visible="visible" header="Finaliser l'adoption" modal class="w-full max-w-lg">
        <p class="mb-4 text-sm text-neutral-500">
            Cette réservation n'a pas encore d'adoptant lié — choisissez-en un existant ou créez-le.
        </p>

        <div class="mb-4 flex gap-6">
            <label class="flex items-center gap-2 text-sm">
                <RadioButton v-model="ownerMode" value="existing" />
                Adoptant existant
            </label>
            <label class="flex items-center gap-2 text-sm">
                <RadioButton v-model="ownerMode" value="new" />
                Nouvel adoptant
            </label>
        </div>

        <div v-if="ownerMode === 'existing'">
            <Select
                v-model="form.owner_id"
                :options="owners"
                :option-label="(owner: OwnerOption) => `${owner.first_name} ${owner.last_name} (${owner.email})`"
                option-value="id"
                placeholder="Choisir un adoptant"
                class="w-full"
            />
            <p v-if="form.errors.owner_id" class="mt-1 text-sm text-red-600">{{ form.errors.owner_id }}</p>
        </div>

        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-500">Prénom</label>
                <InputText v-model="form.new_owner.first_name" class="w-full" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-500">Nom</label>
                <InputText v-model="form.new_owner.last_name" class="w-full" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-500">E-mail</label>
                <InputText v-model="form.new_owner.email" type="email" class="w-full" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-500">Téléphone</label>
                <InputText v-model="form.new_owner.phone" class="w-full" />
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-neutral-500">Ville</label>
                <InputText v-model="form.new_owner.city" class="w-full" />
            </div>
        </div>

        <template #footer>
            <Button label="Annuler" severity="secondary" text @click="visible = false" />
            <Button label="Finaliser" :disabled="form.processing" @click="emit('submit')" />
        </template>
    </Dialog>
</template>
