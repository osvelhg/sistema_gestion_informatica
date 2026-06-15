<script setup>
/**
 * DynamicRow — fila de periférico / dispositivo con cascada Tipo → Marca → Modelo
 *
 * Props:
 *   modelValue  — objeto { component_type_slug, brand, model, serial_number, inventory_number, status }
 *   types       — array de ComponentType filtrado (periferico | dispositivo)
 *   statuses    — array de Status
 *   errors       — array de mensajes de error para esta fila (opcional)
 *   showInventory — mostrar No. Inventario (false para características internas)
 *
 * Emits:
 *   update:modelValue  — fila actualizada
 *   remove             — solicitar eliminación de la fila
 */
import { ref, watch, computed, onMounted } from 'vue'
import axios from 'axios'
import { notifyError, notifyInfo, notifyWarning } from '@/Composables/useNotifications'

const props = defineProps({
    modelValue: { type: Object, required: true },
    types:      { type: Array,  default: () => [] },
    statuses:   { type: Array,  default: () => [] },
    errors:     { type: Array,  default: () => [] },
    /** Periféricos/dispositivos: inventario. Características internas: false */
    showInventory: { type: Boolean, default: true },
    /** Validación RODAS junto al inventario (periféricos / dispositivos) */
    rodasLookupEnabled: { type: Boolean, default: false },
    contextEntityId: { type: [String, Number], default: '' },
    contextDepartmentId: { type: [String, Number], default: '' },
    equipmentFileId: { type: [Number, String], default: null },
    medioCategory: { type: String, default: 'periferico' },
})

const emit = defineEmits(['update:modelValue', 'remove'])

const rodasValidating = ref(false)
const rodasModalOpen = ref(false)
const rodasModalMessage = ref('')
const rodasModalAlertCreated = ref(false)

const typeLabel = computed(() => {
    const slug = props.modelValue.component_type_slug
    if (!slug) return ''
    return props.types.find(t => t.slug === slug)?.name || slug
})

const validateRodasMedio = async () => {
    if (!props.rodasLookupEnabled || !props.showInventory) return
    const inv = (props.modelValue.inventory_number || '').trim()
    if (!inv) {
        notifyWarning('Inventario', 'Escribe el número de inventario para validar en RODAS.')
        return
    }
    if (!props.contextEntityId || !props.contextDepartmentId) {
        notifyWarning('Contexto', 'Selecciona entidad y departamento en datos generales antes de validar.')
        return
    }
    rodasValidating.value = true
    try {
        const { data } = await axios.post('/expedientes/validar-medio-inventario', {
            inventory_number: inv,
            entity_id: props.contextEntityId,
            department_id: props.contextDepartmentId,
            category: props.medioCategory,
            component_type_slug: props.modelValue.component_type_slug || '',
            component_type_label: typeLabel.value,
            equipment_file_id: props.equipmentFileId || null,
            persist_alert: !!props.equipmentFileId,
        })
        if (data.success === false) {
            notifyError('RODAS', data.message || 'No se pudo validar.')
            return
        }
        if (data.match) {
            notifyInfo('RODAS', data.message || 'Inventario coherente con RODAS.')
            return
        }
        rodasModalMessage.value = data.message || 'La información no coincide con RODAS.'
        rodasModalAlertCreated.value = !!data.alert_created
        rodasModalOpen.value = true
    } catch (e) {
        const msg = e.response?.data?.message || e.message
        notifyError('RODAS', msg || 'Error al validar el inventario.')
    } finally {
        rodasValidating.value = false
    }
}

const closeRodasModal = () => {
    rodasModalOpen.value = false
}

const brands       = ref([])
const models       = ref([])
const manualBrand  = ref(false)
const manualModel  = ref(false)
const loadingBrands = ref(false)
const loadingModels = ref(false)

// ID numérico del tipo seleccionado (necesario para los endpoints de la API)
const selectedTypeId = computed(() => {
    if (!props.modelValue.component_type_slug) return null
    return props.types.find(t => t.slug === props.modelValue.component_type_slug)?.id ?? null
})

// Actualiza uno o varios campos a la vez para evitar emits consecutivos que se sobreescriben
const update = (fields) =>
    emit('update:modelValue', { ...props.modelValue, ...fields })

const fetchBrands = async () => {
    brands.value = []
    if (!selectedTypeId.value) return
    loadingBrands.value = true
    try {
        const { data } = await axios.get(`/modelos/marcas-por-tipo/${selectedTypeId.value}`)
        brands.value = data
    } catch {
        brands.value = []
    } finally {
        loadingBrands.value = false
    }
}

