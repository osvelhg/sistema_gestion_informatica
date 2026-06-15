<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatisticsBar from '@/Components/StatisticsBar.vue'
import BaseCard from '@/Components/BaseCard.vue'
import PageHeader from '@/Components/PageHeader.vue'

defineProps({
    statistics: Object,
})

const quickActions = [
    {
        title: 'Nuevo expediente',
        description: 'Registra equipos, componentes y responsables con un flujo guiado.',
        href: '/expedientes/create',
        tone: 'from-cyan-400 to-blue-500',
        icon: 'M12 4v16m8-8H4',
    },
    {
        title: 'Gestion de activos',
        description: 'Explora el inventario tecnico con filtros, estados y movimientos.',
        href: '/expedientes',
        tone: 'from-slate-900 to-slate-700 dark:from-slate-700 dark:to-slate-600',
        icon: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    },
    {
        title: 'Auditoria operativa',
        description: 'Monitorea eventos clave y trazabilidad de decisiones del sistema.',
        href: '/auditoria',
        tone: 'from-violet-500 to-fuchsia-500',
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    },
]
</script>

<template>
    <AppLayout>
        <PageHeader
            eyebrow="Centro de control"
            title="Dashboard operativo"
            description="Una vista ejecutiva, clara y moderna para controlar activos, estados tecnicos y flujos criticos del sistema."
        >
            <template #actions>
                <Link href="/expedientes/create" class="app-button-primary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Crear expediente
                </Link>
            </template>
        </PageHeader>

        <StatisticsBar :statistics="statistics" />

        <div class="mt-8 grid gap-5 xl:grid-cols-[1.5fr_1fr]">
            <BaseCard title="Accesos de alta prioridad" subtitle="Los flujos que mas usa el equipo, en una sola vista.">
                <div class="grid gap-4 md:grid-cols-3">
                    <Link
                        v-for="action in quickActions"
                        :key="action.title"
                        :href="action.href"
                        class="group overflow-hidden rounded-2xl border border-slate-200/80 bg-white/70 transition-all hover:-translate-y-1 hover:border-transparent hover:shadow-glow dark:border-slate-700/70 dark:bg-slate-900/50"
                    >
                        <div :class="action.tone" class="bg-gradient-to-br p-5 text-white dark:text-slate-950">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-white/20 bg-white/10 backdrop-blur">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="action.icon" />
                                </svg>
                            </div>
                            <h3 class="mt-6 text-lg font-semibold">{{ action.title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-white/80 dark:text-slate-950/75">{{ action.description }}</p>
                        </div>
                    </Link>
                </div>
            </BaseCard>

            <BaseCard title="Ritmo del sistema" subtitle="Indicadores visuales orientados a productividad.">
                <div class="space-y-4">
                    <div class="surface-card-muted p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Disponibilidad</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Infraestructura estable y lista para operar.</p>
                            </div>
                            <span class="app-badge border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">99.9%</span>
                        </div>
                    </div>

                    <div class="surface-card-muted p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Foco actual</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Seguimiento de activos y disciplina de inventario.</p>
                            </div>
                            <span class="app-badge border-brand-200 bg-brand-50 text-brand-700 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-300">Core</span>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-dashed border-slate-300/80 p-4 dark:border-slate-700">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">UX note</p>
                        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                            Este dashboard ya usa el nuevo lenguaje visual: capas suaves, contraste controlado, superficies premium y dark mode persistente.
                        </p>
                    </div>
                </div>
            </BaseCard>
        </div>
    </AppLayout>
</template>
