<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({ speeds: Object, filters: Object })

const showModal = ref(false)
const editing  = ref(null)
const search   = ref(props.filters?.search || '')

const form = useForm({
    nombre: '',
    kbps:   '',
    activo: true,
})

const columns = [
    { key: 'nombre', label: 'Velocidad'         },
    { key: 'kbps',   label: 'Valor (Kbps)'      },
    { key: 'activo', label: 'Estado'             },
    { key: 'actions', label: 'Acciones'          },
]

let timeout
const applySearch = () => {
    clearTimeout(timeout)
    timeout = setTimeout(
        () => router.get('/velocidades', { search: search.value || undefined }, { preserveState: true, replace: true }),
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
    form.nombre = row.nombre
    form.kbps   = row.kbps ?? ''
    form.activo = row.activo
    form.clearErrors()
    showModal.value = true
}

const submit = () =>
    editing.value
        ? form.put(`/velocidades/${editing.value.id}`, { onSuccess: () => (showModal.value = false) })
        : form.post('/velocidades', { onSuccess: () => (showModal.value = false) })

const destroy = async (row) => {
    if (!await confirmDanger({
        title:       'Eliminar velocidad',
        text:        `Se eliminará "${row.nombre}".`,
        confirmText: 'Sí, eliminar',
    })) return
    router.delete(`/velocidades/${row.id}`)
}

/** Formatea Kbps de forma legible */
const formatKbps = (kbps) => {
    if (!kbps) return '—'
    if (kbps >= 1024 && kbps % 1024 === 0) return `${kbps / 1024} Mbps`
    return `${kbps} Kbps`
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <PageHeader
                eyebrow="Nomencladores · Conectividad"
                title="Velocidades Contratadas"
                description="Catálogo de velocidades de línea ADSL. Unifica los valores del Excel para obtener reportes comparables. Se auto-completa durante la importación."
            >
                <template #actions>
                    <button class="app-button-primary" @click="openCreate">Nueva velocidad</button>
                </template>
            </PageHeader>

            <BaseCard>
                <input
                    v-model="search"
                    type="text"
                    class="app-input"
                    placeholder="Buscar velocidad…"
                    @input="applySearch"
                />
            </BaseCard>

            <DataTable client-table :columns="columns" :data="speeds">
                <template #cell-nombre="{ value }">
                    <span class="font-semibold text-slate-900 dark:text-slate-100">{{ value }}</span>
                </template>
                <template #cell-kbps="{ row }">
                    <span class="font-mono text-slate-500 dark:text-slate-400 text-sm">{{ formatKbps(row.kbps) }}</span>
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
                        {{ editing ? 'Editar velocidad' : 'Nueva velocidad contratada' }}
                    </h3>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-1.5">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                Nombre canónico <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="form.nombre"
                                type="text"
                                class="app-input"
                                placeholder="Ej: 8 Mbps"
                                maxlength="50"
                            />
                            <p class="text-xs text-slate-400">Debe coincidir exactamente con el valor del Excel para unificar.</p>
                            <p v-if="form.errors.nombre" class="text-xs text-rose-500">{{ form.errors.nombre }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                Valor en Kbps
                                <span class="font-normal text-slate-400">(opcional — para ordenar y comparar)</span>
                            </label>
                            <input
                                v-model.number="form.kbps"
                                type="number"
                                min="1"
                                class="app-input font-mono"
                                placeholder="Ej: 8192"
                            />
                            <p v-if="form.errors.kbps" class="text-xs text-rose-500">{{ form.errors.kbps }}</p>
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
