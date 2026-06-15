<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { notifySuccess, confirmDanger, notifyError } from '@/Composables/useNotifications'
import { formatDateEs } from '@/utils/formatDateEs'

const props = defineProps({
    factura: Object,
})

// ── Formateadores ──────────────────────────────────────────────────────────
const fmtCup    = (v) => v != null ? Number(v).toFixed(2) : '—'
const fmtUsd    = (v) => v != null ? Number(v).toFixed(4) : null
const fmtDate   = formatDateEs
const fmtPeriod = (desde, hasta) => {
    if (!desde && !hasta) return '—'
    return `${fmtDate(desde)} — ${fmtDate(hasta)}`
}

// ── Tipo badge ─────────────────────────────────────────────────────────────
const tipoBadgeClass = {
    telefonia:    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    conectividad: 'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    mixta:        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
}

// ── Copiar código de pago ──────────────────────────────────────────────────
const copied = ref(false)
const copyCodigo = async () => {
    if (!props.factura.codigo_pago_banco) return
    await navigator.clipboard.writeText(props.factura.codigo_pago_banco)
    copied.value = true
    notifySuccess('Copiado', 'Código de pago copiado al portapapeles.')
    setTimeout(() => { copied.value = false }, 2000)
}

// ── Desglose telefonía vs conectividad ────────────────────────────────────
const totalesPorTipo = computed(() => {
    const out = { telefonia: 0, conectividad: 0 }
    for (const svc of props.factura.servicios ?? []) {
        const t = Number(svc.total_servicio)
        if (svc.match_source === 'connectivity') {
            const tipo = (svc.connectivity_record?.tipo_enlace ?? '').toLowerCase()
            if (tipo.includes('adsl') || tipo.includes('conectividad') || tipo.includes('ip')) {
                out.conectividad += t
            } else {
                out.telefonia += t
            }
        } else {
            out.telefonia += t
        }
    }
    return out
})

const tieneDesglose = computed(() =>
    totalesPorTipo.value.telefonia > 0 && totalesPorTipo.value.conectividad > 0
)

const eliminarFactura = async () => {
    const ok = await confirmDanger({
        title: `Eliminar factura ${props.factura.numero_factura}`,
        text: 'Se eliminarán todos los servicios, cuotas, tráfico y llamadas asociados. Esta acción no se puede deshacer.',
        confirmText: 'Eliminar',
    })
    if (!ok) return
    router.delete(route('etecsa.destroy', props.factura.id), {
        onSuccess: () => notifySuccess('Eliminada', `Factura ${props.factura.numero_factura} eliminada.`),
        onError: () => notifyError('Error', 'No se pudo eliminar la factura.'),
    })
}

function etiquetaVinculo(matchSource) {
    const m = {
        connectivity: 'Catálogo conectividad',
        telefonia_piso: 'Teléfono · piso de venta',
        telefonia_departamento: 'Teléfono · oficina / dept.',
    }
    return m[matchSource] ?? '—'
}

// ── Tabla de servicios ────────────────────────────────────────────────────
const columns = [
    { key: 'vinculo_label',   label: 'Vínculo' },
    { key: 'numero',          label: 'N° Servicio', sortValue: r => r.connectivity_record?.id_facturacion ?? r.numero_servicio },
    { key: 'tipo',            label: 'Tipo' },
    { key: 'ubicacion',       label: 'Ubicación' },
    { key: 'cuota_facturada', label: 'Cuota',    class: 'text-right' },
    { key: 'consumo',         label: 'Consumo',  class: 'text-right' },
    { key: 'total_servicio',  label: 'Total',    class: 'text-right' },
    { key: 'llamadas_count',  label: 'Llamadas', class: 'text-right' },
    { key: 'actions', label: '', sortable: false },
]

const serviciosSearch = ref('')

const tableData = computed(() => {
    const q = serviciosSearch.value.trim().toLowerCase()
    const mapped = (props.factura.servicios ?? []).map(svc => {
        const ubicacion =
            svc.connectivity_record?.sales_floor?.name
            ?? svc.sales_floor_direct?.name
            ?? (svc.department ? `Oficina · ${svc.department.name}` : '—')
        const tipo =
            svc.connectivity_record?.tipo_enlace
            ?? (svc.match_source?.startsWith('telefonia') ? 'Telefonía fija' : '—')
        return {
            ...svc,
            vinculo_label: etiquetaVinculo(svc.match_source),
            numero: svc.connectivity_record?.id_facturacion ?? svc.numero_servicio ?? '—',
            tipo,
            ubicacion,
        }
    })
    if (!q) return { data: mapped }
    const data = mapped.filter(svc => {
        const hay = (v) => String(v ?? '').toLowerCase().includes(q)
        return hay(svc.numero) || hay(svc.tipo) || hay(svc.ubicacion) || hay(svc.numero_servicio)
            || hay(svc.connectivity_record?.id_facturacion) || hay(svc.vinculo_label)
    })
    return { data }
})
</script>

