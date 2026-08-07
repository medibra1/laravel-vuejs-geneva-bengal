<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Checkbox from 'primevue/checkbox';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Connexion" />

        <h2 class="font-heading text-xl font-bold text-neutral-900 dark:text-white">Connexion</h2>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Accédez à l'espace d'administration Geneva Bengal.</p>

        <div v-if="status" class="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
            {{ status }}
        </div>

        <form class="mt-6 space-y-5" @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />
                <InputText
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 w-full"
                    required
                    autofocus
                    autocomplete="username"
                />
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
                    :feedback="false"
                    required
                    autocomplete="current-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-300">
                    <Checkbox v-model="form.remember" binary name="remember" />
                    Se souvenir de moi
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-emerald-700 hover:underline dark:text-emerald-400"
                >
                    Mot de passe oublié ?
                </Link>
            </div>

            <PrimaryButton class="w-full justify-center" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Se connecter
            </PrimaryButton>
        </form>
    </GuestLayout>
</template>