const fetchModels = async (brandId) => {
    models.value = []
    if (!selectedTypeId.value || !brandId) return
    loadingModels.value = true
    try {
        const { data } = await axios.get(`/modelos/por-tipo/${selectedTypeId.value}`, {
            params: { brand_id: brandId },
        })
        models.value = data
    } catch {
        models.value = []
    } finally {
        loadingModels.value = false
    }
}

// Al cambiar el tipo: limpiar y recargar marcas (un solo emit)
watch(selectedTypeId, async (newId, oldId) => {
    if (newId === oldId) return
    manualBrand.value = false
    manualModel.value = false
    brands.value = []
    models.value = []
    update({ brand: '', model: '', component_type_slug: props.modelValue.component_type_slug })
    if (newId) await fetchBrands()
})

const onBrandChange = (value) => {
    if (value === '__manual__') {
        manualBrand.value = true
        manualModel.value = true
        models.value = []
        update({ brand: '', model: '' })
        return
    }
    manualBrand.value = false
    manualModel.value = false
    // Un solo emit con marca y modelo juntos → evita la sobreescritura
    update({ brand: value, model: '' })
    const brand = brands.value.find(b => b.name === value)
    if (brand) fetchModels(brand.id)
}

const onModelChange = (value) => {
    if (value === '__manual__') {
        manualModel.value = true
        update({ model: '' })
        return
    }
    manualModel.value = false
    update({ model: value })
}

const resetBrand = () => {
    manualBrand.value = false
    manualModel.value = false
    update({ brand: '', model: '' })
}

const resetModel = () => {
    manualModel.value = false
    update({ model: '' })
}

// Al montar (modo edición): reconstruir el estado del cascada con los valores existentes
onMounted(async () => {
    if (!selectedTypeId.value) return
    await fetchBrands()
    if (!props.modelValue.brand) return
    const foundBrand = brands.value.find(b => b.name === props.modelValue.brand)
    if (foundBrand) {
        await fetchModels(foundBrand.id)
        if (props.modelValue.model && !models.value.find(m => m.name === props.modelValue.model)) {
            manualModel.value = true
        }
    } else {
        manualBrand.value = true
        manualModel.value = true
    }
})
</script>

