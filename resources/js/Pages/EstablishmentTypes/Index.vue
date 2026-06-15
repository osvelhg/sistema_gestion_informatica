<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({ types: Object, filters: Object })

const showModal = ref(false)
const editing = ref(null)
const form = useForm({ name: '', active: true })

const columns = [
    { key: 'name',    label: 'Nombre' },
    { key: 'active',  label: 'Estado' },
    { key: 'actions', label: 'Acciones' },
]

const openCreate = () => { editing.value = null; form.reset(); form.active = true; form.clearErrors(); showModal.value = true }
const openEdit = (row) => { editing.value = row; form.name = row.name; form.active = row.active; form.clearErrors(); showModal.value = true }

const submit = () =>
    editing.value
        ? form.put(`/tipos-establecimiento/${editing.value.id}`, { onSuccess: () => (showModal.value = false) })
        : form.post('/tipos-establecimiento', { onSuccess: () => (showModal.value = false) })

const destroy = async (row) => {
    if (!await confirmDanger({ title: 'Eliminar tipo', text: `Se eliminará "${row.name}".`, confirmText: 'Sí, eliminar' })) return
    router.delete(`/tipos-establecimiento/${row.id}`)
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <PageHeader eyebrow="Nomencladores · Conectividad" title="Tipos de Establecimiento" description="Clasificación del tipo de operación: CUP, MLC, MIXTO.">
                <template #actions>
                    <button class="app-button-primary" @click="openCreate">Nuevo tipo</button>
                </template>
            </PageHeader>

            <DataTable client-table :columns="columns" :data="types">
                <template #cell-name="{ value }">
                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ value }}</span>
                </template>
                <template #cell-active="{ value }">
                    <StatusBadge :status="value ? 'Activo' : 'Inactivo'" :color="value ? 'green' : 'red'" />
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <button class="app-button-secondary px-3 py-2 text-xs" @click="openEdit(row)">Editar</button>
                        <button class="app-button-danger px-3 py-2 text-xs" @click="destroy(row)">Eliminar</button>
                    </div>
                </template>
            </DataTable>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                <div class="surface-card relative z-10 w-full max-w-md p-6">
                    <h3 class="mb-5 text-xl font-semibold text-slate-950 dark:text-slate-100">
                        {{ editing ? 'Editar tipo de establecimiento' : 'Nuevo tipo de establecimiento' }}
                    </h3>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-1.5">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nombre <span class="text-rose-500">*</span></label>
                            <input v-model="form.name" type="text" class="app-input" placeholder="Ej: CUP" />
                            <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="form.active" type="checkbox" class="h-4 w-4" /> Activo
                        </label>
                        <div class="flex justify-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800">
                            <button type="button" class="app-button-secondary" @click="showModal = false">Cancelar</button>
                            <button type="submit" class="app-button-primary" :disabled="form.processing">
                                {{ form.processing ? 'Guardando...' : 'Guardar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
