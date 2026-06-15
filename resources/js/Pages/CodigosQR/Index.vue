<script setup>
import { ref, computed, watch } from 'vue'

const IMPORT_CANAL_STORAGE_KEY = 'codigos-qr.import.canal_filter'

function normCanalKey(s) {
    return String(s ?? '').toUpperCase().replace(/\s+/g, '')
}

function uniqueCanalesFromItems(items, mapping) {
    const map = mapping || {}
    const jsonKey = (map.canal_nombre && String(map.canal_nombre).trim()) ? map.canal_nombre : 'CanalNombre'
    const set = new Set()
    for (const row of items || []) {
        const v = row[jsonKey]
        if (v !== null && v !== undefined && String(v).trim() !== '') set.add(String(v).trim())
    }
    return Array.from(set).sort((a, b) => a.localeCompare(b, 'es'))
}

function pickDefaultCanalFilter(uniqueList) {
    let stored = null
    try {
        stored = localStorage.getItem(IMPORT_CANAL_STORAGE_KEY)
    } catch { /* */ }
    if (stored !== null) {
        if (stored === '') return ''
        const found = uniqueList.find((u) => normCanalKey(u) === normCanalKey(stored))
        if (found) return found
    }
    const tm = uniqueList.find((u) => normCanalKey(u) === 'TRANSFERMOVIL')
    if (tm) return tm
    return ''
}
import { router, Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'

import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import PageHeader from '@/Components/PageHeader.vue'

const page = usePage()

const props = defineProps({
    sources:     Object,
    filters:     Object,
    canales:     Array,
    tipos:       Array,
    monedas:     Array,
    last_synced: String,
})

// ── Filtros ───────────────────────────────────────────────────────────────────
const search      = ref(props.filters?.search      || '')
const fCanal      = ref(props.filters?.canal       || '')
const fTipo       = ref(props.filters?.tipo        || '')
const fMoneda     = ref(props.filters?.moneda      || '')
const soloActivos = ref(props.filters?.solo_activos === '1' || props.filters?.solo_activos === true)
const monedaOptions = (props.monedas || []).map(m => ({
    id: m.sigla,
    label: m.simbolo ? `${m.sigla} (${m.simbolo})` : m.sigla,
}))

let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => applyFilters(), 400)
})

function applyFilters() {
    router.get(route('codigos-qr.index'), {
        search:       search.value || undefined,
        canal:        fCanal.value  || undefined,
        tipo:         fTipo.value   || undefined,
        moneda:       fMoneda.value || undefined,
        solo_activos: soloActivos.value ? '1' : undefined,
    }, { preserveState: true, replace: true })
}

function exportTrabajadoresExcel() {
    const params = new URLSearchParams()
    if (search.value) params.set('search', search.value)
    if (fCanal.value) params.set('canal', String(fCanal.value))
    if (fTipo.value) params.set('tipo', String(fTipo.value))
    if (fMoneda.value) params.set('moneda', String(fMoneda.value))
    if (soloActivos.value) params.set('solo_activos', '1')
    const q = params.toString()
    window.open(`/codigos-qr/exportar/trabajadores-excel${q ? `?${q}` : ''}`, '_blank')
}

function resetFilters() {
    search.value = fCanal.value = fTipo.value = fMoneda.value = ''
    soloActivos.value = false
    applyFilters()
}

// ── Importación JSON + mapeo de columnas ─────────────────────────────────────
const jsonFileInput = ref(null)
const importing = ref(false)
const importMsg = ref('')
const importError = ref('')

