<script setup>
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({ users: Object, roles: Array, filters: Object })
const search = ref(props.filters?.search || '')
const roleFilter = ref(props.filters?.role || '')

const columns = [
    { key: 'name', label: 'Usuario', sortValue: (r) => r.name || '', filterValue: (r) => [r.name, r.email].filter(Boolean).join(' ') },
    { key: 'email', label: 'Email', sortValue: (r) => r.email || '' },
    { key: 'role', label: 'Rol', sortValue: (r) => r.roles?.[0]?.name || '', filterValue: (r) => r.roles?.[0]?.name || '' },
    {
        key: 'access',
        label: 'Alcance',
        sortValue: (r) => (r.entity_access_mode === 'province_directory' ? '1' : '0'),
        filterValue: (r) => (r.entity_access_mode === 'province_directory' ? 'Directorio provincial AD' : 'Entidades concretas'),
    },
    {
        key: 'entities',
        label: 'Entidades',
        sortValue: (r) => (r.entity_access_mode === 'province_directory' ? 0 : r.entities?.length || 0),
        filterValue: (r) =>
            r.entity_access_mode === 'province_directory'
                ? 'directorio provincial'
                : (r.entities || []).map((e) => e.name).join(' '),
    },
    { key: 'active', label: 'Estado', sortValue: (r) => (r.active ? '1' : '0') },
    { key: 'actions', label: 'Acciones', sortable: false, filterable: false },
]

let timeout
const applyFilters = () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/admin/usuarios', {
            search: search.value || undefined,
            role: roleFilter.value || undefined,
        }, { preserveState: true, replace: true })
    }, 300)
}

watch(search, applyFilters)
watch(roleFilter, applyFilters)

const destroy = async (user) => {
    if (!await confirmDanger({ title: 'Eliminar usuario', text: `Se eliminara el usuario "${user.name}".`, confirmText: 'Si, eliminar' })) return
    router.delete(`/admin/usuarios/${user.id}`)
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <PageHeader eyebrow="Administracion" title="Usuarios" description="Administra accesos y roles con filtros y listados adaptados a claro y oscuro.">
                <template #actions>
                    <Link href="/admin/usuarios/create" class="app-button-primary">Nuevo usuario</Link>
                </template>
            </PageHeader>

            <BaseCard>
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_240px]">
                    <input v-model="search" type="text" placeholder="Buscar por nombre o email..." class="app-input" />
                    <select v-model="roleFilter" class="app-select">
                        <option value="">Todos los roles</option>
                        <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                    </select>
                </div>
            </BaseCard>

            <DataTable client-table :columns="columns" :data="users">
                <template #cell-name="{ row }">
                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ row.name }}</span>
                </template>
                <template #cell-email="{ row }">
                    <span class="text-slate-600 dark:text-slate-300">{{ row.email }}</span>
                </template>
                <template #cell-role="{ row }">
                    <StatusBadge :status="row.roles?.[0]?.name || 'Sin rol'" color="blue" />
                </template>
                <template #cell-access="{ row }">
                    <StatusBadge
                        :status="row.entity_access_mode === 'province_directory' ? 'Directorio provincial' : 'Entidades concretas'"
                        :color="row.entity_access_mode === 'province_directory' ? 'blue' : 'amber'"
                    />
                </template>
                <template #cell-entities="{ row }">
                    <span class="font-medium text-slate-700 dark:text-slate-200">{{
                        row.entity_access_mode === 'province_directory' ? '—' : (row.entities?.length || 0)
                    }}</span>
                </template>
                <template #cell-active="{ value }">
                    <StatusBadge :status="value ? 'Activo' : 'Inactivo'" :color="value ? 'green' : 'red'" />
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <Link :href="`/admin/usuarios/${row.id}/edit`" class="app-button-secondary px-3 py-2 text-xs">Editar</Link>
                        <button type="button" class="app-button-danger px-3 py-2 text-xs" @click="destroy(row)">Eliminar</button>
                    </div>
                </template>
                <template #empty>No se encontraron usuarios.</template>
            </DataTable>
        </div>
    </AppLayout>
</template>
