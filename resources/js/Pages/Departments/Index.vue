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
    departments: Object,
    entities: Array,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const entityFilter = ref(props.filters?.entity_id ? String(props.filters.entity_id) : '')
const showModal = ref(false)
const showExportModal = ref(false)
const editing = ref(null)
const exportFormat = ref('csv')

const form = useForm({
    entity_id: '',
    name: '',
    telefono: '',
    code: '',
    codigo_area: '',
    codigo_entidad: '',
    active: true,
})

const columns = [
    { key: 'entity', label: 'Entidad', sortValue: (row) => row.entity?.name || '' },
    { key: 'name', label: 'Nombre' },
    { key: 'telefono', label: 'Teléfono', sortValue: (row) => row.telefono || '' },
    { key: 'code', label: 'Código' },
    { key: 'codigo_area', label: 'Cód. área', sortValue: (row) => row.codigo_area || '' },
    { key: 'codigo_entidad', label: 'Cód. entidad', sortValue: (row) => row.codigo_entidad || '' },
    { key: 'active', label: 'Estado' },
    { key: 'expedientes', label: 'Expedientes', sortable: false },
    { key: 'actions', label: 'Acciones', sortable: false },
]

let searchTimeout
watch(search, (value) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get('/departamentos', {
            search: value || undefined,
            entity_id: entityFilter.value || undefined,
        }, { preserveState: true, replace: true })
    }, 300)
})

watch(entityFilter, (value) => {
    router.get('/departamentos', {
        search: search.value || undefined,
        entity_id: value || undefined,
    }, { preserveState: true, replace: true })
})

watch(() => props.filters?.search, (v) => {
    if (v !== undefined && v !== search.value) search.value = v || ''
})

watch(() => props.filters?.entity_id, (v) => {
    const s = v != null && v !== '' ? String(v) : ''
    if (s !== entityFilter.value) entityFilter.value = s
})

const openCreate = () => {
    editing.value = null
    form.reset()
    form.active = true
    form.entity_id = ''
    form.clearErrors()
    showModal.value = true
}

const openEdit = (row) => {
    editing.value = row
    form.entity_id = row.entity_id
    form.name = row.name
    form.telefono = row.telefono || ''
    form.code = row.code
    form.codigo_area = row.codigo_area || ''
    form.codigo_entidad = row.codigo_entidad || ''
    form.active = row.active !== false
    form.clearErrors()
    showModal.value = true
}

const submit = () => {
    const options = { onSuccess: () => { showModal.value = false } }
    if (editing.value) return form.put(`/departamentos/${editing.value.id}`, options)
    form.post('/departamentos', options)
}

const destroy = async (row) => {
    if (!await confirmDanger({ title: 'Eliminar departamento', text: `Se eliminará el departamento «${row.name}».`, confirmText: 'Sí, eliminar' })) return
    router.delete(`/departamentos/${row.id}`)
}

const exportUrl = () => {
    const params = new URLSearchParams({ format: exportFormat.value })
    if (search.value) params.set('search', search.value)
    if (entityFilter.value) params.set('entity_id', entityFilter.value)
    return `${route('departamentos.export')}?${params.toString()}`
}

