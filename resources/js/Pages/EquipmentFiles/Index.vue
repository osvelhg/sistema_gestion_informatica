<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import FilterBar from '@/Components/FilterBar.vue'
import StatisticsBar from '@/Components/StatisticsBar.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import MoveModal from '@/Components/MoveModal.vue'
import BaseCard from '@/Components/BaseCard.vue'
import PageHeader from '@/Components/PageHeader.vue'
import EmptyState from '@/Components/EmptyState.vue'
import LevantamientoImportModal from '@/Components/LevantamientoImportModal.vue'

const props = defineProps({
    files: Object,
    filters: Object,
    entities: Array,
    statuses: Array,
    statistics: Object,
})

const confirmDelete = ref({ show: false, file: null })
const moveModal = ref({ show: false, file: null })
const showExportModal = ref(false)
const showImportLevantamientoModal = ref(false)
const exportFilters = ref({
    search: props.filters?.search || '',
    entity_id: props.filters?.entity_id || '',
    department_id: props.filters?.department_id || '',
    status: props.filters?.status || '',
    format: 'csv',
})

const exportData = () => {
    const params = new URLSearchParams()
    params.set('format', exportFilters.value.format || 'csv')
    Object.entries(exportFilters.value).forEach(([key, value]) => {
        if (key === 'format') return
        if (value !== '' && value !== null && value !== undefined) params.set(key, value)
    })
    window.open(`/expedientes/exportar?${params.toString()}`, '_blank')
    showExportModal.value = false
}

const deleteFile = () => {
    if (!confirmDelete.value.file) return

    router.delete(`/expedientes/${confirmDelete.value.file.id}`, {
        onSuccess: () => {
            confirmDelete.value = { show: false, file: null }
        },
    })
}

const moveFile = (data) => {
    if (!moveModal.value.file) return

    router.post(`/expedientes/${moveModal.value.file.id}/mover`, data, {
        onSuccess: () => {
            moveModal.value = { show: false, file: null }
        },
    })
}

const onLevantamientoImported = () => {
    router.reload({ only: ['files', 'statistics'] })
    showImportLevantamientoModal.value = false
}
</script>

