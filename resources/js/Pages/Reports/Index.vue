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
    reports: Object,
    filters: Object,
    entities: Array,
    departments: Array,
    files: Array,
    incidentTypes: Array,
    statuses: Array,
    priorities: Array,
})

const search = ref(props.filters?.search || '')
const entityFilter = ref(props.filters?.entity_id || '')
const statusFilter = ref(props.filters?.status || '')
const incidentFilter = ref(props.filters?.incident_type_id || '')
const showModal = ref(false)
const editing = ref(null)
const showExportModal = ref(false)
const exportFilters = ref({
    search: props.filters?.search || '',
    entity_id: props.filters?.entity_id || '',
    status: props.filters?.status || '',
    incident_type_id: props.filters?.incident_type_id || '',
    priority: '',
    date_from: '',
    date_to: '',
    format: 'csv',
})

const form = useForm({
    entity_id: '',
    department_id: '',
    equipment_file_id: '',
    incident_type_id: '',
    title: '',
    description: '',
    reported_by: '',
    status: 'Abierto',
    priority: 'Media',
})

const availableDepartments = computed(() => {
    if (!form.entity_id) return props.departments
    return props.departments.filter((department) => String(department.entity_id) === String(form.entity_id))
})

const availableFiles = computed(() => {
    if (!form.entity_id) return props.files
    return props.files.filter((file) => String(file.entity_id) === String(form.entity_id))
})

const columns = [
    { key: 'ticket_number', label: 'Ticket' },
    { key: 'title', label: 'Reporte' },
    { key: 'incident', label: 'Incidencia' },
    { key: 'context', label: 'Contexto' },
    { key: 'status', label: 'Estado' },
    { key: 'actions', label: 'Acciones' },
]

let timeout
const applyFilters = () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/reportes', {
            search: search.value || undefined,
            entity_id: entityFilter.value || undefined,
            status: statusFilter.value || undefined,
            incident_type_id: incidentFilter.value || undefined,
        }, { preserveState: true, replace: true })
    }, 250)
}

watch(search, applyFilters)
watch(entityFilter, applyFilters)
watch(statusFilter, applyFilters)
watch(incidentFilter, applyFilters)

const openCreate = () => {
    editing.value = null
    form.reset()
    form.status = 'Abierto'
    form.priority = 'Media'
    form.clearErrors()
    showModal.value = true
}

const openEdit = (report) => {
    editing.value = report
    form.entity_id = report.entity_id
    form.department_id = report.department_id || ''
    form.equipment_file_id = report.equipment_file_id || ''
    form.incident_type_id = report.incident_type_id || ''
    form.title = report.title
    form.description = report.description
    form.reported_by = report.reported_by
    form.status = report.status
    form.priority = report.priority
    form.clearErrors()
    showModal.value = true
}

const submit = () => {
    const options = { onSuccess: () => { showModal.value = false } }
    if (editing.value) return form.put(`/reportes/${editing.value.id}`, options)
    form.post('/reportes', options)
}

const destroy = async (report) => {
    if (!await confirmDanger({ title: 'Eliminar reporte', text: `Se eliminara el ticket ${report.ticket_number}.`, confirmText: 'Si, eliminar' })) return
    router.delete(`/reportes/${report.id}`)
}

const statusColor = (status) => ({
    Abierto: 'red',
    'En progreso': 'yellow',
    Cerrado: 'green',
}[status] || 'gray')

const priorityColor = (priority) => ({
    Baja: 'gray',
    Media: 'blue',
    Alta: 'orange',
    Critica: 'red',
}[priority] || 'gray')