const downloadExport = () => {
    window.open(exportUrl(), '_blank')
    showExportModal.value = false
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader eyebrow="Organización" title="Departamentos" description="Áreas u oficinas por entidad, con códigos de sincronización (RODAS), teléfono fijo para facturas ETECSA y listado paginado.">
                <template #actions>
                    <button type="button" class="app-button-secondary" @click="showExportModal = true">Exportar</button>
                    <button type="button" class="app-button-primary" @click="openCreate">Nuevo departamento</button>
                </template>
            </PageHeader>

            <BaseCard>
                <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Búsqueda</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Filtra por texto, entidad o código; la búsqueda se aplica al volver a escribir (debounce).</p>
                    </div>
                    <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <div class="w-full sm:max-w-xs">
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Entidad</label>
                            <select v-model="entityFilter" class="app-select w-full">
                                <option value="">Todas las entidades</option>
                                <option v-for="e in entities" :key="e.id" :value="String(e.id)">{{ e.name }}</option>
                            </select>
                        </div>
                        <div class="w-full sm:max-w-md">
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Texto</label>
                            <input v-model="search" type="search" placeholder="Nombre, código, teléfono, cód. RODAS, entidad…" class="app-input w-full" autocomplete="off" />
                        </div>
                    </div>
                </div>
            </BaseCard>

            <DataTable client-table :columns="columns" :data="departments">
                <template #cell-entity="{ row }">
                    <div>
                        <span class="font-medium text-slate-900 dark:text-slate-100">{{ row.entity?.name }}</span>
                        <span v-if="row.entity?.code" class="ml-2 font-mono text-xs text-slate-500 dark:text-slate-400">({{ row.entity.code }})</span>
                    </div>
                </template>
                <template #cell-name="{ value }">
                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ value }}</span>
                </template>
                <template #cell-telefono="{ value }">
                    <span class="font-mono text-xs text-slate-600 dark:text-slate-400">{{ value || '—' }}</span>
                </template>
                <template #cell-code="{ value }">
                    <span class="font-mono text-xs text-slate-600 dark:text-slate-300">{{ value }}</span>
                </template>
                <template #cell-codigo_area="{ value }">
                    <span class="font-mono text-xs text-slate-600 dark:text-slate-400">{{ value || '—' }}</span>
                </template>
                <template #cell-codigo_entidad="{ value }">
                    <span class="font-mono text-xs text-slate-600 dark:text-slate-400">{{ value || '—' }}</span>
                </template>
                <template #cell-active="{ value }">
                    <StatusBadge :status="value ? 'Activo' : 'Inactivo'" :color="value ? 'green' : 'red'" />
                </template>
                <template #cell-expedientes="{ row }">
                    <span class="inline-flex min-w-[2rem] justify-center font-medium text-slate-700 dark:text-slate-200">{{ row.equipment_files_count }}</span>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <button type="button" class="app-button-secondary px-3 py-2 text-xs" @click="openEdit(row)">Editar</button>
                        <button type="button" class="app-button-danger px-3 py-2 text-xs" @click="destroy(row)">Eliminar</button>
                    </div>
                </template>
                <template #empty>No se encontraron departamentos.</template>
            </DataTable>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                <div class="surface-card relative z-10 max-h-[90vh] w-full max-w-2xl overflow-y-auto p-6">
                    <div class="mb-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600 dark:text-brand-300">Departamento</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950 dark:text-slate-100">{{ editing ? 'Editar departamento' : 'Nuevo departamento' }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">El teléfono fijo permite emparejar líneas en facturas ETECSA importadas. Los códigos de área y entidad suelen rellenarse al sincronizar con RODAS.</p>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Entidad</label>
                            <select v-model="form.entity_id" class="app-select w-full" required>
                                <option value="" disabled>Seleccionar entidad</option>
                                <option v-for="e in entities" :key="e.id" :value="e.id">{{ e.name }}</option>
                            </select>
                            <p v-if="form.errors.entity_id" class="text-xs text-rose-500">{{ form.errors.entity_id }}</p>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="space-y-2 md:col-span-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nombre</label>
                                <input v-model="form.name" type="text" class="app-input w-full" required />
                                <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Código</label>
                                <input v-model="form.code" type="text" class="app-input w-full" />
                                <p v-if="form.errors.code" class="text-xs text-rose-500">{{ form.errors.code }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Teléfono fijo</label>
                                <input v-model="form.telefono" type="text" class="app-input w-full font-mono" placeholder="Opcional" />
                                <p v-if="form.errors.telefono" class="text-xs text-rose-500">{{ form.errors.telefono }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Cód. área (RODAS)</label>
                                <input v-model="form.codigo_area" type="text" class="app-input w-full font-mono" placeholder="Opcional" />
                                <p v-if="form.errors.codigo_area" class="text-xs text-rose-500">{{ form.errors.codigo_area }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Cód. entidad (RODAS)</label>
                                <input v-model="form.codigo_entidad" type="text" class="app-input w-full font-mono" placeholder="Opcional" />
                                <p v-if="form.errors.codigo_entidad" class="text-xs text-rose-500">{{ form.errors.codigo_entidad }}</p>
                            </div>
                        </div>

                        <label class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300">
                            <input v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800" />
                            Departamento activo
                        </label>

                        <div class="flex justify-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800">
                            <button type="button" class="app-button-secondary" @click="showModal = false">Cancelar</button>
                            <button type="submit" :disabled="form.processing" class="app-button-primary">{{ form.processing ? 'Guardando…' : 'Guardar' }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showExportModal = false" />
                <div class="surface-card relative z-10 w-full max-w-md p-6">
                    <h3 class="mb-2 text-xl font-semibold text-slate-950 dark:text-slate-100">Exportar departamentos</h3>
                    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Se aplican el filtro de entidad y la búsqueda actual a la descarga completa.</p>
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Formato</label>
                            <select v-model="exportFormat" class="app-select w-full">
                                <option value="csv">CSV (UTF-8)</option>
                                <option value="xlsx">Excel (.xlsx)</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" class="app-button-secondary" @click="showExportModal = false">Cancelar</button>
                        <button type="button" class="app-button-primary" @click="downloadExport">Descargar</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
