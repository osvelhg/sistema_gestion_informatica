<script setup>
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({
    records: Object,
    filters: Object,
    files: Array,
    inspections: Array,
    aspects: Array,
    sections: Object,
})

const equipmentFilter = ref(props.filters?.equipment_file_id || '')
const showModal = ref(false)
const editing = ref(null)

const groupedAspects = computed(() => {
    const result = {}
    for (const key of Object.keys(props.sections)) {
        result[key] = props.aspects.filter(a => a.section === key)
    }
    return result
})

const buildEmptyChecklist = () =>
    Object.fromEntries(props.aspects.map(a => [String(a.id), null]))

const form = useForm({
    equipment_file_id: equipmentFilter.value || '',
    inspection_record_id: '',
    work_date: new Date().toISOString().slice(0, 10),
    worksheet_number: '',
    controlled_area: '',
    control_action_type: 'Inspección de la Seguridad Informática',
    started_at: '',
    ended_at: '',
    checklist: buildEmptyChecklist(),
    preliminary_results: '',
    observations: '',
    controller_name: '',
    controlled_name: '',
})

// Auto-fill controlled_area from the selected file's department
watch(() => form.equipment_file_id, (id) => {
    if (!id) return
    const file = props.files.find(f => String(f.id) === String(id))
    if (file?.department?.name) form.controlled_area = file.department.name
})

const availableInspections = computed(() =>
    props.inspections.filter(i => !form.equipment_file_id || String(i.equipment_file_id) === String(form.equipment_file_id))
)

const columns = [
    { key: 'work_date', label: 'Fecha' },
    { key: 'worksheet_number', label: 'Hoja' },
    { key: 'equipment', label: 'Expediente' },
    { key: 'control_action_type', label: 'Acción' },
    { key: 'actions', label: 'Acciones' },
]

const applyFilter = () =>
    router.get('/hojas-trabajo', { equipment_file_id: equipmentFilter.value || undefined }, { preserveState: true, replace: true })

const openCreate = () => {
    editing.value = null
    form.reset()
    form.checklist = buildEmptyChecklist()
    form.equipment_file_id = equipmentFilter.value || ''
    form.work_date = new Date().toISOString().slice(0, 10)
    form.control_action_type = 'Inspección de la Seguridad Informática'
    // Pre-fill department if a file filter is active
    if (equipmentFilter.value) {
        const file = props.files.find(f => String(f.id) === String(equipmentFilter.value))
        if (file?.department?.name) form.controlled_area = file.department.name
    }
    form.clearErrors()
    showModal.value = true
}

const openEdit = (row) => {
    editing.value = row
    const base = buildEmptyChecklist()
    const stored = row.checklist || {}
    Object.assign(form, {
        equipment_file_id: row.equipment_file_id,
        inspection_record_id: row.inspection_record_id || '',
        work_date: row.work_date,
        worksheet_number: row.worksheet_number || '',
        controlled_area: row.controlled_area || '',
        control_action_type: row.control_action_type || '',
        started_at: row.started_at || '',
        ended_at: row.ended_at || '',
        checklist: { ...base, ...stored },
        preliminary_results: row.preliminary_results || '',
        observations: row.observations || '',
        controller_name: row.controller_name || '',
        controlled_name: row.controlled_name || '',
    })
    form.clearErrors()
    showModal.value = true
}

const submit = () =>
    editing.value
        ? form.put(`/hojas-trabajo/${editing.value.id}`, { onSuccess: () => (showModal.value = false) })
        : form.post('/hojas-trabajo', { onSuccess: () => (showModal.value = false) })

const destroy = async (row) => {
    if (!await confirmDanger({ title: 'Eliminar hoja de trabajo', text: 'Se eliminará la hoja de trabajo.', confirmText: 'Sí, eliminar' })) return
    router.delete(`/hojas-trabajo/${row.id}`)
}

const checklistSummary = (checklist) => {
    if (!checklist) return '-'
    const vals = Object.values(checklist)
    const filled = vals.filter(v => v)
    const mal = vals.filter(v => v === 'M').length
    if (!filled.length) return 'Sin evaluar'
    return mal > 0 ? `${mal} elemento(s) en M` : 'Sin incidencias'
}

