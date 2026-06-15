<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { TailwindPagination } from 'laravel-vue-pagination'

const props = defineProps({
    servicio: Object,
    llamadas: Object,
})

// Tabs activos con v-show (sin rerender)
const activeTab = ref('cuotas')

const fmtCup = (v) => v != null ? Number(v).toFixed(2) : '—'

// Accesores derivados del servicio
const numeroEfectivo    = computed(() => props.servicio.numero_servicio_efectivo ?? props.servicio.numero_servicio ?? '—')
const tipoServicio      = computed(() => props.servicio.tipo_servicio ?? props.servicio.connectivity_record?.tipo_enlace ?? '—')
const descripcion       = computed(() => props.servicio.descripcion_servicio ?? props.servicio.connectivity_record?.velocidad_etecsa ?? '—')
const ubicacionLabel    = computed(() => props.servicio.ubicacion_label ?? props.servicio.connectivity_record?.sales_floor?.name ?? '—')
const matchEtiqueta     = computed(() => {
    const m = {
        connectivity: 'Catálogo conectividad',
        telefonia_piso: 'Teléfono en piso de venta',
        telefonia_departamento: 'Teléfono en oficina / departamento',
    }
    return m[props.servicio.match_source] ?? null
})
const diferenciaStr     = computed(() => {
    const d = props.servicio.diferencia_cuota
    if (d === null || d === undefined) return null
    const n = Number(d)
    return { value: n, positive: n > 0, negative: n < 0 }
})

// Columnas tabla llamadas
const columnasllamadas = [
    { key: 'fecha', label: 'Fecha' },
    { key: 'hora', label: 'Hora' },
    { key: 'lugar', label: 'Lugar' },
    { key: 'destino', label: 'Destino' },
    { key: 'duracion', label: 'Duración' },
    { key: 'importe', label: 'Importe', class: 'text-right' },
]
</script>

