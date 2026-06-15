<script setup>
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import MatchResolverModal from '@/Components/MatchResolverModal.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({
    records:          Object,
    filters:          Object,
    linkModes:        Array,   // [{ id, code, nombre }] — desde modos_adsl (tipo de enlace)
    contractedSpeeds: Array,   // [{ id, nombre, kbps }] — desde velocidades_contratadas
})

const search = ref(props.filters?.search || '')
const salesFloorFilter = ref(null)
const showModal = ref(false)
const editing = ref(null)
const showExportModal = ref(false)
const exportFilters = ref({
    search:           props.filters?.search || '',
    contracted_speed: '',
    format:           'csv',
})
const exportSalesFloor = ref(null)
// ── Vista previa de importación ───────────────────────────────────────────────
const previewFile          = ref(null)
const previewLoading       = ref(false)
const previewResult        = ref(null)   // respuesta completa del backend
const previewChanges       = ref([])     // filas con _selected
const applyLoading         = ref(false)
const applyResult          = ref(null)
const showResolverModal    = ref(false)
const unresolvedRecords    = ref([])

const previewApplicableCount = computed(() =>
    previewChanges.value.filter(r => r._selected && r.match_status !== 'skipped').length
)

watch(previewResult, (result) => {
    if (result?.records) {
        previewChanges.value = result.records.map(r => ({
            ...r,
            _selected: r.match_status !== 'skipped',
        }))
    } else {
        previewChanges.value = []
    }
})

const onPreviewFile = (e) => {
    previewFile.value = e.target.files?.[0] ?? null
    previewResult.value = null
    applyResult.value = null
}

