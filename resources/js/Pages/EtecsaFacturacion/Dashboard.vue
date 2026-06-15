<script setup>
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import PageHeader from '@/Components/PageHeader.vue'

const props = defineProps({
    kpis:         Object,
    evolucion:    Array,
    topServicios: Array,
    porPiso:      Array,
})

const fmtCup = (v) => v != null ? Number(v).toFixed(2) : '—'
const fmtUsd = (v) => v != null ? Number(v).toFixed(4) : null

const varClass = computed(() => {
    const v = props.kpis?.variacion
    if (v === null || v === undefined) return 'text-slate-400'
    return v > 0 ? 'text-rose-600 dark:text-rose-400' : v < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'
})

const varLabel = computed(() => {
    const v = props.kpis?.variacion
    if (v === null || v === undefined) return 'Sin comparativa'
    const prefix = v > 0 ? '+' : ''
    return `${prefix}${Number(v).toFixed(2)} CUP respecto al período anterior`
})
</script>

<template>
    <AppLayout title="Dashboard ETECSA">
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

            <PageHeader
                title="Dashboard ETECSA"
                :description="kpis.periodo ? `Último período: ${kpis.periodo}` : 'Sin datos aún'"
                eyebrow="Facturación ETECSA"
            >
                <template #actions>
                    <a :href="route('etecsa.index')" class="app-button-secondary">← Facturas</a>
                </template>
            </PageHeader>

            <!-- KPIs -->
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Total a pagar</p>
                    <p class="mt-2 font-display text-3xl font-bold text-slate-900 dark:text-white">{{ fmtCup(kpis.total_a_pagar) }}</p>
                    <p class="mt-1 text-xs font-mono" :class="varClass">{{ varLabel }}</p>
                </article>

                <article class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Total facturado</p>
                    <p class="mt-2 font-display text-3xl font-bold text-slate-900 dark:text-white">{{ fmtCup(kpis.total_facturado) }}</p>
                </article>

                <article class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Servicios activos</p>
                    <p class="mt-2 font-display text-3xl font-bold text-slate-900 dark:text-white">{{ kpis.servicios_activos }}</p>
                    <p class="mt-1 text-xs text-slate-400">en este período</p>
                </article>

                <article class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Total USD</p>
                    <p v-if="fmtUsd(kpis.total_usd)" class="mt-2 font-display text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ fmtUsd(kpis.total_usd) }}
                    </p>
                    <p v-else class="mt-2 font-display text-3xl font-bold text-slate-300 dark:text-slate-600">—</p>
                </article>
            </div>

            <!-- Evolución mensual -->
            <BaseCard title="Evolución mensual" subtitle="Últimos 12 períodos importados">
                <div v-if="evolucion.length" class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-700">
                                <th class="pb-2 text-left text-xs font-semibold uppercase text-slate-500">Período</th>
                                <th class="pb-2 text-left text-xs font-semibold uppercase text-slate-500">Tipo</th>
                                <th class="pb-2 text-right text-xs font-semibold uppercase text-slate-500">Cuota mensual</th>
                                <th class="pb-2 text-right text-xs font-semibold uppercase text-slate-500">Consumo</th>
                                <th class="pb-2 text-right text-xs font-semibold uppercase text-slate-500">Total a pagar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-slate-800">
                            <tr v-for="row in evolucion" :key="row.periodo_desde">
                                <td class="py-2 font-mono text-slate-700 dark:text-slate-300">{{ row.periodo_desde }}</td>
                                <td class="py-2 capitalize text-slate-600 dark:text-slate-400">{{ row.tipo_factura }}</td>
                                <td class="py-2 text-right font-mono text-slate-700 dark:text-slate-300">{{ fmtCup(row.total_cuota_mensual) }}</td>
                                <td class="py-2 text-right font-mono text-slate-700 dark:text-slate-300">{{ fmtCup(row.total_consumo) }}</td>
                                <td class="py-2 text-right font-mono font-bold text-slate-900 dark:text-white">{{ fmtCup(row.total_a_pagar) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-slate-400">No hay facturas importadas aún.</p>
            </BaseCard>

            <div class="grid gap-4 xl:grid-cols-2">
                <!-- Top 10 servicios -->
                <BaseCard title="Top 10 servicios más costosos" subtitle="Último período">
                    <div v-if="topServicios.length" class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-700">
                                    <th class="pb-2 text-left text-xs font-semibold uppercase text-slate-500">N° Servicio</th>
                                    <th class="pb-2 text-left text-xs font-semibold uppercase text-slate-500">Piso</th>
                                    <th class="pb-2 text-right text-xs font-semibold uppercase text-slate-500">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 dark:divide-slate-800">
                                <tr v-for="svc in topServicios" :key="svc.numero_servicio">
                                    <td class="py-1.5 font-mono text-slate-800 dark:text-slate-200">{{ svc.numero_servicio }}</td>
                                    <td class="py-1.5 text-slate-600 dark:text-slate-400">{{ svc.piso ?? '—' }}</td>
                                    <td class="py-1.5 text-right font-mono font-semibold text-slate-900 dark:text-white">{{ fmtCup(svc.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-slate-400">Sin datos.</p>
                </BaseCard>

                <!-- Desglose por Piso de Venta -->
                <BaseCard title="Desglose por Piso de Venta" subtitle="Último período">
                    <div v-if="porPiso.length" class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-700">
                                    <th class="pb-2 text-left text-xs font-semibold uppercase text-slate-500">Piso</th>
                                    <th class="pb-2 text-right text-xs font-semibold uppercase text-slate-500">Servicios</th>
                                    <th class="pb-2 text-right text-xs font-semibold uppercase text-slate-500">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 dark:divide-slate-800">
                                <tr v-for="row in porPiso" :key="row.piso_name">
                                    <td class="py-1.5 text-slate-700 dark:text-slate-300">{{ row.piso_name }}</td>
                                    <td class="py-1.5 text-right text-slate-600 dark:text-slate-400">{{ row.servicios }}</td>
                                    <td class="py-1.5 text-right font-mono font-semibold text-slate-900 dark:text-white">{{ fmtCup(row.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-slate-400">Sin datos de desglose por piso.</p>
                </BaseCard>
            </div>

        </div>
    </AppLayout>
</template>
