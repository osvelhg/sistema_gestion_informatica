<script setup>
defineProps({
    title: { type: String, default: 'Sin resultados' },
    description: { type: String, default: 'No hay informacion disponible para mostrar por ahora.' },
})
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300/90 px-6 py-12 text-center dark:border-slate-700">
        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5m-16.5 5.25h16.5m-16.5 5.25h10.5" />
            </svg>
        </div>
        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h3>
        <p class="mt-2 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">{{ description }}</p>
        <div v-if="$slots.default" class="mt-5">
            <slot />
        </div>
    </div>
</template>
