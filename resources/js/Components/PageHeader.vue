<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
})
</script>

<template>
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
            <p v-if="eyebrow" class="text-xs font-semibold uppercase tracking-[0.22em] text-brand-600 dark:text-brand-300">{{ eyebrow }}</p>
            <h1 class="font-display text-3xl font-bold tracking-tight text-slate-950 dark:text-white md:text-4xl">{{ title }}</h1>
            <p v-if="description" class="max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400 md:text-base">{{ description }}</p>
        </div>

        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-3">
            <slot name="actions" />
        </div>
    </div>
</template>