/** Definición de campos SGI ↔ claves JSON por defecto (debe coincidir con QrSourcesImportService::DEFAULT_FIELD_MAP) */
const IMPORT_FIELDS = [
    { key: 'source', label: 'Código fuente', hint: 'Obligatorio: identificador del código (tabla: source)', defaultJson: 'Source' },
    { key: 'source_name', label: 'Nombre visible', hint: 'Nombre en listados (source_name)', defaultJson: 'SourceName' },
    { key: 'external_id', label: 'ID registro origen', hint: 'ID numérico en el sistema origen (external_id)', defaultJson: 'Id' },
    { key: 'moneda', label: 'Moneda', hint: 'Sigla CUP, USD…', defaultJson: 'Moneda' },
    { key: 'id_unidad', label: 'Id unidad', defaultJson: 'IdUnidad' },
    { key: 'unidad_nombre', label: 'Nombre unidad', defaultJson: 'UnidadNombre' },
    { key: 'id_canal', label: 'Id canal', defaultJson: 'IdCanal' },
    { key: 'canal_nombre', label: 'Nombre canal', defaultJson: 'CanalNombre' },
    { key: 'id_tipo_source', label: 'Id tipo fuente', defaultJson: 'IdTipoSource' },
    { key: 'tipo_nombre', label: 'Nombre tipo', defaultJson: 'TipoNombre' },
    { key: 'activo', label: 'Activo', defaultJson: 'Activo' },
    { key: 'id_piso', label: 'Id piso (vínculo)', defaultJson: 'IdPiso' },
    { key: 'almacen', label: 'Almacén (Golden)', hint: 'Texto Almacen → piso.almacen_golden', defaultJson: 'Almacen' },
    { key: 'id_division', label: 'Id división', defaultJson: 'IdDivision' },
    { key: 'division', label: 'División', defaultJson: 'Division' },
]

function extractItemsFromJson(decoded) {
    if (!decoded || typeof decoded !== 'object') return []
    if (Array.isArray(decoded)) return decoded
    const topKeys = ['data', 'items', 'result', 'sources', 'fuentes', 'qrsource', 'QrSource', 'qrSources', 'QrSources', 'sourceqr', 'SourceQr']
    for (const k of topKeys) {
        if (decoded[k] && Array.isArray(decoded[k])) return decoded[k]
    }
    if (decoded.division && typeof decoded.division === 'object') {
        const div = decoded.division
        for (const k of topKeys) {
            if (div[k] && Array.isArray(div[k])) return div[k]
        }
        if (Array.isArray(div)) return div
    }
    return []
}

function buildInitialMapping(jsonKeys) {
    const keys = jsonKeys || []
    const m = {}
    for (const f of IMPORT_FIELDS) {
        if (keys.includes(f.defaultJson)) {
            m[f.key] = f.defaultJson
        } else {
            const ci = keys.find((k) => k.toLowerCase() === f.defaultJson.toLowerCase())
            m[f.key] = ci || ''
        }
    }
    if (!m.source && keys.length) m.source = keys[0]
    return m
}

const showImportMapModal = ref(false)
const importJsonKeys = ref([])
const importSampleRow = ref(null)
const importMapping = ref({})
const pendingImportFile = ref(null)
/** Todas las filas del JSON (para listar canales únicos según el mapeo). */
const importAllItems = ref([])
const importCanalFilter = ref('')

const importCanalOptions = computed(() =>
    uniqueCanalesFromItems(importAllItems.value, importMapping.value),
)

watch(importMapping, (m) => {
    if (!showImportMapModal.value) return
    const opts = uniqueCanalesFromItems(importAllItems.value, m)
    if (importCanalFilter.value && !opts.some((o) => normCanalKey(o) === normCanalKey(importCanalFilter.value))) {
        importCanalFilter.value = pickDefaultCanalFilter(opts)
    }
}, { deep: true })

const importPreviewCells = computed(() => {
    const row = importSampleRow.value
    const map = importMapping.value || {}
    if (!row) return []
    return IMPORT_FIELDS.map((f) => {
        const jk = map[f.key] || f.defaultJson
        if (!jk) return { label: f.label, value: '—' }
        const v = row[jk]
        if (v === null || v === undefined) return { label: f.label, value: '—' }
        if (typeof v === 'object') return { label: f.label, value: JSON.stringify(v) }
        return { label: f.label, value: String(v) }
    })
})

function triggerJsonImport() {
    importMsg.value = ''
    importError.value = ''
    jsonFileInput.value?.click()
}

function closeImportMapModal() {
    showImportMapModal.value = false
    importJsonKeys.value = []
    importSampleRow.value = null
    importMapping.value = {}
    pendingImportFile.value = null
    importAllItems.value = []
    importCanalFilter.value = ''
}

