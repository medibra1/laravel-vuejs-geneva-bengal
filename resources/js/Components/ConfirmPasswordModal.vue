<script setup lang="ts">
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Password from 'primevue/password';
import type { ConfirmPasswordForm } from '@/Composables/useConfirmsPassword';

/**
 * Paired with useConfirmsPassword.ts — one modal per page, shared by every
 * button that needs a fresh password confirmation before proceeding.
 */
defineProps<{
    form: ConfirmPasswordForm;
}>();

const visible = defineModel<boolean>('visible', { required: true });

const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <Dialog v-model:visible="visible" header="Confirmer le mot de passe" modal class="w-full max-w-sm">
        <p class="mb-4 text-sm text-neutral-500">
            Cette action nécessite de confirmer votre mot de passe avant de continuer.
        </p>

        <Password
            v-model="form.password"
            placeholder="Mot de passe"
            :feedback="false"
            toggle-mask
            class="w-full"
            input-class="w-full"
            autofocus
            @keyup.enter="emit('submit')"
        />
        <p v-if="form.error" class="mt-1 text-sm text-red-600">{{ form.error }}</p>

        <template #footer>
            <Button label="Annuler" severity="secondary" text @click="visible = false" />
            <Button label="Confirmer" :disabled="form.processing" @click="emit('submit')" />
        </template>
    </Dialog>
</template>
