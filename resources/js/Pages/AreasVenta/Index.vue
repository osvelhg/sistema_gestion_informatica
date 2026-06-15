<script setup>
import { ref, watch, computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({
    areas: Object,
    filters: Object,
    floorFilterOptions: Array,
    entityFilterOptions: Array,
    importEntityOptions: { type: Array, default: () => [] },
    cashRegisterModels: Object,
})

const search = ref(props.filters?.search || '')
const entityFilter = ref(
    props.filters?.entity_id
        ? props.entityFilterOptions?.find((o) => Number(o.id) === Number(props.filters.entity_id)) || null
        : null,
)

const showModal = ref(false)
const editing = ref(null)
const floorOptionsModal = ref([])
const floorLoadingModal = ref(false)
const selectedFloorModal = ref(null)

// ── Importar JSON ─────────────────────────────────────────────────────────────
const ALL_IMPORT_FLAGS = [
    'Abierto', 'Exhibicion', 'Interno', 'Merma', 'Gastronomia',
    'Insumo', 'Inversiones', 'Boutique', 'MermaOrigen', 'Consignacion',
    'Emergente', 'DespachoDiv', 'Distribuir', 'MercanciaVenta', 'MLC',
]
const ACTION_LABEL = { create: 'Crear', update: 'Actualizar', skip: 'Sin cambios', no_piso: 'Sin piso' }
const ACTION_CLASS  = {
    create: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    update: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    skip:   'bg-slate-100 text-slate-500',
    no_piso:'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
}

const showImport     = ref(false)
const importStep     = ref('upload')   // 'upload' | 'assign' | 'preview' | 'done'
const importFile     = ref(null)
const importBusy     = ref(false)
const importError    = ref('')
// Paso 1 – filtros de flags (AND)
const importFlagsFilter = ref([])      // e.g. ['Abierto', 'Exhibicion']
// Datos del análisis (paso 1 → paso 2)
const importAnalysis = ref(null)       // { groups, active_flags, total_rows, rows_matching, flags_filter_on }
// Paso 2 – entidad por IdUnidad (el piso se elige en la previsualización, filtrado por esa entidad)
const entityAssignments = ref({})      // { [id_unidad]: entity_id }
// Paso 3 – preview
const importPreview  = ref(null)       // { items, totals }
const importSelected = ref([])
const importPreviewSearch = ref('')
// Paso 4 – resultado
const importApplyBusy   = ref(false)
const importApplyResult = ref(null)

const previewRowsFiltered = computed(() => {
    const items = importPreview.value?.items
    if (!items?.length) return []
    const q = String(importPreviewSearch.value || '').trim().toLowerCase()
    const rows = items.map((item, idx) => ({ item, idx }))
    if (!q) return rows
    return rows.filter(({ item }) => {
        const blob = [
            item.almacen_nombre,
            item.floor_name,
            item.local_area_name,
            String(item.id_almacen_local ?? ''),
            String(item.almacen_id ?? ''),
            String(item.id_unidad ?? ''),
            item.almacen_tipo,
            item.almacen_e_contable,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
        return blob.includes(q)
    })
})

/** Totales recalculados al cambiar piso / acción en el cliente */
const importPreviewTotalsLive = computed(() => {
    const items = importPreview.value?.items
    if (!items?.length) return null
    const t = { create: 0, update: 0, skip: 0, no_piso: 0 }
    for (const it of items) {
        if (Object.prototype.hasOwnProperty.call(t, it.action)) t[it.action]++
    }
    return t
})

function previewRowFloorModel(item) {
    if (item.sales_floor_id == null || item.sales_floor_id === '') return null
    const opts = floorOptionsNameOnlyForEntity(item.entity_id)
    return opts.find((o) => Number(o.id) === Number(item.sales_floor_id)) ?? null
}

/**
 * Cambia el piso en una fila del preview (select con búsqueda).
 * Si el piso deja de coincidir con el del servidor, se invalida el match y pasa a crear.
 */
function onPreviewRowFloorChange(idx, opt) {
    const items = importPreview.value?.items
    if (!items?.[idx]) return
    const item = items[idx]
    const snap = item._snap
    const prevAction = item.action
    const newId = opt?.id != null ? Number(opt.id) : null

    if (!newId) {
        item.sales_floor_id = null
        item.floor_name = null
        item.action = 'no_piso'
        item.local_area_id = null
        item.local_area_name = null
        importSelected.value = importSelected.value.filter((i) => i !== idx)
        return
    }

    item.sales_floor_id = newId
    item.floor_name = opt?.label ?? item.floor_name

    if (snap && newId === Number(snap.sales_floor_id)) {
        item.action = snap.action
        item.local_area_id = snap.local_area_id
        item.local_area_name = snap.local_area_name
        item.floor_name = snap.floor_name
        return
    }

    item.local_area_id = null
    item.local_area_name = null
    item.action = 'create'
    if (prevAction === 'no_piso' && !importSelected.value.includes(idx)) {
        importSelected.value = [...importSelected.value, idx]
    }
}

function openImport() {
    importFile.value = null
    importBusy.value = false
    importError.value = ''
    importFlagsFilter.value = []
    importAnalysis.value = null
    entityAssignments.value = {}
    importPreview.value = null
    importSelected.value = []
    importPreviewSearch.value = ''
    importApplyResult.value = null
    importStep.value = 'upload'
    showImport.value = true
}

// Paso 1 → 2: analizar archivo y obtener grupos por IdUnidad
async function runAnalyze() {
    if (!importFile.value) return
    importBusy.value = true
    importError.value = ''
    try {
        const fd = new FormData()
        fd.append('file', importFile.value)
        fd.append('flags_filter', JSON.stringify(importFlagsFilter.value))
        const { data } = await axios.post(route('areas-venta.importar.analizar'), fd)
        importAnalysis.value = data
        const nextAssign = {}
        data.groups.forEach((g) => {
            const uid = g.id_unidad
            nextAssign[uid] = g.entity_id != null && g.entity_id !== '' ? Number(g.entity_id) : null
        })
        entityAssignments.value = nextAssign
        importStep.value = 'assign'
    } catch (e) {
        importError.value = e.response?.data?.message || 'Error al analizar el archivo.'
    } finally {
        importBusy.value = false
    }
}

// Helper: pisos filtrados por entity_id (datos completos desde el listado de PV)
function floorsForEntity(entityId) {
    if (!entityId) return props.floorFilterOptions || []
    return (props.floorFilterOptions || []).filter((o) => o.entity_id === entityId)
}

/** Opciones de piso solo nombre (sin código ni nombre de entidad) — previsualización */
function floorOptionsNameOnlyForEntity(entityId) {
    return floorsForEntity(entityId).map((o) => {
        const fromComposite = String(o.label ?? '')
            .split(' · ')
            .pop()
            ?.trim()
        const shortLabel = fromComposite || o.label
        return {
            id: o.id,
            label: o.name ?? shortLabel,
        }
    })
}

function importEntitySelectModel(idUnidad) {
    const id = entityAssignments.value[idUnidad]
    if (id == null || id === '') return null
    return (props.importEntityOptions || []).find((o) => Number(o.id) === Number(id)) ?? null
}

function onImportEntitySelect(idUnidad, opt) {
    const id = opt?.id != null ? Number(opt.id) : null
    entityAssignments.value = {
        ...entityAssignments.value,
        [idUnidad]: id,
    }
}

function entityAssignmentForGroup(idUnidad) {
    const raw = entityAssignments.value?.[idUnidad]
    if (raw == null || raw === '') return null
    const n = Number(raw)
    return Number.isFinite(n) && n > 0 ? n : null
}

/** Paso 2 → preview: no exige entidad en todas las unidades; solo las asignadas se procesan con piso, el resto va a «sin piso» en servidor. */
const importAssignStepReady = computed(() => {
    const a = importAnalysis.value
    return a != null && Array.isArray(a.groups)
})

// Paso 2 → 3: generar preview con filtros y asignaciones
async function runPreview() {
    importBusy.value = true
    importError.value = ''
    importPreview.value = null
    importSelected.value = []
    try {
        const fd = new FormData()
        fd.append('file', importFile.value)
        fd.append('flags_filter', JSON.stringify(importFlagsFilter.value))
        const assignments = {}
        ;(importAnalysis.value?.groups || []).forEach((g) => {
            const eid = entityAssignmentForGroup(g.id_unidad)
            if (eid != null) assignments[String(g.id_unidad)] = eid
        })
        fd.append('entity_assignments', JSON.stringify(assignments))
        const { data } = await axios.post(route('areas-venta.importar.preview'), fd)
        data.items = (data.items || []).map((it) => ({
            ...it,
            _snap: {
                sales_floor_id: it.sales_floor_id,
                action: it.action,
                local_area_id: it.local_area_id,
                local_area_name: it.local_area_name,
                floor_name: it.floor_name,
            },
        }))
        importPreview.value = data
        importPreviewSearch.value = ''
        importSelected.value = data.items
            .map((it, i) => (it.action === 'create' || it.action === 'update' ? i : null))
            .filter((i) => i !== null)
        importStep.value = 'preview'
    } catch (e) {
        importError.value = e.response?.data?.message || 'Error al previsualizar.'
    } finally {
        importBusy.value = false
    }
}

function toggleImportAll(actionFilter) {
    const indices = importPreview.value.items
        .map((it, i) => (it.action === actionFilter ? i : null))
        .filter((i) => i !== null)
    const allSel = indices.every((i) => importSelected.value.includes(i))
    importSelected.value = allSel
        ? importSelected.value.filter((i) => !indices.includes(i))
        : [...new Set([...importSelected.value, ...indices])]
}

/** Alterna selección de filas visibles (tras búsqueda) con acción crear/actualizar */
function toggleImportVisible() {
    const vis = previewRowsFiltered.value
        .filter(({ item }) => item.action === 'create' || item.action === 'update')
        .map(({ idx }) => idx)
    if (!vis.length) return
    const allSel = vis.every((i) => importSelected.value.includes(i))
    importSelected.value = allSel
        ? importSelected.value.filter((i) => !vis.includes(i))
        : [...new Set([...importSelected.value, ...vis])]
}

async function applyImport() {
    if (!importPreview.value || !importSelected.value.length) return
    importApplyBusy.value = true
    importError.value = ''
    try {
        const items = importSelected.value
            .map((i) => importPreview.value.items[i])
            .filter((it) => it.action === 'create' || it.action === 'update')
            .map((it) => ({
                action: it.action,
                almacen_nombre: it.almacen_nombre,
                almacen_id: it.almacen_id,
                id_almacen_local: it.id_almacen_local,
                almacen_tipo: it.almacen_tipo,
                almacen_e_contable: it.almacen_e_contable,
                sales_floor_id: it.sales_floor_id,
                local_area_id: it.local_area_id,
                flags: it.flags,
            }))
        if (!items.length) { importError.value = 'No hay ítems accionables seleccionados.'; return }
        const { data } = await axios.post(route('areas-venta.importar.aplicar'), { items })
        importApplyResult.value = data
        importStep.value = 'done'
        if (data.success) router.reload({ only: ['areas'] })
    } catch (e) {
        importError.value = e.response?.data?.message || 'Error al aplicar.'
    } finally {
        importApplyBusy.value = false
    }
}

// ── QR ────────────────────────────────────────────────────────────────────────
const qrArea = ref(null)
const qQr = ref('')
const qrResults = ref([])
const qrBusy = ref(false)
const qrSel = ref(null)
let qrT

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
})

