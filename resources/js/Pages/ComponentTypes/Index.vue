<script setup>
import { ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({ types: Object, filters: Object })
const search = ref(props.filters?.search || '')
const categoryFilter = ref(props.filters?.category || '')
const showModal = ref(false)
const editing = ref(null)

const categoryLabels = {
    caracteristica: 'Caracteristica',
    periferico: 'Periferico',
    dispositivo: 'Dispositivo',
}

const form = useForm({ name: '', category: 'periferico', active: true })
const columns = [
    { key: 'name', label: 'Nombre' },
    { key: 'category', label: 'Categoria' },
    { key: 'slug', label: 'Slug' },
    { key: 'active', label: 'Estado' },
    { key: 'actions', label: 'Acciones' },
]

let timeout
const applyFilters = () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/tipos-componentes', {
            search: search.value || undefined,
            category: categoryFilter.value || undefined,
        }, { preserveState: true, replace: true })
    }, 300)
}

watch(search, applyFilters)
watch(categoryFilter, applyFilters)

const openCreate = () => {
    editing.value = null
    form.reset()
    form.category = 'periferico'
    form.active = true
    form.clearErrors()
    showModal.value = true
}

const openEdit = (type) => {
    editing.value = type
    form.name = type.name
    form.category = type.category
    form.active = type.active
    form.clearErrors()
    showModal.value = true
}

const submit = () => {
    const options = { onSuccess: () => { showModal.value = false } }
    if (editing.value) return form.put(`/tipos-componentes/${editing.value.id}`, options)
    form.post('/tipos-componentes', options)
}

const destroy = async (type) => {
    if (!await confirmDanger({ title: 'Eliminar tipo', text: `Se eliminara el tipo "${type.name}".`, confirmText: 'Si, eliminar' })) return
    router.delete(`/tipos-componentes/${type.id}`)
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <PageHeader eyebrow="Nomenclador" title="Tipos de Dispositivos" description="Gestiona los tipos usados por perifericos, otros dispositivos y caracteristicas con una interfaz premium en ambos temas.">
                <template #actions>
                    <button type="button" class="app-button-primary" @click="openCreate">Nuevo tipo</button>
                </template>
            </PageHeader>

            <BaseCard>
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_240px]">
                    <input v-model="search" type="text" placeholder="Buscar tipo..." class="app-input" />
                    <select v-model="categoryFilter" class="app-select">
                        <option value="">Todas las categorias</option>
                        <option value="caracteristica">Caracteristicas</option>
                        <option value="periferico">Perifericos</option>
                        <option value="dispositivo">Otros dispositivos</option>
                    </select>
                </div>
            </BaseCard>

            <DataTable client-table :columns="columns" :data="types">
                <template #cell-name="{ row }">
                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ row.name }}</span>
                </template>
                <template #cell-category="{ value }">
                    <StatusBadge :status="categoryLabels[value] || value" color="blue" />
                </template>
                <template #cell-slug="{ value }">
                    <span class="font-mono text-xs text-slate-500 dark:text-slate-300">{{ value }}</span>
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
                <template #empty>No se encontraron tipos.</template>
            </DataTable>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                <div class="surface-card relative z-10 w-full max-w-xl p-6">
                    <div class="mb-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600 dark:text-brand-300">Tipo</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950 dark:text-slate-100">{{ editing ? 'Editar tipo' : 'Nuevo tipo' }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">El slug se genera automaticamente a partir del nombre.</p>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nombre</label>
                            <input v-model="form.name" type="text" class="app-input" />
                            <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Categoria</label>
                            <select v-model="form.category" class="app-select">
                                <option value="caracteristica">Caracteristica</option>
                                <option value="periferico">Periferico</option>
                                <option value="dispositivo">Dispositivo</option>
                            </select>
                            <p v-if="form.errors.category" class="text-xs text-rose-500">{{ form.errors.category }}</p>
                        </div>

                        <div class="rounded-2xl border border-brand-200/60 bg-brand-50/70 px-4 py-3 text-sm text-brand-700 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-200">
                            Este nomenclador alimenta las tabs del expediente y las vistas de detalle.
                        </div>

                        <label class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300">
                            <input v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800" />
                            Tipo activo
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
