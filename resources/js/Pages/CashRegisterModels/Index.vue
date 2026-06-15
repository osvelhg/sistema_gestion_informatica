<script setup>
import { ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({ models: Object, filters: Object })
const search = ref(props.filters?.search || '')
const showModal = ref(false)
const editing = ref(null)
const form = useForm({ code: '', name: '', active: true })
const columns = [{ key: 'code', label: 'No.' }, { key: 'name', label: 'Modelo' }, { key: 'active', label: 'Estado' }, { key: 'actions', label: 'Acciones' }]

let timeout
watch(search, (value) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => router.get('/modelos-cajas', { search: value || undefined }, { preserveState: true, replace: true }), 300)
})
const openCreate = () => { editing.value = null; form.reset(); form.active = true; form.clearErrors(); showModal.value = true }
const openEdit = (row) => { editing.value = row; form.code = row.code; form.name = row.name; form.active = row.active; form.clearErrors(); showModal.value = true }
const submit = () => editing.value ? form.put(`/modelos-cajas/${editing.value.id}`, { onSuccess: () => (showModal.value = false) }) : form.post('/modelos-cajas', { onSuccess: () => (showModal.value = false) })
const destroy = async (row) => { if (!await confirmDanger({ title: 'Eliminar modelo', text: `Se eliminara "${row.name}".`, confirmText: 'Si, eliminar' })) return; router.delete(`/modelos-cajas/${row.id}`) }
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl space-y-6">
            <PageHeader eyebrow="Nomenclador" title="Modelos de cajas registradoras" description="Catalogo numerado de modelos para asociar multiples cajas a un PV.">
                <template #actions><button class="app-button-primary" @click="openCreate">Nuevo modelo</button></template>
            </PageHeader>
            <BaseCard><input v-model="search" type="text" class="app-input" placeholder="Buscar modelo..." /></BaseCard>
            <DataTable client-table :columns="columns" :data="models">
                <template #cell-code="{ value }"><span class="font-mono font-semibold">{{ value }}</span></template>
                <template #cell-name="{ value }"><span class="font-medium text-slate-900 dark:text-slate-100">{{ value }}</span></template>
                <template #cell-active="{ value }"><StatusBadge :status="value ? 'Activo' : 'Inactivo'" :color="value ? 'green' : 'red'" /></template>
                <template #cell-actions="{ row }"><div class="flex justify-end gap-2"><button class="app-button-secondary px-3 py-2 text-xs" @click="openEdit(row)">Editar</button><button class="app-button-danger px-3 py-2 text-xs" @click="destroy(row)">Eliminar</button></div></template>
            </DataTable>
        </div>
        <Teleport to="body"><div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center px-4"><div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal=false" /><div class="surface-card relative z-10 w-full max-w-xl p-6"><h3 class="mb-5 text-xl font-semibold text-slate-950 dark:text-slate-100">{{ editing ? 'Editar modelo de caja' : 'Nuevo modelo de caja' }}</h3><form class="space-y-5" @submit.prevent="submit"><div class="grid grid-cols-2 gap-4"><input v-model="form.code" type="number" min="1" class="app-input" placeholder="Numero" /><input v-model="form.name" type="text" class="app-input" placeholder="Nombre" /></div><label class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-sm"><input v-model="form.active" type="checkbox" class="h-4 w-4" />Activo</label><div class="flex justify-end gap-3 border-t border-slate-200/80 pt-4"><button type="button" class="app-button-secondary" @click="showModal=false">Cancelar</button><button type="submit" :disabled="form.processing" class="app-button-primary">{{ form.processing ? 'Guardando...' : 'Guardar' }}</button></div></form></div></div></Teleport>
    </AppLayout>
</template>
