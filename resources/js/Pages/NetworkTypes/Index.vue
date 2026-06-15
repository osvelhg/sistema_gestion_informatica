<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({ types: Object, filters: Object })

const showModal = ref(false)
const editing = ref(null)
const form = useForm({ name: '', color: 'slate', active: true })

const colorOptions = [
    { value: 'blue',   label: 'Azul' },
    { value: 'green',  label: 'Verde' },
    { value: 'cyan',   label: 'Cyan' },
    { value: 'yellow', label: 'Amarillo' },
    { value: 'violet', label: 'Violeta' },
    { value: 'slate',  label: 'Gris' },
    { value: 'red',    label: 'Rojo' },
    { value: 'orange', label: 'Naranja' },
]

const colorClass = (color) => ({
    blue:   'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
    green:  'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300',
    cyan:   'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300',
    yellow: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
    slate:  'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300',
    red:    'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
    orange: 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
}[color] ?? 'bg-slate-100 text-slate-700')

const columns = [
    { key: 'name',   label: 'Nombre' },
    { key: 'color',  label: 'Color' },
    { key: 'active', label: 'Estado' },
    { key: 'actions', label: 'Acciones' },
]

const openCreate = () => {
    editing.value = null
    form.reset()
    form.active = true
    form.color = 'slate'
    form.clearErrors()
    showModal.value = true
}

const openEdit = (row) => {
    editing.value = row
    form.name   = row.name
    form.color  = row.color || 'slate'
    form.active = row.active
    form.clearErrors()
    showModal.value = true
}

const submit = () =>
    editing.value
        ? form.put(`/tipos-red/${editing.value.id}`, { onSuccess: () => (showModal.value = false) })
        : form.post('/tipos-red', { onSuccess: () => (showModal.value = false) })

const destroy = async (row) => {
    if (!await confirmDanger({ title: 'Eliminar tipo de red', text: `Se eliminará "${row.name}".`, confirmText: 'Sí, eliminar' })) return
    router.delete(`/tipos-red/${row.id}`)
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <PageHeader eyebrow="Nomencladores · Conectividad" title="Tipos de Red Comercial" description="Clasificación de redes: RED CUP, RED MLC, Mixta, Hotelera, Virtual, Mi Pieza.">
                <template #actions>
                    <button class="app-button-primary" @click="openCreate">Nuevo tipo</button>
                </template>
            </PageHeader>

            <DataTable client-table :columns="columns" :data="types">
                <template #cell-name="{ value }">
                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ value }}</span>
                </template>
                <template #cell-color="{ row }">
                    <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold', colorClass(row.color)]">
                        {{ colorOptions.find(c => c.value === row.color)?.label || row.color }}
                    </span>
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
                        {{ editing ? 'Editar tipo de red' : 'Nuevo tipo de red' }}
                    </h3>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-1.5">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nombre <span class="text-rose-500">*</span></label>
                            <input v-model="form.name" type="text" class="app-input" placeholder="Ej: RED CUP" />
                            <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Color de badge</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="opt in colorOptions"
                                    :key="opt.value"
                                    type="button"
                                    :class="['inline-flex items-center rounded-full px-3 py-1.5 text-xs font-semibold transition', colorClass(opt.value), form.color === opt.value ? 'ring-2 ring-offset-1 ring-brand-500' : 'opacity-60 hover:opacity-100']"
                                    @click="form.color = opt.value"
                                >
                                    {{ opt.label }}
                                </button>
                            </div>
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
