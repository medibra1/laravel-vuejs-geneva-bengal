<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Créer un compte" />

        <h2 class="font-heading text-xl font-bold text-neutral-900 dark:text-white">Créer un compte</h2>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Rejoignez l'espace d'administration Geneva Bengal.</p>

        <form class="mt-6 space-y-5" @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Nom" />
                <InputText id="name" v-model="form.name" type="text" class="mt-1 w-full" required autofocus autocomplete="name" />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />
                <InputText id="email" v-model="form.email" type="email" class="mt-1 w-full" required autocomplete="username" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Mot de passe" />
                <Password
                    id="password"
                    v-model="form.password"
                    class="mt-1 w-full"
                    input-class="w-full"
                    toggle-mask
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirmer le mot de passe" />
                <Password
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    class="mt-1 w-full"
                    input-class="w-full"
                    toggle-mask
                    :feedback="false"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <PrimaryButton class="w-full justify-center" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Créer le compte
            </PrimaryButton>

            <p class="text-center text-sm text-neutral-500 dark:text-neutral-400">
                Déjà un compte ?
                <Link :href="route('login')" class="font-medium text-emerald-700 hover:underline dark:text-emerald-400">
                    Se connecter
                </Link>
            </p>
        </form>
    </GuestLayout>
</template>
