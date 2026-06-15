<script setup>
import { ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({ statuses: Object, filters: Object })
const search = ref(props.filters?.search || '')
const showModal = ref(false)
const editing = ref(null)

const colorOptions = [
    { value: 'green', label: 'Verde', class: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300' },
    { value: 'yellow', label: 'Amarillo', class: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300' },
    { value: 'red', label: 'Rojo', class: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300' },
    { value: 'blue', label: 'Azul', class: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300' },
    { value: 'gray', label: 'Gris', class: 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-600/40 dark:bg-slate-700/30 dark:text-slate-300' },
    { value: 'orange', label: 'Naranja', class: 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-500/20 dark:bg-orange-500/10 dark:text-orange-300' },
    { value: 'purple', label: 'Morado', class: 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-300' },
]

const form = useForm({ name: '', color: 'gray', active: true, order: 0 })
const columns = [
    { key: 'order', label: 'Orden' },
    { key: 'name', label: 'Estado' },
    { key: 'color', label: 'Color' },
    { key: 'active', label: 'Activo' },
    { key: 'actions', label: 'Acciones' },
]

let timeout
watch(search, (value) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/estados', { search: value || undefined }, { preserveState: true, replace: true })
    }, 300)
})

const colorClass = (color) => colorOptions.find((option) => option.value === color)?.class || colorOptions[4].class

const openCreate = () => {
    editing.value = null
    form.reset()
    form.color = 'gray'
    form.active = true
    form.order = 0
    form.clearErrors()
    showModal.value = true
}

const openEdit = (status) => {
    editing.value = status
    form.name = status.name
    form.color = status.color
    form.active = status.active
    form.order = status.order
    form.clearErrors()
    showModal.value = true
}

const submit = () => {
    const options = { onSuccess: () => { showModal.value = false } }
    if (editing.value) return form.put(`/estados/${editing.value.id}`, options)
    form.post('/estados', options)
}

const destroy = async (status) => {
    if (!await confirmDanger({ title: 'Eliminar estado', text: `Se eliminara el estado "${status.name}".`, confirmText: 'Si, eliminar' })) return
    router.delete(`/estados/${status.id}`)
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl space-y-6">
            <PageHeader eyebrow="Flujo" title="Estados" description="Controla estados operativos con colores legibles y componentes totalmente compatibles con dark mode.">
                <template #actions>
                    <button type="button" class="app-button-primary" @click="openCreate">Nuevo estado</button>
                </template>
            </PageHeader>

            <BaseCard>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Busqueda</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Filtra rapidamente sin perder consistencia visual.</p>
                    </div>
                    <div class="w-full max-w-md">
                        <input v-model="search" type="text" placeholder="Buscar estado..." class="app-input" />
                    </div>
                </div>
            </BaseCard>

            <DataTable client-table :columns="columns" :data="statuses">
                <template #cell-order="{ value }">
                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ value }}</span>
                </template>
                <template #cell-name="{ row }">
                    <StatusBadge :status="row.name" :color="row.color" />
                </template>
                <template #cell-color="{ value }">
                    <span class="capitalize text-slate-600 dark:text-slate-300">{{ value }}</span>
                </template>
                <template #cell-active="{ value }">
                    <StatusBadge :status="value ? 'Si' : 'No'" :color="value ? 'green' : 'red'" />
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <button type="button" class="app-button-secondary px-3 py-2 text-xs" @click="openEdit(row)">Editar</button>
                        <button type="button" class="app-button-danger px-3 py-2 text-xs" @click="destroy(row)">Eliminar</button>
                    </div>
                </template>
                <template #empty>No se encontraron estados.</template>
            </DataTable>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                <div class="surface-card relative z-10 w-full max-w-xl p-6">
                    <div class="mb-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600 dark:text-brand-300">Estado</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950 dark:text-slate-100">{{ editing ? 'Editar estado' : 'Nuevo estado' }}</h3>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nombre</label>
                                <input v-model="form.name" type="text" class="app-input" />
                                <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Orden</label>
                                <input v-model.number="form.order" type="number" class="app-input" />
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Color</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="color in colorOptions"
                                    :key="color.value"
                                    type="button"
                                    class="rounded-full border px-3 py-2 text-xs font-medium transition-all"
                                    :class="[colorClass(color.value), form.color === color.value ? 'ring-2 ring-brand-400 ring-offset-2 ring-offset-white dark:ring-offset-slate-950' : '']"
                                    @click="form.color = color.value"
                                >
                                    {{ color.label }}
                                </button>
                            </div>
                        </div>

                        <label class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300">
                            <input v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800" />
                            Estado activo
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
