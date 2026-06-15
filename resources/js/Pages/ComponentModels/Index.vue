<script setup>
import { ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({
    models: Object,
    componentTypes: Array,
    brands: Array,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const typeFilter = ref(props.filters?.component_type_id || '')
const brandFilter = ref(props.filters?.brand_id || '')
const showModal = ref(false)
const editing = ref(null)

const form = useForm({
    component_type_id: '',
    brand_id: '',
    name: '',
    active: true,
})

const categoryLabels = {
    caracteristica: 'Caracteristica',
    periferico: 'Periferico',
    dispositivo: 'Dispositivo',
}

const columns = [
    { key: 'component_type', label: 'Tipo' },
    { key: 'brand', label: 'Marca' },
    { key: 'name', label: 'Modelo' },
    { key: 'active', label: 'Estado' },
    { key: 'actions', label: 'Acciones' },
]

let timeout
const applyFilters = () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/modelos', {
            search: search.value || undefined,
            component_type_id: typeFilter.value || undefined,
            brand_id: brandFilter.value || undefined,
        }, { preserveState: true, replace: true })
    }, 300)
}

watch(search, applyFilters)
watch(typeFilter, applyFilters)
watch(brandFilter, applyFilters)

const openCreate = () => {
    editing.value = null
    form.reset()
    form.active = true
    form.clearErrors()
    showModal.value = true
}

const openEdit = (model) => {
    editing.value = model
    form.component_type_id = model.component_type_id
    form.brand_id = model.brand_id
    form.name = model.name
    form.active = model.active
    form.clearErrors()
    showModal.value = true
}

const submit = () => {
    const options = { onSuccess: () => { showModal.value = false } }
    if (editing.value) return form.put(`/modelos/${editing.value.id}`, options)
    form.post('/modelos', options)
}

const destroy = async (model) => {
    if (!await confirmDanger({ title: 'Eliminar modelo', text: `Se eliminara el modelo "${model.name}".`, confirmText: 'Si, eliminar' })) return
    router.delete(`/modelos/${model.id}`)
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <PageHeader eyebrow="Catalogo tecnico" title="Modelos de Componentes" description="Administra modelos con filtros combinados y contraste consistente en tablas y formularios.">
                <template #actions>
                    <button type="button" class="app-button-primary" @click="openCreate">Nuevo modelo</button>
                </template>
            </PageHeader>

            <BaseCard>
                <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_260px_220px]">
                    <input v-model="search" type="text" placeholder="Buscar modelo..." class="app-input" />
                    <select v-model="typeFilter" class="app-select">
                        <option value="">Todos los tipos</option>
                        <optgroup v-for="category in ['caracteristica', 'periferico', 'dispositivo']" :key="category" :label="categoryLabels[category]">
                            <option v-for="type in componentTypes.filter((item) => item.category === category)" :key="type.id" :value="type.id">
                                {{ type.name }}
                            </option>
                        </optgroup>
                    </select>
                    <select v-model="brandFilter" class="app-select">
                        <option value="">Todas las marcas</option>
                        <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                    </select>
                </div>
            </BaseCard>

            <DataTable client-table :columns="columns" :data="models">
                <template #cell-component_type="{ row }">
                    <div>
                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ row.component_type?.name || '-' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ categoryLabels[row.component_type?.category] || 'Sin categoria' }}</p>
                    </div>
                </template>
                <template #cell-brand="{ row }">
                    <span class="text-slate-600 dark:text-slate-300">{{ row.brand?.name || '-' }}</span>
                </template>
                <template #cell-name="{ row }">
                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ row.name }}</span>
                </template>
                <template #cell-active="{ value }">
                    <StatusBadge :status="value ? 'Activo' : 'Inactivo'" :color="value ? 'green' : 'red'" />
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <button type="button" class="app-button-secondary px-3 py-2 text-xs" @click="openEdit(row)">Editar</button>
                        <button type="button" class="app-button-danger px-3 py-2 text-xs" @click="destroy(row)">Eliminar</button>
                    </div>
                </template>
                <template #empty>No se encontraron modelos.</template>
            </DataTable>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                <div class="surface-card relative z-10 w-full max-w-xl p-6">
                    <div class="mb-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600 dark:text-brand-300">Modelo</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950 dark:text-slate-100">{{ editing ? 'Editar modelo' : 'Nuevo modelo' }}</h3>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tipo de componente</label>
                                <select v-model="form.component_type_id" class="app-select">
                                    <option value="">Seleccionar...</option>
                                    <optgroup v-for="category in ['caracteristica', 'periferico', 'dispositivo']" :key="category" :label="categoryLabels[category]">
                                        <option v-for="type in componentTypes.filter((item) => item.category === category)" :key="type.id" :value="type.id">
                                            {{ type.name }}
                                        </option>
                                    </optgroup>
                                </select>
                                <p v-if="form.errors.component_type_id" class="text-xs text-rose-500">{{ form.errors.component_type_id }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Marca</label>
                                <select v-model="form.brand_id" class="app-select">
                                    <option value="">Seleccionar...</option>
                                    <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                                </select>
                                <p v-if="form.errors.brand_id" class="text-xs text-rose-500">{{ form.errors.brand_id }}</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nombre del modelo</label>
                            <input v-model="form.name" type="text" class="app-input" />
                            <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
                        </div>

                        <label class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300">
                            <input v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800" />
                            Modelo activo
                        </label>

                        <div class="flex justify-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800">
                            <button type="button" class="app-button-secondary" @click="showModal = false">Cancelar</button>
                            <button type="submit" :disabled="form.processing" class="app-button-primary">{{ form.processing ? 'Guardando...' : 'Guardar' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
