<script setup>
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({
    seals: Object,
    filters: Object,
    entities: Array,
    files: Array,
    incidentTypes: Array,
})

const search = ref(props.filters?.search || '')
const entityFilter = ref(props.filters?.entity_id || '')
const incidentFilter = ref(props.filters?.incident_type_id || '')
const showModal = ref(false)
const editing = ref(null)
const showExportModal = ref(false)
const exportFilters = ref({
    search: props.filters?.search || '',
    entity_id: props.filters?.entity_id || '',
    incident_type_id: props.filters?.incident_type_id || '',
    date_from: '',
    date_to: '',
    format: 'csv',
})

const form = useForm({
    entity_id: '',
    equipment_file_id: '',
    incident_type_id: '',
    inventory_number: '',
    removed_seal: '',
    applied_seal: '',
    reason: '',
    date: new Date().toISOString().slice(0, 10),
    time: new Date().toTimeString().slice(0, 5),
    performed_by: '',
})

const availableFiles = computed(() => {
    if (!form.entity_id) return props.files
    return props.files.filter((file) => String(file.entity_id) === String(form.entity_id))
})

const columns = [
    { key: 'equipment_file', label: 'Expediente' },
    { key: 'incident_type', label: 'Incidencia' },
    { key: 'seals', label: 'Sellos' },
    { key: 'reason', label: 'Motivo' },
    { key: 'executed', label: 'Fecha y hora' },
    { key: 'actions', label: 'Acciones' },
]

let timeout
const applyFilters = () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/sellos', {
            search: search.value || undefined,
            entity_id: entityFilter.value || undefined,
            incident_type_id: incidentFilter.value || undefined,
        }, { preserveState: true, replace: true })
    }, 250)
}

watch(search, applyFilters)
watch(entityFilter, applyFilters)
watch(incidentFilter, applyFilters)

watch(() => form.equipment_file_id, (value) => {
    const file = props.files.find((item) => String(item.id) === String(value))
    if (file) {
        form.inventory_number = file.inventory_number
        form.entity_id = file.entity_id
    }
})

const openCreate = () => {
    editing.value = null
    form.reset()
    form.date = new Date().toISOString().slice(0, 10)
    form.time = new Date().toTimeString().slice(0, 5)
    form.clearErrors()
    showModal.value = true
}

const openEdit = (seal) => {
    editing.value = seal
    form.entity_id = seal.entity_id
    form.equipment_file_id = seal.equipment_file_id || ''
    form.incident_type_id = seal.incident_type_id || ''
    form.inventory_number = seal.inventory_number || ''
    form.removed_seal = seal.removed_seal || ''
    form.applied_seal = seal.applied_seal || ''
    form.reason = seal.reason
    form.date = seal.date
    form.time = (seal.time || '').slice(0, 5)
    form.performed_by = seal.performed_by || ''
    form.clearErrors()
    showModal.value = true
}

const submit = () => {
    const options = { onSuccess: () => { showModal.value = false } }
    if (editing.value) return form.put(`/sellos/${editing.value.id}`, options)
    form.post('/sellos', options)
}

const destroy = async (seal) => {
    if (!await confirmDanger({ title: 'Eliminar registro de sello', text: 'Se eliminara el control seleccionado.', confirmText: 'Si, eliminar' })) return
    router.delete(`/sellos/${seal.id}`)
}

