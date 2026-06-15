<script setup>
import { ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({ municipios: Object, provincias: Array, filters: Object })
const search = ref(props.filters?.search || '')
const provinciaFilter = ref(props.filters?.provincia_id || '')
const showModal = ref(false)
const editing = ref(null)

const form = useForm({ provincia_id: '', name: '', code: '', active: true })
const columns = [
    { key: 'code', label: 'Codigo' },
    { key: 'name', label: 'Municipio' },
    { key: 'provincia', label: 'Provincia' },
    { key: 'active', label: 'Estado' },
    { key: 'actions', label: 'Acciones' },
]

let searchTimeout
const applyFilters = () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get('/municipios', {
            search: search.value || undefined,
            provincia_id: provinciaFilter.value || undefined,
        }, { preserveState: true, replace: true })
    }, 300)
}

watch(search, applyFilters)
watch(provinciaFilter, applyFilters)

const openCreate = () => {
    editing.value = null
    form.reset()
    form.active = true
    form.clearErrors()
    showModal.value = true
}

const openEdit = (municipio) => {
    editing.value = municipio
    form.provincia_id = municipio.provincia_id
    form.name = municipio.name
    form.code = municipio.code
    form.active = municipio.active
    form.clearErrors()
    showModal.value = true
}

const submit = () => {
    const options = { onSuccess: () => { showModal.value = false } }
    if (editing.value) return form.put(`/municipios/${editing.value.id}`, options)
    form.post('/municipios', options)
}

const destroy = async (municipio) => {
    if (!await confirmDanger({ title: 'Eliminar municipio', text: `Se eliminara el municipio "${municipio.name}".`, confirmText: 'Si, eliminar' })) return
    router.delete(`/municipios/${municipio.id}`)
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl space-y-6">
            <PageHeader eyebrow="Territorio" title="Municipios" description="Filtra por provincia y trabaja con formularios totalmente adaptados a oscuro.">
                <template #actions>
                    <button type="button" class="app-button-primary" @click="openCreate">Nuevo municipio</button>
                </template>
            </PageHeader>

            <BaseCard>
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                    <input v-model="search" type="text" placeholder="Buscar municipio..." class="app-input" />
                    <select v-model="provinciaFilter" class="app-select">
                        <option value="">Todas las provincias</option>
                        <option v-for="provincia in provincias" :key="provincia.id" :value="provincia.id">{{ provincia.name }}</option>
                    </select>
                </div>
            </BaseCard>

            <DataTable client-table :columns="columns" :data="municipios">
                <template #cell-code="{ value }">
                    <span class="font-mono text-xs font-semibold tracking-wide text-slate-500 dark:text-slate-300">{{ value }}</span>
                </template>
                <template #cell-name="{ row }">
                    <div>
                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ row.name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Gestion territorial</p>
                    </div>
                </template>
                <template #cell-provincia="{ row }">
                    <span class="text-slate-600 dark:text-slate-300">{{ row.provincia?.name || '-' }}</span>
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
                <template #empty>No se encontraron municipios.</template>
            </DataTable>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                <div class="surface-card relative z-10 w-full max-w-xl p-6">
                    <div class="mb-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600 dark:text-brand-300">Municipio</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950 dark:text-slate-100">{{ editing ? 'Editar municipio' : 'Nuevo municipio' }}</h3>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Provincia</label>
                            <select v-model="form.provincia_id" class="app-select">
                                <option value="">Seleccionar...</option>
                                <option v-for="provincia in provincias" :key="provincia.id" :value="provincia.id">{{ provincia.name }}</option>
                            </select>
                            <p v-if="form.errors.provincia_id" class="text-xs text-rose-500">{{ form.errors.provincia_id }}</p>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nombre</label>
                                <input v-model="form.name" type="text" class="app-input" />
                                <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Codigo</label>
                                <input v-model="form.code" type="text" class="app-input" />
                                <p v-if="form.errors.code" class="text-xs text-rose-500">{{ form.errors.code }}</p>
                            </div>
                        </div>

                        <label class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300">
                            <input v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800" />
                            Municipio activo
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
