<script setup>
/**
 * Importacion masiva desde Excel de levantamiento de equipos (vista previa + aplicar).
 */
import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'

const page = usePage()

function formatImportAxiosError(err) {
    const d = err.response?.data
    if (typeof d === 'string' && d.length > 0 && d.length < 600) {
        return d.replace(/<[^>]+>/g, ' ').trim().slice(0, 500)
    }
    if (d && typeof d === 'object' && d.message) {
        return d.message
    }
    if (d && typeof d === 'object' && d.errors) {
        const first = Object.values(d.errors).flat()[0]
        if (first) return first
    }
    const status = err.response?.status
    if (status === 419) {
        return 'Sesion expirada. Recargue la pagina e intente de nuevo.'
    }
    if (status === 413) {
        return 'El archivo supera el tamano maximo permitido (10 MB).'
    }
    if (status === 403) {
        return 'No tiene permiso para importar expedientes.'
    }
    if (status === 404) {
        return 'No se encontro la ruta de importacion en el servidor. Actualice despliegue y cache de rutas.'
    }
    if (err.code === 'ERR_NETWORK' || err.message === 'Network Error') {
        return 'Error de red. Compruebe la conexion.'
    }
    return 'No se pudo procesar el archivo.'
}

const props = defineProps({
    show: { type: Boolean, default: false },
    entities: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'imported'])

const fileInput = ref(null)
const loadingPreview = ref(false)
const loadingApply = ref(false)
const previewError = ref(null)
const applyMessage = ref(null)
const sheetName = ref('')
const summary = ref(null)
const records = ref([])
const departmentsByEntity = ref({})

const defaults = ref({
    type: 'PC',
    status: '',
    repairable: 'Si',
    responsible_name: 'Importacion levantamiento Excel',
})

watch(
    () => props.show,
    (open) => {
        if (open) {
            previewError.value = null
            applyMessage.value = null
            if (!defaults.value.status && props.statuses?.length) {
                defaults.value.status = props.statuses[0].name
            }
        }
    },
)

watch(
    () => props.statuses,
    (s) => {
        if (s?.length && !defaults.value.status) {
            defaults.value.status = s[0].name
        }
    },
    { immediate: true },
)

const importable = (rec) => {
    if (!rec.payload) return false
    if (
        rec.match_status === 'invalid'
        || rec.match_status === 'duplicate'
        || rec.match_status === 'not_in_rodas'
        || rec.match_status === 'rodas_off'
    ) {
        return false
    }

    return true
}

const selectedCount = computed(() => records.value.filter((r) => r._selected && importable(r)).length)

const statusLabel = (s) => {
    const map = {
        matched: 'OK',
        not_in_rodas: 'No en RODAS',
        rodas_off: 'RODAS off',
        no_department: 'Sin depto.',
        duplicate: 'Duplicado',
        invalid: 'Invalido',
        no_access: 'Sin acceso',
    }

    return map[s] || s
}

const statusClass = (s) => {
    const map = {
        matched: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
        not_in_rodas: 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200',
        rodas_off: 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
        no_department: 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200',
        duplicate: 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
        invalid: 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
        no_access: 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
    }

    return map[s] || 'bg-slate-100 text-slate-700'
}

const triggerFile = () => fileInput.value?.click()