const brm = ['B', 'R', 'M']
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <PageHeader eyebrow="Historial" title="Hojas de Trabajo" description="Detalle técnico y operativo de controles realizados al expediente.">
                <template #actions>
                    <button class="app-button-primary" @click="openCreate">Nueva hoja</button>
                </template>
            </PageHeader>

            <BaseCard>
                <select v-model="equipmentFilter" class="app-select max-w-md" @change="applyFilter">
                    <option value="">Todos los expedientes</option>
                    <option v-for="file in files" :key="file.id" :value="file.id">{{ file.file_number }} - {{ file.inventory_number }}</option>
                </select>
            </BaseCard>

            <DataTable client-table :columns="columns" :data="records">
                <template #cell-equipment="{ row }">
                    <div>
                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ row.equipment_file?.file_number }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.equipment_file?.inventory_number }}</p>
                    </div>
                </template>
                <template #cell-worksheet_number="{ row }">
                    <div>
                        <p class="font-mono text-slate-900 dark:text-slate-100">{{ row.worksheet_number || '-' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ checklistSummary(row.checklist) }}</p>
                    </div>
                </template>
                <template #cell-control_action_type="{ row }">
                    <span class="text-slate-700 dark:text-slate-200">{{ row.control_action_type || '-' }}</span>
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
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-8">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                <div class="surface-card relative z-10 w-full max-w-4xl p-6">
                    <h3 class="mb-1 text-xl font-semibold text-slate-950 dark:text-slate-100">
                        {{ editing ? 'Editar hoja de trabajo' : 'Nueva hoja de trabajo' }}
                    </h3>
                    <p class="mb-5 text-xs text-slate-400 dark:text-slate-500">R-13/PD-GPR-02 · Área controladora: Departamento de Informática</p>

                    <form class="space-y-5" @submit.prevent="submit">

                        <!-- Fila 1: Expediente + Fecha + No. Hoja -->
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Expediente <span class="text-rose-500">*</span></label>
                                <select v-model="form.equipment_file_id" class="app-select">
                                    <option value="">Seleccionar...</option>
                                    <option v-for="file in files" :key="file.id" :value="file.id">{{ file.file_number }}</option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Fecha <span class="text-rose-500">*</span></label>
                                <input v-model="form.work_date" type="date" class="app-input" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">No. Hoja</label>
                                <input v-model="form.worksheet_number" type="text" class="app-input" placeholder="Ej: 1/2025" />
                            </div>
                        </div>

                        <!-- Fila 2: Área controlada + Tipo de acción + Inicio / Terminación -->
                        <div class="grid gap-4 md:grid-cols-4">
                            <div class="space-y-1.5 md:col-span-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Área controlada
                                    <span class="ml-1 text-xs font-normal text-slate-400 dark:text-slate-500">(se completa del expediente)</span>
                                </label>
                                <input v-model="form.controlled_area" type="text" class="app-input" placeholder="Departamento del expediente" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Inicio</label>
                                <input v-model="form.started_at" type="time" class="app-input" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Terminación</label>
                                <input v-model="form.ended_at" type="time" class="app-input" />
                            </div>
                        </div>

                        <!-- Fila 3: Tipo de acción + Inspección origen -->
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tipo de acción de control</label>
                                <input v-model="form.control_action_type" type="text" class="app-input" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Inspección origen
                                    <span class="ml-1 text-xs font-normal text-slate-400 dark:text-slate-500">(opcional — vincula con Registro 4)</span>
                                </label>
                                <select v-model="form.inspection_record_id" class="app-select">
                                    <option value="">Sin vincular</option>
                                    <option v-for="inspection in availableInspections" :key="inspection.id" :value="inspection.id">
                                        Inspección del {{ inspection.inspection_date }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Separador -->
                        <div class="border-t border-slate-200/80 pt-1 dark:border-slate-700/70">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">Resultados del control</p>
                        </div>

                        <!-- Checklist dinámico por sección -->
                        <div
                            v-for="(sectionLabel, sectionKey) in sections"
                            :key="sectionKey"
                            class="rounded-xl border border-slate-200/80 dark:border-slate-700/70"
                        >
                            <div class="border-b border-slate-200/80 bg-slate-50/80 px-4 py-2 dark:border-slate-700/70 dark:bg-slate-900/60">
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ sectionLabel }}</p>
                            </div>

                            <template v-if="groupedAspects[sectionKey]?.length">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-200/80 dark:border-slate-700/70">
                                            <th class="px-4 py-2 text-left font-medium text-slate-500 dark:text-slate-400">Elemento</th>
                                            <th v-for="v in brm" :key="v" class="w-14 px-2 py-2 text-center font-semibold text-slate-600 dark:text-slate-400">{{ v }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="aspect in groupedAspects[sectionKey]"
                                            :key="aspect.id"
                                            class="border-b border-slate-200/50 last:border-0 dark:border-slate-700/50"
                                        >
                                            <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300">{{ aspect.label }}</td>
                                            <td v-for="v in brm" :key="v" class="px-2 py-2.5 text-center">
                                                <input
                                                    type="radio"
                                                    :name="`aspect_${aspect.id}`"
                                                    :value="v"
                                                    v-model="form.checklist[String(aspect.id)]"
                                                    class="cursor-pointer accent-brand-500"
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </template>
                            <p v-else class="px-4 py-3 text-sm text-slate-400 dark:text-slate-500">
                                Sin aspectos activos en esta sección.
                                <a href="/aspectos-hoja" class="text-brand-600 underline dark:text-brand-300">Gestionar aspectos</a>
                            </p>
                        </div>

                        <!-- Resultados y observaciones -->
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Resultados preliminares</label>
                                <textarea v-model="form.preliminary_results" rows="3" class="app-input" placeholder="Resultados del control realizado..."></textarea>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Observaciones</label>
                                <textarea v-model="form.observations" rows="3" class="app-input" placeholder="Observaciones adicionales..."></textarea>
                            </div>
                        </div>

                        <!-- Elaborado / Recibido -->
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Elaborado por <span class="text-slate-400 font-normal text-xs">(controlador)</span></label>
                                <input v-model="form.controller_name" type="text" class="app-input" placeholder="Nombre y apellidos" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Recibido por <span class="text-slate-400 font-normal text-xs">(controlado)</span></label>
                                <input v-model="form.controlled_name" type="text" class="app-input" placeholder="Nombre y apellidos" />
                            </div>
                        </div>

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
