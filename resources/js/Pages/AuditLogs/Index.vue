<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

const props = defineProps({ logs: Object, filters: Object })
const action = ref(props.filters?.action || '')
const search = ref(props.filters?.search || '')

const actionLabels = {
    CREAR: 'Crear',
    MODIFICAR: 'Modificar',
    ELIMINAR: 'Eliminar',
    MOVER: 'Mover',
}

const actionColors = {
    CREAR: 'green',
    MODIFICAR: 'yellow',
    ELIMINAR: 'red',
    MOVER: 'blue',
}

const columns = [
    { key: 'created_at', label: 'Fecha' },
    { key: 'user', label: 'Usuario' },
    { key: 'action', label: 'Accion' },
    { key: 'description', label: 'Descripcion' },
    { key: 'ip_address', label: 'IP' },
]

const applyFilters = () => {
    router.get('/audit-logs', {
        action: action.value || undefined,
        search: search.value || undefined,
    }, { preserveState: true, replace: true })
}

watch([action], applyFilters)

const formatDate = (dateStr) => {
    if (!dateStr) return ''
    const date = new Date(dateStr)
    return date.toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' })
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader eyebrow="Seguridad" title="Registro de Auditoria" description="Consulta eventos clave del sistema con filtros claros y una tabla legible en cualquier tema." />

            <BaseCard>
                <div class="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)_auto]">
                    <select v-model="action" class="app-select">
                        <option value="">Todas las acciones</option>
                        <option v-for="(label, key) in actionLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <input v-model="search" type="text" class="app-input" placeholder="Buscar en descripcion..." @keyup.enter="applyFilters" />
                    <button type="button" class="app-button-primary" @click="applyFilters">Filtrar</button>
                </div>
            </BaseCard>

            <DataTable client-table :columns="columns" :data="logs">
                <template #cell-created_at="{ value }">
                    <span class="text-slate-500 dark:text-slate-400">{{ formatDate(value) }}</span>
                </template>
                <template #cell-user="{ row }">
                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ row.user?.name || '-' }}</span>
                </template>
                <template #cell-action="{ row }">
                    <StatusBadge :status="actionLabels[row.action] || row.action" :color="actionColors[row.action] || 'gray'" />
                </template>
                <template #cell-description="{ row }">
                    <span class="text-slate-600 dark:text-slate-300">{{ row.description }}</span>
                </template>
                <template #cell-ip_address="{ value }">
                    <span class="font-mono text-xs text-slate-500 dark:text-slate-300">{{ value }}</span>
                </template>
                <template #empty>No se encontraron registros.</template>
            </DataTable>
        </div>
    </AppLayout>
</template>
