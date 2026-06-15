<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import BaseCard from '@/Components/BaseCard.vue'
import EmptyState from '@/Components/EmptyState.vue'

const props = defineProps({
    alerts: Object,
})

const typeLabels = {
    rodas_inventario_inexistente: 'RODAS: inventario inexistente',
    rodas_incongruencia: 'RODAS: incongruencia',
    rodas_medio_inventario_inexistente: 'Medio: inventario inexistente',
    rodas_medio_incongruencia: 'Medio: incongruencia',
    responsible_sin_ad: 'Responsable sin AD',
}

const labelFor = (type) => typeLabels[type] || type

const rows = computed(() => props.alerts?.data || [])
</script>

<template>
    <AppLayout>
        <PageHeader
            eyebrow="Expedientes"
            title="Alertas RODAS"
            description="Inventario RODAS, incongruencias y responsables indicados sin verificación en Active Directory. Enlace al expediente."
        >
            <template #actions>
                <Link href="/expedientes" class="app-button-secondary">Volver a expedientes</Link>
            </template>
        </PageHeader>

        <BaseCard class="mt-6" :padded="false">
            <template #header>
                <div class="flex w-full flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-700/60">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-950 dark:text-white">Registro de alertas</h3>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Ordenadas por fecha de creacion.</p>
                    </div>
                    <span class="rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-400">
                        {{ alerts.total || 0 }} registros
                    </span>
                </div>
            </template>

            <div v-if="rows.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200/80 text-sm dark:divide-slate-700/70">
                    <thead class="bg-slate-50/70 dark:bg-slate-900/60">
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2.5">Fecha</th>
                            <th class="px-4 py-2.5">Tipo</th>
                            <th class="px-4 py-2.5">Expediente</th>
                            <th class="px-4 py-2.5">Contexto</th>
                            <th class="px-4 py-2.5">Mensaje</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/70 dark:divide-slate-800/70">
                        <tr
                            v-for="a in rows"
                            :key="a.id"
                            class="transition hover:bg-brand-50/40 dark:hover:bg-brand-500/5"
                        >
                            <td class="whitespace-nowrap px-4 py-2.5 text-slate-600 dark:text-slate-300">
                                {{ a.created_at ? new Date(a.created_at).toLocaleString('es-CU') : '—' }}
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex rounded-lg border border-amber-200/80 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                                    {{ labelFor(a.type) }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5">
                                <Link
                                    v-if="a.equipment_file"
                                    :href="`/expedientes/${a.equipment_file.id}`"
                                    class="font-medium text-brand-600 hover:underline dark:text-brand-300"
                                >
                                    {{ a.equipment_file.file_number || a.equipment_file.station_name || `Expediente #${a.equipment_file.id}` }}
                                </Link>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="max-w-[220px] px-4 py-2.5 text-xs text-slate-600 dark:text-slate-400">
                                <template v-if="a.equipment_file">
                                    <span class="block truncate">{{ a.equipment_file.entity?.name || '—' }}</span>
                                    <span class="block truncate text-slate-500">{{ a.equipment_file.department?.name || '' }}</span>
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td class="max-w-md px-4 py-2.5 text-slate-700 dark:text-slate-300">
                                <span class="line-clamp-2">{{ a.message || '—' }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState v-else class="py-12" title="Sin alertas" description="No hay alertas registradas en este momento." />

            <div
                v-if="alerts.last_page > 1"
                class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200/80 px-4 py-3 text-xs dark:border-slate-700/60"
            >
                <p class="text-slate-500 dark:text-slate-400">
                    Pagina {{ alerts.current_page }} de {{ alerts.last_page }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="alerts.prev_page_url"
                        :href="alerts.prev_page_url"
                        class="app-button-secondary !py-1.5 !text-xs"
                    >
                        Anterior
                    </Link>
                    <Link
                        v-if="alerts.next_page_url"
                        :href="alerts.next_page_url"
                        class="app-button-secondary !py-1.5 !text-xs"
                    >
                        Siguiente
                    </Link>
                </div>
            </div>
        </BaseCard>
    </AppLayout>
</template>
