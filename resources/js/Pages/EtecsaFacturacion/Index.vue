<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { confirmDanger, notifySuccess, notifyError } from '@/Composables/useNotifications'
import { formatDateEs } from '@/utils/formatDateEs'

const props = defineProps({
    facturas: Object,
    filters:  Object,
})

// ── Filtros ────────────────────────────────────────────────────────────────
const search        = ref(props.filters?.search || '')
const tipoFilter    = ref(props.filters?.tipo || '')
const periodoDesde  = ref(props.filters?.periodo_desde || '')
const periodoHasta  = ref(props.filters?.periodo_hasta || '')

let filterTimer = null
const applyFilters = () => {
    clearTimeout(filterTimer)
    filterTimer = setTimeout(() => {
        const params = {}
        if (search.value)       params.search = search.value
        if (tipoFilter.value)   params.tipo = tipoFilter.value
        if (periodoDesde.value) params.periodo_desde = periodoDesde.value
        if (periodoHasta.value) params.periodo_hasta = periodoHasta.value
        router.get(route('etecsa.index'), params, { preserveState: true, replace: true })
    }, 350)
}

watch([search, tipoFilter, periodoDesde, periodoHasta], applyFilters)

const resetFilters = () => {
    search.value       = ''
    tipoFilter.value   = ''
    periodoDesde.value = ''
    periodoHasta.value = ''
}

// ── Importación PDF ────────────────────────────────────────────────────────
const pdfFile       = ref(null)
const previewLoading = ref(false)
const applyLoading  = ref(false)
const previewData   = ref(null)    // resultado de buildPreview

const onPdfFile = (e) => {
    pdfFile.value    = e.target.files?.[0] ?? null
    previewData.value = null
}

