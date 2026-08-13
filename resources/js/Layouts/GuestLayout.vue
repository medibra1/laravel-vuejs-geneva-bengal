<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import logo from '../../images/shared/logo-gb.png';
</script>

<template>
    <div class="flex min-h-screen bg-white dark:bg-neutral-900">
        <!-- Branding panel — dropped below lg rather than squeezed, so the
             form always keeps its full width instead of fighting a
             shrinking sidebar on tablets. -->
        <div class="relative hidden w-[42%] shrink-0 overflow-hidden bg-gradient-to-br from-emerald-800 via-emerald-700 to-neutral-900 lg:flex lg:flex-col lg:items-center lg:justify-center">
            <svg class="absolute inset-0 h-full w-full opacity-[0.07]" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="guest-dots" width="28" height="28" patternUnits="userSpaceOnUse">
                        <circle cx="2" cy="2" r="1.6" fill="white" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#guest-dots)" />
            </svg>

            <div class="relative flex flex-col items-center px-12 text-center">
                <Link href="/" class="rounded-2xl bg-white p-4 shadow-xl">
                    <img :src="logo" alt="Geneva Bengal" width="64" height="64" class="h-16 w-auto" />
                </Link>
                <h1 class="font-script mt-8 text-4xl text-white">Geneva Bengal</h1>
                <p class="mt-3 max-w-xs text-sm text-emerald-100/90">
                    Espace d'administration — gérez les chats, portées, contenus et demandes en un seul endroit.
                </p>
            </div>
        </div>

        <!-- Form side -->
        <div class="flex flex-1 flex-col items-center justify-center px-6 py-12 sm:px-12">
            <div class="w-full max-w-sm">
                <Link href="/" class="mb-8 flex justify-center lg:hidden">
                    <img :src="logo" alt="Geneva Bengal" width="56" height="56" class="h-14 w-auto" />
                </Link>

                <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm dark:border-neutral-800 dark:bg-neutral-800">
                    <slot />
                </div>

                <p class="mt-6 text-center text-xs text-neutral-400 dark:text-neutral-500">
                    © {{ new Date().getFullYear() }} Geneva Bengal — Back-office
                </p>
            </div>
        </div>
    </div>
</template>
