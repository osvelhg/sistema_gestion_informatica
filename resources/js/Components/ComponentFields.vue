<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: { type: Object, required: true },
    label: { type: String, required: true },
    showInventory: { type: Boolean, default: false },
    showCustomName: { type: Boolean, default: false },
    componentTypeSlug: { type: String, default: '' },
    componentTypes: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const brands = ref([])
const models = ref([])
const manualBrand = ref(false)
const manualModel = ref(false)

const componentTypeId = computed(() => {
    if (!props.componentTypeSlug) return null
    const type = props.componentTypes.find(item => item.slug === props.componentTypeSlug)
    return type?.id || null
})

// Actualiza uno o varios campos en un solo emit para evitar sobreescrituras
const update = (fields) =>
    emit('update:modelValue', { ...props.modelValue, ...fields })

const fetchBrands = async () => {
    if (!componentTypeId.value) return
    try {
        const response = await axios.get(`/modelos/marcas-por-tipo/${componentTypeId.value}`)
        brands.value = response.data
    } catch {
        brands.value = []
    }
}

const fetchModels = async (brandId) => {
    if (!componentTypeId.value || !brandId) {
        models.value = []
        return
    }

    try {
        const response = await axios.get(`/modelos/por-tipo/${componentTypeId.value}`, { params: { brand_id: brandId } })
        models.value = response.data
    } catch {
        models.value = []
    }
}

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

    // Si el modelo actual estaba en el catálogo anterior, limpiarlo.
    // Si era texto libre (pre-llenado por AIDA u otro), conservarlo.
    const currentModelInCatalog = models.value.some(m => m.name === props.modelValue.model)
    const keepModel = currentModelInCatalog ? '' : (props.modelValue.model || '')

    update({ brand: value, model: keepModel })

    const brand = brands.value.find(item => item.name === value)
    if (brand) fetchModels(brand.id)
    else models.value = []
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

onMounted(() => {
    fetchBrands().then(() => {
        if (!props.modelValue.brand) return

        const foundBrand = brands.value.find(item => item.name === props.modelValue.brand)
        if (foundBrand) {
            fetchModels(foundBrand.id)
        } else {
            manualBrand.value = true
            manualModel.value = true
        }
    })
})

const inputClass = 'app-input'
</script>

<template>
    <fieldset class="surface-card-muted rounded-2xl p-4 md:p-5">
        <legend class="px-2 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ label }}</legend>

        <div class="grid grid-cols-1 gap-4 pt-2 md:grid-cols-2 xl:grid-cols-4">
            <div v-if="showCustomName">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Nombre</label>
                <input :value="modelValue.custom_name" type="text" :class="inputClass" @input="update({ custom_name: $event.target.value })" />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Marca</label>
                <select
                    v-if="componentTypeSlug && !manualBrand && brands.length"
                    :value="modelValue.brand"
                    :class="inputClass"
                    @change="onBrandChange($event.target.value)"
                >
                    <option value="">Seleccionar...</option>
                    <option v-for="brand in brands" :key="brand.id" :value="brand.name">{{ brand.name }}</option>
                    <option value="__manual__">-- Otra (escribir) --</option>
                </select>
                <div v-else class="flex gap-2">
                    <input :value="modelValue.brand" type="text" :class="inputClass" placeholder="Escribir marca..." @input="update({ brand: $event.target.value })" />
                    <button
                        v-if="componentTypeSlug && brands.length"
                        type="button"
                        class="inline-flex items-center rounded-xl px-2 text-brand-600 transition hover:text-brand-700 dark:text-brand-300 dark:hover:text-brand-200"
                        title="Volver a seleccionar"
                        @click="manualBrand = false; manualModel = false; update({ brand: '', model: '' })"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Modelo</label>
                <select
                    v-if="componentTypeSlug && !manualModel && !manualBrand && models.length"
                    :value="modelValue.model"
                    :class="inputClass"
                    @change="onModelChange($event.target.value)"
                >
                    <option value="">Seleccionar...</option>
                    <option v-for="model in models" :key="model.id" :value="model.name">{{ model.name }}</option>
                    <option value="__manual__">-- Otro (escribir) --</option>
                </select>
                <div v-else class="flex gap-2">
                    <input :value="modelValue.model" type="text" :class="inputClass" placeholder="Escribir modelo..." @input="update({ model: $event.target.value })" />
                    <button
                        v-if="componentTypeSlug && !manualBrand && brands.length"
                        type="button"
                        class="inline-flex items-center rounded-xl px-2 text-brand-600 transition hover:text-brand-700 dark:text-brand-300 dark:hover:text-brand-200"
                        title="Volver a seleccionar"
                        @click="manualModel = false; update({ model: '' })"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Numero de serie</label>
                <input :value="modelValue.serial_number" type="text" :class="inputClass" @input="update({ serial_number: $event.target.value })" />
            </div>

            <div v-if="showInventory">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Numero de inventario</label>
                <input :value="modelValue.inventory_number" type="text" :class="inputClass" @input="update({ inventory_number: $event.target.value })" />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Estado</label>
                <select :value="modelValue.status" :class="inputClass" @change="update({ status: $event.target.value })">
                    <option value="">Seleccionar</option>
                    <option v-for="status in statuses" :key="status.id" :value="status.name">{{ status.name }}</option>
                </select>
            </div>
        </div>
    </fieldset>
</template>
