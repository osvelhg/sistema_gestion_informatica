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
    records: Object,
    filters: Object,
    cashRegisterModels: Object,
})

const search = ref(props.filters?.search || '')
const salesFloorFilter = ref(null)
const showModal = ref(false)
const editing = ref(null)
const showExportModal = ref(false)
const exportSalesFloor = ref(null)
const exportFilters = ref({
    search: props.filters?.search || '',
    format: 'csv',
})
const previewFile = ref(null)
const previewLoading = ref(false)
const previewResult = ref(null)
const previewChanges = ref([])
const applyLoading = ref(false)
const applyResult = ref(null)
const showResolverModal = ref(false)
const unresolvedRecords = ref([])

const form = useForm({
    sales_floor_id: '',
    name: '',
    tpv_boxes: 0,
    pos_phone_qty: 0,
    pos_ip_qty: 0,
    pos_ip_demand: 0,
    pos_gprs_qty: 0,
    pos_gprs_demand: 0,
    has_ip_connectivity: false,
    broken_pos_qty: 0,
    cash_register_model_code: null,
    pos_currency_mlc: false,
    pos_currency_cup: false,
    qr_fincimex_mlc: false,
    qr_fincimex_cup: false,
    src_fincimex_mlc: '',
    src_fincimex_cup: '',
    terminal_id: '',
    terminal_ip: '',
})

const previewApplicableCount = computed(() =>
    previewChanges.value.filter(r => r._selected && r.match_status !== 'skipped').length
)

watch(previewResult, (result) => {
    if (result?.records) {
        previewChanges.value = result.records.map(r => ({ ...r, _selected: r.match_status !== 'skipped' }))
    } else {
        previewChanges.value = []
    }
})

// ── Autocomplete pisos de venta ───────────────────────────────────────────────
const floorOptionsModal = ref([])
const floorLoadingModal = ref(false)
const selectedFloorModal = ref(null)

const floorOptionsToolbar = ref([])
const floorLoadingToolbar = ref(false)

const floorOptionsExport = ref([])
const floorLoadingExport = ref(false)

