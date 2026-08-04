<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import type { AdminUser } from '@/types/models';

const props = defineProps<{
    user?: AdminUser;
}>();

const roleOptions = [
    { label: 'Admin', value: 'admin' },
    { label: 'Super admin', value: 'super_admin' },
];

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    role: props.user?.role ?? 'admin',
});

function submit(): void {
    if (props.user) {
        form.put(route('admin.users.update', props.user.id));
    } else {
        form.post(route('admin.users.store'));
    }
}
</script>

<template>

    <Head :title="user ? `Modifier ${user.name}` : 'Nouveau compte admin'" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                {{ user ? `Modifier ${user.name}` : 'Nouveau compte admin' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-xl sm:px-6 lg:px-8">
                <form class="space-y-6 bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <template v-if="!user">
                        <div>
                            <InputLabel for="name" value="Nom" />
                            <InputText id="name" v-model="form.name" class="mt-1 w-full" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="email" value="E-mail" />
                            <InputText id="email" v-model="form.email" type="email" class="mt-1 w-full" />
                            <InputError :message="form.errors.email" />
                            <p class="mt-1 text-xs text-gray-500">
                                Un lien de définition de mot de passe sera envoyé à cette adresse.
                            </p>
                        </div>
                    </template>
                    <template v-else>
                        <p class="text-sm text-gray-700">
                            <strong>{{ user.name }}</strong> — {{ user.email }}
                        </p>
                    </template>

                    <div>
                        <InputLabel for="role" value="Rôle" />
                        <Select id="role" v-model="form.role" :options="roleOptions" option-label="label"
                            option-value="value" class="mt-1 w-full" />
                        <InputError :message="form.errors.role" />
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">
                            {{ user ? 'Enregistrer' : 'Créer' }}
                        </PrimaryButton>
                        <Button label="Annuler" severity="secondary" text
                            @click="$inertia.get(route('admin.users.index'))" />
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
