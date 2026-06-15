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
    entities: Object,
    municipios: Array,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const showModal = ref(false)
const editing = ref(null)
const syncingExternal = ref(false)

const form = useForm({
    name: '',
    code: '',
    municipio_id: '',
    active: true,
})

const columns = [
    { key: 'code', label: 'Codigo' },
    { key: 'name', label: 'Entidad' },
    { key: 'municipio', label: 'Municipio' },
    { key: 'metrics', label: 'Carga' },
    { key: 'active', label: 'Estado' },
    { key: 'actions', label: 'Acciones' },
]

let timeout
watch(search, (value) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/entidades', { search: value || undefined }, { preserveState: true, replace: true })
    }, 300)
})

const openCreate = () => {
    editing.value = null
    form.reset()
    form.active = true
    form.clearErrors()
    showModal.value = true
}

const openEdit = (entity) => {
    editing.value = entity
    form.name = entity.name
    form.code = entity.code
    form.municipio_id = entity.municipio_id || ''
    form.active = entity.active
    form.clearErrors()
    showModal.value = true
}

const submit = () => {
    const options = { onSuccess: () => { showModal.value = false } }
    if (editing.value) return form.put(`/entidades/${editing.value.id}`, options)
    form.post('/entidades', options)
}

const destroy = async (entity) => {
    if (!await confirmDanger({ title: 'Eliminar entidad', text: `Se eliminara la entidad "${entity.name}".`, confirmText: 'Si, eliminar' })) return
    router.delete(`/entidades/${entity.id}`)
}

const syncExternal = async () => {
    if (!await confirmDanger({
        title: 'Sincronizar entidades externas',
        text: 'Se crearan las entidades que no existen y se actualizaran codigos desde PostgreSQL externo.',
        confirmText: 'Si, sincronizar',
    })) return

    syncingExternal.value = true
    router.post('/entidades/sync-external', {}, {
        preserveScroll: true,
        onFinish: () => { syncingExternal.value = false },
    })
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <PageHeader eyebrow="Organizacion" title="Entidades" description="Administra entidades, municipio asociado y carga operativa con una interfaz consistente en dark mode.">
                <template #actions>
                    <button type="button" class="app-button-secondary" :disabled="syncingExternal" @click="syncExternal">
                        {{ syncingExternal ? 'Sincronizando...' : 'Sincronizar externo' }}
                    </button>
                    <button type="button" class="app-button-primary" @click="openCreate">Nueva entidad</button>
                </template>
            </PageHeader>

            <BaseCard>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Busqueda</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Localiza entidades por nombre o codigo sin componentes lavados en oscuro.</p>
                    </div>
                    <div class="w-full max-w-md">
                        <input v-model="search" type="text" placeholder="Buscar entidad..." class="app-input" />
                    </div>
                </div>
            </BaseCard>

            <DataTable client-table :columns="columns" :data="entities">
                <template #cell-code="{ value }">
                    <span class="font-mono text-xs font-semibold tracking-wide text-slate-500 dark:text-slate-300">{{ value }}</span>
                </template>
                <template #cell-name="{ row }">
                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ row.name }}</span>
                </template>
                <template #cell-municipio="{ row }">
                    <span class="text-slate-600 dark:text-slate-300">{{ row.municipio?.name || '-' }}</span>
                </template>
                <template #cell-metrics="{ row }">
                    <div class="flex flex-wrap gap-2">
                        <span class="app-badge border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            Deptos: {{ row.departments_count }}
                        </span>
                        <span class="app-badge border-brand-200 bg-brand-50 text-brand-700 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-200">
                            Expedientes: {{ row.equipment_files_count }}
                        </span>
                    </div>
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
                <template #empty>No se encontraron entidades.</template>
            </DataTable>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                <div class="surface-card relative z-10 w-full max-w-xl p-6">
                    <div class="mb-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600 dark:text-brand-300">Entidad</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950 dark:text-slate-100">{{ editing ? 'Editar entidad' : 'Nueva entidad' }}</h3>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
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

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Municipio</label>
                            <select v-model="form.municipio_id" class="app-select">
                                <option value="">Sin municipio</option>
                                <option v-for="municipio in municipios" :key="municipio.id" :value="municipio.id">{{ municipio.name }}</option>
                            </select>
                        </div>

                        <label class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300">
                            <input v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800" />
                            Entidad activa
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