const exportData = () => {
    const params = new URLSearchParams()
    params.set('format', exportFilters.value.format || 'csv')
    Object.entries(exportFilters.value).forEach(([key, value]) => {
        if (key === 'format') return
        if (value !== '' && value !== null && value !== undefined) params.set(key, value)
    })
    window.open(`/reportes/exportar?${params.toString()}`, '_blank')
    showExportModal.value = false
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <PageHeader eyebrow="Reportes" title="Tickets de Soporte" description="Controla quejas y reportes de usuarios como tickets del departamento de sistemas.">
                <template #actions>
                    <button type="button" class="app-button-secondary" @click="showExportModal = true">Exportar</button>
                    <button type="button" class="app-button-primary" @click="openCreate">Nuevo ticket</button>
                    <a href="/reportes-incidencias" class="app-button-secondary">Ver incidencias de sellos</a>
                </template>
            </PageHeader>

            <BaseCard>
                <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px_220px_220px]">
                    <input v-model="search" type="text" class="app-input" placeholder="Buscar por ticket, asunto, descripcion o reportado por..." />
                    <select v-model="entityFilter" class="app-select">
                        <option value="">Todas las entidades</option>
                        <option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option>
                    </select>
                    <select v-model="statusFilter" class="app-select">
                        <option value="">Todos los estados</option>
                        <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                    </select>
                    <select v-model="incidentFilter" class="app-select">
                        <option value="">Todas las incidencias</option>
                        <option v-for="type in incidentTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                    </select>
                </div>
            </BaseCard>

            <DataTable client-table :columns="columns" :data="reports">
                <template #cell-ticket_number="{ row }">
                    <div>
                        <p class="font-mono text-xs font-semibold text-slate-700 dark:text-slate-200">{{ row.ticket_number }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.created_at?.slice(0, 10) }}</p>
                    </div>
                </template>
                <template #cell-title="{ row }">
                    <div>
                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ row.title }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Reportado por {{ row.reported_by }}</p>
                    </div>
                </template>
                <template #cell-incident="{ row }">
                    <div class="space-y-2">
                        <StatusBadge :status="row.incident_type?.name || 'Sin clasificar'" :color="row.incident_type ? 'blue' : 'gray'" />
                        <StatusBadge :status="row.priority" :color="priorityColor(row.priority)" />
                    </div>
                </template>
                <template #cell-context="{ row }">
                    <div class="space-y-1">
                        <p class="text-slate-700 dark:text-slate-200">{{ row.entity?.name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.department?.name || 'Sin departamento' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.equipment_file?.file_number || 'Sin expediente asociado' }}</p>
                    </div>
                </template>
                <template #cell-status="{ row }">
                    <StatusBadge :status="row.status" :color="statusColor(row.status)" />
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
                <div class="surface-card relative z-10 w-full max-w-4xl p-6">
                    <h3 class="mb-5 text-xl font-semibold text-slate-950 dark:text-slate-100">{{ editing ? 'Editar ticket' : 'Nuevo ticket' }}</h3>
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
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Departamento</label>
                                <select v-model="form.department_id" class="app-select">
                                    <option value="">Seleccionar...</option>
                                    <option v-for="department in availableDepartments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-3">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Expediente</label>
                                <select v-model="form.equipment_file_id" class="app-select">
                                    <option value="">Sin expediente asociado</option>
                                    <option v-for="file in availableFiles" :key="file.id" :value="file.id">{{ file.file_number }} - {{ file.inventory_number }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tipo de incidencia</label>
                                <select v-model="form.incident_type_id" class="app-select">
                                    <option value="">Seleccionar...</option>
                                    <option v-for="type in incidentTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Reportado por *</label>
                                <input v-model="form.reported_by" type="text" class="app-input" />
                                <p v-if="form.errors.reported_by" class="text-xs text-rose-500">{{ form.errors.reported_by }}</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Asunto *</label>
                            <input v-model="form.title" type="text" class="app-input" />
                            <p v-if="form.errors.title" class="text-xs text-rose-500">{{ form.errors.title }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Descripcion *</label>
                            <textarea v-model="form.description" rows="4" class="app-input"></textarea>
                            <p v-if="form.errors.description" class="text-xs text-rose-500">{{ form.errors.description }}</p>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Estado *</label>
                                <select v-model="form.status" class="app-select">
                                    <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Prioridad *</label>
                                <select v-model="form.priority" class="app-select">
                                    <option v-for="priority in priorities" :key="priority" :value="priority">{{ priority }}</option>
                                </select>
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
                <div class="surface-card relative z-10 w-full max-w-3xl p-6">
                    <h3 class="mb-4 text-xl font-semibold text-slate-950 dark:text-slate-100">Exportar reportes</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <input v-model="exportFilters.search" type="text" class="app-input" placeholder="Texto de busqueda" />
                        <select v-model="exportFilters.entity_id" class="app-select"><option value="">Todas las entidades</option><option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option></select>
                        <select v-model="exportFilters.status" class="app-select"><option value="">Todos los estados</option><option v-for="status in statuses" :key="status" :value="status">{{ status }}</option></select>
                        <select v-model="exportFilters.incident_type_id" class="app-select"><option value="">Todas las incidencias</option><option v-for="type in incidentTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select>
                        <select v-model="exportFilters.priority" class="app-select"><option value="">Todas las prioridades</option><option v-for="priority in priorities" :key="priority" :value="priority">{{ priority }}</option></select>
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