<template>
    <AppLayout :title="`Servicio ${numeroEfectivo}`">
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

            <PageHeader
                :title="`Servicio ${numeroEfectivo}`"
                :description="`${tipoServicio} · ${descripcion}`"
                eyebrow="Facturación ETECSA"
            >
                <template #actions>
                    <a
                        v-if="servicio.factura"
                        :href="route('etecsa.show', servicio.factura_id)"
                        class="app-button-secondary"
                    >
                        ← Factura {{ servicio.factura?.numero_factura }}
                    </a>
                </template>
            </PageHeader>

            <!-- Resumen del servicio -->
            <div class="grid gap-4 md:grid-cols-3">
                <BaseCard title="Identificación">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">N° Servicio</dt>
                            <dd class="font-mono font-semibold text-slate-900 dark:text-white">{{ numeroEfectivo }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Tipo</dt>
                            <dd class="text-slate-800 dark:text-slate-200">{{ tipoServicio }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Velocidad</dt>
                            <dd class="text-slate-800 dark:text-slate-200">{{ descripcion }}</dd>
                        </div>
                        <div v-if="matchEtiqueta" class="flex justify-between border-b border-slate-100 pb-2 dark:border-slate-700">
                            <dt class="text-slate-500">Vínculo</dt>
                            <dd class="text-right text-xs font-medium text-slate-700 dark:text-slate-300">{{ matchEtiqueta }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Ubicación</dt>
                            <dd class="max-w-[60%] text-right text-slate-800 dark:text-slate-200">{{ ubicacionLabel }}</dd>
                        </div>
                    </dl>
                </BaseCard>

                <BaseCard title="Importes">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Cuota facturada</dt>
                            <dd class="font-mono text-slate-800 dark:text-slate-200">{{ fmtCup(servicio.cuota_facturada) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Consumo</dt>
                            <dd class="font-mono text-slate-800 dark:text-slate-200">{{ fmtCup(servicio.consumo) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Comisión</dt>
                            <dd class="font-mono text-slate-800 dark:text-slate-200">{{ fmtCup(servicio.comision) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Impuesto</dt>
                            <dd class="font-mono text-slate-800 dark:text-slate-200">{{ fmtCup(servicio.impuesto) }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-slate-100 pt-2 dark:border-slate-700">
                            <dt class="font-semibold text-slate-700 dark:text-slate-200">Total servicio</dt>
                            <dd class="font-mono font-bold text-slate-900 dark:text-white">{{ fmtCup(servicio.total_servicio) }}</dd>
                        </div>
                    </dl>
                </BaseCard>

                <BaseCard title="Alerta de cuota" v-if="diferenciaStr && servicio.connectivity_record_id">
                    <div class="flex flex-col items-center justify-center h-full gap-2 text-center">
                        <p class="text-sm text-slate-500">Diferencia con catálogo</p>
                        <p class="font-display text-3xl font-bold"
                            :class="{
                                'text-rose-600 dark:text-rose-400': diferenciaStr.positive,
                                'text-emerald-600 dark:text-emerald-400': diferenciaStr.negative,
                                'text-slate-400': !diferenciaStr.positive && !diferenciaStr.negative
                            }">
                            {{ diferenciaStr.positive ? '+' : '' }}{{ diferenciaStr.value.toFixed(2) }} CUP
                        </p>
                        <p v-if="diferenciaStr.positive" class="text-xs text-rose-500">ETECSA cobra más que el catálogo</p>
                        <p v-else-if="diferenciaStr.negative" class="text-xs text-emerald-500">ETECSA cobra menos que el catálogo</p>
                        <p v-else class="text-xs text-slate-400">Sin diferencia</p>
                    </div>
                </BaseCard>
                <BaseCard v-else title="Catálogo">
                    <p class="text-sm text-slate-400">Este servicio no está vinculado a un registro de conectividad.</p>
                </BaseCard>
            </div>

            <!-- Tabs: Cuotas | Tráfico | Llamadas -->
            <BaseCard :padded="false">
                <!-- Tab nav -->
                <div class="border-b border-slate-200 dark:border-slate-700">
                    <nav class="flex gap-1 px-4 pt-4">
                        <button
                            v-for="tab in ['cuotas', 'trafico', 'llamadas']"
                            :key="tab"
                            type="button"
                            class="rounded-t-lg px-4 py-2 text-sm font-medium capitalize transition-colors"
                            :class="activeTab === tab
                                ? 'bg-white dark:bg-slate-900 text-brand-600 dark:text-brand-400 border border-b-white dark:border-b-slate-900 border-slate-200 dark:border-slate-700 -mb-px'
                                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                            @click="activeTab = tab"
                        >
                            {{ tab === 'cuotas' ? 'Cuotas' : tab === 'trafico' ? 'Tráfico' : 'Llamadas' }}
                            <span v-if="tab === 'llamadas' && llamadas.total > 0"
                                class="ml-1 rounded-full bg-brand-100 px-1.5 text-xs text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">
                                {{ llamadas.total }}
                            </span>
                        </button>
                    </nav>
                </div>

                <!-- Tab Cuotas -->
                <div v-show="activeTab === 'cuotas'" class="p-5">
                    <div v-if="servicio.cuotas_detalle?.length" class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-700">
                                    <th class="pb-2 text-left text-xs font-semibold uppercase text-slate-500">Concepto</th>
                                    <th class="pb-2 text-right text-xs font-semibold uppercase text-slate-500">Importe (CUP)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 dark:divide-slate-800">
                                <tr v-for="cuota in servicio.cuotas_detalle" :key="cuota.id">
                                    <td class="py-2 text-slate-700 dark:text-slate-300">{{ cuota.concepto }}</td>
                                    <td class="py-2 text-right font-mono text-slate-800 dark:text-slate-200">{{ fmtCup(cuota.importe) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-slate-400">No hay cuotas detalladas para este servicio.</p>
                </div>

                <!-- Tab Tráfico -->
                <div v-show="activeTab === 'trafico'" class="p-5">
                    <div v-if="servicio.trafico?.length" class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-700">
                                    <th class="pb-2 text-left text-xs font-semibold uppercase text-slate-500">Categoría</th>
                                    <th class="pb-2 text-left text-xs font-semibold uppercase text-slate-500">Subcategoría</th>
                                    <th class="pb-2 text-right text-xs font-semibold uppercase text-slate-500">Importe (CUP)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 dark:divide-slate-800">
                                <tr v-for="traf in servicio.trafico" :key="traf.id">
                                    <td class="py-2 text-slate-600 dark:text-slate-400 capitalize">{{ traf.categoria?.replace(/_/g, ' ') }}</td>
                                    <td class="py-2 text-slate-700 dark:text-slate-300">{{ traf.subcategoria ?? '—' }}</td>
                                    <td class="py-2 text-right font-mono text-slate-800 dark:text-slate-200">{{ fmtCup(traf.importe) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-slate-400">No hay datos de tráfico para este servicio.</p>
                </div>

                <!-- Tab Llamadas -->
                <div v-show="activeTab === 'llamadas'" class="p-5">
                    <div v-if="llamadas.total > 0">
                        <DataTable :columns="columnasllamadas" :data="llamadas">
                            <template #cell-importe="{ row }">
                                <span class="font-mono">{{ Number(row.importe).toFixed(4) }}</span>
                            </template>
                        </DataTable>
                    </div>
                    <p v-else class="text-sm text-slate-400">No hay llamadas de larga distancia registradas.</p>
                </div>
            </BaseCard>

        </div>
    </AppLayout>
</template>