const onFileChange = async (e) => {
    const file = e.target.files?.[0]
    if (!file) return

    loadingPreview.value = true
    previewError.value = null
    applyMessage.value = null
    records.value = []
    summary.value = null
    sheetName.value = ''

    try {
        const fd = new FormData()
        fd.append('excel_file', file)
        const token = page.props.csrf_token
        if (token) {
            fd.append('_token', token)
        }
        const { data } = await axios.post(route('expedientes.levantamiento-preview'), fd, {
            headers: {
                'Content-Type': 'multipart/form-data',
                Accept: 'application/json',
            },
        })
        if (!data.success) {
            previewError.value = data.message || 'Error en vista previa.'
            return
        }
        sheetName.value = data.sheet_name || ''
        summary.value = data.summary || {}
        records.value = (data.records || []).map((r) => ({
            ...r,
            _selected: r.match_status === 'matched',
            _entity_id: r.entity_id ? String(r.entity_id) : '',
            _department_id: r.department_id ? String(r.department_id) : '',
        }))
        for (const r of records.value) {
            if (r._entity_id) {
                await ensureDepartments(r._entity_id)
            }
        }
    } catch (err) {
        previewError.value = formatImportAxiosError(err)
    } finally {
        loadingPreview.value = false
        if (fileInput.value) fileInput.value.value = ''
    }
}

const ensureDepartments = async (entityId) => {
    if (!entityId || departmentsByEntity.value[entityId]) return
    try {
        const { data } = await axios.get(`/departamentos/por-entidad/${entityId}`)
        departmentsByEntity.value = { ...departmentsByEntity.value, [entityId]: data || [] }
    } catch {
        departmentsByEntity.value = { ...departmentsByEntity.value, [entityId]: [] }
    }
}

const onEntityChange = async (rec) => {
    rec._department_id = ''
    if (rec._entity_id) {
        await ensureDepartments(rec._entity_id)
    }
}

const buildApplyPayload = () => {
    const items = []
    const resp = defaults.value.responsible_name.trim()
    if (!resp) {
        throw new Error('Indique el nombre del responsable por defecto.')
    }
    if (!defaults.value.status) {
        throw new Error('Seleccione el estado del expediente.')
    }

    for (const r of records.value) {
        if (!r._selected || !importable(r)) continue

        const eid = parseInt(r._entity_id || r.entity_id, 10)
        const did = parseInt(r._department_id || r.department_id, 10)
        if (!eid || !did) continue

        items.push({
            entity_id: eid,
            department_id: did,
            inventory_number: r.payload.inventory_number,
            station_name: r.payload.station_name || null,
            operating_system: r.payload.operating_system || null,
            chassis: r.payload.chassis || null,
            caracteristicas: r.payload.caracteristicas || [],
        })
    }

    if (!items.length) {
        throw new Error('Seleccione al menos una fila con entidad y departamento completos.')
    }

    return {
        defaults: {
            type: defaults.value.type,
            status: defaults.value.status,
            repairable: defaults.value.repairable,
            responsibles: [
                {
                    display_name: resp,
                    source: 'manual',
                    samaccountname: null,
                    mail: null,
                    trabajador_id: null,
                },
            ],
        },
        items,
    }
}

const applyImport = async () => {
    applyMessage.value = null
    let payload
    try {
        payload = buildApplyPayload()
    } catch (e) {
        applyMessage.value = { type: 'error', text: e.message || 'Revise el formulario.' }
        return
    }

    loadingApply.value = true
    try {
        const { data } = await axios.post(route('expedientes.levantamiento-aplicar'), payload, {
            headers: { Accept: 'application/json' },
        })
        applyMessage.value = {
            type: data.created > 0 ? 'ok' : 'warn',
            text: data.message || 'Listo.',
            failed: data.failed || [],
            created: data.created,
        }
        if (data.created > 0) {
            emit('imported')
        }
    } catch (err) {
        applyMessage.value = {
            type: 'error',
            text: err.response?.data?.message || err.response?.data?.errors?.defaults?.[0] || 'Error al importar.',
        }
    } finally {
        loadingApply.value = false
    }
}

const selectAllMatched = () => {
    for (const r of records.value) {
        if (importable(r)) {
            r._selected = r.match_status === 'matched'
        }
    }
}