const runPreview = async () => {
    if (!pdfFile.value) return
    previewData.value = null
    previewLoading.value = true
    try {
        const fd = new FormData()
        fd.append('pdf_file', pdfFile.value)
        const { data } = await axios.post(route('etecsa.importar.preview'), fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        previewData.value = data
    } catch (e) {
        const msg = e.response?.data?.message || 'Error al procesar el PDF.'
        if (e.response?.status === 409) {
            notifyError('Factura duplicada', msg)
        } else {
            notifyError('Error', msg)
        }
    } finally {
        previewLoading.value = false
    }
}

const applyImport = async () => {
    if (!previewData.value) return
    applyLoading.value = true
    try {
        const { data } = await axios.post(route('etecsa.importar.aplicar'), {
            preview: previewData.value,
        })
        notifySuccess('Importada', data.message)
        previewData.value = null
        pdfFile.value = null
        router.visit(data.redirect || route('etecsa.index'))
    } catch (e) {
        notifyError('Error al importar', e.response?.data?.message || 'Error desconocido.')
    } finally {
        applyLoading.value = false
    }
}

// ── Eliminación ────────────────────────────────────────────────────────────
const eliminar = async (factura) => {
    const ok = await confirmDanger({
        title: `Eliminar factura ${factura.numero_factura}`,
        text: 'Se eliminarán todos los servicios, cuotas, tráfico y llamadas asociadas. Esta acción no se puede deshacer.',
        confirmText: 'Eliminar',
    })
    if (!ok) return
    router.delete(route('etecsa.destroy', factura.id), {
        onSuccess: () => notifySuccess('Eliminada', `Factura ${factura.numero_factura} eliminada.`),
        onError: () => notifyError('Error', 'No se pudo eliminar la factura.'),
    })
}

// ── Exportación ────────────────────────────────────────────────────────────
const exportFormat = ref('csv')
const exportUrl = computed(() => {
    const params = new URLSearchParams({ format: exportFormat.value })
    if (search.value)       params.set('search', search.value)
    if (tipoFilter.value)   params.set('tipo', tipoFilter.value)
    return route('etecsa.exportar') + '?' + params.toString()
})

// ── Tabla facturas ─────────────────────────────────────────────────────────
const columns = [
    { key: 'numero_factura', label: 'N° Factura' },
    { key: 'numero_cliente', label: 'N° Cliente' },
    { key: 'nombre_cliente', label: 'Cliente', class: 'max-w-[180px] truncate' },
    { key: 'periodo', label: 'Período', sortValue: r => r.periodo_desde },
    { key: 'tipo_factura', label: 'Tipo' },
    { key: 'servicios_count', label: 'Servicios' },
    { key: 'total_a_pagar', label: 'Total CUP', class: 'text-right' },
    { key: 'total_usd', label: 'Total USD', class: 'text-right' },
]

const formatPeriodoRow = (row) => {
    const a = formatDateEs(row.periodo_desde)
    const b = formatDateEs(row.periodo_hasta)
    if (a === '—' && b === '—') return '—'
    return `${a} — ${b}`
}

const tipoBadgeClass = (tipo) => ({
    telefonia:   'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    conectividad: 'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    mixta:       'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
}[tipo] ?? 'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-slate-100 text-slate-600')

// ── Estadísticas del preview ───────────────────────────────────────────────
const previewResumen = computed(() => previewData.value?.resumen ?? null)

function previewEtiquetaVinculo(svc) {
    if (svc.match_status !== 'matched') return '—'
    const m = {
        connectivity: 'Catálogo conectividad',
        telefonia_piso: 'Tel. piso de venta',
        telefonia_departamento: 'Tel. oficina / dept.',
    }
    return m[svc.match_source] ?? svc.match_source ?? '—'
}

function previewUbicacion(svc) {
    if (svc.sales_floor_name) return svc.sales_floor_name
    if (svc.department_name) return `Oficina · ${svc.department_name}`
    return '—'
}
</script>

<template>
    <AppLayout title="Facturación ETECSA">
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

            <PageHeader
                title="Facturación ETECSA"
                description="Importación y análisis de facturas PDF de ETECSA (telefonía y conectividad)."
                eyebrow="Módulo"
            >
                <template #actions>
                    <a :href="route('etecsa.dashboard')" class="app-button-secondary">Dashboard</a>
                </template>
            </PageHeader>

            <!-- Panel de importación PDF -->
            <BaseCard title="Importar factura PDF" subtitle="Selecciona un PDF de factura ETECSA para previsualizar antes de confirmar.">
                <div class="flex flex-col gap-4 md:flex-row md:items-end">
                    <div class="flex-1">
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Archivo PDF</label>
                        <input
                            type="file"
                            accept=".pdf"
                            class="app-input"
                            @change="onPdfFile"
                        />
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="app-button"
                            :disabled="!pdfFile || previewLoading"
                            @click="runPreview"
                        >
                            <span v-if="previewLoading">Procesando…</span>
                            <span v-else>Previsualizar</span>
                        </button>
                    </div>
                </div>

                <!-- Resultado del preview -->
                <div v-if="previewData" class="mt-6 space-y-4">
                    <!-- Cabecera de la factura -->
                    <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800/50 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">Factura</p>
                            <p class="font-mono font-semibold text-slate-900 dark:text-white">{{ previewData.factura?.numero_factura }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">Cliente</p>
                            <p class="text-slate-700 dark:text-slate-200">{{ previewData.factura?.nombre_cliente }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">Período</p>
                            <p class="text-slate-700 dark:text-slate-200">{{ previewData.factura?.periodo_desde }} – {{ previewData.factura?.periodo_hasta }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">Total a pagar</p>
                            <p class="font-bold text-slate-900 dark:text-white">{{ previewData.factura?.total_a_pagar }} CUP</p>
                        </div>
                    </div>

                    <!-- Resumen de matching -->
                    <div v-if="previewResumen" class="flex flex-wrap gap-3 text-sm">
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                            {{ previewResumen.matched }} vinculados
                        </span>
                        <span v-if="previewResumen.unmatched > 0" class="rounded-full bg-amber-100 px-3 py-1 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                            {{ previewResumen.unmatched }} sin vincular
                        </span>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            Total: {{ previewResumen.total }} servicios
                        </span>
                    </div>

                    <!-- Tabla de servicios del preview -->
                    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">N° Servicio</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Vínculo</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Tipo</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Ubicación</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Cuota</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Total</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700 bg-white dark:bg-slate-900">
                                <tr v-for="(svc, idx) in previewData.servicios" :key="`${svc.numero_servicio}-${idx}`">
                                    <td class="px-3 py-2 font-mono text-slate-800 dark:text-slate-200">{{ svc.numero_servicio }}</td>
                                    <td class="px-3 py-2 text-xs text-slate-700 dark:text-slate-300">{{ previewEtiquetaVinculo(svc) }}</td>
                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ svc.tipo_enlace || '—' }}</td>
                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ previewUbicacion(svc) }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-slate-700 dark:text-slate-300">{{ svc.cuota_facturada }}</td>
                                    <td class="px-3 py-2 text-right font-mono font-semibold text-slate-900 dark:text-white">{{ svc.total_servicio }}</td>
                                    <td class="px-3 py-2">
                                        <span v-if="svc.match_status === 'matched'"
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                            Vinculado
                                        </span>
                                        <span v-else
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                            Sin vincular
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="button"
                            class="app-button"
                            :disabled="applyLoading"
                            @click="applyImport"
                        >
                            <span v-if="applyLoading">Importando…</span>
                            <span v-else>Confirmar importación</span>
                        </button>
                    </div>
                </div>
            </BaseCard>

            <!-- Filtros -->
            <BaseCard title="Filtros">
                <div class="flex flex-col gap-4 md:flex-row md:items-end">
                    <div class="w-40">
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Tipo</label>
                        <select v-model="tipoFilter" class="app-select">
                            <option value="">Todos</option>
                            <option value="telefonia">Telefonía</option>
                            <option value="conectividad">Conectividad</option>
                            <option value="mixta">Mixta</option>
                        </select>
                    </div>
                    <div class="w-40">
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Desde</label>
                        <input v-model="periodoDesde" type="date" class="app-input" />
                    </div>
                    <div class="w-40">
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Hasta</label>
                        <input v-model="periodoHasta" type="date" class="app-input" />
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="app-button-secondary" @click="resetFilters">Limpiar</button>
                        <select v-model="exportFormat" class="app-select w-20">
                            <option value="csv">CSV</option>
                            <option value="xlsx">XLSX</option>
                        </select>
                        <a :href="exportUrl" class="app-button-secondary">Exportar</a>
                    </div>
                </div>
            </BaseCard>

            <!-- Tabla de facturas -->
            <BaseCard :padded="false">
                <div class="flex flex-col gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Facturas importadas</h3>
                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Búsqueda en servidor; respeta tipo y fechas de los filtros.</p>
                    </div>
                    <input
                        v-model="search"
                        type="search"
                        class="app-input w-full min-w-0 max-w-md shrink-0"
                        placeholder="Buscar por N° factura, cliente, N° cliente…"
                        autocomplete="off"
                    />
                </div>
                <DataTable :columns="columns" :data="facturas">
                    <template #cell-tipo_factura="{ row }">
                        <span :class="tipoBadgeClass(row.tipo_factura)">{{ row.tipo_factura }}</span>
                    </template>
                    <template #cell-total_a_pagar="{ row }">
                        <span class="font-mono font-semibold">{{ Number(row.total_a_pagar).toFixed(2) }}</span>
                    </template>
                    <template #cell-total_usd="{ row }">
                        <span v-if="row.total_usd" class="font-mono text-emerald-600 dark:text-emerald-400">{{ Number(row.total_usd).toFixed(4) }}</span>
                        <span v-else class="text-slate-400">—</span>
                    </template>
                    <template #cell-periodo="{ row }">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ formatPeriodoRow(row) }}</span>
                    </template>
                    <template #cell-numero_factura="{ row }">
                        <a :href="route('etecsa.show', row.id)" class="font-mono font-semibold text-brand-600 hover:underline dark:text-brand-400">
                            {{ row.numero_factura }}
                        </a>
                    </template>
                    <template #actions="{ row }">
                        <a :href="route('etecsa.show', row.id)" class="text-xs text-brand-600 hover:underline dark:text-brand-400">Ver</a>
                        <button type="button" class="text-xs text-rose-600 hover:underline dark:text-rose-400 ml-3" @click="eliminar(row)">Eliminar</button>
                    </template>
                </DataTable>
            </BaseCard>

        </div>
    </AppLayout>
</template>
