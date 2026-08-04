<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import type { Owner, OwnerCatOption, Color } from '@/types/models';

const props = defineProps<{
    owner?: Owner;
    cats: OwnerCatOption[];
    colors: Color[];
}>();

const form = useForm({
    first_name: props.owner?.first_name ?? '',
    last_name: props.owner?.last_name ?? '',
    email: props.owner?.email ?? '',
    phone: props.owner?.phone ?? '',
    city: props.owner?.city ?? '',
    desired_cat_id: props.owner?.desired_cat_id ?? null,
    desired_color_id: props.owner?.desired_color_id ?? null,
});

function submit(): void {
    if (props.owner) {
        form.put(route('admin.owners.update', props.owner.id));
    } else {
        form.post(route('admin.owners.store'));
    }
}
</script>

<template>
    <Head :title="owner ? `Modifier ${owner.first_name}` : 'Nouvel adoptant'" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ owner ? `Modifier ${owner.first_name} ${owner.last_name}` : 'Nouvel adoptant' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <form class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel for="first_name" value="Prénom" />
                            <InputText id="first_name" v-model="form.first_name" class="mt-1 w-full" />
                            <InputError :message="form.errors.first_name" />
                        </div>

                        <div>
                            <InputLabel for="last_name" value="Nom" />
                            <InputText id="last_name" v-model="form.last_name" class="mt-1 w-full" />
                            <InputError :message="form.errors.last_name" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Email" />
                            <InputText id="email" v-model="form.email" type="email" class="mt-1 w-full" />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="phone" value="Téléphone" />
                            <InputText id="phone" v-model="form.phone" class="mt-1 w-full" />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div>
                            <InputLabel for="city" value="Ville" />
                            <InputText id="city" v-model="form.city" class="mt-1 w-full" />
                            <InputError :message="form.errors.city" />
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-sm font-semibold text-neutral-900">Préférence d'adoption</h3>
                        <p class="mt-1 text-xs text-neutral-500">
                            Un chat précis s'il en a déjà choisi un, sinon une couleur souhaitée pour une inscription
                            en liste d'attente. Les deux sont facultatifs et indépendants.
                        </p>

                        <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <InputLabel for="desired_cat_id" value="Chat souhaité (optionnel)" />
                                <Select
                                    id="desired_cat_id"
                                    v-model="form.desired_cat_id"
                                    :options="cats"
                                    option-label="name"
                                    option-value="id"
                                    show-clear
                                    placeholder="Aucun en particulier"
                                    class="mt-1 w-full"
                                />
                                <InputError :message="form.errors.desired_cat_id" />
                            </div>

                            <div>
                                <InputLabel for="desired_color_id" value="Couleur souhaitée (liste d'attente)" />
                                <Select
                                    id="desired_color_id"
                                    v-model="form.desired_color_id"
                                    :options="colors"
                                    option-label="name"
                                    option-value="id"
                                    show-clear
                                    placeholder="Aucune préférence"
                                    class="mt-1 w-full"
                                />
                                <InputError :message="form.errors.desired_color_id" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">
                            {{ owner ? 'Enregistrer' : 'Créer' }}
                        </PrimaryButton>
                        <Button
                            label="Annuler"
                            severity="secondary"
                            text
                            @click="$inertia.get(route('admin.owners.index'))"
                        />
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