async function searchFloorsToolbar(query, loading) {
    if (!query || query.length < 1) return
    loading(true)
    try {
        const { data } = await axios.get(route('pisos-venta.search'), { params: { q: query } })
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
        const { data } = await axios.get(route('pisos-venta.search'), { params: { q: query } })
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
        const { data } = await axios.get(route('pisos-venta.search'), { params: { q: query } })
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

// ── Snapshot piso de venta desde fila ────────────────────────────────────────
function snapshotFromRow(sf) {
    if (!sf) return null
    const parts = [sf.municipio?.name, sf.entity?.name, sf.name].filter(Boolean)
    return { id: sf.id, label: parts.join(' · ') }
}

// ── Filtros reactivos ─────────────────────────────────────────────────────────
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
                '/fincimex',
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

// ── CRUD (listado: una fila por piso de venta) ───────────────────────────────
const columns = [
    { key: 'pv_entity', label: 'Piso de venta' },
    { key: 'areas_brief', label: 'Áreas' },
    { key: 'address_phone', label: 'Dirección / Teléfono' },
    { key: 'pos_resume', label: 'Resumen POS / TPV' },
    { key: 'floor_status', label: 'Estado' },
    { key: 'actions', label: 'Acciones' },
]

function floorPosResume(floor) {
    const areas = floor?.areas_venta || []
    if (!areas.length) return '—'
    const t = areas.reduce(
        (acc, a) => {
            acc.tpv += Number(a.tpv_boxes || 0)
            acc.tel += Number(a.pos_phone_qty || 0)
            acc.ip += Number(a.pos_ip_qty || 0)
            acc.gprs += Number(a.pos_gprs_qty || 0)
            return acc
        },
        { tpv: 0, tel: 0, ip: 0, gprs: 0 }
    )
    return `Áreas ${areas.length} · TPV ${t.tpv} · Tel ${t.tel} · IP ${t.ip} · GPRS ${t.gprs}`
}

const openCreate = (floor = null) => {
    editing.value = null
    form.reset()
    selectedFloorModal.value = floor ? snapshotFromRow(floor) : null
    if (floor) form.sales_floor_id = floor.id
    floorOptionsModal.value = []
    form.clearErrors()
    form.name = ''
    showModal.value = true
}

const openEdit = row => {
    editing.value = row
    form.reset()
    Object.assign(form, {
        sales_floor_id: row.sales_floor_id || '',
        name: row.name || '',
        tpv_boxes: row.tpv_boxes ?? 0,
        pos_phone_qty: row.pos_phone_qty ?? 0,
        pos_ip_qty: row.pos_ip_qty ?? 0,
        pos_ip_demand: row.pos_ip_demand ?? 0,
        pos_gprs_qty: row.pos_gprs_qty ?? 0,
        pos_gprs_demand: row.pos_gprs_demand ?? 0,
        has_ip_connectivity: row.has_ip_connectivity ?? false,
        broken_pos_qty: row.broken_pos_qty ?? 0,
        cash_register_model_code: row.cash_register_model_code ?? null,
        pos_currency_mlc: row.pos_currency_mlc ?? false,
        pos_currency_cup: row.pos_currency_cup ?? false,
        qr_fincimex_mlc: row.qr_fincimex_mlc ?? false,
        qr_fincimex_cup: row.qr_fincimex_cup ?? false,
        src_fincimex_mlc: row.src_fincimex_mlc || '',
        src_fincimex_cup: row.src_fincimex_cup || '',
        terminal_id: row.terminal_id || '',
        terminal_ip: row.terminal_ip || '',
    })
    selectedFloorModal.value = snapshotFromRow(row.sales_floor)
    floorOptionsModal.value = []
    form.clearErrors()
    showModal.value = true
}

const onPreviewFile = (e) => {
    previewFile.value = e.target.files?.[0] ?? null
    previewResult.value = null
    applyResult.value = null
}

const runPreview = async () => {
    if (!previewFile.value) return
    previewLoading.value = true
    previewResult.value = null
    applyResult.value = null
    try {
        const fd = new FormData()
        fd.append('excel_file', previewFile.value)
        const { data } = await axios.post('/fincimex/importar-preview', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        previewResult.value = data
    } catch (e) {
        previewResult.value = { success: false, message: e.response?.data?.message || 'Error al procesar el archivo.' }
    } finally {
        previewLoading.value = false
    }
}

const selectAllPreview = () => previewChanges.value.forEach(r => { r._selected = true })
const deselectAllPreview = () => previewChanges.value.forEach(r => { r._selected = false })

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

function stripPreviewRowForApi(row) {
    const o = {}
    for (const k of Object.keys(row)) {
        if (k.startsWith('_')) continue
        o[k] = row[k]
    }
    return o
}

function syncFincimexPreviewSummary() {
    if (!previewResult.value?.success) return
    const s = { matched: 0, unmatched: 0, skipped: 0 }
    previewChanges.value.forEach(r => {
        if (r.match_status in s) s[r.match_status]++
    })
    previewResult.value = { ...previewResult.value, summary: { ...s } }
}

async function searchEntitiesForFincimexImport(query, loading, idx) {
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

async function onPickedEntityForSkippedFincimex(idx, opt) {
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
            syncFincimexPreviewSummary()
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
        const { data } = await axios.post('/fincimex/importar-aplicar', { records: selected })
        applyResult.value = { success: true, message: data.message }
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

const submit = () =>
    editing.value
        ? form.put(`/fincimex/${editing.value.id}`, { onSuccess: () => (showModal.value = false) })
        : form.post('/fincimex', { onSuccess: () => (showModal.value = false) })

const destroy = async area => {
    if (
        !(await confirmDanger({
            title: 'Eliminar área FINCIMEX',
            text: `Se eliminará el área "${area.name}" del piso "${area.sales_floor?.name || ''}".`,
            confirmText: 'Si, eliminar',
        }))
    ) {
        return
    }
    router.delete(`/fincimex/${area.id}`)
}

// ── Modal exportar ────────────────────────────────────────────────────────────
watch(showExportModal, open => {
    if (open) {
        exportSalesFloor.value = salesFloorFilter.value
        exportFilters.value = {
            search: search.value || '',
            format: exportFilters.value.format || 'csv',
        }
        floorOptionsExport.value = []
    }
})

const exportData = () => {
    const params = new URLSearchParams()
    params.set('format', exportFilters.value.format || 'csv')
    if (exportFilters.value.search) params.set('search', exportFilters.value.search)
    if (exportSalesFloor.value?.id) params.set('sales_floor_id', String(exportSalesFloor.value.id))
    window.open(`/fincimex/exportar?${params.toString()}`, '_blank')
    showExportModal.value = false
}

// ── Helpers de visualización ──────────────────────────────────────────────────
function modelLabel(code) {
    if (!code || !props.cashRegisterModels) return '—'
    return props.cashRegisterModels[code] || `Modelo ${code}`
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <PageHeader
                eyebrow="Módulo"
                title="FINCIMEX"
                description="Datos de conciliación mensual con FINCIMEX. Terminales POS/TPV, modelos de caja y QR por piso de venta."
            >
                <template #actions>
                    <button type="button" class="app-button-secondary" @click="showExportModal = true">Exportar</button>
                    <button type="button" class="app-button-primary" @click="openCreate">Nuevo registro</button>
                </template>
            </PageHeader>

            <!-- Filtros -->
            <BaseCard>
                <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(280px,1fr)_auto]">
                    <input
                        v-model="search"
                        type="text"
                        class="app-input"
                        placeholder="Buscar por piso de venta, municipio, entidad..."
                    />
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Piso de venta (jerarquía geográfica)</label>
                        <VSelect
                            v-model="salesFloorFilter"
                            :options="floorOptionsToolbar"
                            :filterable="false"
                            :loading="floorLoadingToolbar"
                            placeholder="Buscar municipio, entidad o nombre del PV..."
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
                            {{ previewLoading ? 'Procesando...' : 'Importar Excel FINCIMEX' }}
                        </button>
                    </div>
                </div>
            </BaseCard>

            <BaseCard v-if="previewResult">
                <h3 class="mb-1 text-base font-semibold text-slate-900 dark:text-slate-100">Vista previa de importación</h3>

                <div v-if="!previewResult.success" class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300">
                    <span class="mt-0.5">❌</span>
                    <p class="font-medium">{{ previewResult.message }}</p>
                </div>

                <template v-else>
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
                        Elija la <strong>entidad correcta</strong> en la columna Entidad para recalcular el emparejamiento con pisos de venta.
                    </p>

                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">
                            {{ previewApplicableCount }} a aplicar · {{ previewChanges.length }} filas
                        </span>
                        <div class="flex gap-3 text-xs">
                            <button type="button" class="text-indigo-600 hover:underline dark:text-indigo-400" @click="selectAllPreview">Todos</button>
                            <button type="button" class="text-slate-500 hover:underline dark:text-slate-400" @click="deselectAllPreview">Ninguno</button>
                        </div>
                    </div>

                    <div class="overflow-auto rounded-xl border border-slate-200 dark:border-slate-700">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                    <th class="w-10 px-3 py-2"></th>
                                    <th class="px-3 py-2">Estado</th>
                                    <th class="px-3 py-2">Piso</th>
                                    <th class="px-3 py-2">Área</th>
                                    <th class="px-3 py-2">Entidad</th>
                                    <th class="px-3 py-2">TPV/POS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="(rec, idx) in previewChanges" :key="idx" :class="!rec._selected ? 'opacity-40' : ''">
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
                                    <td class="px-3 py-2 font-medium text-slate-800 dark:text-slate-100">{{ rec.unit_name }}</td>
                                    <td class="px-3 py-2">{{ rec.area_name }}</td>
                                    <td class="px-3 py-2 align-top text-xs">
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
                                                    @search="(q, loading) => searchEntitiesForFincimexImport(q, loading, idx)"
                                                    @update:modelValue="opt => onPickedEntityForSkippedFincimex(idx, opt)"
                                                >
                                                    <template #no-options="{ search: q, searching }">
                                                        <span v-if="searching" class="text-sm text-slate-400">Sin resultados para "{{ q }}"</span>
                                                        <span v-else class="text-sm text-slate-400">Escriba para buscar…</span>
                                                    </template>
                                                </VSelect>
                                                <p v-if="rec._entityBindLoading" class="text-[11px] text-slate-500">Vinculando…</p>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <span v-if="rec.entity_code" class="font-mono">{{ rec.entity_code }}</span>
                                            <span v-if="rec.entity_name" class="ml-1">{{ rec.entity_name }}</span>
                                            <span v-if="!rec.entity_code && !rec.entity_name" class="text-slate-400">—</span>
                                        </template>
                                    </td>
                                    <td class="px-3 py-2 text-xs">
                                        TPV {{ rec.tpv_boxes || 0 }} · Tel {{ rec.pos_phone_qty || 0 }} · IP {{ rec.pos_ip_qty || 0 }} · GPRS {{ rec.pos_gprs_qty || 0 }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-4">
                        <div v-if="applyResult" class="flex items-center gap-2 text-sm"
                             :class="applyResult.success ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400'">
                            <span>{{ applyResult.success ? '✅' : '❌' }}</span>
                            <span class="font-medium">{{ applyResult.message }}</span>
                        </div>
                        <div v-else class="flex-1" />
                        <button type="button" class="app-button-primary" :disabled="previewApplicableCount === 0 || applyLoading" @click="applySelectedChanges">
                            {{ applyLoading ? 'Aplicando...' : `Aplicar ${previewApplicableCount} cambio(s)` }}
                        </button>
                    </div>
                </template>
            </BaseCard>

            <!-- Tabla: una fila por piso de venta -->
            <DataTable client-table :columns="columns" :data="records">
                <template #cell-pv_entity="{ row }">
                    <div>
                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ row.name || '—' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            <template v-if="row.entity?.name">
                                <span v-if="row.entity?.code" class="font-mono">[{{ row.entity.code }}]</span>
                                {{ row.entity.name }}
                            </template>
                            <template v-else>—</template>
                        </p>
                    </div>
                </template>
                <template #cell-areas_brief="{ row }">
                    <span class="text-sm text-slate-700 dark:text-slate-200">{{ (row.areas_venta || []).length }} área(s)</span>
                </template>
                <template #cell-address_phone="{ row }">
                    <div>
                        <p>{{ row.address || '—' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.phone || '—' }}</p>
                    </div>
                </template>
                <template #cell-pos_resume="{ row }">
                    <span class="text-xs text-slate-600 dark:text-slate-300">{{ floorPosResume(row) }}</span>
                </template>
                <template #cell-floor_status="{ row }">
                    <span v-if="row.establishment_status" class="text-xs text-slate-700 dark:text-slate-200">{{ row.establishment_status.name }}</span>
                    <StatusBadge v-else :status="row.active ? 'Activo' : 'Inactivo'" :color="row.active ? 'green' : 'red'" />
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex max-w-[14rem] flex-col items-end gap-1">
                        <button type="button" class="app-button-secondary px-2 py-1 text-[11px]" @click="openCreate(row)">+ Nueva área</button>
                        <template v-for="a in row.areas_venta || []" :key="a.id">
                            <div class="flex flex-wrap justify-end gap-1">
                                <button type="button" class="rounded border border-slate-200 px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800" @click="openEdit(a)">
                                    Editar · {{ a.name }}
                                </button>
                                <button type="button" class="rounded border border-red-200 px-2 py-1 text-[11px] text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950/40" @click="destroy(a)">
                                    Eliminar
                                </button>
                            </div>
                        </template>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Modal crear / editar -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-8">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                <div class="surface-card relative z-10 w-full max-w-3xl p-6">
                    <h3 class="mb-5 text-xl font-semibold text-slate-950 dark:text-slate-100">
                        {{ editing ? 'Editar registro FINCIMEX' : 'Nuevo registro FINCIMEX' }}
                    </h3>
                    <form class="space-y-6" @submit.prevent="submit">

                        <!-- Piso de venta -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Piso de venta *</label>
                            <VSelect
                                v-model="selectedFloorModal"
                                :options="floorOptionsModal"
                                :filterable="false"
                                :loading="floorLoadingModal"
                                placeholder="Buscar municipio, entidad, nombre del PV..."
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
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Área de venta *</label>
                            <input v-model="form.name" type="text" class="app-input" placeholder="Ej: Mercado, Electro, Perfumería..." />
                            <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>

                        <!-- Terminales POS/TPV -->
                        <div>
                            <p class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Terminales POS / TPV</p>
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Cajas TPV</label>
                                    <input v-model.number="form.tpv_boxes" type="number" min="0" class="app-input" />
                                    <p v-if="form.errors.tpv_boxes" class="text-xs text-red-500">{{ form.errors.tpv_boxes }}</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">POS Teléfono</label>
                                    <input v-model.number="form.pos_phone_qty" type="number" min="0" class="app-input" />
                                    <p v-if="form.errors.pos_phone_qty" class="text-xs text-red-500">{{ form.errors.pos_phone_qty }}</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">POS IP</label>
                                    <input v-model.number="form.pos_ip_qty" type="number" min="0" class="app-input" />
                                    <p v-if="form.errors.pos_ip_qty" class="text-xs text-red-500">{{ form.errors.pos_ip_qty }}</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Demanda POS IP</label>
                                    <input v-model.number="form.pos_ip_demand" type="number" min="0" class="app-input" />
                                    <p v-if="form.errors.pos_ip_demand" class="text-xs text-red-500">{{ form.errors.pos_ip_demand }}</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">POS GPRS</label>
                                    <input v-model.number="form.pos_gprs_qty" type="number" min="0" class="app-input" />
                                    <p v-if="form.errors.pos_gprs_qty" class="text-xs text-red-500">{{ form.errors.pos_gprs_qty }}</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Demanda POS GPRS</label>
                                    <input v-model.number="form.pos_gprs_demand" type="number" min="0" class="app-input" />
                                    <p v-if="form.errors.pos_gprs_demand" class="text-xs text-red-500">{{ form.errors.pos_gprs_demand }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Estado -->
                        <div>
                            <p class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Estado</p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                    <input v-model="form.has_ip_connectivity" type="checkbox" class="rounded border-slate-300 text-indigo-600 dark:border-slate-600" />
                                    Conectividad IP
                                </label>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">POS Rotos</label>
                                    <input v-model.number="form.broken_pos_qty" type="number" min="0" class="app-input" />
                                    <p v-if="form.errors.broken_pos_qty" class="text-xs text-red-500">{{ form.errors.broken_pos_qty }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Modelo y moneda -->
                        <div>
                            <p class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Modelo y moneda</p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Modelo de caja registradora</label>
                                    <select v-model="form.cash_register_model_code" class="app-select">
                                        <option :value="null">— Sin modelo —</option>
                                        <option v-for="(label, code) in cashRegisterModels" :key="code" :value="Number(code)">{{ label }}</option>
                                    </select>
                                    <p v-if="form.errors.cash_register_model_code" class="text-xs text-red-500">{{ form.errors.cash_register_model_code }}</p>
                                </div>
                                <div class="flex items-end gap-6 pb-1">
                                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                        <input v-model="form.pos_currency_mlc" type="checkbox" class="rounded border-slate-300 text-indigo-600 dark:border-slate-600" />
                                        Acepta MLC
                                    </label>
                                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                        <input v-model="form.pos_currency_cup" type="checkbox" class="rounded border-slate-300 text-indigo-600 dark:border-slate-600" />
                                        Acepta CUP
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- QR Fincimex -->
                        <div>
                            <p class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">QR Fincimex</p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                        <input v-model="form.qr_fincimex_mlc" type="checkbox" class="rounded border-slate-300 text-indigo-600 dark:border-slate-600" />
                                        QR FINCIMEX MLC
                                    </label>
                                    <input
                                        v-model="form.src_fincimex_mlc"
                                        type="text"
                                        class="app-input"
                                        placeholder="Source QR MLC"
                                        :disabled="!form.qr_fincimex_mlc"
                                    />
                                    <p v-if="form.errors.src_fincimex_mlc" class="text-xs text-red-500">{{ form.errors.src_fincimex_mlc }}</p>
                                </div>
                                <div class="space-y-2">
                                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                        <input v-model="form.qr_fincimex_cup" type="checkbox" class="rounded border-slate-300 text-indigo-600 dark:border-slate-600" />
                                        QR FINCIMEX CUP
                                    </label>
                                    <input
                                        v-model="form.src_fincimex_cup"
                                        type="text"
                                        class="app-input"
                                        placeholder="Source QR CUP"
                                        :disabled="!form.qr_fincimex_cup"
                                    />
                                    <p v-if="form.errors.src_fincimex_cup" class="text-xs text-red-500">{{ form.errors.src_fincimex_cup }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Terminal ID / IP -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Terminal ID</label>
                                <input v-model="form.terminal_id" type="text" class="app-input" placeholder="Ej: TRM-00123" />
                                <p v-if="form.errors.terminal_id" class="text-xs text-red-500">{{ form.errors.terminal_id }}</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">IP del Terminal</label>
                                <input v-model="form.terminal_ip" type="text" class="app-input" placeholder="Ej: 192.168.1.100" />
                                <p v-if="form.errors.terminal_ip" class="text-xs text-red-500">{{ form.errors.terminal_ip }}</p>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800">
                            <button type="button" class="app-button-secondary" @click="showModal = false">Cancelar</button>
                            <button type="submit" class="app-button-primary" :disabled="form.processing">
                                {{ form.processing ? 'Guardando...' : 'Guardar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
        <MatchResolverModal
            v-if="showResolverModal"
            :records="unresolvedRecords"
            name-key="unit_name"
            label="piso de venta"
            @resolved="applyResolvedUnmatched"
            @cancel="showResolverModal = false"
        />

        <!-- Modal exportar -->
        <Teleport to="body">
            <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showExportModal = false" />
                <div class="surface-card relative z-10 w-full max-w-2xl p-6">
                    <h3 class="mb-4 text-xl font-semibold text-slate-950 dark:text-slate-100">Exportar FINCIMEX</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <input
                            v-model="exportFilters.search"
                            type="text"
                            class="app-input md:col-span-2"
                            placeholder="Texto de búsqueda"
                        />
                        <div class="space-y-1 md:col-span-2">
                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Piso de venta</label>
                            <VSelect
                                v-model="exportSalesFloor"
                                :options="floorOptionsExport"
                                :filterable="false"
                                :loading="floorLoadingExport"
                                placeholder="Todos — buscar para filtrar por PV..."
                                :clearable="true"
                                @search="searchFloorsExport"
                            />
                        </div>
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
