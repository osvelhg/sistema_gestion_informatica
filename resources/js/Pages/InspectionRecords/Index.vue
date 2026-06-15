<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({ records: Object, filters: Object, files: Array, entities: Array, departments: Array })
const equipmentFilter = ref(props.filters?.equipment_file_id || '')
const query = new URLSearchParams(window.location.search)
const showModal = ref(false)
const editing = ref(null)
const form = useForm({ equipment_file_id: equipmentFilter.value || '', entity_id: '', department_id: '', inspection_date: new Date().toISOString().slice(0,10), participants: '', situations_detected: '', worksheet_reference: '' })

const availableDepartments = computed(() => props.departments.filter(d => !form.entity_id || String(d.entity_id) === String(form.entity_id)))

const columns = [{key:'inspection_date',label:'Fecha'},{key:'equipment',label:'Expediente'},{key:'participants',label:'Participantes'},{key:'situations_detected',label:'Situaciones'},{key:'actions',label:'Acciones'}]

const applyFilter = () => router.get('/inspecciones', { equipment_file_id: equipmentFilter.value || undefined }, { preserveState:true, replace:true })

const fillByFile = (id) => {
  const file = props.files.find(f => String(f.id) === String(id))
  if (file) { form.entity_id = file.entity_id || ''; form.department_id = file.department_id || '' }
}

const openCreate = () => { editing.value=null; form.reset(); form.equipment_file_id = equipmentFilter.value || ''; form.inspection_date = new Date().toISOString().slice(0,10); form.entity_id = query.get('entity_id') || ''; form.department_id = query.get('department_id') || ''; if (form.equipment_file_id) fillByFile(form.equipment_file_id); form.clearErrors(); showModal.value=true }
const openEdit = (row) => { editing.value=row; Object.assign(form,{ equipment_file_id: row.equipment_file_id, entity_id: row.entity_id, department_id: row.department_id || '', inspection_date: row.inspection_date, participants: row.participants || '', situations_detected: row.situations_detected, worksheet_reference: row.worksheet_reference || '' }); form.clearErrors(); showModal.value=true }
const submit = () => editing.value ? form.put(`/inspecciones/${editing.value.id}`, { onSuccess:()=> showModal.value=false }) : form.post('/inspecciones', { onSuccess:()=> showModal.value=false })
const destroy = async (row) => { if (!await confirmDanger({ title:'Eliminar inspeccion', text:'Se eliminara el registro de inspeccion.', confirmText:'Si, eliminar' })) return; router.delete(`/inspecciones/${row.id}`) }
</script>

<template>
<AppLayout>
  <div class="mx-auto max-w-7xl space-y-6">
    <PageHeader eyebrow="Historial" title="Inspecciones" description="Registro historico de inspecciones por expediente.">
      <template #actions><button class="app-button-primary" @click="openCreate">Nueva inspeccion</button></template>
    </PageHeader>
    <BaseCard><select v-model="equipmentFilter" class="app-select max-w-md" @change="applyFilter"><option value="">Todos los expedientes</option><option v-for="file in files" :key="file.id" :value="file.id">{{ file.file_number }} - {{ file.inventory_number }}</option></select></BaseCard>
    <DataTable client-table :columns="columns" :data="records">
      <template #cell-equipment="{ row }"><div><p class="font-medium text-slate-900 dark:text-slate-100">{{ row.equipment_file?.file_number }}</p><p class="text-xs text-slate-500 dark:text-slate-400">{{ row.equipment_file?.inventory_number }}</p></div></template>
      <template #cell-participants="{ row }"><span class="text-slate-700 dark:text-slate-200">{{ row.participants || '-' }}</span></template>
      <template #cell-situations_detected="{ row }"><span class="text-slate-700 dark:text-slate-200">{{ row.situations_detected }}</span></template>
      <template #cell-actions="{ row }"><div class="flex justify-end gap-2"><button class="app-button-secondary px-3 py-2 text-xs" @click="openEdit(row)">Editar</button><button class="app-button-danger px-3 py-2 text-xs" @click="destroy(row)">Eliminar</button></div></template>
    </DataTable>
  </div>
  <Teleport to="body"><div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center px-4"><div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal=false" /><div class="surface-card relative z-10 w-full max-w-3xl p-6"><h3 class="mb-5 text-xl font-semibold text-slate-950 dark:text-slate-100">{{ editing ? 'Editar inspeccion' : 'Nueva inspeccion' }}</h3><form class="space-y-5" @submit.prevent="submit"><div class="grid gap-5 md:grid-cols-2"><div class="space-y-2"><label class="text-sm font-medium text-slate-700 dark:text-slate-300">Expediente *</label><select v-model="form.equipment_file_id" class="app-select" @change="fillByFile(form.equipment_file_id)"><option value="">Seleccionar...</option><option v-for="file in files" :key="file.id" :value="file.id">{{ file.file_number }} - {{ file.inventory_number }}</option></select></div><div class="space-y-2"><label class="text-sm font-medium text-slate-700 dark:text-slate-300">Fecha *</label><input v-model="form.inspection_date" type="date" class="app-input" /></div></div><div class="grid gap-5 md:grid-cols-2"><div class="space-y-2"><label class="text-sm font-medium text-slate-700 dark:text-slate-300">Entidad *</label><select v-model="form.entity_id" class="app-select"><option value="">Seleccionar...</option><option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option></select></div><div class="space-y-2"><label class="text-sm font-medium text-slate-700 dark:text-slate-300">Departamento</label><select v-model="form.department_id" class="app-select"><option value="">Seleccionar...</option><option v-for="department in availableDepartments" :key="department.id" :value="department.id">{{ department.name }}</option></select></div></div><div class="space-y-2"><label class="text-sm font-medium text-slate-700 dark:text-slate-300">Participantes</label><input v-model="form.participants" type="text" class="app-input" /></div><div class="space-y-2"><label class="text-sm font-medium text-slate-700 dark:text-slate-300">Situaciones detectadas *</label><textarea v-model="form.situations_detected" rows="4" class="app-input"></textarea></div><div class="space-y-2"><label class="text-sm font-medium text-slate-700 dark:text-slate-300">Referencia hoja de trabajo</label><input v-model="form.worksheet_reference" type="text" class="app-input" /></div><div class="flex justify-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800"><button type="button" class="app-button-secondary" @click="showModal=false">Cancelar</button><button type="submit" class="app-button-primary" :disabled="form.processing">{{ form.processing ? 'Guardando...' : 'Guardar' }}</button></div></form></div></div></Teleport>
</AppLayout>
</template>