const linkQrForm = useForm({
    link_type: 'area',
    area_venta_id: null,
    sales_floor_id: null,
    fuente_id: null,
})

let listTimer
function applyListFilters() {
    clearTimeout(listTimer)
    listTimer = setTimeout(
        () =>
            router.get(
                route('areas-venta.index'),
                {
                    search: search.value || undefined,
                    entity_id: entityFilter.value?.id || undefined,
                },
                { preserveState: true, replace: true },
            ),
        280,
    )
}

watch(search, applyListFilters)
watch(entityFilter, () => applyListFilters())

async function searchFloorsModal(query, loading) {
    if (!query || query.length < 1) return
    loading(true)
    try {
        const { data } = await axios.get(route('pisos-venta.search'), { params: { q: query } })
        floorOptionsModal.value = (data.floors || []).map((f) => ({ id: f.id, label: f.label || f.name }))
    } catch {
        floorOptionsModal.value = []
    } finally {
        loading(false)
    }
}

function onModalFloorChange(val) {
    form.sales_floor_id = val ? val.id : ''
}

function openCreate() {
    editing.value = null
    form.reset()
    selectedFloorModal.value = null
    form.sales_floor_id = ''
    floorOptionsModal.value = []
    form.clearErrors()
    showModal.value = true
}