const runPreview = async () => {
    if (!previewFile.value) return
    previewResult.value = null
    applyResult.value = null
    previewLoading.value = true
    try {
        const fd = new FormData()
        fd.append('excel_file', previewFile.value)
        const { data } = await axios.post('/conectividad/importar-preview', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        previewResult.value = data
    } catch (e) {
        previewResult.value = { success: false, message: e.response?.data?.message || 'Error al procesar el archivo.' }
    } finally {
        previewLoading.value = false
    }
}

const openResolverForUnmatched = () => {
    unresolvedRecords.value = previewChanges.value.filter(
        r => r._selected && r.match_status === 'unmatched'
    )
    showResolverModal.value = unresolvedRecords.value.length > 0
}

const applyResolvedUnmatched = (resolved) => {
    let idx = 0
    previewChanges.value = previewChanges.value.map(rec => {
        if (rec.match_status === 'unmatched' && rec._selected) {
            const next = resolved[idx++]
            return next ? { ...next, _selected: true } : rec
        }
        return rec
    })
    showResolverModal.value = false
    unresolvedRecords.value = []
}

/** Quita campos internos UI (_prefixed) antes de enviar al backend. */
function stripPreviewRowForApi(row) {
    const o = {}
    for (const k of Object.keys(row)) {
        if (k.startsWith('_')) continue
        o[k] = row[k]
    }
    return o
}

function syncImportPreviewSummary() {
    if (!previewResult.value?.success) return
    const s = { matched: 0, unmatched: 0, skipped: 0 }
    previewChanges.value.forEach(r => {
        if (r.match_status in s) s[r.match_status]++
    })
    previewResult.value = { ...previewResult.value, summary: { ...s } }
}

async function searchEntitiesForImport(query, loading, idx) {
    if (!query || query.length < 1) return
    loading(true)
    const row = previewChanges.value[idx]
    if (row) row._entitySearchLoading = true
    try {
        const { data } = await axios.get(route('conectividad.entidades.search'), { params: { q: query } })
        if (previewChanges.value[idx]) {
            previewChanges.value[idx]._entityOptions = (data.entities || []).map(e => ({
                id: e.id,
                label: e.label,
            }))
        }
    } catch {
        if (previewChanges.value[idx]) previewChanges.value[idx]._entityOptions = []
    } finally {
        loading(false)
        if (previewChanges.value[idx]) previewChanges.value[idx]._entitySearchLoading = false
    }
}

async function onPickedEntityForSkipped(idx, opt) {
    const row = previewChanges.value[idx]
    if (!row || row.match_status !== 'skipped') return

    row._pickedEntity = opt ?? null
    if (!opt?.id) return

    row._entityBindLoading = true
    try {
        const { data } = await axios.post(route('conectividad.importar.vincularEntidad'), {
            entity_id: opt.id,
            record: stripPreviewRowForApi(row),
        })
        if (data.success && data.record) {
            const wasSelected = row._selected
            previewChanges.value[idx] = {
                ...data.record,
                _selected: wasSelected,
                _pickedEntity: { id: opt.id, label: opt.label },
            }
            syncImportPreviewSummary()
        }
    } catch (e) {
        applyResult.value = {
            success: false,
            message: e.response?.data?.message || 'No se pudo vincular la entidad.',
        }
    } finally {
        if (previewChanges.value[idx]) previewChanges.value[idx]._entityBindLoading = false
    }
}

const applySelectedChanges = async () => {
    const selected = previewChanges.value.filter(r => r._selected && r.match_status !== 'skipped')
    if (!selected.length) return
    if (selected.some(r => r.match_status === 'unmatched' && !r.sales_floor_id && !r.create_new)) {
        openResolverForUnmatched()
        return
    }
    applyLoading.value = true
    applyResult.value = null
    try {
        const { data } = await axios.post('/conectividad/importar-aplicar', { records: selected })
        applyResult.value = { success: true, message: data.message || 'Aplicado.' }
        previewResult.value = null
        previewChanges.value = []
        previewFile.value = null
        router.reload({ preserveScroll: true })
    } catch (e) {
        applyResult.value = { success: false, message: e.response?.data?.message || 'Error al aplicar cambios.' }
    } finally {
        applyLoading.value = false
    }
}

const selectAllPreview   = () => previewChanges.value.forEach(r => {
    if (r.match_status !== 'skipped') r._selected = true
})
const deselectAllPreview = () => previewChanges.value.forEach(r => { r._selected = false })
/** Líneas de facturación ETECSA vinculadas (no vienen del formulario; modelo `etecsa_servicios`). */
const editingBillingServicios = computed(() => {
    const row = editing.value
    if (!row) return []
    return row.etecsa_servicios ?? []
})

function formatFacturaPeriodo(f) {
    if (!f?.periodo_desde || !f?.periodo_hasta) return ''
    const a = String(f.periodo_desde).slice(0, 10)
    const b = String(f.periodo_hasta).slice(0, 10)
    return `${a} → ${b}`
}

const form = useForm({
    sales_floor_id: '',
    contracted_speed: '',
    /** Catálogo ETECSA en `registros_conectividad` (Excel / edición manual). La factura PDF solo enlaza ID + nº servicio. */
    tipo_enlace: '',
    id_facturacion: '',
    ed: '',
    ina: '',
    velocidad_etecsa: '',
    cuota: '',
    wan_cidr: '',
    lan_cidr: '',
    notes: '',
})

const floorOptionsModal = ref([])
const floorLoadingModal = ref(false)
const selectedFloorModal = ref(null)

const floorOptionsToolbar = ref([])
const floorLoadingToolbar = ref(false)

const floorOptionsExport = ref([])
const floorLoadingExport = ref(false)

// Opciones para el selector de velocidad contratada — provienen del nomenclador BD
const speedOptions = computed(() =>
    (props.contractedSpeeds ?? []).map(s => ({ id: s.nombre, label: s.nombre }))
)

// Opciones para tipo de enlace (nomenclador modos_adsl).
const linkModeOptions = computed(() => {
    const base = (props.linkModes ?? []).map(m => ({ id: m.code, label: `${m.code} - ${m.nombre}` }))
    const known = new Set(base.map(o => String(o.id).toLowerCase()))
    const legacySeen = new Set()
    const legacy = (props.records?.data ?? [])
        .map(r => (r?.tipo_enlace ?? '').trim())
        .filter(Boolean)
        .filter(v => {
            const k = v.toLowerCase()
            if (known.has(k) || legacySeen.has(k)) return false
            legacySeen.add(k)
            return true
        })
        .map(v => ({ id: v, label: `${v} (historico)` }))

    return [...base, ...legacy]
})

const columns = [
    {
        key: 'sales_floor',
        label: 'Piso de venta',
        sortValue: (r) => r.sales_floor?.name || r.unit_name || '',
        filterValue: (r) => [r.sales_floor?.name, r.unit_name, r.sales_floor?.address, r.sales_floor?.phone].filter(Boolean).join(' '),
    },
    {
        key: 'hierarchy',
        label: 'Entidad',
        sortValue: (r) => r.sales_floor?.entity?.name || '',
        filterValue: (r) => [r.sales_floor?.entity?.code, r.sales_floor?.entity?.name].filter(Boolean).join(' '),
    },
    { key: 'tipo_enlace', label: 'Tipo enlace', sortValue: (r) => r.tipo_enlace || '', filterValue: (r) => r.tipo_enlace || '' },
    {
        key: 'etecsa_meta',
        label: 'ETECSA',
        sortValue: (r) => r.id_facturacion || '',
        filterValue: (r) => [r.id_facturacion, r.ed, r.ina, r.cuota].map((x) => (x == null ? '' : String(x))).join(' '),
    },
    {
        key: 'ips',
        label: 'IPs',
        sortValue: (r) => r.ip_wan || r.ip_lan || '',
        filterValue: (r) => [r.ip_wan, r.wan_cidr, r.ip_lan, r.lan_cidr].filter(Boolean).join(' '),
    },
    {
        key: 'contracted_speed',
        label: 'Velocidad',
        sortValue: (r) => r.velocidad_etecsa || r.contracted_speed || '',
        filterValue: (r) => [r.velocidad_etecsa, r.contracted_speed].filter(Boolean).join(' '),
    },
    { key: 'actions', label: 'Acciones', sortable: false, filterable: false },
]

function snapshotFromRow(sf) {
    if (!sf) return null
    const codePart = sf.entity?.code ? `[${sf.entity.code}]` : null
    const parts = [codePart, sf.entity?.name, sf.name].filter(Boolean)
    const base = parts.join(' · ')
    const suffix = sf.datacell_piso_id != null ? ` (ID Piso: ${sf.datacell_piso_id})` : ''
    return { id: sf.id, label: base + suffix }
}

watch(
    () => props.filters,
    f => {
        if (f?.sales_floor_snapshot) {
            salesFloorFilter.value = {
                id: f.sales_floor_snapshot.id,
                label: f.sales_floor_snapshot.label,
            }
        } else if (f?.sales_floor_id) {
            salesFloorFilter.value = { id: f.sales_floor_id, label: `Piso #${f.sales_floor_id}` }
        } else {
            salesFloorFilter.value = null
        }
    },
    { immediate: true }
)

let timeout
const applyFilter = () => {
    clearTimeout(timeout)
    timeout = setTimeout(
        () =>
            router.get(
                '/conectividad',
                {
                    search: search.value || undefined,
                    sales_floor_id: salesFloorFilter.value?.id || undefined,
                },
                { preserveState: true, replace: true }
            ),
        300
    )
}

watch(search, applyFilter)

watch(salesFloorFilter, v => {
    const id = v?.id != null ? Number(v.id) : null
    const cur = props.filters?.sales_floor_id != null ? Number(props.filters.sales_floor_id) : null
    if (id === cur) return
    applyFilter()
})

async function searchFloorsToolbar(query, loading) {
    if (!query || query.length < 1) return
    loading(true)
    try {
        const { data } = await axios.get(route('pisos-venta.search'), { params: { q: query, entity_code_labels: 1 } })
        floorOptionsToolbar.value = (data.floors || []).map(f => ({ id: f.id, label: f.label || f.name }))
    } catch {
        floorOptionsToolbar.value = []
    } finally {
        loading(false)
    }
}

async function searchFloorsModal(query, loading) {
    if (!query || query.length < 1) return
    loading(true)
    try {
        const { data } = await axios.get(route('pisos-venta.search'), { params: { q: query, entity_code_labels: 1 } })
        floorOptionsModal.value = (data.floors || []).map(f => ({ id: f.id, label: f.label || f.name }))
    } catch {
        floorOptionsModal.value = []
    } finally {
        loading(false)
    }
}

async function searchFloorsExport(query, loading) {
    if (!query || query.length < 1) return
    loading(true)
    try {
        const { data } = await axios.get(route('pisos-venta.search'), { params: { q: query, entity_code_labels: 1 } })
        floorOptionsExport.value = (data.floors || []).map(f => ({ id: f.id, label: f.label || f.name }))
    } catch {
        floorOptionsExport.value = []
    } finally {
        loading(false)
    }
}

function onModalFloorChange(val) {
    form.sales_floor_id = val ? val.id : ''
}

const openCreate = () => {
    editing.value = null
    form.reset()
    selectedFloorModal.value = null
    floorOptionsModal.value = []
    form.clearErrors()
    showModal.value = true
}

const openEdit = row => {
    editing.value = row
    form.reset()
    Object.assign(form, {
        sales_floor_id: row.sales_floor_id || '',
        contracted_speed: row.contracted_speed || '',
        tipo_enlace: row.tipo_enlace || '',
        id_facturacion: row.id_facturacion || '',
        ed: row.ed || '',
        ina: row.ina || '',
        velocidad_etecsa: row.velocidad_etecsa || '',
        cuota: row.cuota != null && row.cuota !== '' ? String(row.cuota) : '',
        wan_cidr: row.wan_cidr || '',
        lan_cidr: row.lan_cidr || '',
        notes: row.notes || '',
    })
    selectedFloorModal.value = snapshotFromRow(row.sales_floor)
    floorOptionsModal.value = []
    form.clearErrors()
    showModal.value = true
}

const submit = () =>
    editing.value
        ? form.put(`/conectividad/${editing.value.id}`, { onSuccess: () => (showModal.value = false) })
        : form.post('/conectividad', { onSuccess: () => (showModal.value = false) })

const destroy = async row => {
    if (
        !(await confirmDanger({
            title: 'Eliminar registro',
            text: `Se eliminara "${row.unit_name}".`,
            confirmText: 'Si, eliminar',
        }))
    ) {
        return
    }
    router.delete(`/conectividad/${row.id}`)
}
watch(showExportModal, open => {
    if (open) {
        exportSalesFloor.value = salesFloorFilter.value
        exportFilters.value = {
            search:           search.value || '',
            contracted_speed: '',
            format:           exportFilters.value.format || 'csv',
        }
        floorOptionsExport.value = []
    }
})

const exportData = () => {
    const params = new URLSearchParams()
    params.set('format', exportFilters.value.format || 'csv')
    if (exportFilters.value.search) params.set('search', exportFilters.value.search)
    if (exportSalesFloor.value?.id) params.set('sales_floor_id', String(exportSalesFloor.value.id))
    if (exportFilters.value.contracted_speed) params.set('contracted_speed', exportFilters.value.contracted_speed)
    window.open(`/conectividad/exportar?${params.toString()}`, '_blank')
    showExportModal.value = false
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <PageHeader eyebrow="Modulo" title="Conectividad" description="Conectividad ETECSA por piso de venta: tipo de enlace, velocidad, cuota, IPs y segmentos WAN/LAN (CIDR).">
                <template #actions>
                    <button type="button" class="app-button-secondary" @click="showExportModal = true">Exportar</button>
                    <button type="button" class="app-button-primary" @click="openCreate">Nuevo registro</button>
                </template>
            </PageHeader>
            <BaseCard>
                <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(280px,1fr)_auto]">
                    <input v-model="search" type="text" class="app-input" placeholder="Buscar por PV, direccion, telefono, IP..." />
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Piso de venta</label>
                        <VSelect
                            v-model="salesFloorFilter"
                            :options="floorOptionsToolbar"
                            :filterable="false"
                            :loading="floorLoadingToolbar"
                            placeholder="Buscar por código de entidad, nombre de entidad o PV..."
                            :clearable="true"
                            @search="searchFloorsToolbar"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="file" accept=".xls,.xlsx" class="text-sm" @change="onPreviewFile" />
                        <button
                            type="button"
                            class="app-button-secondary whitespace-nowrap"
                            :disabled="!previewFile || previewLoading"
                            @click="runPreview"
                        >
                            {{ previewLoading ? 'Procesando...' : 'Importar Excel ETECSA' }}
                        </button>
                    </div>
                </div>
            </BaseCard>

            <!-- Panel de vista previa -->
            <BaseCard v-if="previewResult">
                <h3 class="mb-1 text-base font-semibold text-slate-900 dark:text-slate-100">Vista previa de importación</h3>

                <!-- Error global -->
                <div v-if="!previewResult.success" class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300">
                    <span class="mt-0.5">❌</span>
                    <p class="font-medium">{{ previewResult.message }}</p>
                </div>

                <template v-else>
                    <!-- Resumen -->
                    <div class="mb-4 flex flex-wrap gap-3 text-sm">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                            <span class="font-semibold">{{ previewResult.summary.matched || 0 }}</span> con match
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                            <span class="font-semibold">{{ previewResult.summary.unmatched || 0 }}</span> sin match
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                            <span class="font-semibold">{{ previewResult.summary.skipped || 0 }}</span> omitidos
                        </span>
                    </div>
                    <p v-if="(previewResult.summary.skipped || 0) > 0" class="mb-3 rounded-lg border border-amber-200/80 bg-amber-50/80 px-3 py-2 text-xs text-amber-900 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-200">
                        Filas omitidas: el código de entidad del Excel no existe en el sistema.
                        Elija la <strong>entidad correcta</strong> en la columna Entidad de cada fila para recalcular el emparejamiento con pisos de venta.
                    </p>

                    <!-- Selección rápida -->
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">
                            {{ previewApplicableCount }} a aplicar · {{ previewChanges.length }} filas
                        </span>
                        <div class="flex gap-3 text-xs">
                            <button type="button" class="text-indigo-600 hover:underline dark:text-indigo-400" @click="selectAllPreview">Todos</button>
                            <button type="button" class="text-slate-500 hover:underline dark:text-slate-400" @click="deselectAllPreview">Ninguno</button>
                        </div>
                    </div>

                    <!-- Tabla de cambios -->
                    <div class="overflow-auto rounded-xl border border-slate-200 dark:border-slate-700">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                    <th class="w-10 px-3 py-2"></th>
                                    <th class="px-3 py-2">Estado</th>
                                    <th class="px-3 py-2">Punto de venta</th>
                                    <th class="px-3 py-2">Entidad</th>
                                    <th class="px-3 py-2">Tipo enlace</th>
                                    <th class="px-3 py-2">Velocidad</th>
                                    <th class="px-3 py-2">Cuota</th>
                                    <th class="px-3 py-2">IP WAN</th>
                                    <th class="px-3 py-2">Seg. WAN</th>
                                    <th class="px-3 py-2">IP LAN</th>
                                    <th class="px-3 py-2">Seg. LAN</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr
                                    v-for="(rec, idx) in previewChanges"
                                    :key="idx"
                                    :class="[
                                        'transition-opacity',
                                        !rec._selected ? 'opacity-40' : '',
                                        rec.match_status === 'matched' ? 'bg-emerald-50/40 dark:bg-emerald-900/10' : '',
                                    ]"
                                >
                                    <td class="px-3 py-2">
                                        <input type="checkbox" v-model="rec._selected" class="rounded border-slate-300 text-indigo-600 dark:border-slate-600" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                            :class="rec.match_status === 'matched'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                : rec.match_status === 'unmatched'
                                                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                                                    : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                                        >
                                            {{ rec.match_status }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 font-medium text-slate-800 dark:text-slate-100">{{ rec.floor_name }}</td>
                                    <td class="px-3 py-2 align-top">
                                        <template v-if="rec.match_status === 'skipped'">
                                            <div class="min-w-[16rem] max-w-md space-y-1.5">
                                                <p v-if="rec.entity_code" class="text-[11px] leading-snug text-amber-800 dark:text-amber-300">
                                                    Excel: <span class="font-mono">{{ rec.entity_code }}</span>
                                                    <span class="text-amber-600 dark:text-amber-400"> (sin coincidencia)</span>
                                                </p>
                                                <VSelect
                                                    :modelValue="rec._pickedEntity"
                                                    :options="rec._entityOptions || []"
                                                    :filterable="false"
                                                    :loading="!!rec._entitySearchLoading"
                                                    :append-to-body="true"
                                                    placeholder="Buscar entidad por nombre o código…"
                                                    :clearable="true"
                                                    @search="(q, loading) => searchEntitiesForImport(q, loading, idx)"
                                                    @update:modelValue="opt => onPickedEntityForSkipped(idx, opt)"
                                                >
                                                    <template #no-options="{ search: q, searching }">
                                                        <span v-if="searching" class="text-sm text-slate-400">Sin resultados para "{{ q }}"</span>
                                                        <span v-else class="text-sm text-slate-400">Escriba para buscar…</span>
                                                    </template>
                                                </VSelect>
                                                <p v-if="rec._entityBindLoading" class="text-[11px] text-slate-500">Vinculando…</p>
                                            </div>
                                        </template>
                                        <template v-else-if="rec.entity_id">
                                            <span class="font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ rec.entity_code }}</span>
                                            <span class="ml-1 text-slate-600 dark:text-slate-400 text-xs">{{ rec.entity_name }}</span>
                                        </template>
                                        <span v-else class="text-amber-600 dark:text-amber-400 italic text-xs">
                                            {{ rec.entity_code || '—' }} (sin vincular)
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-xs">{{ rec.tipo_enlace || '—' }}</td>
                                    <td class="px-3 py-2 text-xs">{{ rec.velocidad_etecsa || rec.contracted_speed || '—' }}</td>
                                    <td class="px-3 py-2 text-xs">{{ rec.cuota ?? '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ rec.ip_wan || '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ rec.wan_cidr || '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ rec.ip_lan || '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ rec.lan_cidr || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Barra de aplicar -->
                    <div class="mt-4 flex items-center justify-between gap-4">
                        <div v-if="applyResult" class="flex items-center gap-2 text-sm"
                            :class="applyResult.success ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400'">
                            <span>{{ applyResult.success ? '✅' : '❌' }}</span>
                            <span class="font-medium">{{ applyResult.message }}</span>
                        </div>
                        <div v-else class="flex-1" />
                        <button
                            type="button"
                            class="app-button-primary"
                            :disabled="previewApplicableCount === 0 || applyLoading"
                            @click="applySelectedChanges"
                        >
                            {{ applyLoading ? 'Aplicando...' : `Aplicar ${previewApplicableCount} cambio(s)` }}
                        </button>
                    </div>
                </template>
            </BaseCard>

            <DataTable client-table :columns="columns" :data="records">
                <template #cell-sales_floor="{ row }">
                    <div>
                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ row.sales_floor?.name || row.unit_name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.sales_floor?.address || '-' }} · {{ row.sales_floor?.phone || '-' }}</p>
                    </div>
                </template>
                <template #cell-hierarchy="{ row }">
                    <div>
                        <p v-if="row.sales_floor?.entity?.code" class="font-mono text-sm text-slate-800 dark:text-slate-100">
                            {{ row.sales_floor.entity.code }}
                        </p>
                        <p
                            :class="row.sales_floor?.entity?.code
                                ? 'text-xs text-slate-500 dark:text-slate-400'
                                : 'text-sm text-slate-700 dark:text-slate-200'"
                        >
                            {{ row.sales_floor?.entity?.name || '—' }}
                        </p>
                    </div>
                </template>
                <template #cell-tipo_enlace="{ row }">
                    <StatusBadge :status="row.tipo_enlace || '-'" color="blue" />
                </template>
                <template #cell-etecsa_meta="{ row }">
                    <div class="text-xs text-slate-600 dark:text-slate-300">
                        <p>ID: <span class="font-mono">{{ row.id_facturacion || '—' }}</span></p>
                        <p>ED: <span class="font-mono">{{ row.ed || '—' }}</span> · INA: <span class="font-mono">{{ row.ina || '—' }}</span></p>
                        <p>Cuota: {{ row.cuota ?? '—' }}</p>
                    </div>
                </template>
                <template #cell-ips="{ row }">
                    <div class="text-xs text-slate-600 dark:text-slate-300">
                        <p>WAN: <span class="font-mono">{{ row.wan_cidr || row.ip_wan || '—' }}</span></p>
                        <p>LAN: <span class="font-mono">{{ row.lan_cidr || row.ip_lan || '—' }}</span></p>
                    </div>
                </template>
                <template #cell-contracted_speed="{ row }">
                    <div class="text-xs text-slate-600 dark:text-slate-300">
                        <p>ETECSA: {{ row.velocidad_etecsa || '—' }}</p>
                        <p>Nomenclador: {{ row.contracted_speed || '—' }}</p>
                    </div>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" class="app-button-secondary px-3 py-2 text-xs" @click="openEdit(row)">Editar</button>
                        <button type="button" class="app-button-danger px-3 py-2 text-xs" @click="destroy(row)">Eliminar</button>
                    </div>
                </template>
            </DataTable>
        </div>
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center px-4 py-8">
                    <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                    <div class="surface-card relative z-10 flex max-h-[min(90vh,720px)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl shadow-xl">
                        <div class="shrink-0 border-b border-slate-200/80 px-5 py-4 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-950 dark:text-slate-100">
                                {{ editing ? 'Editar registro de conectividad' : 'Nuevo registro de conectividad' }}
                            </h3>
                        </div>
                        <form class="flex min-h-0 flex-1 flex-col overflow-hidden" @submit.prevent="submit">
                            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Piso de venta *</label>
                                    <VSelect
                                        v-model="selectedFloorModal"
                                        :options="floorOptionsModal"
                                        :filterable="false"
                                        :loading="floorLoadingModal"
                                        placeholder="Buscar por código de entidad, nombre de entidad, PV o ID Piso…"
                                        @search="searchFloorsModal"
                                        @update:modelValue="onModalFloorChange"
                                    >
                                        <template #no-options="{ search: q, searching }">
                                            <span v-if="searching" class="text-sm text-slate-400">Sin resultados para "{{ q }}"</span>
                                            <span v-else class="text-sm text-slate-400">Escriba para buscar…</span>
                                        </template>
                                    </VSelect>
                                    <p v-if="form.errors.sales_floor_id" class="text-xs text-red-500">{{ form.errors.sales_floor_id }}</p>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-2 sm:col-span-2">
                                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tipo de enlace</label>
                                        <VSelect
                                            v-model="form.tipo_enlace"
                                            :options="linkModeOptions"
                                            :reduce="opt => opt.id"
                                            placeholder="Seleccionar tipo de enlace..."
                                            :clearable="true"
                                            :searchable="true"
                                        >
                                            <template #no-options="{ search: q, searching }">
                                                <span v-if="searching" class="text-sm text-slate-400">Sin coincidencias para "{{ q }}"</span>
                                                <span v-else class="text-sm text-slate-400">Escriba para buscar...</span>
                                            </template>
                                        </VSelect>
                                        <p v-if="form.errors.tipo_enlace" class="text-xs text-red-500">{{ form.errors.tipo_enlace }}</p>
                                    </div>
                                    <div class="space-y-2 sm:col-span-2">
                                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Velocidad contratada (nomenclador) *</label>
                                        <VSelect
                                            v-model="form.contracted_speed"
                                            :options="speedOptions"
                                            :reduce="opt => opt.id"
                                            placeholder="Seleccionar velocidad…"
                                            :clearable="true"
                                            :searchable="true"
                                        >
                                            <template #no-options="{ search: q, searching }">
                                                <span v-if="searching" class="text-sm text-slate-400">Sin resultados para "{{ q }}"</span>
                                                <span v-else class="text-sm text-slate-400">Seleccione o busque…</span>
                                            </template>
                                        </VSelect>
                                        <p v-if="form.errors.contracted_speed" class="text-xs text-red-500">{{ form.errors.contracted_speed }}</p>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">ID facturación</label>
                                        <input v-model="form.id_facturacion" type="text" class="app-input font-mono text-sm" autocomplete="off" />
                                        <p v-if="form.errors.id_facturacion" class="text-xs text-red-500">{{ form.errors.id_facturacion }}</p>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">ED (catálogo ETECSA)</label>
                                        <input v-model="form.ed" type="text" class="app-input text-sm" placeholder="Ej. ED-572127" autocomplete="off" />
                                        <p v-if="form.errors.ed" class="text-xs text-red-500">{{ form.errors.ed }}</p>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">INA</label>
                                        <input v-model="form.ina" type="text" class="app-input text-sm" autocomplete="off" />
                                        <p v-if="form.errors.ina" class="text-xs text-red-500">{{ form.errors.ina }}</p>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Velocidad ETECSA</label>
                                        <input v-model="form.velocidad_etecsa" type="text" class="app-input text-sm" placeholder="Como figura en ETECSA" autocomplete="off" />
                                        <p v-if="form.errors.velocidad_etecsa" class="text-xs text-red-500">{{ form.errors.velocidad_etecsa }}</p>
                                    </div>
                                    <div class="space-y-2 sm:col-span-2">
                                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Cuota</label>
                                        <input v-model="form.cuota" type="number" step="any" min="0" class="app-input" placeholder="—" />
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Catálogo en este registro; el módulo de facturas PDF enlaza por ID y nº de servicio.</p>
                                        <p v-if="form.errors.cuota" class="text-xs text-red-500">{{ form.errors.cuota }}</p>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 dark:border-slate-600 dark:bg-slate-900/40">
                                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Red WAN / LAN</p>
                                    <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                                        Un solo campo por red: IPv4 y prefijo (la IP se deriva del CIDR al guardar).
                                    </p>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="space-y-1 sm:col-span-2">
                                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Segmento WAN (CIDR)</label>
                                            <input
                                                v-model="form.wan_cidr"
                                                type="text"
                                                class="app-input font-mono text-sm"
                                                placeholder="ej. 172.16.17.4/30"
                                                autocomplete="off"
                                            />
                                            <p v-if="form.errors.wan_cidr" class="text-xs text-red-500">{{ form.errors.wan_cidr }}</p>
                                        </div>
                                        <div class="space-y-1 sm:col-span-2">
                                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Segmento LAN (CIDR)</label>
                                            <input
                                                v-model="form.lan_cidr"
                                                type="text"
                                                class="app-input font-mono text-sm"
                                                placeholder="ej. 10.146.16.0/24"
                                                autocomplete="off"
                                            />
                                            <p v-if="form.errors.lan_cidr" class="text-xs text-red-500">{{ form.errors.lan_cidr }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="editing && editingBillingServicios.length" class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 dark:border-slate-600 dark:bg-slate-900/40">
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Facturas PDF importadas (enlace)</p>
                                    <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">Relación por registro de conectividad; importes de la factura vs. catálogo arriba.</p>
                                    <ul class="max-h-40 space-y-2 overflow-y-auto text-sm">
                                        <li
                                            v-for="s in editingBillingServicios"
                                            :key="s.id"
                                            class="rounded-lg border border-slate-200/60 bg-white/80 px-3 py-2 dark:border-slate-600 dark:bg-slate-800/60"
                                        >
                                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                                <a
                                                    v-if="s.factura_id"
                                                    :href="route('etecsa.show', s.factura_id)"
                                                    class="font-mono text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                                >
                                                    {{ s.factura?.numero_factura ?? `Factura #${s.factura_id}` }}
                                                </a>
                                                <span v-else class="text-slate-600 dark:text-slate-400">Servicio #{{ s.id }}</span>
                                                <span v-if="s.factura" class="text-xs text-slate-500 dark:text-slate-400">{{ formatFacturaPeriodo(s.factura) }}</span>
                                            </div>
                                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                                Cuota facturada: {{ s.cuota_facturada ?? '—' }} · Total línea: {{ s.total_servicio ?? '—' }}
                                                <span v-if="s.numero_servicio"> · Nº servicio: {{ s.numero_servicio }}</span>
                                            </p>
                                        </li>
                                    </ul>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Notas</label>
                                    <textarea v-model="form.notes" class="app-input" rows="2" placeholder="Notas..." />
                                </div>
                            </div>
                            <div class="flex shrink-0 justify-end gap-3 border-t border-slate-200/80 bg-slate-50/50 px-5 py-4 dark:border-slate-800 dark:bg-slate-900/30">
                                <button type="button" class="app-button-secondary" @click="showModal = false">Cancelar</button>
                                <button type="submit" class="app-button-primary" :disabled="form.processing">{{ form.processing ? 'Guardando...' : 'Guardar' }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
        <MatchResolverModal
            v-if="showResolverModal"
            :records="unresolvedRecords"
            name-key="floor_name"
            label="piso de venta"
            @resolved="applyResolvedUnmatched"
            @cancel="showResolverModal = false"
        />
        <Teleport to="body">
            <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showExportModal = false" />
                <div class="surface-card relative z-10 w-full max-w-2xl p-6">
                    <h3 class="mb-4 text-xl font-semibold text-slate-950 dark:text-slate-100">Exportar conectividad</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <input v-model="exportFilters.search" type="text" class="app-input md:col-span-2" placeholder="Texto de busqueda" />
                        <div class="space-y-1 md:col-span-2">
                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Piso de venta</label>
                            <VSelect
                                v-model="exportSalesFloor"
                                :options="floorOptionsExport"
                                :filterable="false"
                                :loading="floorLoadingExport"
                                placeholder="Todos — buscar para filtrar por PV…"
                                :clearable="true"
                                @search="searchFloorsExport"
                            />
                        </div>
                        <select v-model="exportFilters.contracted_speed" class="app-select">
                            <option value="">Todas las velocidades</option>
                            <option v-for="s in (contractedSpeeds ?? [])" :key="s.nombre" :value="s.nombre">
                                {{ s.nombre }}
                            </option>
                        </select>
                        <div class="md:col-span-2 space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Formato de archivo</label>
                            <select v-model="exportFilters.format" class="app-select">
                                <option value="csv">CSV (UTF-8)</option>
                                <option value="xlsx">Excel (.xlsx)</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" class="app-button-secondary" @click="showExportModal = false">Cancelar</button>
                        <button type="button" class="app-button-primary" @click="exportData">Descargar</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