const exportData = () => {
    const params = new URLSearchParams()
    params.set('format', exportFilters.value.format || 'csv')
    Object.entries(exportFilters.value).forEach(([key, value]) => {
        if (key === 'format') return
        if (value !== '' && value !== null && value !== undefined) params.set(key, value)
    })
    window.open(`/sellos/exportar?${params.toString()}`, '_blank')
    showExportModal.value = false
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <PageHeader eyebrow="Control" title="Sellos" description="Administra retiros y aplicaciones de sellos con motivo, incidencia, fecha, hora y responsable.">
                <template #actions>
                    <button type="button" class="app-button-secondary" @click="showExportModal = true">Exportar</button>
                    <button type="button" class="app-button-primary" @click="openCreate">Nuevo registro</button>
                </template>
            </PageHeader>

            <BaseCard>
                <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px_220px]">
                    <input v-model="search" type="text" class="app-input" placeholder="Buscar por sello, motivo, inventario o responsable..." />
                    <select v-model="entityFilter" class="app-select">
                        <option value="">Todas las entidades</option>
                        <option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option>
                    </select>
                    <select v-model="incidentFilter" class="app-select">
                        <option value="">Todas las incidencias</option>
                        <option v-for="type in incidentTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                    </select>
                </div>
            </BaseCard>

            <DataTable client-table :columns="columns" :data="seals">
                <template #cell-equipment_file="{ row }">
                    <div>
                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ row.equipment_file?.file_number || 'Sin expediente' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.inventory_number }}</p>
                    </div>
                </template>
                <template #cell-incident_type="{ row }">
                    <StatusBadge :status="row.incident_type?.name || 'Sin clasificar'" :color="row.incident_type ? 'blue' : 'gray'" />
                </template>
                <template #cell-seals="{ row }">
                    <div class="space-y-1 text-xs">
                        <p><span class="font-semibold text-slate-700 dark:text-slate-200">Retirado:</span> {{ row.removed_seal || '-' }}</p>
                        <p><span class="font-semibold text-slate-700 dark:text-slate-200">Aplicado:</span> {{ row.applied_seal || '-' }}</p>
                    </div>
                </template>
                <template #cell-reason="{ row }">
                    <div>
                        <p class="text-slate-700 dark:text-slate-200">{{ row.reason }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Por {{ row.performed_by || 'Sistema' }}</p>
                    </div>
                </template>
                <template #cell-executed="{ row }">
                    <span class="text-slate-600 dark:text-slate-300">{{ row.date }} {{ row.time?.slice(0, 5) }}</span>
                </template>
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
                <div class="surface-card relative z-10 w-full max-w-3xl p-6">
                    <h3 class="mb-5 text-xl font-semibold text-slate-950 dark:text-slate-100">{{ editing ? 'Editar control de sello' : 'Nuevo control de sello' }}</h3>
                    <form class="space-y-5" @submit.prevent="submit">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Entidad *</label>
                                <select v-model="form.entity_id" class="app-select">
                                    <option value="">Seleccionar...</option>
                                    <option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option>
                                </select>
                                <p v-if="form.errors.entity_id" class="text-xs text-rose-500">{{ form.errors.entity_id }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Expediente</label>
                                <select v-model="form.equipment_file_id" class="app-select">
                                    <option value="">Sin expediente asociado</option>
                                    <option v-for="file in availableFiles" :key="file.id" :value="file.id">{{ file.file_number }} - {{ file.inventory_number }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tipo de incidencia</label>
                                <select v-model="form.incident_type_id" class="app-select">
                                    <option value="">Seleccionar...</option>
                                    <option v-for="type in incidentTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">No. inventario</label>
                                <input v-model="form.inventory_number" type="text" class="app-input" />
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Sello retirado</label>
                                <input v-model="form.removed_seal" type="text" class="app-input" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Sello aplicado</label>
                                <input v-model="form.applied_seal" type="text" class="app-input" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Motivo *</label>
                            <textarea v-model="form.reason" rows="3" class="app-input"></textarea>
                            <p v-if="form.errors.reason" class="text-xs text-rose-500">{{ form.errors.reason }}</p>
                        </div>

                        <div class="grid gap-5 md:grid-cols-3">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Fecha *</label>
                                <input v-model="form.date" type="date" class="app-input" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Hora *</label>
                                <input v-model="form.time" type="time" class="app-input" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Realizado por</label>
                                <input v-model="form.performed_by" type="text" class="app-input" />
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800">
                            <button type="button" class="app-button-secondary" @click="showModal = false">Cancelar</button>
                            <button type="submit" class="app-button-primary" :disabled="form.processing">{{ form.processing ? 'Guardando...' : 'Guardar' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
        <Teleport to="body">
            <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showExportModal = false" />
                <div class="surface-card relative z-10 w-full max-w-2xl p-6">
                    <h3 class="mb-4 text-xl font-semibold text-slate-950 dark:text-slate-100">Exportar control de sellos</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <input v-model="exportFilters.search" type="text" class="app-input" placeholder="Texto de busqueda" />
                        <select v-model="exportFilters.entity_id" class="app-select"><option value="">Todas las entidades</option><option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option></select>
                        <select v-model="exportFilters.incident_type_id" class="app-select"><option value="">Todas las incidencias</option><option v-for="type in incidentTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select>
                        <div></div>
                        <input v-model="exportFilters.date_from" type="date" class="app-input" />
                        <input v-model="exportFilters.date_to" type="date" class="app-input" />
                        <div class="md:col-span-2 space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Formato de archivo</label>
                            <select v-model="exportFilters.format" class="app-select">
                                <option value="csv">CSV (UTF-8)</option>
                                <option value="xlsx">Excel (.xlsx)</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" class="app-button-secondary" @click="showExportModal = false">Cancelar</button>
                        <button type="button" class="app-button-primary" @click="exportData">Descargar</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