function openEdit(row) {
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
        has_ip_connectivity: !!row.has_ip_connectivity,
        broken_pos_qty: row.broken_pos_qty ?? 0,
        cash_register_model_code: row.cash_register_model_code ?? null,
        pos_currency_mlc: !!row.pos_currency_mlc,
        pos_currency_cup: !!row.pos_currency_cup,
        qr_fincimex_mlc: !!row.qr_fincimex_mlc,
        qr_fincimex_cup: !!row.qr_fincimex_cup,
        src_fincimex_mlc: row.src_fincimex_mlc || '',
        src_fincimex_cup: row.src_fincimex_cup || '',
        terminal_id: row.terminal_id || '',
    })
    selectedFloorModal.value =
        row.sales_floor && row.sales_floor.id
            ? {
                  id: row.sales_floor.id,
                  label:
                      [row.sales_floor.entity?.code ? `[${row.sales_floor.entity.code}]` : null, row.sales_floor.entity?.name, row.sales_floor.name]
                          .filter(Boolean)
                          .join(' ') || row.sales_floor.name,
              }
            : null
    floorOptionsModal.value = []
    form.clearErrors()
    showModal.value = true
}

const submit = () =>
    editing.value
        ? form.put(route('areas-venta.update', editing.value.id), { onSuccess: () => (showModal.value = false) })
        : form.post(route('areas-venta.store'), { onSuccess: () => (showModal.value = false) })

const destroy = async (row) => {
    if (!(await confirmDanger({ title: 'Eliminar área', text: `Se eliminará «${row.name}».`, confirmText: 'Eliminar' }))) return
    router.delete(route('areas-venta.destroy', row.id))
}

function resumePos(a) {
    return `TPV ${a.tpv_boxes ?? 0} · Tel ${a.pos_phone_qty ?? 0} · IP ${a.pos_ip_qty ?? 0} · GPRS ${a.pos_gprs_qty ?? 0}`
}

function openQr(area) {
    qrArea.value = area
    qQr.value = ''
    qrResults.value = []
    qrSel.value = null
    linkQrForm.clearErrors()
}

function closeQr() {
    qrArea.value = null
}

watch(qQr, (v) => {
    clearTimeout(qrT)
    qrT = setTimeout(async () => {
        const q = String(v || '').trim()
        if (!q) {
            qrResults.value = []
            return
        }
        qrBusy.value = true
        try {
            const { data } = await axios.get(route('pisos-venta.areas-qr.buscar-fuentes'), { params: { q } })
            qrResults.value = data.fuentes || []
        } catch {
            qrResults.value = []
        } finally {
            qrBusy.value = false
        }
    }, 320)
})

