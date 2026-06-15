<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import BaseCard from '@/Components/BaseCard.vue'

const props = defineProps({
    canales: Array,
    tipos:   Array,
    monedas: Array,
})

const form = useForm({
    sales_floor_id:       '',
    source:               '',
    source_name:          '',
    canal_electronico_id: '',
    tipo_fuente_id:       '',
    moneda:               '',
    activo:               true,
})

// ── Piso de Venta — async VSelect ──────────────────────────────────────────
const floorOptions   = ref([])
const floorLoading   = ref(false)
const selectedFloor  = ref(null)

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

const defaultMoneda = monedaOptions.find(m => m.id === 'CUP') ?? monedaOptions[0]
form.moneda = defaultMoneda.id

const selectedMoneda = ref(defaultMoneda)
const selectedCanal  = ref(null)
const selectedTipo   = ref(null)

function onCanalChange(val)  { form.canal_electronico_id = val ? val.id : '' }
function onTipoChange(val)   { form.tipo_fuente_id       = val ? val.id : '' }
function onMonedaChange(val) { form.moneda               = val ? val.id : defaultMoneda.id }

function submit() {
    form.post(route('codigos-qr.store'))
}

const inputClass = 'w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent'
</script>

<template>
    <AppLayout title="Nuevo Código QR">
        <PageHeader title="Nuevo Código QR" subtitle="Crear un QR manualmente">
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
                            :loading="floorLoading"
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
                    Guardar
                </button>
            </div>
        </form>
    </AppLayout>
</template>
