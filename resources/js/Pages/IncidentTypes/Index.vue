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
const showModal = ref(false)
const editing = ref(null)
const form = useForm({ name: '', active: true })

const columns = [
    { key: 'name', label: 'Tipo' },
    { key: 'slug', label: 'Slug' },
    { key: 'seals_count', label: 'Registros' },
    { key: 'active', label: 'Estado' },
    { key: 'actions', label: 'Acciones' },
]

let timeout
watch(search, (value) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/tipos-incidencias', { search: value || undefined }, { preserveState: true, replace: true })
    }, 300)
})

const openCreate = () => {
    editing.value = null
    form.reset()
    form.active = true
    form.clearErrors()
    showModal.value = true
}

const openEdit = (type) => {
    editing.value = type
    form.name = type.name
    form.active = type.active
    form.clearErrors()
    showModal.value = true
}

const submit = () => {
    const options = { onSuccess: () => { showModal.value = false } }
    if (editing.value) return form.put(`/tipos-incidencias/${editing.value.id}`, options)
    form.post('/tipos-incidencias', options)
}

const destroy = async (type) => {
    if (!await confirmDanger({ title: 'Eliminar tipo de incidencia', text: `Se eliminara el tipo "${type.name}".`, confirmText: 'Si, eliminar' })) return
    router.delete(`/tipos-incidencias/${type.id}`)
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl space-y-6">
            <PageHeader eyebrow="Nomenclador" title="Tipos de Incidencia" description="Clasifica incidencias para sellos y reportes operativos.">
                <template #actions>
                    <button type="button" class="app-button-primary" @click="openCreate">Nuevo tipo</button>
                </template>
            </PageHeader>

            <BaseCard>
                <input v-model="search" type="text" placeholder="Buscar tipo de incidencia..." class="app-input max-w-md" />
            </BaseCard>

            <DataTable client-table :columns="columns" :data="types">
                <template #cell-name="{ row }"><span class="font-medium text-slate-900 dark:text-slate-100">{{ row.name }}</span></template>
                <template #cell-slug="{ value }"><span class="font-mono text-xs text-slate-500 dark:text-slate-300">{{ value }}</span></template>
                <template #cell-seals_count="{ value }"><span class="font-medium text-slate-700 dark:text-slate-200">{{ value }}</span></template>
                <template #cell-active="{ value }"><StatusBadge :status="value ? 'Activo' : 'Inactivo'" :color="value ? 'green' : 'red'" /></template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <button type="button" class="app-button-secondary px-3 py-2 text-xs" @click="openEdit(row)">Editar</button>
                        <button type="button" class="app-button-danger px-3 py-2 text-xs" @click="destroy(row)">Eliminar</button>
                    </div>
                </template>
            </DataTable>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                <div class="surface-card relative z-10 w-full max-w-lg p-6">
                    <h3 class="mb-5 text-xl font-semibold text-slate-950 dark:text-slate-100">{{ editing ? 'Editar tipo de incidencia' : 'Nuevo tipo de incidencia' }}</h3>
                    <form class="space-y-5" @submit.prevent="submit">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nombre</label>
                            <input v-model="form.name" type="text" class="app-input" />
                            <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
                        </div>
                        <label class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300">
                            <input v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800" />
                            Tipo activo
                        </label>
                        <div class="flex justify-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800">
                            <button type="button" class="app-button-secondary" @click="showModal = false">Cancelar</button>
                            <button type="submit" class="app-button-primary" :disabled="form.processing">{{ form.processing ? 'Guardando...' : 'Guardar' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