async function onJsonFileSelected(e) {
    const file = e.target.files?.[0]
    if (!file) return
    importMsg.value = ''
    importError.value = ''
    try {
        const text = await file.text()
        const decoded = JSON.parse(text)
        const items = extractItemsFromJson(decoded)
        if (!items.length) {
            importError.value = 'El JSON no contiene una lista de objetos reconocible.'
            e.target.value = ''
            return
        }
        const first = items[0]
        const keys = Object.keys(first).filter((k) => k !== null && k !== '')
        if (!keys.length) {
            importError.value = 'El primer elemento no tiene propiedades.'
            e.target.value = ''
            return
        }
        pendingImportFile.value = file
        importJsonKeys.value = keys
        importSampleRow.value = first
        importAllItems.value = items
        const initialMap = buildInitialMapping(keys)
        importMapping.value = initialMap
        importCanalFilter.value = pickDefaultCanalFilter(uniqueCanalesFromItems(items, initialMap))
        showImportMapModal.value = true
    } catch (err) {
        importError.value = err?.message || 'No se pudo leer el JSON.'
    } finally {
        e.target.value = ''
    }
}

async function confirmImportWithMapping() {
    const file = pendingImportFile.value
    if (!file) return
    const raw = importMapping.value || {}
    const map = {}
    for (const f of IMPORT_FIELDS) {
        const v = raw[f.key]
        if (v) map[f.key] = v
    }
    if (!map.source) {
        importError.value = 'Debe elegir qué propiedad del JSON es el código fuente (Código fuente).'
        return
    }
    importing.value = true
    importError.value = ''

    const formData = new FormData()
    formData.append('json_file', file)
    formData.append('mapping', JSON.stringify(map))
    if (importCanalFilter.value && String(importCanalFilter.value).trim() !== '') {
        formData.append('canal_filter', String(importCanalFilter.value).trim())
    }
    formData.append('_token', page.props.csrf_token)

    const canalElegido = importCanalFilter.value

    try {
        const { data } = await axios.post(route('codigos-qr.import-json'), formData, {
            headers: { Accept: 'application/json' },
        })
        if (data.success) {
            try {
                localStorage.setItem(IMPORT_CANAL_STORAGE_KEY, canalElegido ? String(canalElegido).trim() : '')
            } catch { /* */ }
            closeImportMapModal()
            let msg = `Importación: ${data.created} nuevas, ${data.updated} actualizadas (${data.total} filas en el archivo).`
            if (data.skipped > 0) msg += ` Sin código fuente (omitidas): ${data.skipped}.`
            if ((data.skipped_canal ?? 0) > 0) msg += ` Omitidas por otro canal (filtro): ${data.skipped_canal}.`
            importMsg.value = msg
            router.reload({ preserveScroll: true })
        } else {
            importError.value = data.error || 'Error al importar.'
        }
    } catch (err) {
        importError.value = err.response?.data?.error || err.response?.data?.message || err.message || 'Error al importar.'
    } finally {
        importing.value = false
    }
}

// ── Eliminar ──────────────────────────────────────────────────────────────────
function confirmDelete(id) {
    if (!confirm('¿Eliminar este Código QR?')) return
    router.delete(route('codigos-qr.destroy', id), { preserveScroll: true })
}

// ── Vinculación manual ────────────────────────────────────────────────────────
const showLinkModal  = ref(false)
const linkSource     = ref(null)
const floorSearch    = ref('')
const floorResults   = ref([])
const linkLoading    = ref(false)
const floorSearching = ref(false)

function openLink(source) {
    linkSource.value    = source
    floorSearch.value   = ''
    floorResults.value  = []
    showLinkModal.value = true
}

let floorTimer = null
watch(floorSearch, (v) => {
    if (!v || v.length < 1) { floorResults.value = []; return }
    clearTimeout(floorTimer)
    floorTimer = setTimeout(() => searchFloors(v), 350)
})

async function searchFloors(q) {
    floorSearching.value = true
    try {
        const { data } = await axios.get(route('codigos-qr.salesFloors'), { params: { q } })
        floorResults.value = data.floors
    } catch { floorResults.value = [] }
    finally { floorSearching.value = false }
}

async function selectFloor(floor) {
    if (!linkSource.value) return
    linkLoading.value = true
    try {
        await axios.put(route('codigos-qr.link', linkSource.value.id), { sales_floor_id: floor?.id ?? null })
        showLinkModal.value = false
        router.reload({ preserveScroll: true })
    } catch { /* */ }
    finally { linkLoading.value = false }
}

