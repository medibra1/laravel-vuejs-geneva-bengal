<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { formatTimeAgo, type UseTimeAgoMessages } from '@vueuse/core';
import type { AppNotification } from '@/types/models';
import type { PageProps } from '@/types';

const timeAgoMessages: UseTimeAgoMessages = {
    justNow: "à l'instant",
    past: (n) => (n.match(/\d/) ? `il y a ${n}` : n),
    future: (n) => (n.match(/\d/) ? `dans ${n}` : n),
    invalid: '',
    second: (n) => `${n} seconde${n > 1 ? 's' : ''}`,
    minute: (n) => `${n} minute${n > 1 ? 's' : ''}`,
    hour: (n) => `${n} heure${n > 1 ? 's' : ''}`,
    day: (n) => `${n} jour${n > 1 ? 's' : ''}`,
    week: (n) => `${n} semaine${n > 1 ? 's' : ''}`,
    month: (n) => `${n} mois`,
    year: (n) => `${n} an${n > 1 ? 's' : ''}`,
};

function timeAgo(notification: AppNotification): string {
    return formatTimeAgo(new Date(notification.created_at), { messages: timeAgoMessages });
}

const page = usePage<PageProps>();
const open = ref(false);

function close() {
    open.value = false;
}

// Closes on outside click, same pattern as Dropdown.vue — not reused
// directly here since its `width` prop only supports '48', too narrow for
// notification text.
const closeOnEscape = (e: KeyboardEvent) => {
    if (open.value && e.key === 'Escape') close();
};
onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));

function iconFor(notification: AppNotification): string {
    if (notification.type === 'stripe_issue') {
        return notification.reason === 'error' ? 'pi-exclamation-triangle' : 'pi-info-circle';
    }

    if (notification.type === 'deposit_created') return 'pi-wallet';
    if (notification.type === 'newsletter_subscriber') return 'pi-send';

    return 'pi-envelope';
}

function iconColorClass(notification: AppNotification): string {
    if (notification.type === 'stripe_issue') {
        // Technical error needs a quick look, an expired reservation is
        // just informative — see CLAUDE.md's notification spec.
        return notification.reason === 'error'
            ? 'bg-orange-100 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400'
            : 'bg-neutral-100 text-neutral-500 dark:bg-white/5 dark:text-neutral-400';
    }

    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400';
}

function openNotification(notification: AppNotification) {
    close();

    if (notification.read_at) {
        if (notification.url) router.visit(notification.url);

        return;
    }

    router.post(
        route('admin.notifications.read', notification.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                if (notification.url) router.visit(notification.url);
            },
        },
    );
}

function markAllRead() {
    router.post(route('admin.notifications.read-all'), {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <div v-if="page.props.notifications" class="relative">
        <button
            type="button"
            title="Notifications"
            class="relative flex h-9 w-9 items-center justify-center rounded-full text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 dark:text-neutral-400 dark:hover:bg-white/5 dark:hover:text-white"
            @click="open = !open"
        >
            <i class="pi pi-bell" />
            <span
                v-if="page.props.notifications.unread_count > 0"
                class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-none text-white"
            >
                {{ page.props.notifications.unread_count > 9 ? '9+' : page.props.notifications.unread_count }}
            </span>
        </button>

        <div v-if="open" class="fixed inset-0 z-40" @click="close" />

        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="fixed inset-x-4 top-16 z-50 origin-top rounded-md border border-gray-200 bg-white shadow-lg sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-96 sm:max-w-[90vw] sm:origin-top-right dark:border-neutral-800 dark:bg-neutral-800"
            >
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-neutral-700">
                    <span class="text-sm font-semibold text-neutral-900 dark:text-white">Notifications</span>
                    <button
                        v-if="page.props.notifications.unread_count > 0"
                        type="button"
                        class="text-xs font-medium text-emerald-700 hover:underline dark:text-emerald-400"
                        @click="markAllRead"
                    >
                        Tout marquer comme lu
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <p
                        v-if="page.props.notifications.recent.length === 0"
                        class="px-4 py-6 text-center text-sm text-neutral-400"
                    >
                        Aucune notification
                    </p>

                    <button
                        v-for="notification in page.props.notifications.recent"
                        :key="notification.id"
                        type="button"
                        class="flex w-full items-start gap-3 border-b border-gray-100 px-4 py-3 text-left last:border-b-0 hover:bg-neutral-50 dark:border-neutral-700/50 dark:hover:bg-white/5"
                        :class="{ 'bg-emerald-50/50 dark:bg-emerald-500/5': !notification.read_at }"
                        @click="openNotification(notification)"
                    >
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                            :class="iconColorClass(notification)"
                        >
                            <i class="pi text-sm" :class="iconFor(notification)" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-neutral-900 dark:text-white">
                                {{ notification.title }}
                            </span>
                            <span class="block truncate text-xs text-neutral-500 dark:text-neutral-400">
                                {{ notification.message }}
                            </span>
                            <span class="mt-0.5 block text-[11px] text-neutral-400 dark:text-neutral-500">
                                {{ timeAgo(notification) }}
                            </span>
                        </span>
                        <span v-if="!notification.read_at" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-emerald-600" />
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