<template>
    <AppLayout>
        <PageHeader
            eyebrow="Inventario tecnico"
            title="Expedientes tecnicos"
            description="Explora, filtra y opera sobre el inventario con una interfaz moderna, rapida y orientada a productividad."
        >
            <template #actions>
                <button type="button" class="app-button-secondary" @click="showExportModal = true">Exportar</button>
                <button type="button" class="app-button-secondary" @click="showImportLevantamientoModal = true">
                    Importar levantamiento
                </button>
                <Link href="/expedientes/create" class="app-button-primary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo expediente
                </Link>
            </template>
        </PageHeader>

        <StatisticsBar :statistics="statistics" />

        <div class="mt-6">
            <FilterBar :entities="entities" :filters="filters" />
        </div>

        <BaseCard class="mt-6" :padded="false">
            <template #header>
                <div class="flex w-full items-center justify-between gap-4 border-b border-slate-200/80 px-5 py-4 dark:border-slate-700/60">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Vista operativa</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Listado principal con acciones rapidas, estados y trazabilidad.</p>
                    </div>
                    <div class="hidden rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-400 md:block">
                        {{ files.total || 0 }} registros
                    </div>
                </div>
            </template>

            <div v-if="files.data?.length" class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200/80 text-sm dark:divide-slate-700/70">
                        <thead class="bg-slate-50/70 dark:bg-slate-900/60">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
                                <th class="px-5 py-4">Activo</th>
                                <th class="px-5 py-4">Contexto</th>
                                <th class="px-5 py-4">Responsable</th>
                                <th class="px-5 py-4">Estado</th>
                                <th class="px-5 py-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/70 dark:divide-slate-800/70">
                            <tr
                                v-for="file in files.data"
                                :key="file.id"
                                class="group transition hover:bg-brand-50/40 dark:hover:bg-brand-500/5"
                            >
                                <td class="px-5 py-4 align-top">
                                    <div class="space-y-1">
                                        <Link :href="`/expedientes/${file.id}`" class="text-sm font-semibold text-slate-950 transition hover:text-brand-700 dark:text-white dark:hover:text-brand-300">
                                            {{ file.file_number }}
                                        </Link>
                                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                            <span class="app-badge border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ file.type }}</span>
                                            <span class="font-mono">{{ file.inventory_number }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-4 align-top">
                                    <div class="space-y-1">
                                        <p class="font-medium text-slate-800 dark:text-slate-200">{{ file.entity?.name }}</p>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ file.department?.name }}</p>
                                    </div>
                                </td>

                                <td class="px-5 py-4 align-top">
                                    <p class="font-medium text-slate-800 dark:text-slate-200">{{ file.responsible }}</p>
                                </td>

                                <td class="px-5 py-4 align-top">
                                    <StatusBadge :status="file.status" />
                                </td>

                                <td class="px-5 py-4 align-top">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="`/expedientes/${file.id}`" class="app-button-secondary !px-3 !py-2 text-xs">
                                            Ver
                                        </Link>
                                        <Link :href="`/expedientes/${file.id}/edit`" class="app-button-secondary !px-3 !py-2 text-xs">
                                            Editar
                                        </Link>
                                        <button type="button" class="app-button-secondary !px-3 !py-2 text-xs" @click="moveModal = { show: true, file }">
                                            Mover
                                        </button>
                                        <button type="button" class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-100 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20" @click="confirmDelete = { show: true, file }">
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="files.links?.length > 3" class="flex flex-col gap-3 border-t border-slate-200/80 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-700/60">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Mostrando {{ files.from }} a {{ files.to }} de {{ files.total }} expedientes
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                        <template v-for="link in files.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-state
                                preserve-scroll
                                v-html="link.label"
                                :class="link.active ? 'bg-ink-900 text-white dark:bg-brand-400 dark:text-slate-950' : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'"
                                class="inline-flex min-w-[2.5rem] items-center justify-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium transition dark:border-slate-700"
                            />
                            <span v-else v-html="link.label" class="inline-flex min-w-[2.5rem] items-center justify-center px-2 text-sm text-slate-400" />
                        </template>
                    </div>
                </div>
            </div>

            <div v-else class="p-6">
                <EmptyState
                    title="No se encontraron expedientes"
                    description="Ajusta los filtros o crea un nuevo expediente para comenzar a poblar el inventario con el nuevo sistema visual."
                >
                    <Link href="/expedientes/create" class="app-button-primary">
                        Crear primer expediente
                    </Link>
                </EmptyState>
            </div>
        </BaseCard>

        <ConfirmModal
            :show="confirmDelete.show"
            title="Eliminar expediente"
            :message="`Se eliminara el expediente ${confirmDelete.file?.file_number}. Esta accion no se puede deshacer.`"
            @confirm="deleteFile"
            @close="confirmDelete = { show: false, file: null }"
        />

        <MoveModal
            :show="moveModal.show"
            :entities="entities"
            @submit="moveFile"
            @close="moveModal = { show: false, file: null }"
        />

        <LevantamientoImportModal
            :show="showImportLevantamientoModal"
            :entities="entities"
            :statuses="statuses || []"
            @close="showImportLevantamientoModal = false"
            @imported="onLevantamientoImported"
        />

        <Teleport to="body">
            <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showExportModal = false" />
                <div class="surface-card relative z-10 w-full max-w-2xl p-6">
                    <h3 class="mb-4 text-xl font-semibold text-slate-950 dark:text-slate-100">Exportar expedientes</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <input v-model="exportFilters.search" type="text" class="app-input" placeholder="Texto de busqueda" />
                        <select v-model="exportFilters.entity_id" class="app-select"><option value="">Todas las entidades</option><option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option></select>
                        <input v-model="exportFilters.department_id" type="text" class="app-input" placeholder="ID departamento (opcional)" />
                        <select v-model="exportFilters.status" class="app-select"><option value="">Todos los estados</option><option value="Bien">Bien</option><option value="Regular">Regular</option><option value="Mal">Mal</option></select>
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
