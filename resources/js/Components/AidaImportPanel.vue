<script setup>
/**
 * Panel interactivo para importar informes AIDA64.
 * Muestra los datos detectados en una tabla donde el usuario puede:
 *   - Ver qué se detectó
 *   - Elegir a qué campo del expediente se aplica
 *   - Marcar/desmarcar qué se aplica
 * Al confirmar, emite "apply" con el payload listo para fusionar con el form.
 */
import { ref, computed } from 'vue'
import axios from 'axios'

const emit = defineEmits(['apply'])

// ── Estado ────────────────────────────────────────────────────────────────────
const loading   = ref(false)
const error     = ref(null)
const parsed    = ref(null)   // respuesta JSON del parser
const rows      = ref([])     // filas del panel interactivo
const showPanel = ref(false)
const fileInput = ref(null)

// ── Destinos disponibles ──────────────────────────────────────────────────────
const DESTINATIONS = [
    { value: '__skip__',                       label: '— No aplicar —' },
    { group: 'Expediente' },
    { value: 'equipment.type',                 label: 'Tipo (PC/Laptop)' },
    { value: 'equipment.chassis',              label: 'Chasis' },
    { value: 'equipment.ip_address',           label: 'Dirección IP' },
    { value: 'equipment.station_name',         label: 'Nombre de estación' },
    { value: 'equipment.operating_system',     label: 'Sistema operativo' },
    { group: 'Tarjeta Madre' },
    { value: 'components.motherboard.brand',   label: 'Marca' },
    { value: 'components.motherboard.model',   label: 'Modelo' },
    { value: 'components.motherboard.serial_number', label: 'Número de serie' },
    { group: 'CPU' },
    { value: 'components.cpu.brand',           label: 'Marca' },
    { value: 'components.cpu.model',           label: 'Modelo' },
    { group: 'RAM' },
    { value: 'components.ram.model',           label: 'Modelo / Descripción' },
    { group: 'Disco Duro' },
    { value: 'components.hdd.model',           label: 'Modelo / Descripción' },
    { group: 'Fuente de Alimentación' },
    { value: 'components.power_supply.model',  label: 'Modelo' },
]

// ── Lógica de importación ─────────────────────────────────────────────────────
const triggerFile = () => fileInput.value?.click()