<template>
    <div class="surface-card-muted relative rounded-2xl p-4">
        <!-- Botón eliminar fila -->
        <button
            type="button"
            title="Eliminar"
            class="absolute right-2 top-2 text-red-400 transition hover:text-red-600 dark:text-red-300 dark:hover:text-red-200"
            @click="$emit('remove')"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <div
            class="grid grid-cols-1 gap-4 md:grid-cols-3"
            :class="showInventory ? 'xl:grid-cols-6' : 'xl:grid-cols-5'"
        >
            <!-- Tipo -->
            <div class="xl:col-span-1">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Tipo <span class="text-rose-500">*</span></label>
                <select
                    :value="modelValue.component_type_slug"
                    class="app-select"
                    @change="update({ component_type_slug: $event.target.value })"
                >
                    <option value="">Seleccionar...</option>
                    <option v-for="type in types" :key="type.slug" :value="type.slug">{{ type.name }}</option>
                </select>
            </div>

            <!-- Marca con cascada -->
            <div class="xl:col-span-1">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Marca</label>

                <!-- Select de marcas (cuando el tipo tiene marcas en BD y no es manual) -->
                <template v-if="selectedTypeId && !manualBrand && brands.length">
                    <select
                        :value="modelValue.brand"
                        class="app-select"
                        :disabled="loadingBrands"
                        @change="onBrandChange($event.target.value)"
                    >
                        <option value="">{{ loadingBrands ? 'Cargando...' : 'Seleccionar...' }}</option>
                        <option v-for="brand in brands" :key="brand.id" :value="brand.name">{{ brand.name }}</option>
                        <option value="__manual__">— Otra (escribir) —</option>
                    </select>
                </template>

                <!-- Input manual o sin tipo seleccionado -->
                <template v-else>
                    <div class="flex gap-1.5">
                        <input
                            :value="modelValue.brand"
                            type="text"
                            class="app-input"
                            placeholder="Escribir marca..."
                            @input="update({ brand: $event.target.value })"
                        />
                        <!-- Botón volver al select si hay marcas disponibles -->
                        <button
                            v-if="selectedTypeId && brands.length && manualBrand"
                            type="button"
                            title="Volver a seleccionar"
                            class="shrink-0 rounded-xl px-2 text-brand-600 transition hover:text-brand-700 dark:text-brand-300 dark:hover:text-brand-200"
                            @click="resetBrand"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
                            </svg>
                        </button>
                    </div>
                </template>
            </div>

            <!-- Modelo con cascada -->
            <div class="xl:col-span-1">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Modelo</label>

                <template v-if="selectedTypeId && !manualBrand && !manualModel && models.length">
                    <select
                        :value="modelValue.model"
                        class="app-select"
                        :disabled="loadingModels"
                        @change="onModelChange($event.target.value)"
                    >
                        <option value="">{{ loadingModels ? 'Cargando...' : 'Seleccionar...' }}</option>
                        <option v-for="model in models" :key="model.id" :value="model.name">{{ model.name }}</option>
                        <option value="__manual__">— Otro (escribir) —</option>
                    </select>
                </template>

                <template v-else>
                    <div class="flex gap-1.5">
                        <input
                            :value="modelValue.model"
                            type="text"
                            class="app-input"
                            placeholder="Escribir modelo..."
                            @input="update({ model: $event.target.value })"
                        />
                        <button
                            v-if="selectedTypeId && !manualBrand && models.length && manualModel"
                            type="button"
                            title="Volver a seleccionar"
                            class="shrink-0 rounded-xl px-2 text-brand-600 transition hover:text-brand-700 dark:text-brand-300 dark:hover:text-brand-200"
                            @click="resetModel"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
                            </svg>
                        </button>
                    </div>
                </template>
            </div>

            <!-- No. Serie -->
            <div class="xl:col-span-1">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">No. Serie</label>
                <input
                    :value="modelValue.serial_number"
                    type="text"
                    class="app-input"
                    @input="update({ serial_number: $event.target.value })"
                />
            </div>

            <!-- No. Inventario (medios con inventario institucional; no aplica a componentes internos) -->
            <div v-if="showInventory" class="xl:col-span-1">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">No. Inventario</label>
                <div class="flex gap-1.5">
                    <input
                        :value="modelValue.inventory_number"
                        type="text"
                        class="app-input min-w-0 flex-1"
                        autocomplete="off"
                        @input="update({ inventory_number: $event.target.value })"
                    />
                    <button
                        v-if="rodasLookupEnabled"
                        type="button"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200/90 bg-white text-slate-500 transition hover:border-brand-300 hover:text-brand-600 dark:border-slate-600 dark:bg-slate-800/80 dark:text-slate-400 dark:hover:border-brand-500/50 dark:hover:text-brand-300"
                        :disabled="rodasValidating"
                        :title="'Validar en RODAS (entidad y departamento actuales)'"
                        @click="validateRodasMedio"
                    >
                        <span class="sr-only">Validar en RODAS</span>
                        <svg v-if="!rodasValidating" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <svg v-else class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Estado -->
            <div class="xl:col-span-1">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Estado</label>
                <select
                    :value="modelValue.status"
                    class="app-select"
                    @change="update({ status: $event.target.value })"
                >
                    <option value="">Seleccionar</option>
                    <option v-for="status in statuses" :key="status.id" :value="status.name">{{ status.name }}</option>
                </select>
            </div>
        </div>

        <!-- Errores de fila -->
        <div v-if="errors.length" class="mt-3 rounded-xl border border-red-200/80 bg-red-50/80 p-3 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
            <p v-for="(msg, i) in errors" :key="i">{{ msg }}</p>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="rodasModalOpen"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="rodas-modal-title"
        >
            <div class="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" @click="closeRodasModal" />
            <div
                class="relative w-full max-w-md rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xl dark:border-slate-600 dark:bg-slate-800"
            >
                <h3 id="rodas-modal-title" class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    Validación RODAS
                </h3>
                <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                    {{ rodasModalMessage }}
                </p>
                <p
                    v-if="rodasModalAlertCreated"
                    class="mt-3 rounded-lg border border-amber-200/80 bg-amber-50/90 px-3 py-2 text-xs text-amber-950 dark:border-amber-500/25 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    Quedó registrada una alerta en este expediente para seguimiento y corrección.
                </p>
                <p
                    v-else-if="!equipmentFileId"
                    class="mt-3 text-xs text-slate-500 dark:text-slate-400"
                >
                    Al guardar el expediente el sistema volverá a comprobar y registrar alertas si aplica.
                </p>
                <button
                    type="button"
                    class="app-button-primary mt-5 w-full"
                    @click="closeRodasModal"
                >
                    Continuar
                </button>
            </div>
        </div>
    </Teleport>
</template>
