<script setup>
const props = defineProps({
    statistics: { type: Object, required: true },
})

const cards = [
    { key: 'total', label: 'Activos totales', tone: 'from-slate-900 to-slate-700 dark:from-brand-400 dark:to-blue-500', accent: 'text-white dark:text-slate-950' },
    { key: 'bien', label: 'En buen estado', tone: 'from-emerald-500 to-teal-500', accent: 'text-white' },
    { key: 'regular', label: 'Requieren seguimiento', tone: 'from-amber-400 to-orange-500', accent: 'text-slate-950' },
    { key: 'mal', label: 'Criticos', tone: 'from-rose-500 to-red-600', accent: 'text-white' },
]
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article
            v-for="card in cards"
            :key="card.key"
            class="surface-card surface-hover overflow-hidden p-0"
        >
            <div :class="card.tone" class="bg-gradient-to-br p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em]" :class="card.accent">{{ card.label }}</p>
                        <p class="mt-4 font-display text-4xl font-bold tracking-tight" :class="card.accent">
                            {{ statistics[card.key] ?? 0 }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-white/20 bg-white/10 px-3 py-2 text-xs font-semibold text-white/90 backdrop-blur dark:border-slate-950/10 dark:bg-slate-950/10 dark:text-slate-950/80">
                        Live
                    </div>
                </div>
            </div>
        </article>
    </div>
</template>
