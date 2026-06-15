<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
    /** Registros con match_status === 'unmatched' */
    records: { type: Array, required: true },
    /** Texto que identifica cada registro (ej: 'floor_name' o 'unit_name') */
    nameKey: { type: String, default: 'floor_name' },
    /** Texto descriptivo del tipo de match */
    label: { type: String, default: 'piso de venta' },
})

const emit = defineEmits(['resolved', 'cancel'])

// Estado de resolución por índice
const resolutions = ref(
    props.records.map(rec => ({
        mode: null, // 'existing' | 'create'
        selectedFloor: null,
        searchOptions: rec.available_floors?.map(f => ({ id: f.id, label: f.name })) || [],
    }))
)

const allResolved = computed(() =>
    resolutions.value.every(r => r.mode === 'existing' && r.selectedFloor || r.mode === 'create')
)

async function searchFloors(query, loading, idx) {
    if (!query || query.length < 1) return
    loading(true)
    try {
        const params = { q: query, entity_code_labels: 1 }
        const { data } = await axios.get('/pisos-venta/buscar', { params })
        resolutions.value[idx].searchOptions = (data.floors || []).map(f => ({ id: f.id, label: f.label || f.name }))
    } catch {
        resolutions.value[idx].searchOptions = []
    } finally {
        loading(false)
    }
}

function onSelectFloor(val, idx) {
    resolutions.value[idx].selectedFloor = val
    if (val) resolutions.value[idx].mode = 'existing'
}

function setCreate(idx) {
    resolutions.value[idx].mode = 'create'
    resolutions.value[idx].selectedFloor = null
}

function setExisting(idx) {
    resolutions.value[idx].mode = 'existing'
}

function setCreateAll() {
    resolutions.value = resolutions.value.map(r => ({
        ...r,
        mode: 'create',
        selectedFloor: null,
    }))
}

function apply() {
    const result = props.records.map((rec, idx) => {
        const r = resolutions.value[idx]
        if (r.mode === 'create') {
            return { ...rec, create_new: true, sales_floor_id: null }
        }
        if (r.mode === 'existing' && r.selectedFloor) {
            return { ...rec, sales_floor_id: r.selectedFloor.id, create_new: false }
        }
        return null
    }).filter(Boolean)

    emit('resolved', result)
}
</script>

<template>
    <div class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto px-4 py-8">
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="emit('cancel')" />
        <div class="surface-card relative z-10 w-full max-w-4xl p-6">
            <h3 class="mb-2 text-lg font-semibold text-slate-950 dark:text-slate-100">
                Resolver registros sin coincidencia
            </h3>
            <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">
                Los siguientes registros del Excel no coinciden con ningún {{ label }} existente.
                Para cada uno, seleccione uno existente o cree uno nuevo.
            </p>
            <div class="mb-4 flex justify-end">
                <button
                    type="button"
                    class="rounded-lg bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300 dark:hover:bg-emerald-900/60"
                    @click="setCreateAll"
                >
                    Crear todos como nuevos
                </button>
            </div>

            <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                <div
                    v-for="(rec, idx) in records"
                    :key="idx"
                    class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                >
                    <div class="mb-3 flex items-start justify-between gap-4">
                        <div>
                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ rec[nameKey] }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                <span v-if="rec.entity_code" class="font-mono text-indigo-600 dark:text-indigo-400">{{ rec.entity_code }}</span>
                                <span v-if="rec.entity_name" class="ml-1">{{ rec.entity_name }}</span>
                                <span v-if="rec.municipio_excel" class="ml-1">· {{ rec.municipio_excel }}</span>
                                <span v-if="rec.address" class="ml-1">· {{ rec.address }}</span>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="text-xs px-3 py-1.5 rounded-lg transition-colors"
                                :class="resolutions[idx].mode === 'existing'
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400'"
                                @click="setExisting(idx)"
                            >
                                Asociar existente
                            </button>
                            <button
                                type="button"
                                class="text-xs px-3 py-1.5 rounded-lg transition-colors"
                                :class="resolutions[idx].mode === 'create'
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400'"
                                @click="setCreate(idx)"
                            >
                                Crear nuevo
                            </button>
                        </div>
                    </div>

                    <!-- Selector de piso existente -->
                    <div v-if="resolutions[idx].mode === 'existing'" class="mt-2">
                        <VSelect
                            :modelValue="resolutions[idx].selectedFloor"
                            :options="resolutions[idx].searchOptions"
                            :filterable="false"
                            :append-to-body="true"
                            placeholder="Buscar piso de venta..."
                            @search="(q, loading) => searchFloors(q, loading, idx)"
                            @update:modelValue="val => onSelectFloor(val, idx)"
                        >
                            <template #no-options="{ search: q, searching }">
                                <span v-if="searching" class="text-sm text-slate-400">Sin resultados para "{{ q }}"</span>
                                <span v-else class="text-sm text-slate-400">Escriba para buscar…</span>
                            </template>
                        </VSelect>
                    </div>

                    <!-- Confirmación de crear nuevo -->
                    <div v-if="resolutions[idx].mode === 'create'" class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">
                        Se creará un nuevo {{ label }}: <strong>{{ rec[nameKey] }}</strong>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800">
                <button type="button" class="app-button-secondary" @click="emit('cancel')">Cancelar</button>
                <button
                    type="button"
                    class="app-button-primary"
                    :disabled="!allResolved"
                    @click="apply"
                >
                    Aplicar resoluciones ({{ records.length }})
                </button>
            </div>
        </div>
    </div>
</template>
