<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import BaseCard from '@/Components/BaseCard.vue'

const props = defineProps({
    source:  Object,
    canales: Array,
    tipos:   Array,
    monedas: Array,
})

const form = useForm({
    sales_floor_id:       props.source.sales_floor_id ?? '',
    source:               props.source.source ?? '',
    source_name:          props.source.source_name ?? '',
    canal_electronico_id: props.source.canal_electronico_id ?? '',
    tipo_fuente_id:       props.source.tipo_fuente_id ?? '',
    moneda:               props.source.moneda ?? '',
    activo:               props.source.activo ?? true,
})

// ── Piso de Venta — async VSelect ──────────────────────────────────────────
const floorOptions  = ref([])
const selectedFloor = ref(
    props.source.sales_floor
        ? {
            id:    props.source.sales_floor.id,
            label: (() => {
                const sf = props.source.sales_floor
                const parts = [sf.municipio?.name, sf.entity?.name, sf.name].filter(Boolean)
                const base = parts.length ? parts.join(' · ') : sf.name
                return sf.datacell_piso_id != null ? `${base} (ID Piso: ${sf.datacell_piso_id})` : base
            })(),
          }
        : null
)

async function searchFloors(query, loading) {
    if (!query || query.length < 1) return
    loading(true)
    try {
        const { data } = await axios.get(route('codigos-qr.salesFloors'), { params: { q: query } })
        floorOptions.value = (data.floors || []).map(f => ({
            id: f.id,
            label: f.label || (f.datacell_piso_id ? `${f.name} (ID: ${f.datacell_piso_id})` : f.name),
        }))
    } catch { floorOptions.value = [] }
    finally { loading(false) }
}

function onFloorChange(val) {
    form.sales_floor_id = val ? val.id : ''
}

// ── Opciones estáticas ──────────────────────────────────────────────────────
const canalOptions  = (props.canales || []).map(c => ({ id: c.id, label: c.nombre }))
const tipoOptions   = (props.tipos   || []).map(t => ({ id: t.id, label: t.nombre }))
const monedaOptions = (props.monedas || []).map(m => ({
    id: m.sigla,
    label: m.simbolo ? `${m.sigla} (${m.simbolo})` : m.sigla,
}))
if (!monedaOptions.length) {
    monedaOptions.push({ id: 'CUP', label: 'CUP' })
}

const selectedCanal = ref(
    props.source.canal_electronico_id
        ? canalOptions.find(c => c.id === props.source.canal_electronico_id) ?? null
        : null
)
const selectedTipo = ref(
    props.source.tipo_fuente_id
        ? tipoOptions.find(t => t.id === props.source.tipo_fuente_id) ?? null
        : null
)
const selectedMoneda = ref(
    monedaOptions.find(m => m.id === (props.source.moneda ?? 'CUP')) ?? monedaOptions[0]
)
form.moneda = selectedMoneda.value?.id ?? 'CUP'

function onCanalChange(val)  { form.canal_electronico_id = val ? val.id : '' }
function onTipoChange(val)   { form.tipo_fuente_id       = val ? val.id : '' }
function onMonedaChange(val) { form.moneda               = val ? val.id : (monedaOptions[0]?.id ?? 'CUP') }

function submit() {
    form.put(route('codigos-qr.update', props.source.id))
}

const inputClass = 'w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent'
</script>

<template>
    <AppLayout title="Editar Código QR">
        <PageHeader title="Editar Código QR" :subtitle="source.source">
            <template #actions>
                <Link :href="route('codigos-qr.index')"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Regresar
                </Link>
            </template>
        </PageHeader>

        <!-- Aviso API -->
        <div v-if="source.synced_at" class="max-w-2xl mx-auto mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg text-sm text-blue-700 dark:text-blue-300 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
            QR sincronizado desde la API. Los cambios manuales se preservarán en futuras sincronizaciones.
        </div>

        <form @submit.prevent="submit" class="max-w-2xl mx-auto pb-8">
            <BaseCard>
                <div class="p-5 space-y-4">
                    <!-- Piso de Venta — async search -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Piso de Venta</label>
                        <VSelect
                            v-model="selectedFloor"
                            :options="floorOptions"
                            :filterable="false"
                            placeholder="Buscar piso de venta por nombre..."
                            @search="searchFloors"
                            @update:modelValue="onFloorChange"
                        >
                            <template #no-options="{ search, searching }">
                                <span v-if="searching" class="text-sm text-gray-400">Sin resultados para "{{ search }}"</span>
                                <span v-else class="text-sm text-gray-400">Escriba para buscar...</span>
                            </template>
                        </VSelect>
                        <p v-if="form.errors.sales_floor_id" class="text-xs text-red-500 mt-1">{{ form.errors.sales_floor_id }}</p>
                    </div>

                    <!-- Source y Nombre -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Código Source <span class="text-red-500">*</span></label>
                            <input v-model="form.source" type="text" :class="inputClass" required/>
                            <p v-if="form.errors.source" class="text-xs text-red-500 mt-1">{{ form.errors.source }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nombre / Descripción</label>
                            <input v-model="form.source_name" type="text" :class="inputClass"/>
                        </div>
                    </div>

                    <!-- Canal y Tipo -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Canal Electrónico</label>
                            <VSelect
                                v-model="selectedCanal"
                                :options="canalOptions"
                                placeholder="— Seleccione —"
                                :clearable="true"
                                @update:modelValue="onCanalChange"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tipo de Fuente</label>
                            <VSelect
                                v-model="selectedTipo"
                                :options="tipoOptions"
                                placeholder="— Seleccione —"
                                :clearable="true"
                                @update:modelValue="onTipoChange"
                            />
                        </div>
                    </div>

                    <!-- Moneda + Activo -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Moneda</label>
                            <VSelect
                                v-model="selectedMoneda"
                                :options="monedaOptions"
                                :clearable="false"
                                :searchable="false"
                                @update:modelValue="onMonedaChange"
                            />
                        </div>
                        <div class="flex items-center gap-3 pb-1">
                            <input id="activo" v-model="form.activo" type="checkbox"
                                class="w-4 h-4 text-blue-600 rounded border-gray-300 dark:border-gray-600"/>
                            <label for="activo" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Activo</label>
                        </div>
                    </div>
                </div>
            </BaseCard>

            <div class="flex justify-end gap-3 mt-4">
                <Link :href="route('codigos-qr.index')"
                    class="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Cancelar
                </Link>
                <button type="submit" :disabled="form.processing"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-60 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Actualizar
                </button>
            </div>
        </form>
    </AppLayout>
</template>
