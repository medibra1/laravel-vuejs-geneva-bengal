<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const honeypot = page.props.honeypot;

const form = useForm({
    email: '',
    [honeypot.nameFieldName]: '',
    [honeypot.validFromFieldName]: honeypot.encryptedValidFrom,
});

function submit(): void {
    form.post(route('newsletter.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('email'),
    });
}
</script>

<template>
    <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="submit">
        <div v-if="honeypot.enabled" style="display: none" aria-hidden="true">
            <input
                :id="honeypot.nameFieldName"
                v-model="(form as Record<string, string>)[honeypot.nameFieldName]"
                type="text"
                tabindex="-1"
                autocomplete="off"
            />
            <input
                v-model="(form as Record<string, string>)[honeypot.validFromFieldName]"
                type="text"
                tabindex="-1"
                autocomplete="off"
            />
        </div>

        <input
            v-model="form.email"
            type="email"
            placeholder="E-mail"
            required
            class="flex-1 rounded-md border-gray-300"
        />
        <button
            type="submit"
            :disabled="form.processing"
            class="rounded-md bg-emerald-700 px-6 py-2 font-medium text-white hover:bg-emerald-800"
        >
            S'inscrire
        </button>
    </form>
    <p v-if="form.recentlySuccessful" class="mt-2 text-sm text-emerald-700">
        Merci de votre inscription !
    </p>
</template>