// ── Badges ────────────────────────────────────────────────────────────────────
function monedaColor(m) {
    if (m === 'USD') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
    if (m === 'MLC') return 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300'
    return 'bg-gray-100 text-gray-600 dark:bg-gray-500/15 dark:text-gray-300'
}

const totalCount = computed(() => props.sources?.total ?? 0)

</script>

<template>
    <AppLayout title="Códigos QR">
        <PageHeader title="Códigos QR" subtitle="Fuentes de cobro digital"/>

        <!-- Barra superior -->
        <div class="max-w-screen-2xl mx-auto px-4 mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                <span v-if="last_synced">Última importación: <strong class="text-gray-700 dark:text-gray-200">{{ last_synced }}</strong></span>
                <span v-else class="italic">Sin importar todavía</span>
                <span class="ml-3 text-gray-400">({{ totalCount }} códigos)</span>
            </div>
            <div class="flex items-center gap-2">
                <!-- Mensajes de importación -->
                <span v-if="importMsg" class="text-xs text-green-600 dark:text-green-400 max-w-xs truncate" :title="importMsg">{{ importMsg }}</span>
                <span v-if="importError" class="text-xs text-red-500 dark:text-red-400 max-w-xs truncate" :title="importError">{{ importError }}</span>

                <!-- Input oculto para el JSON -->
                <input ref="jsonFileInput" type="file" accept=".json,application/json" class="hidden" @change="onJsonFileSelected"/>

                <!-- Exportar Excel trabajadores / QR -->
                <button type="button" @click="exportTrabajadoresExcel"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-slate-700 hover:bg-slate-800 dark:bg-slate-600 dark:hover:bg-slate-500 text-white text-sm font-medium rounded-lg transition"
                    title="Resumen: QR sin trabajadores y listado consolidado; una hoja por codigo con equipo asignado">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Exportar Excel (QR + trabajadores)
                </button>

                <!-- Botón importar JSON -->
                <button type="button" @click="triggerJsonImport" :disabled="importing"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white text-sm font-medium rounded-lg transition">
                    <svg v-if="!importing" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    {{ importing ? 'Importando...' : 'Importar JSON' }}
                </button>

                <Link :href="route('codigos-qr.create')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nuevo QR
                </Link>
            </div>
        </div>

        <BaseCard class="max-w-screen-2xl mx-auto">
            <!-- Filtros -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Buscar</label>
                    <input v-model="search" type="text" placeholder="Source, nombre, unidad..."
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"/>
                </div>
                <div class="min-w-[160px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Canal</label>
                    <VSelect
                        v-model="fCanal"
                        :options="canales.map(c => ({ id: c.id, label: c.nombre }))"
                        :reduce="opt => opt.id"
                        placeholder="Todos"
                        :clearable="true"
                        @update:modelValue="applyFilters()"
                    />
                </div>
                <div class="min-w-[160px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tipo</label>
                    <VSelect
                        v-model="fTipo"
                        :options="tipos.map(t => ({ id: t.id, label: t.nombre }))"
                        :reduce="opt => opt.id"
                        placeholder="Todos"
                        :clearable="true"
                        @update:modelValue="applyFilters()"
                    />
                </div>
                <div class="min-w-[110px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Moneda</label>
                    <VSelect
                        v-model="fMoneda"
                        :options="monedaOptions"
                        :reduce="opt => opt.id"
                        placeholder="Todas"
                        :clearable="true"
                        :searchable="false"
                        @update:modelValue="applyFilters()"
                    />
                </div>
                <div class="flex items-center gap-2 pb-0.5">
                    <input id="soloActivos" v-model="soloActivos" type="checkbox" @change="applyFilters()"
                        class="w-4 h-4 text-blue-600 rounded border-gray-300 dark:border-gray-600"/>
                    <label for="soloActivos" class="text-sm text-gray-700 dark:text-gray-300 select-none cursor-pointer">Solo activos</label>
                </div>
                <button @click="resetFilters"
                    class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Limpiar
                </button>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Unidad</th>
                            <th class="px-4 py-3">Canal</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Moneda</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Código Golden</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-if="!sources?.data?.length">
                            <td colspan="9" class="px-4 py-12 text-center text-gray-400 dark:text-gray-500">
                                <p>No hay Códigos QR registrados.</p>
                                <p class="text-xs mt-1">Use «Importar JSON» para cargar el listado exportado, o cree uno manualmente.</p>
                            </td>
                        </tr>
                        <tr v-for="s in sources?.data" :key="s.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3">
                                <code class="text-xs font-mono bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded text-gray-800 dark:text-gray-200">{{ s.source }}</code>
                                <span v-if="s.synced_at" class="ml-1 text-blue-400" title="Sincronizado desde API">
                                    <svg class="w-3 h-3 inline" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/></svg>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200 max-w-[160px]">
                                <span class="line-clamp-1" :title="s.source_name">{{ s.source_name || '—' }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs max-w-[180px]">
                                <span class="line-clamp-1" :title="s.sales_floor?.name">{{ s.sales_floor?.name || '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="s.canal_electronico" class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">
                                    {{ s.canal_electronico.nombre }}
                                </span>
                                <span v-else class="text-gray-400 text-xs">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="s.tipo_fuente" class="px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-500/15 dark:text-purple-300">
                                    {{ s.tipo_fuente.nombre }}
                                </span>
                                <span v-else class="text-gray-400 text-xs">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="s.moneda" :class="['px-2 py-0.5 rounded text-xs font-medium', monedaColor(s.moneda)]">{{ s.moneda }}</span>
                                <span v-else class="text-gray-400 text-xs">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="s.activo" class="inline-flex items-center gap-1 text-xs text-green-700 dark:text-green-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Activo
                                </span>
                                <span v-else class="inline-flex items-center gap-1 text-xs text-gray-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-300 inline-block"></span> Inactivo
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="s.sales_floor" class="text-xs font-mono text-slate-700 dark:text-slate-200">{{ s.sales_floor.codigo_golden || '—' }}</span>
                                <span v-else class="text-xs text-red-400 italic">Sin piso</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                                <!-- Trabajadores -->
                                <Link :href="route('codigos-qr.trabajadores.index', s.id)"
                                    class="inline-flex items-center text-xs px-2 py-1.5 text-purple-600 hover:text-purple-800 dark:text-purple-400 border border-purple-200 dark:border-purple-800 hover:bg-purple-50 dark:hover:bg-purple-900/30 rounded-lg transition" title="Trabajadores">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </Link>
                                <!-- Vincular piso -->
                                <button @click="openLink(s)"
                                    class="text-xs px-2 py-1.5 text-blue-600 hover:text-blue-800 dark:text-blue-400 border border-blue-200 dark:border-blue-800 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition" title="Vincular piso de venta">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                </button>
                                <!-- Editar -->
                                <Link :href="route('codigos-qr.edit', s.id)"
                                    class="inline-flex items-center text-xs px-2 py-1.5 text-gray-600 hover:text-gray-800 dark:text-gray-400 border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition" title="Editar">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </Link>
                                <!-- Eliminar -->
                                <button @click="confirmDelete(s.id)"
                                    class="text-xs px-2 py-1.5 text-red-500 hover:text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition" title="Eliminar">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="sources?.links?.length > 3" class="flex flex-col gap-3 border-t border-slate-200/80 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-700/60">
                <p class="text-sm text-slate-500 dark:text-slate-400">Mostrando {{ sources.from }} a {{ sources.to }} de {{ sources.total }} códigos</p>
                <div class="flex flex-wrap items-center gap-2">
                    <template v-for="link in sources.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" preserve-state preserve-scroll v-html="link.label"
                            :class="link.active ? 'bg-slate-900 text-white dark:bg-cyan-400 dark:text-slate-950' : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'"
                            class="inline-flex min-w-[2.5rem] items-center justify-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium transition dark:border-slate-700"/>
                        <span v-else v-html="link.label" class="inline-flex min-w-[2.5rem] items-center justify-center px-2 text-sm text-slate-400"/>
                    </template>
                </div>
            </div>
        </BaseCard>

        <!-- Modal vinculación de Piso -->
        <Teleport to="body">
            <div v-if="showLinkModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="showLinkModal = false"/>
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Vincular Piso de Venta</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        <code class="bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded text-xs">{{ linkSource?.source }}</code>
                        <span v-if="linkSource?.source_name"> — {{ linkSource.source_name }}</span>
                    </p>
                    <div v-if="linkSource?.sales_floor" class="mb-3 p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Vinculado: </span>
                        <strong class="text-blue-700 dark:text-blue-300">{{ linkSource.sales_floor.name }}</strong>
                    </div>
                    <input v-model="floorSearch" type="text" placeholder="Buscar piso por nombre o ID Piso..." autofocus
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 mb-3"/>
                    <div class="max-h-56 overflow-y-auto space-y-1">
                        <div v-if="floorSearching" class="text-center py-4 text-gray-400 text-sm">Buscando...</div>
                        <button v-for="f in floorResults" :key="f.id" @click="selectFloor(f)" :disabled="linkLoading"
                            class="w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 text-gray-800 dark:text-gray-200 transition">
                            <span class="font-medium block leading-snug">{{ f.label || f.name }}</span>
                        </button>
                        <p v-if="!floorSearching && floorSearch && !floorResults.length" class="text-center py-3 text-gray-400 text-sm">Sin resultados</p>
                    </div>
                    <div v-if="linkSource?.sales_floor" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <button @click="selectFloor(null)" :disabled="linkLoading" class="text-sm text-red-600 hover:text-red-800 dark:text-red-400">
                            Quitar vinculación
                        </button>
                    </div>
                    <button @click="showLinkModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </Teleport>

        <!-- Modal mapeo JSON → columnas SGI -->
        <Teleport to="body">
            <div v-if="showImportMapModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="closeImportMapModal"/>
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Mapeo de columnas</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Indique qué propiedad del JSON corresponde a cada campo que se guardará. Puede usar otra clave (p. ej. «Nombre» en <code class="text-xs bg-gray-100 dark:bg-gray-900 px-1 rounded">UnidadNombre</code> o <code class="text-xs bg-gray-100 dark:bg-gray-900 px-1 rounded">Almacen</code> para el nombre visible).
                            </p>
                        </div>
                        <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 shrink-0" @click="closeImportMapModal">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="px-5 py-3 overflow-y-auto flex-1 space-y-4">
                        <p v-if="importError" class="text-sm text-red-600 dark:text-red-400 rounded-lg bg-red-50 dark:bg-red-900/20 px-3 py-2">{{ importError }}</p>
                        <div class="rounded-lg border border-amber-200 dark:border-amber-900/50 bg-amber-50/80 dark:bg-amber-900/20 px-3 py-3">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Canal a importar</label>
                            <select
                                v-model="importCanalFilter"
                                class="w-full sm:max-w-md text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-2 py-2"
                            >
                                <option value="">Todos los canales</option>
                                <option v-for="c in importCanalOptions" :key="c" :value="c">{{ c }}</option>
                            </select>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1.5">
                                Usa el campo mapeado como «Nombre canal». Por defecto se sugiere Transfermóvil si existe en el archivo. Deje «Todos» para importar ENZONA, POST, etc.
                            </p>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <div
                                v-for="f in IMPORT_FIELDS"
                                :key="f.key"
                                class="space-y-1"
                            >
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">{{ f.label }}</label>
                                <select
                                    v-model="importMapping[f.key]"
                                    class="w-full text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-2 py-2"
                                >
                                    <option value="">— (no importar / vacío)</option>
                                    <option v-for="k in importJsonKeys" :key="`${f.key}-${k}`" :value="k">{{ k }}</option>
                                </select>
                                <p v-if="f.hint" class="text-[11px] text-gray-400">{{ f.hint }}</p>
                            </div>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 bg-gray-50 dark:bg-gray-900/40">
                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2">Vista previa (primera fila del archivo)</p>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                <template v-for="(cell, idx) in importPreviewCells" :key="idx">
                                    <dt class="text-gray-500 truncate">{{ cell.label }}</dt>
                                    <dd class="font-mono text-gray-800 dark:text-gray-200 break-all">{{ cell.value }}</dd>
                                </template>
                            </dl>
                        </div>
                    </div>
                    <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-700 flex flex-wrap justify-end gap-2">
                        <button
                            type="button"
                            class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                            @click="closeImportMapModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 text-sm rounded-lg bg-blue-600 hover:bg-blue-700 text-white disabled:opacity-50"
                            :disabled="importing || !importMapping.source"
                            @click="confirmImportWithMapping"
                        >
                            Importar con este mapeo
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
