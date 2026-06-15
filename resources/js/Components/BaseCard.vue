<script setup>
defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    padded: { type: Boolean, default: true },
    hoverable: { type: Boolean, default: false },
})
</script>

<template>
    <section :class="['surface-card', hoverable ? 'surface-hover' : '', padded ? 'p-5 md:p-6' : '']">
        <header v-if="title || subtitle || $slots.header" class="mb-5 flex items-start justify-between gap-4">
            <div v-if="title || subtitle">
                <h3 v-if="title" class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h3>
                <p v-if="subtitle" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ subtitle }}</p>
            </div>
            <slot name="header" />
        </header>

        <slot />
    </section>
</template>