const close = () => {
    emit('close')
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-8">
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="close" />

            <div class="surface-card relative z-10 mb-12 w-full max-w-6xl p-6">
                <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-950 dark:text-slate-100">Importar levantamiento (Excel)</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            La entidad y el departamento se asignan solo con el inventario (MB) consultado en RODAS; el codigo de entidad en el Excel (p. ej. columna D) no se usa para eso, pero el resto de columnas de hardware del archivo se importan con el expediente.
                        </p>
                        <p v-if="sheetName" class="mt-0.5 text-xs text-slate-400">Hoja leida: {{ sheetName }}</p>
                    </div>
                    <button type="button" class="app-button-secondary px-3 py-2 text-xs" @click="close">Cerrar</button>
                </div>

                <div class="mb-5 flex flex-wrap items-end gap-4 rounded-2xl border border-dashed border-brand-300/60 bg-brand-50/50 px-4 py-3 dark:border-brand-500/25 dark:bg-brand-500/10">
                    <input ref="fileInput" type="file" accept=".xlsx,.xls" class="hidden" @change="onFileChange" />
                    <button type="button" :disabled="loadingPreview" class="app-button-secondary text-sm" @click="triggerFile">
                        {{ loadingPreview ? 'Leyendo...' : 'Elegir Excel' }}
                    </button>

                    <div class="flex flex-wrap gap-3">
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-300">
                            Tipo
                            <select v-model="defaults.type" class="app-select mt-1 block text-sm">
                                <option value="PC">PC</option>
                                <option value="Laptop">Laptop</option>
                            </select>
                        </label>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-300">
                            Estado
                            <select v-model="defaults.status" class="app-select mt-1 block min-w-[8rem] text-sm">
                                <option v-for="s in statuses" :key="s.id" :value="s.name">{{ s.name }}</option>
                            </select>
                        </label>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-300">
                            Reparable
                            <select v-model="defaults.repairable" class="app-select mt-1 block text-sm">
                                <option value="Si">Si</option>
                                <option value="No">No</option>
                            </select>
                        </label>
                        <label class="min-w-[12rem] text-xs font-medium text-slate-600 dark:text-slate-300">
                            Responsable (todas las filas)
                            <input v-model="defaults.responsible_name" type="text" class="app-input mt-1 block text-sm" placeholder="Nombre visible" />
                        </label>
                    </div>
                </div>

                <p v-if="previewError" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">
                    {{ previewError }}
                </p>

                <div v-if="summary" class="mb-4 flex flex-wrap gap-3 text-xs text-slate-600 dark:text-slate-400">
                    <span>Total: <strong>{{ summary.total }}</strong></span>
                    <span>Listos: <strong>{{ summary.matched }}</strong></span>
                    <span>No en RODAS: {{ summary.not_in_rodas ?? 0 }}</span>
                    <span>RODAS no disponible: {{ summary.rodas_off ?? 0 }}</span>
                    <span>Sin depto.: {{ summary.no_department }}</span>
                    <span>Duplicados: {{ summary.duplicate }}</span>
                    <span>Sin acceso: {{ summary.no_access }}</span>
                </div>

                <div v-if="records.length" class="overflow-hidden rounded-xl border border-slate-200/80 dark:border-slate-700/70">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 bg-slate-50/80 px-3 py-2 dark:border-slate-700/70 dark:bg-slate-900/60">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Vista previa ({{ selectedCount }} seleccionadas)
                        </p>
                        <button type="button" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400" @click="selectAllMatched">
                            Seleccionar filas OK
                        </button>
                    </div>
                    <div class="max-h-[min(28rem,55vh)] overflow-auto">
                        <table class="w-full min-w-[56rem] text-sm">
                            <thead class="sticky top-0 bg-white dark:bg-slate-900">
                                <tr class="border-b border-slate-200 text-left text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                    <th class="px-2 py-2"></th>
                                    <th class="px-2 py-2">Fila</th>
                                    <th class="px-2 py-2">Inv.</th>
                                    <th class="px-2 py-2 max-w-[7rem]" title="Valor del Excel; no define la entidad en RODAS">Cod. Excel</th>
                                    <th class="px-2 py-2">Estado</th>
                                    <th class="px-2 py-2">Entidad</th>
                                    <th class="px-2 py-2">Departamento</th>
                                    <th class="px-2 py-2">Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="rec in records"
                                    :key="rec.index"
                                    class="border-b border-slate-100 dark:border-slate-800"
                                    :class="!importable(rec) ? 'opacity-50' : ''"
                                >
                                    <td class="px-2 py-2 align-top">
                                        <input
                                            v-model="rec._selected"
                                            type="checkbox"
                                            class="h-4 w-4 accent-brand-500"
                                            :disabled="!importable(rec)"
                                        />
                                    </td>
                                    <td class="px-2 py-2 align-top font-mono text-xs text-slate-500">{{ rec.sheet_row }}</td>
                                    <td class="px-2 py-2 align-top font-mono text-xs">{{ rec.payload?.inventory_number }}</td>
                                    <td class="max-w-[7rem] px-2 py-2 align-top font-mono text-xs text-slate-500" :title="rec.excel_codigo_entidad || ''">
                                        {{ rec.excel_codigo_entidad || '—' }}
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <span class="inline-flex rounded-lg px-2 py-0.5 text-xs font-medium" :class="statusClass(rec.match_status)">
                                            {{ statusLabel(rec.match_status) }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <template v-if="importable(rec)">
                                            <select
                                                v-model="rec._entity_id"
                                                class="app-select max-w-[11rem] py-1 text-xs"
                                                @change="onEntityChange(rec)"
                                            >
                                                <option value="">� Entidad �</option>
                                                <option v-for="e in entities" :key="e.id" :value="String(e.id)">{{ e.name }} ({{ e.code }})</option>
                                            </select>
                                        </template>
                                        <template v-else>
                                            <span class="text-xs text-slate-500">{{ rec.entity_name || '�' }}</span>
                                        </template>
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <template v-if="importable(rec)">
                                            <select v-model="rec._department_id" class="app-select max-w-[11rem] py-1 text-xs" :disabled="!rec._entity_id">
                                                <option value="">� Depto. �</option>
                                                <option
                                                    v-for="d in departmentsByEntity[rec._entity_id] || []"
                                                    :key="d.id"
                                                    :value="String(d.id)"
                                                >
                                                    {{ d.name }}
                                                </option>
                                            </select>
                                        </template>
                                        <template v-else>
                                            <span class="text-xs text-slate-500">{{ rec.department_name || '�' }}</span>
                                        </template>
                                    </td>
                                    <td class="max-w-[14rem] px-2 py-2 align-top text-xs text-amber-700 dark:text-amber-300">
                                        <span v-for="(err, ei) in rec.errors || []" :key="ei">{{ err }} </span>
                                        <span v-if="rec.match_status === 'matched'" class="text-slate-400">�</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p v-if="applyMessage" class="mt-4 rounded-xl border px-4 py-3 text-sm" :class="applyMessage.type === 'ok' ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200' : applyMessage.type === 'error' ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-500/25 dark:bg-red-500/10 dark:text-red-300' : 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-200'">
                    {{ applyMessage.text }}
                    <template v-if="applyMessage.failed?.length">
                        <ul class="mt-2 list-inside list-disc text-xs">
                            <li v-for="(f, fi) in applyMessage.failed" :key="fi">
                                Inv. {{ f.inventory_number }}: {{ f.message }}
                            </li>
                        </ul>
                    </template>
                </p>

                <div class="mt-6 flex justify-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800">
                    <button type="button" class="app-button-secondary" @click="close">Cancelar</button>
                    <button
                        type="button"
                        class="app-button-primary"
                        :disabled="loadingApply || !records.length || selectedCount === 0"
                        @click="applyImport"
                    >
                        {{ loadingApply ? 'Importando...' : 'Importar seleccionadas' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
