<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import ConfirmPasswordModal from '@/Components/ConfirmPasswordModal.vue';
import { useConfirmsPassword } from '@/Composables/useConfirmsPassword';
import type { AdminUser } from '@/types/models';

defineProps<{
    users: AdminUser[];
}>();

// destroy/toggle-active/resend-reset-link all sit behind password.confirm
// server-side (see routes/admin.php) — creating/removing/locking out an
// admin account is exactly the kind of action that middleware exists for.
const { confirmingPassword, form: confirmPasswordForm, confirmPassword, submitPassword: submitConfirmPassword } = useConfirmsPassword();

function formatDate(date: string | null): string {
    if (!date) return 'Jamais connecté';

    return new Intl.DateTimeFormat('fr-CH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date));
}

function toggleActive(user: AdminUser): void {
    const verb = user.is_active ? 'désactiver' : 'réactiver';

    if (confirm(`Voulez-vous ${verb} le compte de ${user.name} ?`)) {
        confirmPassword(() => router.patch(route('admin.users.toggle-active', user.id), {}, { preserveScroll: true }));
    }
}

function resendResetLink(user: AdminUser): void {
    confirmPassword(() => router.post(route('admin.users.resend-reset-link', user.id), {}, { preserveScroll: true }));
}

function destroy(user: AdminUser): void {
    if (confirm(`Supprimer définitivement le compte de ${user.name} ?`)) {
        confirmPassword(() => router.delete(route('admin.users.destroy', user.id), { preserveScroll: true }));
    }
}
</script>

<template>

    <Head title="Comptes admin" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">Comptes admin</h2>
                <Link :href="route('admin.users.create')">
                    <Button label="Nouveau compte" icon="pi pi-plus" />
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg">
                    <DataTable :value="users" data-key="id">
                        <Column field="name" header="Nom" />
                        <Column field="email" header="E-mail" />
                        <Column header="Rôle">
                            <template #body="{ data }">
                                <Tag :value="data.role === 'super_admin' ? 'super_admin' : 'admin'"
                                    :severity="data.role === 'super_admin' ? 'warn' : 'info'" />
                            </template>
                        </Column>
                        <Column header="Statut">
                            <template #body="{ data }">
                                <Tag :value="data.is_active ? 'Actif' : 'Désactivé'"
                                    :severity="data.is_active ? 'success' : 'secondary'" />
                            </template>
                        </Column>
                        <Column header="Dernière connexion">
                            <template #body="{ data }">{{ formatDate(data.last_login_at) }}</template>
                        </Column>
                        <Column header="Actions">
                            <template #body="{ data }">
                                <div class="flex flex-wrap gap-2">
                                    <Link :href="route('admin.users.edit', data.id)">
                                        <Button icon="pi pi-pencil" severity="secondary" size="small" text />
                                    </Link>
                                    <Button icon="pi pi-envelope" severity="secondary" size="small" text
                                        title="Renvoyer le lien de définition de mot de passe"
                                        @click="resendResetLink(data)" />
                                    <Button :icon="data.is_active ? 'pi pi-ban' : 'pi pi-check'" severity="secondary"
                                        size="small" text @click="toggleActive(data)" />
                                    <Button icon="pi pi-trash" severity="danger" size="small" text
                                        @click="destroy(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <ConfirmPasswordModal
            v-model:visible="confirmingPassword"
            :form="confirmPasswordForm"
            @submit="submitConfirmPassword()"
        />
    </AdminLayout>
</template>
