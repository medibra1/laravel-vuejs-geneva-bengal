import { reactive, ref } from 'vue';

export interface ConfirmPasswordForm {
    password: string;
    error: string;
    processing: boolean;
}

function csrfToken(): string {
    return document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * Adapted from Laravel Jetstream's confirms-password.js for this Breeze +
 * Inertia stack (Jetstream/Fortify ship the same feature out of the box;
 * this project doesn't pull either in, so it's reimplemented against
 * Breeze's own Auth\ConfirmablePasswordController).
 *
 * Deliberately uses plain fetch() instead of Inertia's router: an Inertia
 * visit to password.confirm's POST route would follow ConfirmablePasswordController::store()'s
 * redirect and navigate the whole page away from wherever the sensitive
 * action was triggered (e.g. the deposits list). Both of that controller's
 * actions respond with JSON instead when asked (Accept: application/json),
 * exactly like Fortify's own controller does for the same reason.
 *
 * One instance of this composable per page/component is enough — every
 * button that needs confirmation shares the same modal and calls
 * confirmPassword() with its own callback.
 */
export function useConfirmsPassword() {
    const confirmingPassword = ref(false);
    const form = reactive<ConfirmPasswordForm>({
        password: '',
        error: '',
        processing: false,
    });

    let onConfirmed: () => void = () => {};

    /**
     * Checks whether the session's last confirmation is still fresh
     * (config('auth.password_timeout')) before deciding whether to run
     * `callback` right away or ask for the password first — avoids
     * showing the modal on every single click once the admin has already
     * confirmed recently.
     */
    async function confirmPassword(callback: () => void): Promise<void> {
        onConfirmed = callback;

        try {
            const response = await fetch(route('password.confirm'), {
                headers: { Accept: 'application/json' },
            });
            const data: { confirmed: boolean } = await response.json();

            if (data.confirmed) {
                onConfirmed();
                return;
            }
        } catch {
            // Falls through to the modal below — asking again is the safe
            // default if the check itself couldn't be completed.
        }

        form.password = '';
        form.error = '';
        confirmingPassword.value = true;
    }

    async function submitPassword(): Promise<void> {
        form.processing = true;
        form.error = '';

        try {
            const response = await fetch(route('password.confirm'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ password: form.password }),
            });

            if (response.ok) {
                confirmingPassword.value = false;
                form.password = '';
                onConfirmed();
                return;
            }

            const data: { errors?: { password?: string[] } } = await response.json();
            form.error = data.errors?.password?.[0] ?? 'Une erreur est survenue.';
        } finally {
            form.processing = false;
        }
    }

    return {
        confirmingPassword,
        form,
        confirmPassword,
        submitPassword,
    };
}
