<script setup>
import { ref, computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({ roles: Array, permissions: Array })
const showModal = ref(false)
const editing = ref(null)
const form = useForm({ name: '', permissions: [] })

const filterText = ref('')
const expandAll = ref(true)
const expandedInModal = ref({})

/** Etiquetas de grupo (prefijo antes del primer punto) */
const moduleLabels = {
    admin: 'Administración',
    configuracion: 'Configuración',
    modulos: 'Configuración / Módulos',
    profile: 'Perfil',
    dashboard: 'Panel',
    entidades: 'Entidades',
    departamentos: 'Departamentos',
    provincias: 'Provincias',
    municipios: 'Municipios',
    marcas: 'Marcas',
    modelos: 'Modelos',
    estados: 'Estados',
    expedientes: 'Expedientes',
    sellos: 'Control de sellos',
    conectividad: 'Conectividad',
    'pisos-venta': 'Pisos de venta',
    'modelos-cajas': 'Modelos de caja',
    'tipos-red': 'Tipos de red',
    'tipos-establecimiento': 'Tipos de establecimiento',
    'estados-establecimiento': 'Estados de establecimiento',
    reportes: 'Reportes',
    'reportes-incidencias': 'Reportes de incidencias',
    'tipos-componentes': 'Tipos de componentes',
    'tipos-incidencias': 'Tipos de incidencias',
    'aspectos-hoja': 'Aspectos hoja',
    'canales-electronicos': 'Canales electrónicos',
    'tipos-fuente': 'Tipos de fuente',
    monedas: 'Monedas',
    trabajadores: 'Trabajadores',
    'codigos-qr': 'Códigos QR',
    auditoria: 'Auditoría',
}

const actionLabels = {
    index: 'Listar',
    create: 'Formulario crear',
    store: 'Guardar',
    edit: 'Formulario editar',
    update: 'Actualizar',
    destroy: 'Eliminar',
    show: 'Ver detalle',
    search: 'Buscar',
    statistics: 'Estadísticas',
    export: 'Exportar',
    importar: 'Importar',
    move: 'Mover',
    sync: 'Sincronizar',
    test: 'Probar conexión',
    searchLdap: 'Buscar en LDAP',
    appearance: 'Apariencia',
    logo: 'Subir logo',
    delete: 'Eliminar',
}

const areaLabels = {
    workspace: 'Workspace',
    nomencladores: 'Nomencladores',
    control: 'Control',
}

const areaOrder = ['workspace', 'nomencladores', 'control']

function resolveArea(module) {
    const workspace = [
        'dashboard',
        'expedientes',
        'conectividad',
        'reportes',
        'reportes-incidencias',
        'codigos-qr',
        'trabajadores',
        'inspecciones',
        'hojas-trabajo',
        'incidencias-seguridad',
        'control-soportes',
    ]
    const nomencladores = [
        'provincias',
        'municipios',
        'entidades',
        'departamentos',
        'estados',
        'sellos',
        'marcas',
        'tipos-componentes',
        'tipos-incidencias',
        'modelos',
        'aspectos-hoja',
        'canales-electronicos',
        'tipos-fuente',
        'monedas',
        'pisos-venta',
        'modelos-cajas',
        'tipos-red',
        'tipos-establecimiento',
        'estados-establecimiento',
    ]
    const control = [
        'admin',
        'auditoria',
        'configuracion',
        'modulos',
    ]

    if (workspace.includes(module)) return 'workspace'
    if (nomencladores.includes(module)) return 'nomencladores'
    if (control.includes(module)) return 'control'
    return 'control'
}

function humanizeModuleKey(key) {
    if (moduleLabels[key]) return moduleLabels[key]
    return key
        .split('-')
        .map((s) => s.charAt(0).toUpperCase() + s.slice(1))
        .join(' ')
}

function parsePermission(name) {
    const dot = name.indexOf('.')
    if (dot < 0) return { module: name, action: name }
    return { module: name.slice(0, dot), action: name.slice(dot + 1) }
}

/** Lista ordenada de grupos para el modal y filtros */
const permissionGroupsList = computed(() => {
    const groups = {}
    props.permissions.forEach((p) => {
        const { module } = parsePermission(p.name)
        if (!groups[module]) groups[module] = []
        groups[module].push(p.name)
    })
    return Object.entries(groups)
        .map(([module, perms]) => ({
            module,
            label: humanizeModuleKey(module),
            area: resolveArea(module),
            permissions: [...perms].sort((a, b) => a.localeCompare(b)),
        }))
        .sort((a, b) => {
            const byArea = areaOrder.indexOf(a.area) - areaOrder.indexOf(b.area)
            if (byArea !== 0) return byArea
            return a.label.localeCompare(b.label, 'es')
        })
})

const filteredModalGroups = computed(() => {
    const q = filterText.value.trim().toLowerCase()
    if (!q) return permissionGroupsList.value
    return permissionGroupsList.value.filter(
        (g) =>
            g.label.toLowerCase().includes(q) ||
            g.module.toLowerCase().includes(q) ||
            g.permissions.some((p) => p.toLowerCase().includes(q)),
    )
})

watch(
    () => showModal.value,
    (open) => {
        if (open) {
            filterText.value = ''
            const next = {}
            permissionGroupsList.value.forEach((g) => {
                next[g.module] = expandAll.value
            })
            expandedInModal.value = next
        }
    },
)

watch(expandAll, (val) => {
    const next = { ...expandedInModal.value }
    permissionGroupsList.value.forEach((g) => {
        next[g.module] = val
    })
    expandedInModal.value = next
})

function toggleSection(module) {
    expandedInModal.value = {
        ...expandedInModal.value,
        [module]: !expandedInModal.value[module],
    }
}

/** Resumen legible por rol (agrupado por modulo) */
function summarizeRole(role) {
    const byModule = {}
    ;(role.permissions || []).forEach((p) => {
        const { module, action } = parsePermission(p.name)
        if (!byModule[module]) byModule[module] = []
        if (!byModule[module].includes(action)) byModule[module].push(action)
    })
    return Object.entries(byModule)
        .map(([module, actions]) => ({
            module,
            label: humanizeModuleKey(module),
            actions: [...actions].sort((a, b) => a.localeCompare(b)),
        }))
        .sort((a, b) => a.label.localeCompare(b.label, 'es'))
}

const openCreate = () => {
    editing.value = null
    form.name = ''
    form.permissions = []
    form.clearErrors()
    showModal.value = true
}

const openEdit = (role) => {
    editing.value = role
    form.name = role.name
    form.permissions = role.permissions.map((permission) => permission.name)
    form.clearErrors()
    showModal.value = true
}

const submit = () => {
    const options = { onSuccess: () => { showModal.value = false } }
    if (editing.value) return form.put(`/admin/roles/${editing.value.id}`, options)
    form.post('/admin/roles', options)
}

const destroy = async (role) => {
    if (!await confirmDanger({ title: 'Eliminar rol', text: `Se eliminara el rol "${role.name}".`, confirmText: 'Si, eliminar' })) return
    router.delete(`/admin/roles/${role.id}`)
}

const togglePermission = (permission) => {
    const index = form.permissions.indexOf(permission)
    if (index >= 0) form.permissions.splice(index, 1)
    else form.permissions.push(permission)
}

const toggleModule = (permissionNames) => {
    const allSelected = permissionNames.every((permission) => form.permissions.includes(permission))
    if (allSelected) {
        form.permissions = form.permissions.filter((permission) => !permissionNames.includes(permission))
        return
    }
    permissionNames.forEach((permission) => {
        if (!form.permissions.includes(permission)) form.permissions.push(permission)
    })
}

const selectAllPermissions = () => {
    form.permissions = props.permissions.map((p) => p.name)
}

const clearAllPermissions = () => {
    form.permissions = []
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <PageHeader
                eyebrow="Administracion"
                title="Roles y permisos"
                description="Perfiles de acceso agrupados por area del sistema. Al editar un rol, los permisos se eligen por modulos."
            >
                <template #actions>
                    <button type="button" class="app-button-primary" @click="openCreate">Nuevo rol</button>
                </template>
            </PageHeader>

            <div class="grid gap-5 lg:grid-cols-2">
                <BaseCard v-for="role in roles" :key="role.id" hoverable>
                    <template #header>
                        <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ role.name }}</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ role.permissions_count }} permisos en {{ summarizeRole(role).length }} areas
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button type="button" class="app-button-secondary px-3 py-2 text-xs" @click="openEdit(role)">Editar</button>
                                <button v-if="role.name !== 'Administrador'" type="button" class="app-button-danger px-3 py-2 text-xs" @click="destroy(role)">Eliminar</button>
                            </div>
                        </div>
                    </template>

                    <div class="max-h-56 space-y-2 overflow-y-auto pr-1">
                        <div
                            v-for="block in summarizeRole(role)"
                            :key="block.module"
                            class="rounded-xl border border-slate-200/90 bg-slate-50/80 px-3 py-2.5 dark:border-slate-700/80 dark:bg-slate-900/50"
                        >
                            <div class="mb-1.5 flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ block.label }}</span>
                                <span class="text-[11px] font-medium text-slate-400 dark:text-slate-500">{{ block.actions.length }}</span>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="action in block.actions"
                                    :key="action"
                                    class="inline-flex items-center rounded-md border border-slate-200/90 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-600 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ actionLabels[action] || action }}
                                </span>
                            </div>
                        </div>
                    </div>
                </BaseCard>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                <div class="surface-card relative z-10 flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl shadow-2xl">
                    <div class="border-b border-slate-200/80 px-6 py-5 dark:border-slate-800">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600 dark:text-brand-300">Rol</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950 dark:text-slate-100">{{ editing ? 'Editar rol' : 'Nuevo rol' }}</h3>
                    </div>

                    <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
                        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nombre del rol</label>
                                <input v-model="form.name" type="text" :disabled="editing?.name === 'Administrador'" class="app-input disabled:cursor-not-allowed disabled:opacity-70" />
                                <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
                            </div>

                            <div v-if="editing?.name !== 'Administrador'" class="mt-6 space-y-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Permisos por area</label>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" class="app-button-secondary px-3 py-1.5 text-xs" @click="selectAllPermissions">Marcar todos</button>
                                        <button type="button" class="app-button-secondary px-3 py-1.5 text-xs" @click="clearAllPermissions">Quitar todos</button>
                                        <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                                            <input v-model="expandAll" type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-brand-600 dark:border-slate-600" />
                                            Secciones abiertas
                                        </label>
                                    </div>
                                </div>

                                <div class="relative">
                                    <input
                                        v-model="filterText"
                                        type="search"
                                        class="app-input w-full pl-9"
                                        placeholder="Buscar area o permiso..."
                                        autocomplete="off"
                                    />
                                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                    </svg>
                                </div>

                                <p v-if="!filteredModalGroups.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                    No hay areas que coincidan con la busqueda.
                                </p>

                                <div class="space-y-2">
                                    <div
                                        v-for="group in filteredModalGroups"
                                        :key="group.module"
                                        class="overflow-hidden rounded-xl border border-slate-200/90 bg-white dark:border-slate-700/80 dark:bg-slate-900/40"
                                    >
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-slate-50 dark:hover:bg-slate-800/60"
                                            @click="toggleSection(group.module)"
                                        >
                                            <div class="flex min-w-0 items-center gap-3">
                                                <input
                                                    type="checkbox"
                                                    :checked="group.permissions.every((p) => form.permissions.includes(p))"
                                                    class="h-4 w-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800"
                                                    @click.stop
                                                    @change="toggleModule(group.permissions)"
                                                />
                                                <span class="truncate font-semibold text-slate-800 dark:text-slate-100">[{{ areaLabels[group.area] }}] {{ group.label }}</span>
                                                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ group.permissions.length }}</span>
                                            </div>
                                            <svg
                                                class="h-5 w-5 shrink-0 text-slate-400 transition-transform"
                                                :class="expandedInModal[group.module] ? 'rotate-180' : ''"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                                stroke-width="2"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <div v-show="expandedInModal[group.module]" class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                <label
                                                    v-for="permission in group.permissions"
                                                    :key="permission"
                                                    class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-transparent px-2 py-2 text-sm text-slate-600 transition hover:border-slate-200 hover:bg-slate-50 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:bg-slate-800/50"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :checked="form.permissions.includes(permission)"
                                                        class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800"
                                                        @change="togglePermission(permission)"
                                                    />
                                                    <span class="min-w-0">
                                                        <span class="block font-medium text-slate-700 dark:text-slate-200">
                                                            {{ actionLabels[parsePermission(permission).action] || parsePermission(permission).action }}
                                                        </span>
                                                        <span class="block text-[11px] text-slate-400 dark:text-slate-500 font-mono break-all">
                                                            {{ permission }}
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-300">
                                El rol Administrador tiene acceso total por regla antibloqueo. No requiere selección de permisos.
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 border-t border-slate-200/80 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-900/50">
                            <button type="button" class="app-button-secondary" @click="showModal = false">Cancelar</button>
                            <button type="submit" :disabled="form.processing" class="app-button-primary">{{ form.processing ? 'Guardando...' : 'Guardar' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
