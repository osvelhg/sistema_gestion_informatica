<script setup>
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import BaseCard from '@/Components/BaseCard.vue'

const props = defineProps({
    source:       Object,
    asignaciones: Array,
})

function confirmDelete(pivotId) {
    if (!confirm('¿Remover esta asignación?')) return
    router.delete(route('codigos-qr.trabajadores.destroy', { source: props.source.id, pivotId }), {
        preserveScroll: true,
    })
}
</script>

<template>
    <AppLayout :title="`Trabajadores — ${source.source}`">
        <PageHeader :title="`Trabajadores asignados`" :subtitle="`QR: ${source.source} — ${source.source_name || ''}`">
            <template #actions>
                <Link :href="route('codigos-qr.trabajadores.create', source.id)"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Asignar trabajador
                </Link>
                <Link :href="route('codigos-qr.index')"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver
                </Link>
            </template>
        </PageHeader>

        <BaseCard class="max-w-screen-lg mx-auto">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">CI</th>
                            <th class="px-4 py-3">Teléfono</th>
                            <th class="px-4 py-3">Rol QR</th>
                            <th class="px-4 py-3">Fecha Alta</th>
                            <th class="px-4 py-3">Fecha Baja</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-if="!asignaciones.length">
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">
                                Sin trabajadores asignados a este QR.
                            </td>
                        </tr>
                        <tr v-for="a in asignaciones" :key="a.pivot_id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ a.nombre }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 font-mono text-xs">{{ a.ci }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ a.telefono || '—' }}</td>
                            <td class="px-4 py-3">
                                <span v-if="a.rolqr_nombre" class="px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-500/15 dark:text-purple-300">
                                    {{ a.rolqr_nombre }}
                                </span>
                                <span v-else class="text-gray-400 text-xs">—</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">{{ a.fecha_alta }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">{{ a.fecha_baja || '—' }}</td>
                            <td class="px-4 py-3">
                                <span v-if="a.estado" class="inline-flex items-center gap-1 text-xs text-green-700 dark:text-green-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Activo
                                </span>
                                <span v-else class="inline-flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-300 inline-block"></span> Inactivo
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                                <Link :href="route('codigos-qr.trabajadores.edit', { source: source.id, pivotId: a.pivot_id })"
                                    class="inline-flex items-center text-xs px-2 py-1.5 text-gray-600 hover:text-gray-800 dark:text-gray-400 border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </Link>
                                <button @click="confirmDelete(a.pivot_id)"
                                    class="text-xs px-2 py-1.5 text-red-500 hover:text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </BaseCard>
    </AppLayout>
</template>