function pickQrFuente(f) {
    qrSel.value = f
    qrResults.value = []
    qQr.value = [f.source, f.source_name].filter(Boolean).join(' · ')
}

function submitQrLink() {
    if (!qrArea.value || !qrSel.value) return
    linkQrForm.link_type = 'area'
    linkQrForm.area_venta_id = qrArea.value.id
    linkQrForm.sales_floor_id = null
    linkQrForm.fuente_id = qrSel.value.id
    linkQrForm.post(route('pisos-venta.areas-qr.vinculos.store'), {
        preserveScroll: true,
        onSuccess: () => closeQr(),
    })
}

async function unlinkQr(pivotId) {
    if (!(await confirmDanger({ title: 'Quitar QR', text: '¿Desvincular esta fuente del área?', confirmText: 'Sí' }))) return
    router.delete(route('pisos-venta.areas-qr.vinculos-area.destroy', pivotId), { preserveScroll: true })
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl space-y-4">
            <PageHeader eyebrow="Entidades" title="Áreas de venta" description="Alta, edición y fuentes Datacell por área (datos importados o manuales).">
                <template #actions>
                    <Link :href="route('pisos-venta.areas-qr.index')" class="app-button-secondary text-sm">Mapa rápido QR</Link>
                    <button type="button" class="app-button-secondary text-sm" @click="openImport">Importar JSON</button>
                    <button type="button" class="app-button-primary text-sm" @click="openCreate">Nueva área</button>
                </template>
            </PageHeader>

            <div class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200/80 bg-white/70 px-3 py-2 dark:border-slate-700/70 dark:bg-slate-900/40">
                <div class="min-w-[10rem] flex-1">
                    <label class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Buscar</label>
                    <input v-model="search" type="search" class="app-input py-1.5 text-sm" placeholder="Área o piso…" />
                </div>
                <div class="w-full min-w-[14rem] sm:w-2/5">
                    <label class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Entidad</label>
                    <VSelect
                        v-model="entityFilter"
                        :options="entityFilterOptions || []"
                        :reduce="(o) => o"
                        label="label"
                        placeholder="Todas"
                        :clearable="true"
                        class="vs-sm"
                    />
                </div>
            </div>

            <BaseCard class="overflow-x-auto p-0">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/90 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2">Piso</th>
                            <th class="px-3 py-2">Área</th>
                            <th class="px-3 py-2">POS</th>
                            <th class="px-3 py-2">QR</th>
                            <th class="px-3 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="a in areas?.data || []" :key="a.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30">
                            <td class="px-3 py-2 text-xs text-slate-600 dark:text-slate-300">
                                {{ a.sales_floor?.name || '—' }}
                                <span v-if="a.sales_floor?.entity" class="block text-[10px] text-slate-400">
                                    [{{ a.sales_floor.entity.code || '—' }}] {{ a.sales_floor.entity.name }}
                                </span>
                            </td>
                            <td class="px-3 py-2 font-medium text-slate-900 dark:text-slate-100">{{ a.name }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600 dark:text-slate-400">{{ resumePos(a) }}</td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="src in a.datacell_sources || []"
                                        :key="src.pivot?.id"
                                        class="inline-flex items-center gap-0.5 rounded bg-brand-500/10 px-1 py-0.5 text-[10px] font-mono text-brand-800 dark:text-brand-200"
                                    >
                                        {{ src.source }}
                                    </span>
                                    <span v-if="!(a.datacell_sources || []).length" class="text-[11px] text-slate-400">—</span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <button type="button" class="mr-1 rounded border border-slate-200 px-2 py-1 text-[11px] dark:border-slate-600" @click="openQr(a)">
                                    QR
                                </button>
                                <button type="button" class="rounded border border-slate-200 px-2 py-1 text-[11px] dark:border-slate-600" @click="openEdit(a)">
                                    Editar
                                </button>
                                <button type="button" class="ml-1 rounded border border-red-200 px-2 py-1 text-[11px] text-red-600 dark:border-red-800" @click="destroy(a)">
                                    Elim.
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!(areas?.data || []).length">
                            <td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">No hay áreas con estos filtros.</td>
                        </tr>
                    </tbody>
                </table>
            </BaseCard>

            <div v-if="areas?.links?.length > 3" class="flex flex-wrap justify-between gap-2 text-xs text-slate-500">
                <span>{{ areas.from }}–{{ areas.to }} / {{ areas.total }}</span>
                <div class="flex flex-wrap gap-1">
                    <template v-for="link in areas.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-state
                            preserve-scroll
                            class="rounded border border-slate-200 px-2 py-1 dark:border-slate-700"
                            :class="link.active ? 'bg-slate-900 text-white dark:bg-cyan-400 dark:text-slate-950' : ''"
                            v-html="link.label"
                        />
                        <span v-else v-html="link.label" class="px-1 text-slate-400" />
                    </template>
                </div>
            </div>
        </div>

        <!-- Modal CRUD -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-3 py-8">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                <div class="surface-card relative z-10 w-full max-w-2xl p-5 text-sm">
                    <h3 class="mb-4 text-lg font-semibold">{{ editing ? 'Editar área' : 'Nueva área' }}</h3>
                    <form class="max-h-[80vh] space-y-4 overflow-y-auto pr-1" @submit.prevent="submit">
                        <div>
                            <label class="text-xs font-medium text-slate-600">Piso de venta *</label>
                            <VSelect
                                v-model="selectedFloorModal"
                                :options="floorOptionsModal"
                                :filterable="false"
                                :loading="floorLoadingModal"
                                placeholder="Buscar PV…"
                                @search="searchFloorsModal"
                                @update:modelValue="onModalFloorChange"
                            />
                            <p v-if="form.errors.sales_floor_id" class="text-xs text-red-500">{{ form.errors.sales_floor_id }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-600">Nombre del área *</label>
                            <input v-model="form.name" type="text" class="app-input" />
                            <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <label class="text-xs text-slate-600">TPV</label>
                                <input v-model.number="form.tpv_boxes" type="number" min="0" class="app-input" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-600">Tel</label>
                                <input v-model.number="form.pos_phone_qty" type="number" min="0" class="app-input" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-600">IP</label>
                                <input v-model.number="form.pos_ip_qty" type="number" min="0" class="app-input" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-600">Dem. IP</label>
                                <input v-model.number="form.pos_ip_demand" type="number" min="0" class="app-input" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-600">GPRS</label>
                                <input v-model.number="form.pos_gprs_qty" type="number" min="0" class="app-input" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-600">Dem. GPRS</label>
                                <input v-model.number="form.pos_gprs_demand" type="number" min="0" class="app-input" />
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-xs">
                            <input v-model="form.has_ip_connectivity" type="checkbox" class="rounded" />
                            Conectividad IP
                        </label>
                        <div>
                            <label class="text-xs text-slate-600">POS rotos</label>
                            <input v-model.number="form.broken_pos_qty" type="number" min="0" class="app-input w-32" />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-xs text-slate-600">Modelo caja</label>
                                <select v-model="form.cash_register_model_code" class="app-select">
                                    <option :value="null">—</option>
                                    <option v-for="(label, code) in cashRegisterModels" :key="code" :value="Number(code)">{{ label }}</option>
                                </select>
                            </div>
                            <div class="flex items-end gap-3 pb-1">
                                <label class="flex items-center gap-1 text-xs"><input v-model="form.pos_currency_mlc" type="checkbox" class="rounded" /> MLC</label>
                                <label class="flex items-center gap-1 text-xs"><input v-model="form.pos_currency_cup" type="checkbox" class="rounded" /> CUP</label>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="flex items-center gap-1 text-xs"><input v-model="form.qr_fincimex_mlc" type="checkbox" class="rounded" /> QR FINCIMEX MLC</label>
                                <input v-model="form.src_fincimex_mlc" class="app-input text-xs" :disabled="!form.qr_fincimex_mlc" />
                            </div>
                            <div>
                                <label class="flex items-center gap-1 text-xs"><input v-model="form.qr_fincimex_cup" type="checkbox" class="rounded" /> QR FINCIMEX CUP</label>
                                <input v-model="form.src_fincimex_cup" class="app-input text-xs" :disabled="!form.qr_fincimex_cup" />
                            </div>
                        </div>
                        <div>
                            <label class="text-xs text-slate-600">Terminal ID</label>
                            <input v-model="form.terminal_id" class="app-input" />
                        </div>
                        <div class="flex justify-end gap-2 border-t border-slate-200 pt-3 dark:border-slate-700">
                            <button type="button" class="app-button-secondary" @click="showModal = false">Cancelar</button>
                            <button type="submit" class="app-button-primary" :disabled="form.processing">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal Importar JSON -->
        <Teleport to="body">
            <div v-if="showImport" class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto px-3 py-8">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showImport = false" />
                <div class="surface-card relative z-10 w-full max-w-4xl p-5 text-sm">
                    <h3 class="mb-1 text-lg font-semibold">Importar áreas desde JSON (Golden)</h3>
                    <p class="mb-4 text-xs text-slate-500">
                        Archivo <code>Almacenes.json</code>. Código Golden del área: <code>IdAlmacen</code> (local). Unidad comercial por
                        <code>IdUnidad</code> (coincide con <code>entidades.code</code>). Elija el piso de venta por unidad en el paso siguiente;
                        no se usa <code>IdPiso</code> del JSON. Opcionalmente exija que ciertos flags sean verdaderos (todos a la vez, AND) antes de importar.
                    </p>

                    <!-- Paso 1: subir + filtros de flags -->
                    <div v-if="importStep === 'upload'" class="space-y-4">
                        <div>
                            <label class="text-xs font-medium text-slate-600">Archivo JSON *</label>
                            <input
                                type="file"
                                accept=".json"
                                class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded file:border-0 file:bg-brand-600 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white hover:file:bg-brand-700"
                                @change="importFile = $event.target.files[0]"
                            />
                        </div>
                        <div>
                            <p class="mb-2 text-xs font-medium text-slate-600">
                                Características requeridas (AND) — solo se importan filas que cumplan todas las marcadas; deje vacío para no filtrar por flags.
                            </p>
                            <div class="flex max-h-36 flex-wrap gap-x-4 gap-y-2 overflow-y-auto rounded border border-slate-200 p-2 text-xs dark:border-slate-700">
                                <label v-for="fl in ALL_IMPORT_FLAGS" :key="fl" class="flex cursor-pointer items-center gap-1.5">
                                    <input v-model="importFlagsFilter" type="checkbox" class="rounded" :value="fl" />
                                    <span>{{ fl }}</span>
                                </label>
                            </div>
                        </div>
                        <p v-if="importError" class="text-xs text-red-600">{{ importError }}</p>
                        <div class="flex justify-end gap-2 border-t border-slate-200 pt-3 dark:border-slate-700">
                            <button type="button" class="app-button-secondary" @click="showImport = false">Cancelar</button>
                            <button type="button" class="app-button-primary" :disabled="!importFile || importBusy" @click="runAnalyze">
                                {{ importBusy ? 'Analizando…' : 'Analizar' }}
                            </button>
                        </div>
                    </div>

                    <!-- Paso 2: asignar entidad por IdUnidad -->
                    <div v-else-if="importStep === 'assign'" class="space-y-4">
                        <p class="text-xs text-slate-500">
                            <template v-if="importAnalysis?.flags_filter_on">
                                <strong>{{ importAnalysis.rows_matching }}</strong> filas cumplen el filtro AND marcado en el paso anterior (el JSON tiene {{ importAnalysis.total_rows }} filas en total).
                                · {{ importAnalysis?.groups?.length || 0 }} unidad(es) <code>IdUnidad</code> con al menos una fila que cumple.
                            </template>
                            <template v-else>
                                {{ importAnalysis?.total_rows }} filas en el archivo · {{ importAnalysis?.groups?.length || 0 }} unidades (<code>IdUnidad</code>).
                            </template>
                            Elija la <strong>entidad</strong> de referencia por unidad; en la siguiente pantalla solo verá los <strong>nombres de piso</strong> de esa entidad.
                            Las unidades sin entidad elegida <strong>no entran</strong> en la previsualización ni en la importación (solo se procesan las que asignó aquí).
                        </p>
                        <p
                            v-if="importAnalysis?.flags_filter_on && !(importAnalysis?.groups || []).length"
                            class="rounded border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                        >
                            Ninguna fila cumple todos los flags seleccionados. Vuelva atrás y desmarque o cambie las características requeridas.
                        </p>
                        <div class="max-h-[50vh] space-y-3 overflow-y-auto pr-1">
                            <div
                                v-for="g in importAnalysis?.groups || []"
                                :key="g.id_unidad"
                                class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                            >
                                <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                                    <span class="font-medium text-slate-800 dark:text-slate-100">
                                        IdUnidad {{ g.id_unidad }}
                                        <span v-if="g.entity_name" class="text-xs font-normal text-slate-600">
                                            · [{{ g.entity_code }}] {{ g.entity_name }}
                                        </span>
                                        <span v-else class="text-xs font-normal text-amber-700">· Sin entidad local (código {{ String(g.id_unidad).padStart(5, '0') }})</span>
                                    </span>
                                    <span class="text-[10px] text-slate-400">{{ g.count }} almacén(es) en JSON</span>
                                </div>
                                <p v-if="g.sample?.length" class="mb-2 text-[10px] text-slate-500">Ejemplos: {{ g.sample.join(' · ') }}</p>
                                <label class="text-[10px] font-semibold uppercase text-slate-500">Entidad comercial</label>
                                <VSelect
                                    class="vs-sm mt-1 w-full max-w-xl"
                                    :model-value="importEntitySelectModel(g.id_unidad)"
                                    :options="importEntityOptions || []"
                                    :reduce="(o) => o"
                                    label="label"
                                    placeholder="— Elegir entidad —"
                                    :clearable="true"
                                    :filterable="true"
                                    :append-to-body="true"
                                    @update:model-value="(opt) => onImportEntitySelect(g.id_unidad, opt)"
                                />
                                <p v-if="!g.matched" class="mt-1 text-[10px] text-amber-700">
                                    No hay entidad local con el código de esta unidad; elija manualmente la entidad correcta.
                                </p>
                            </div>
                        </div>
                        <p v-if="importError" class="text-xs text-red-600">{{ importError }}</p>
                        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-3 dark:border-slate-700">
                            <button type="button" class="app-button-secondary" @click="importStep = 'upload'">← Volver</button>
                            <button type="button" class="app-button-secondary" @click="showImport = false">Cancelar</button>
                            <button
                                type="button"
                                class="app-button-primary"
                                :disabled="importBusy || !importAssignStepReady"
                                @click="runPreview"
                            >
                                {{ importBusy ? 'Generando…' : 'Previsualizar' }}
                            </button>
                        </div>
                    </div>

                    <!-- Paso 3: preview / match -->
                    <div v-else-if="importStep === 'preview'" class="space-y-3">
                        <p class="text-[11px] text-slate-500">
                            Solo se listan los <strong>nombres de piso de venta</strong> de la entidad elegida para cada <code>IdUnidad</code>. Puede cambiar el piso por fila; si difiere del cálculo inicial, la fila pasa a <strong>Crear</strong> salvo que vuelva al piso original.
                        </p>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="rounded bg-green-100 px-2 py-1 font-semibold text-green-800 dark:bg-green-900/30 dark:text-green-300">Crear: {{ importPreviewTotalsLive?.create ?? importPreview.totals.create }}</span>
                            <span class="rounded bg-yellow-100 px-2 py-1 font-semibold text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">Actualizar: {{ importPreviewTotalsLive?.update ?? importPreview.totals.update }}</span>
                            <span class="rounded bg-slate-100 px-2 py-1 text-slate-600 dark:bg-slate-400">Sin cambios: {{ importPreviewTotalsLive?.skip ?? importPreview.totals.skip }}</span>
                            <span class="rounded bg-red-100 px-2 py-1 text-red-700 dark:bg-red-900/30 dark:text-red-300">Sin piso: {{ importPreviewTotalsLive?.no_piso ?? importPreview.totals.no_piso }}</span>
                            <span v-if="importPreview.totals.filtered_out != null" class="rounded bg-slate-100 px-2 py-1 text-slate-500">Filtrados (flags): {{ importPreview.totals.filtered_out }}</span>
                            <span
                                v-if="(importPreview.totals.excluded_no_entity ?? 0) > 0"
                                class="rounded bg-slate-100 px-2 py-1 text-slate-500"
                            >
                                Omitidos (unidad sin entidad): {{ importPreview.totals.excluded_no_entity }}
                            </span>
                            <span class="ml-auto text-slate-400">{{ importPreview.items.length }} filas en vista</span>
                        </div>

                        <div class="flex flex-wrap items-end gap-2">
                            <div class="min-w-[12rem] flex-1">
                                <label class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Buscar en la tabla</label>
                                <input
                                    v-model="importPreviewSearch"
                                    type="search"
                                    class="app-input py-1.5 text-sm"
                                    placeholder="Nombre, código Golden, IdUnidad, piso, área local…"
                                />
                            </div>
                            <span class="text-xs text-slate-500">{{ previewRowsFiltered.length }} visibles</span>
                        </div>

                        <div class="flex flex-wrap gap-2 text-xs">
                            <button type="button" class="rounded border border-green-300 px-2 py-1 text-green-700 hover:bg-green-50 dark:border-green-800 dark:text-green-400 dark:hover:bg-green-950/40" @click="toggleImportAll('create')">± Todos crear</button>
                            <button type="button" class="rounded border border-yellow-300 px-2 py-1 text-yellow-700 hover:bg-yellow-50 dark:border-yellow-800 dark:text-yellow-400 dark:hover:bg-yellow-950/40" @click="toggleImportAll('update')">± Todos actualizar</button>
                            <button
                                type="button"
                                class="rounded border border-slate-300 px-2 py-1 text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                                @click="toggleImportVisible"
                            >
                                ± Visibles (crear/actualizar)
                            </button>
                            <span class="ml-auto text-slate-500">{{ importSelected.length }} seleccionados</span>
                        </div>

                        <div class="max-h-[55vh] overflow-auto rounded border border-slate-200 dark:border-slate-700">
                            <table class="w-full text-left text-xs">
                                <thead class="sticky top-0 border-b border-slate-200 bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-900">
                                    <tr>
                                        <th class="w-8 px-2 py-1.5"></th>
                                        <th class="px-2 py-1.5">Acción</th>
                                        <th class="px-2 py-1.5">Almacén</th>
                                        <th class="px-2 py-1.5">IdAlmacen</th>
                                        <th class="px-2 py-1.5">Tipo</th>
                                        <th class="min-w-[14rem] px-2 py-1.5">Piso de venta</th>
                                        <th class="px-2 py-1.5">Área existente</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr
                                        v-for="{ item, idx } in previewRowsFiltered"
                                        :key="idx"
                                        :class="
                                            item.action === 'no_piso'
                                                ? 'bg-amber-50/50 dark:bg-amber-950/25'
                                                : 'hover:bg-slate-50/50 dark:hover:bg-slate-900/30'
                                        "
                                    >
                                        <td class="px-2 py-1 text-center">
                                            <input
                                                v-if="item.action === 'create' || item.action === 'update'"
                                                type="checkbox"
                                                :checked="importSelected.includes(idx)"
                                                class="rounded"
                                                @change="importSelected.includes(idx) ? importSelected.splice(importSelected.indexOf(idx), 1) : importSelected.push(idx)"
                                            />
                                        </td>
                                        <td class="px-2 py-1">
                                            <span :class="['rounded px-1.5 py-0.5 font-semibold', ACTION_CLASS[item.action]]">
                                                {{ ACTION_LABEL[item.action] }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-1 font-medium">{{ item.almacen_nombre }}</td>
                                        <td class="px-2 py-1 font-mono text-[10px] text-slate-500">{{ item.id_almacen_local }}</td>
                                        <td class="px-2 py-1 text-[10px] text-slate-500">{{ item.almacen_tipo || '—' }}</td>
                                        <td class="px-2 py-1 align-top">
                                            <VSelect
                                                class="vs-sm min-w-[12rem] max-w-[22rem]"
                                                :model-value="previewRowFloorModel(item)"
                                                :options="floorOptionsNameOnlyForEntity(item.entity_id)"
                                                :reduce="(o) => o"
                                                label="label"
                                                placeholder="Elegir piso…"
                                                :clearable="true"
                                                :filterable="true"
                                                :append-to-body="true"
                                                @update:model-value="(opt) => onPreviewRowFloorChange(idx, opt)"
                                            />
                                            <p v-if="!item.sales_floor_id" class="mt-0.5 text-[10px] text-amber-700 dark:text-amber-300">IdUnidad {{ item.id_unidad }}</p>
                                        </td>
                                        <td class="px-2 py-1 text-[10px] text-slate-500">{{ item.local_area_name || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-if="!previewRowsFiltered.length" class="p-4 text-center text-xs text-slate-500">Ninguna fila coincide con la búsqueda.</p>
                        </div>

                        <p v-if="importError" class="text-xs text-red-600">{{ importError }}</p>
                        <div class="flex justify-end gap-2 border-t border-slate-200 pt-3 dark:border-slate-700">
                            <button type="button" class="app-button-secondary" @click="importStep = 'assign'">← Volver</button>
                            <button type="button" class="app-button-secondary" @click="showImport = false">Cancelar</button>
                            <button
                                type="button"
                                class="app-button-primary"
                                :disabled="importApplyBusy || !importSelected.length"
                                @click="applyImport"
                            >
                                {{
                                    importApplyBusy
                                        ? 'Aplicando…'
                                        : `Aplicar (${importSelected.filter((i) => importPreview.items[i].action !== 'skip' && importPreview.items[i].action !== 'no_piso').length})`
                                }}
                            </button>
                        </div>
                    </div>

                    <!-- Paso 4: resultado -->
                    <div v-else-if="importStep === 'done'" class="space-y-3 py-2">
                        <div v-if="importApplyResult?.success" class="rounded-lg bg-green-50 p-4 text-green-800 dark:bg-green-900/20 dark:text-green-300">
                            <p class="font-semibold">Importación completada</p>
                            <p class="text-xs">Creadas: {{ importApplyResult.created }} · Actualizadas: {{ importApplyResult.updated }}</p>
                        </div>
                        <div v-else class="rounded-lg bg-red-50 p-4 text-red-800 dark:bg-red-900/20">
                            <p class="font-semibold">Importación con errores</p>
                        </div>
                        <ul v-if="importApplyResult?.errors?.length" class="max-h-32 overflow-auto rounded border border-red-200 p-2 text-xs text-red-600">
                            <li v-for="(err, i) in importApplyResult.errors" :key="i">{{ err }}</li>
                        </ul>
                        <div class="flex justify-end">
                            <button type="button" class="app-button-primary" @click="showImport = false">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal QR -->
        <Teleport to="body">
            <div v-if="qrArea" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60" @click="closeQr" />
                <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-4 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    <h4 class="mb-1 font-semibold">Fuentes QR · {{ qrArea.name }}</h4>
                    <p class="mb-3 text-xs text-slate-500">Una fuente por canal electrónico.</p>
                    <div class="mb-2 flex flex-wrap gap-1">
                        <span
                            v-for="src in qrArea.datacell_sources || []"
                            :key="'q' + src.pivot.id"
                            class="inline-flex items-center gap-1 rounded border border-slate-200 px-2 py-0.5 text-xs dark:border-slate-600"
                        >
                            <code>{{ src.source }}</code>
                            <button type="button" class="text-red-600" @click="unlinkQr(src.pivot.id)">×</button>
                        </span>
                    </div>
                    <input v-model="qQr" type="search" class="app-input mb-2 text-sm" placeholder="Buscar por source o nombre…" />
                    <ul v-if="qrResults.length" class="mb-2 max-h-40 overflow-auto rounded border border-slate-200 text-xs dark:border-slate-700">
                        <li
                            v-for="f in qrResults"
                            :key="f.id"
                            class="cursor-pointer border-b border-slate-100 px-2 py-1 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"
                            @click="pickQrFuente(f)"
                        >
                            {{ f.source }} — {{ f.source_name }} · {{ f.canal_electronico?.nombre || '—' }}{{ (f.areas_venta || []).length ? ' · [' + f.areas_venta.map((a) => a.name).join(', ') + ']' : '' }}
                        </li>
                    </ul>
                    <p v-if="linkQrForm.errors.fuente_id" class="mb-2 text-xs text-red-600">{{ linkQrForm.errors.fuente_id }}</p>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="app-button-secondary text-sm" @click="closeQr">Cerrar</button>
                        <button type="button" class="app-button-primary text-sm" :disabled="linkQrForm.processing || !qrSel" @click="submitQrLink">Vincular</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.vs-sm :deep(.vs__dropdown-toggle) {
    padding: 0.35rem 0.5rem;
    min-height: 2.25rem;
}
</style>
