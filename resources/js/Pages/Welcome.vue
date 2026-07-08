<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ version: string }>();
</script>

<template>
    <Head title="Welcome" />

    <main class="flex min-h-screen flex-col items-center justify-center gap-6 px-6 text-center">
        <div class="flex items-center gap-3">
            <div class="grid size-12 place-items-center rounded-[--radius-md] bg-accent text-on-accent text-2xl font-bold">
                M
            </div>
            <h1 class="text-3xl font-semibold tracking-tight">MediaForge</h1>
        </div>

        <p class="max-w-prose text-fg-muted">
            An open-source local media enhancement suite for Jellyfin &amp; Audiobookshelf.
        </p>

        <span
            class="rounded-[--radius-sm] border border-line bg-surface-raised px-3 py-1 font-mono text-sm text-fg-muted"
        >
            {{ version }}
        </span>
    </main>
</template>