const onFileChange = async (e) => {
    const file = e.target.files[0]
    if (!file) return

    loading.value = true
    error.value   = null
    parsed.value  = null

    try {
        const fd = new FormData()
        fd.append('report', file)
        const { data } = await axios.post('/expedientes/aida-parse', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        parsed.value  = data
        rows.value    = buildRows(data)
        showPanel.value = true
    } catch (err) {
        error.value = err.response?.data?.message
            || err.response?.data?.error
            || 'No se pudo procesar el archivo. Verifique que es un informe AIDA64 válido.'
    } finally {
        loading.value = false
        // Limpiar input para permitir volver a cargar el mismo archivo
        if (fileInput.value) fileInput.value.value = ''
    }
}

// Construir las filas a partir del JSON del parser
const buildRows = (data) => {
    const result = []

    const add = (label, value, destination, apply = true) => {
        if (!value) return
        result.push({ label, value: String(value), destination, apply })
    }

    // Expediente
    add('Tipo de equipo',       data.equipment?.type,    'equipment.type')
    add('Chasis / Placa',       data.equipment?.chassis, 'equipment.chassis')
    add('Dirección IP',         data.meta?.ip,           'equipment.ip_address')
    add('Nombre de estación',   data.meta?.computer_name,'equipment.station_name')
    add('Sistema operativo',    data.meta?.os,           'equipment.operating_system')

    // Componentes
    const c = data.components || {}
    add('Placa Madre — Marca',        c.motherboard?.brand,         'components.motherboard.brand')
    add('Placa Madre — Modelo',       c.motherboard?.model,         'components.motherboard.model')
    add('Placa Madre — Serie',        c.motherboard?.serial_number, 'components.motherboard.serial_number')
    add('CPU — Marca',                c.cpu?.brand,                 'components.cpu.brand')
    add('CPU — Modelo',               c.cpu?.model,                 'components.cpu.model')
    add('RAM — Descripción',          c.ram?.model,                 'components.ram.model')
    add('Disco Duro — Modelo',        c.hdd?.model,                 'components.hdd.model')

    return result
}

// Periféricos como grupo separado con checkbox individual
const perifericoRows = computed(() =>
    (parsed.value?.perifericos || []).map((p, i) => ({
        index: i,
        label: labelForType(p.component_type_slug),
        brand: p.brand,
        model: p.model,
        serial_number: p.serial_number,
        component_type_slug: p.component_type_slug,
        apply: true,
    }))
)

const perifericoApply = ref([])
const initPerifericoApply = () => {
    perifericoApply.value = (parsed.value?.perifericos || []).map(() => true)
}

// Observar cuando cambia parsed para inicializar los checks de periféricos
const onParsedReady = () => {
    initPerifericoApply()
}

const labelForType = (slug) => ({
    monitor:  'Monitor',
    keyboard: 'Teclado',
    mouse:    'Mouse',
    printer:  'Impresora',
    scanner:  'Scanner',
    speakers: 'Bocinas',
    backup:   'Backup',
}[slug] ?? slug)

// ── Aplicar al formulario ─────────────────────────────────────────────────────
const applyToForm = () => {
    const payload = {
        equipment:    {},
        components:   {},
        perifericos:  [],
    }

    // Campos escalar del formulario
    for (const row of rows.value) {
        if (!row.apply || row.destination === '__skip__' || !row.value) continue

        const parts = row.destination.split('.')
        if (parts[0] === 'equipment') {
            payload.equipment[parts[1]] = row.value
        } else if (parts[0] === 'components') {
            const [, type, field] = parts
            if (!payload.components[type]) payload.components[type] = {}
            payload.components[type][field] = row.value
        }
    }

    // Periféricos seleccionados
    const perifericos = parsed.value?.perifericos || []
    perifericos.forEach((p, i) => {
        if (perifericoApply.value[i]) {
            payload.perifericos.push({
                component_type_slug: p.component_type_slug,
                brand:          p.brand  || '',
                model:          p.model  || '',
                serial_number:  p.serial_number || '',
                inventory_number: '',
                status: '',
            })
        }
    })

    emit('apply', payload)
    showPanel.value = false
}

// Inicializar apply de periféricos cada vez que se abre el panel
const openPanel = () => {
    initPerifericoApply()
}
</script>

<template>
    <!-- Botón trigger -->
    <div class="flex items-center gap-3 rounded-2xl border border-dashed border-brand-300/70 bg-brand-50/60 px-4 py-3 dark:border-brand-500/30 dark:bg-brand-500/10">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-600 dark:bg-brand-500/20 dark:text-brand-300">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
            </svg>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Importar desde AIDA64</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">Sube el informe TXT para pre-llenar los componentes automáticamente</p>
        </div>
        <button
            type="button"
            :disabled="loading"
            class="app-button-secondary shrink-0 text-xs"
            @click="triggerFile"
        >
            {{ loading ? 'Procesando...' : 'Seleccionar archivo' }}
        </button>
        <input ref="fileInput" type="file" accept=".txt" class="hidden" @change="onFileChange" />
    </div>

    <!-- Error -->
    <p v-if="error" class="mt-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">
        {{ error }}
    </p>

    <!-- Panel de revisión -->
    <Teleport to="body">
        <div v-if="showPanel" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-8">
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showPanel = false" />

            <div class="surface-card relative z-10 w-full max-w-3xl p-6">
                <!-- Cabecera del panel -->
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-950 dark:text-slate-100">Revisión del informe AIDA64</h3>
                        <p v-if="parsed?.meta?.computer_name || parsed?.meta?.os" class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                            {{ parsed?.meta?.computer_name }}
                            <span v-if="parsed?.meta?.os"> · {{ parsed.meta.os }}</span>
                            <span v-if="parsed?.meta?.ip"> · IP: {{ parsed.meta.ip }}</span>
                        </p>
                    </div>
                    <button type="button" class="app-button-secondary px-3 py-2 text-xs" @click="showPanel = false">Cancelar</button>
                </div>

                <!-- Tabla de campos detectados -->
                <div class="mb-4 overflow-hidden rounded-xl border border-slate-200/80 dark:border-slate-700/70">
                    <div class="border-b border-slate-200/80 bg-slate-50/80 px-4 py-2 dark:border-slate-700/70 dark:bg-slate-900/60">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Campos del expediente y componentes</p>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200/80 dark:border-slate-700/70">
                                <th class="w-8 px-4 py-2"></th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400">Dato detectado</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400">Valor</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400">Destino</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, i) in rows"
                                :key="i"
                                :class="['border-b border-slate-200/50 last:border-0 dark:border-slate-700/50 transition', row.apply ? '' : 'opacity-40']"
                            >
                                <td class="px-4 py-2.5 text-center">
                                    <input type="checkbox" v-model="row.apply" class="h-4 w-4 cursor-pointer accent-brand-500" />
                                </td>
                                <td class="px-4 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ row.label }}</td>
                                <td class="max-w-[200px] truncate px-4 py-2.5 font-mono text-xs text-slate-800 dark:text-slate-200" :title="row.value">{{ row.value }}</td>
                                <td class="px-4 py-2.5">
                                    <select v-model="row.destination" class="app-select py-1 text-xs">
                                        <template v-for="dest in DESTINATIONS" :key="dest.value ?? dest.group">
                                            <optgroup v-if="dest.group" :label="dest.group" />
                                            <option v-else :value="dest.value">{{ dest.label }}</option>
                                        </template>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Periféricos detectados -->
                <div v-if="perifericoRows.length" class="mb-4 overflow-hidden rounded-xl border border-slate-200/80 dark:border-slate-700/70">
                    <div class="border-b border-slate-200/80 bg-slate-50/80 px-4 py-2 dark:border-slate-700/70 dark:bg-slate-900/60">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Periféricos detectados</p>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200/80 dark:border-slate-700/70">
                                <th class="w-8 px-4 py-2"></th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400">Tipo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400">Marca</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400">Modelo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400">Serie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, i) in perifericoRows"
                                :key="i"
                                :class="['border-b border-slate-200/50 last:border-0 dark:border-slate-700/50 transition', perifericoApply[i] ? '' : 'opacity-40']"
                            >
                                <td class="px-4 py-2.5 text-center">
                                    <input type="checkbox" v-model="perifericoApply[i]" class="h-4 w-4 cursor-pointer accent-brand-500" />
                                </td>
                                <td class="px-4 py-2.5 text-xs font-medium text-slate-700 dark:text-slate-300">{{ row.label }}</td>
                                <td class="px-4 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ row.brand || '—' }}</td>
                                <td class="max-w-[200px] truncate px-4 py-2.5 text-xs text-slate-800 dark:text-slate-200" :title="row.model">{{ row.model || '—' }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-slate-500 dark:text-slate-400">{{ row.serial_number || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="mb-4 text-xs text-slate-400 dark:text-slate-500">
                    Los cambios se aplican sobre el formulario — puedes seguir editando después. Los campos administrativos (entidad, inventario, responsable) deben completarse manualmente.
                </p>

                <div class="flex justify-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800">
                    <button type="button" class="app-button-secondary" @click="showPanel = false">Cancelar</button>
                    <button type="button" class="app-button-primary" @click="applyToForm">Aplicar seleccionados</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
