<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({ modes: Object, filters: Object })

const showModal = ref(false)
const editing  = ref(null)
const search   = ref(props.filters?.search || '')

const form = useForm({
    code:   '',
    nombre: '',
    activo: true,
})

const columns = [
    { key: 'code',    label: 'Código' },
    { key: 'nombre',  label: 'Nombre' },
    { key: 'activo',  label: 'Estado' },
    { key: 'actions', label: 'Acciones' },
]

let timeout
const applySearch = () => {
    clearTimeout(timeout)
    timeout = setTimeout(
        () => router.get('/modos-adsl', { search: search.value || undefined }, { preserveState: true, replace: true }),
        300
    )
}

const openCreate = () => {
    editing.value = null
    form.reset()
    form.activo = true
    form.clearErrors()
    showModal.value = true
}

const openEdit = (row) => {
    editing.value = row
    form.code   = row.code
    form.nombre = row.nombre
    form.activo = row.activo
    form.clearErrors()
    showModal.value = true
}

const submit = () =>
    editing.value
        ? form.put(`/modos-adsl/${editing.value.id}`, { onSuccess: () => (showModal.value = false) })
        : form.post('/modos-adsl', { onSuccess: () => (showModal.value = false) })

const destroy = async (row) => {
    if (!await confirmDanger({
        title:       'Eliminar modo ADSL',
        text:        `Se eliminará "${row.code} – ${row.nombre}".`,
        confirmText: 'Sí, eliminar',
    })) return
    router.delete(`/modos-adsl/${row.id}`)
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <PageHeader
                eyebrow="Nomencladores · Conectividad"
                title="Modos ADSL"
                description="Catálogo de modos de línea ADSL (ED, LC, FR y otros). Se pobla automáticamente durante la importación del Excel."
            >
                <template #actions>
                    <button class="app-button-primary" @click="openCreate">Nuevo modo</button>
                </template>
            </PageHeader>

            <BaseCard>
                <input
                    v-model="search"
                    type="text"
                    class="app-input"
                    placeholder="Buscar por código o nombre…"
                    @input="applySearch"
                />
            </BaseCard>

            <DataTable client-table :columns="columns" :data="modes">
                <template #cell-code="{ value }">
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">{{ value }}</span>
                </template>
                <template #cell-nombre="{ value }">
                    <span class="text-slate-700 dark:text-slate-300">{{ value }}</span>
                </template>
                <template #cell-activo="{ value }">
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
                        {{ editing ? 'Editar modo ADSL' : 'Nuevo modo ADSL' }}
                    </h3>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Código <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="form.code"
                                    type="text"
                                    class="app-input font-mono uppercase"
                                    placeholder="Ej: ED"
                                    maxlength="20"
                                    :disabled="!!editing"
                                />
                                <p v-if="form.errors.code" class="text-xs text-rose-500">{{ form.errors.code }}</p>
                                <p v-if="editing" class="text-xs text-slate-400">El código no se puede modificar.</p>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Nombre descriptivo <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="form.nombre"
                                    type="text"
                                    class="app-input"
                                    placeholder="Ej: Enlace Directo"
                                    maxlength="100"
                                />
                                <p v-if="form.errors.nombre" class="text-xs text-rose-500">{{ form.errors.nombre }}</p>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="form.activo" type="checkbox" class="h-4 w-4 rounded" />
                            Activo
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