<template>
    <AppLayout :title="`Factura ${factura.numero_factura}`">
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

            <!-- Cabecera -->
            <PageHeader
                :title="`Factura ${factura.numero_factura}`"
                :description="factura.nombre_cliente"
                eyebrow="Facturación ETECSA"
            >
                <template #actions>
                    <span :class="tipoBadgeClass[factura.tipo_factura] ?? 'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-600'" class="capitalize">
                        {{ factura.tipo_factura }}
                    </span>
                    <a :href="route('etecsa.exportar-entidad', factura.id)" class="app-button-secondary">Excel por entidad</a>
                    <button type="button" class="app-button-danger" @click="eliminarFactura">Eliminar factura</button>
                    <a :href="route('etecsa.index')" class="app-button-secondary">← Volver</a>
                </template>
            </PageHeader>

            <!-- ── Sección 1: Identificación de la factura ──────────────────── -->
            <div class="grid gap-4 md:grid-cols-3">

                <!-- Datos del cliente -->
                <BaseCard class="md:col-span-2">
                    <template #header>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Datos del cliente</h3>
                    </template>

                    <div class="grid gap-x-8 gap-y-3 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500">Cliente</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ factura.nombre_cliente }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500">N° Cliente</p>
                            <p class="mt-1 font-mono text-sm text-slate-800 dark:text-slate-200">{{ factura.numero_cliente }}</p>
                        </div>
                        <div v-if="factura.oficina_comercial">
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500">Dirección</p>
                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ factura.oficina_comercial }}</p>
                        </div>
                        <div v-if="factura.zona_postal">
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500">Zona postal</p>
                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ factura.zona_postal }}</p>
                        </div>
                        <div v-if="factura.imported_by" class="sm:col-span-2 border-t border-slate-100 dark:border-slate-700 pt-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500">Importado por</p>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ factura.imported_by.name }}</p>
                        </div>
                    </div>
                </BaseCard>

                <!-- Período y vencimiento -->
                <BaseCard>
                    <template #header>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Período</h3>
                    </template>

                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500">Período de consumo</p>
                            <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">
                                {{ fmtPeriod(factura.periodo_desde, factura.periodo_hasta) }}
                            </p>
                        </div>

                        <div v-if="factura.moneda">
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500">Moneda</p>
                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                                {{ factura.moneda }}
                                <span v-if="factura.tasa_cambio" class="text-slate-400">(TC: {{ factura.tasa_cambio }})</span>
                            </p>
                        </div>
                    </div>
                </BaseCard>
            </div>

            <!-- ── Sección 2: Código de pago (destacado) ────────────────────── -->
            <BaseCard v-if="factura.codigo_pago_banco">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500">Código de pago en banco</p>
                        <p class="mt-1 font-mono text-xl font-bold tracking-widest text-slate-900 dark:text-white lg:text-2xl">
                            {{ factura.codigo_pago_banco }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white/70 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        @click="copyCodigo"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                        </svg>
                        {{ copied ? '¡Copiado!' : 'Copiar' }}
                    </button>
                </div>
            </BaseCard>

            <!-- ── Sección 3: Resumen financiero ────────────────────────────── -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <!-- Cuota mensual -->
                <article class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Cuota mensual</p>
                    <p class="mt-2 font-display text-2xl font-bold text-slate-900 dark:text-white">{{ fmtCup(factura.total_cuota_mensual) }}</p>
                    <p class="mt-1 text-xs text-slate-400">CUP</p>
                </article>

                <!-- Consumo -->
                <article class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Consumo</p>
                    <p class="mt-2 font-display text-2xl font-bold text-slate-900 dark:text-white">{{ fmtCup(factura.total_consumo) }}</p>
                    <p class="mt-1 text-xs text-slate-400">CUP</p>
                </article>

                <!-- Total facturado -->
                <article class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Total facturado</p>
                    <p class="mt-2 font-display text-2xl font-bold text-slate-900 dark:text-white">{{ fmtCup(factura.total_facturado) }}</p>
                    <p class="mt-1 text-xs text-slate-400">CUP</p>
                </article>

                <!-- Total a pagar (destacado) -->
                <article class="surface-card overflow-hidden p-0">
                    <div class="bg-gradient-to-br from-brand-500 to-blue-600 p-5">
                        <p class="text-xs font-semibold uppercase tracking-widest text-white/70">Total a pagar</p>
                        <p class="mt-2 font-display text-2xl font-bold text-white">{{ fmtCup(factura.total_a_pagar) }}</p>
                        <p class="mt-1 text-xs text-white/60">CUP</p>
                    </div>
                    <div v-if="fmtUsd(factura.total_usd)" class="px-5 py-3">
                        <p class="text-xs text-slate-400">Equivalente USD</p>
                        <p class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">{{ fmtUsd(factura.total_usd) }} USD</p>
                    </div>
                </article>
            </div>

            <!-- Desglose cargos: cuota / consumo / comisión / impuesto / saldo -->
            <BaseCard title="Desglose de cargos">
                <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-5">
                    <div v-for="item in [
                        { label: 'Cuota mensual', value: factura.total_cuota_mensual },
                        { label: 'Consumo',       value: factura.total_consumo },
                        { label: 'Comisión',      value: factura.total_comision },
                        { label: 'Impuesto',      value: factura.total_impuesto },
                        { label: 'Saldo anterior', value: factura.total_saldo },
                    ]" :key="item.label" class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/50">
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500">{{ item.label }}</p>
                        <p class="mt-2 font-mono text-lg font-bold text-slate-800 dark:text-slate-200">{{ fmtCup(item.value) }}</p>
                        <p class="text-[10px] text-slate-400">CUP</p>
                    </div>
                </div>
            </BaseCard>

            <!-- ── Sección 4: Desglose telefonía vs conectividad ─────────────── -->
            <div v-if="tieneDesglose" class="grid gap-4 md:grid-cols-2">
                <article class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Telefonía fija</p>
                    <p class="mt-2 font-display text-3xl font-bold text-slate-900 dark:text-white">
                        {{ totalesPorTipo.telefonia.toFixed(2) }} <span class="text-base font-normal text-slate-400">CUP</span>
                    </p>
                </article>
                <article class="surface-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Conectividad / Internet</p>
                    <p class="mt-2 font-display text-3xl font-bold text-slate-900 dark:text-white">
                        {{ totalesPorTipo.conectividad.toFixed(2) }} <span class="text-base font-normal text-slate-400">CUP</span>
                    </p>
                </article>
            </div>

            <!-- ── Sección 5: Tabla de servicios ────────────────────────────── -->
            <BaseCard :padded="false">
                <template #header>
                    <div class="flex flex-col gap-3 px-5 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Servicios facturados</h3>
                            <p class="mt-0.5 text-sm text-slate-500">{{ factura.servicios?.length ?? 0 }} servicios en este período</p>
                        </div>
                        <input
                            v-model="serviciosSearch"
                            type="search"
                            class="app-input w-full max-w-sm shrink-0"
                            placeholder="Filtrar por vínculo, N° servicio, tipo, ubicación…"
                            autocomplete="off"
                        />
                    </div>
                </template>

                <DataTable :columns="columns" :data="tableData" client-table>
                    <template #cell-vinculo_label="{ row }">
                        <span
                            class="inline-flex max-w-[14rem] rounded-lg border px-2 py-1 text-[11px] font-medium leading-tight"
                            :class="{
                                'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-200': row.match_source === 'connectivity',
                                'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-800/60 dark:bg-sky-950/40 dark:text-sky-200': row.match_source === 'telefonia_piso',
                                'border-violet-200 bg-violet-50 text-violet-900 dark:border-violet-800/60 dark:bg-violet-950/40 dark:text-violet-200': row.match_source === 'telefonia_departamento',
                                'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400': !row.match_source,
                            }"
                        >
                            {{ row.vinculo_label }}
                        </span>
                    </template>
                    <template #cell-numero="{ row }">
                        <a :href="route('etecsa.servicios.show', row.id)" class="font-mono text-brand-600 hover:underline dark:text-brand-400">
                            {{ row.numero }}
                        </a>
                    </template>
                    <template #cell-cuota_facturada="{ row }">
                        <span class="font-mono">{{ Number(row.cuota_facturada).toFixed(2) }}</span>
                    </template>
                    <template #cell-consumo="{ row }">
                        <span class="font-mono">{{ Number(row.consumo).toFixed(2) }}</span>
                    </template>
                    <template #cell-total_servicio="{ row }">
                        <span class="font-mono font-semibold">{{ Number(row.total_servicio).toFixed(2) }}</span>
                    </template>
                    <template #cell-llamadas_count="{ row }">
                        <span v-if="row.llamadas_count > 0" class="text-slate-700 dark:text-slate-300">{{ row.llamadas_count }}</span>
                        <span v-else class="text-slate-300">—</span>
                    </template>
                    <template #actions="{ row }">
                        <a :href="route('etecsa.servicios.show', row.id)" class="text-xs text-brand-600 hover:underline dark:text-brand-400">Ver detalle</a>
                    </template>
                </DataTable>
            </BaseCard>

        </div>
    </AppLayout>
</template>
