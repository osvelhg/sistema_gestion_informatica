<script setup>
import { ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({
    floors: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
let searchTimer
watch(search, (value) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(
        () =>
            router.get(
                route('pisos-venta.areas-qr.index'),
                { search: value || undefined },
                { preserveState: true, replace: true },
            ),
        300,
    )
})

const qByArea = ref({})
const resultsByArea = ref({})
const selByArea = ref({})
const busyByArea = ref({})
const areaTimers = {}
const qByPiso = ref({})
const resultsByPiso = ref({})
const selByPiso = ref({})
const busyByPiso = ref({})
const pisoTimers = {}
const lastSubmitKey = ref(null)

const linkForm = useForm({
    link_type: 'area',
    area_venta_id: null,
    sales_floor_id: null,
    fuente_id: null,
})

function searchDebounced(kind, id, value, timersRef, busyRef, resultsRef) {
    clearTimeout(timersRef[id])
    timersRef[id] = setTimeout(async () => {
        const q = String(value || '').trim()
        if (!q) {
            resultsRef.value = { ...resultsRef.value, [id]: [] }
            return
        }
        busyRef.value = { ...busyRef.value, [id]: true }
        try {
            const { data } = await axios.get(route('pisos-venta.areas-qr.buscar-fuentes'), { params: { q } })
            resultsRef.value = { ...resultsRef.value, [id]: data.fuentes || [] }
        } finally {
            busyRef.value = { ...busyRef.value, [id]: false }
        }
    }, 300)
}

function onSearchArea(areaId, value) {
    qByArea.value = { ...qByArea.value, [areaId]: value }
    searchDebounced('area', areaId, value, areaTimers, busyByArea, resultsByArea)
}

function onSearchPiso(floorId, value) {
    qByPiso.value = { ...qByPiso.value, [floorId]: value }
    searchDebounced('piso', floorId, value, pisoTimers, busyByPiso, resultsByPiso)
}

function pickFuenteArea(areaId, f) {
    selByArea.value = { ...selByArea.value, [areaId]: f }
    resultsByArea.value = { ...resultsByArea.value, [areaId]: [] }
    qByArea.value = { ...qByArea.value, [areaId]: [f.source, f.source_name].filter(Boolean).join(' · ') }
}

function pickFuentePiso(floorId, f) {
    selByPiso.value = { ...selByPiso.value, [floorId]: f }
    resultsByPiso.value = { ...resultsByPiso.value, [floorId]: [] }
    qByPiso.value = { ...qByPiso.value, [floorId]: [f.source, f.source_name].filter(Boolean).join(' · ') }
}

function submitLinkArea(areaId) {
    const f = selByArea.value[areaId]
    if (!f) return
    lastSubmitKey.value = `a:${areaId}`
    linkForm.link_type = 'area'
    linkForm.area_venta_id = areaId
    linkForm.sales_floor_id = null
    linkForm.fuente_id = f.id
    linkForm.post(route('pisos-venta.areas-qr.vinculos.store'), {
        preserveScroll: true,
        onSuccess: () => {
            selByArea.value = { ...selByArea.value, [areaId]: null }
            qByArea.value = { ...qByArea.value, [areaId]: '' }
            resultsByArea.value = { ...resultsByArea.value, [areaId]: [] }
            lastSubmitKey.value = null
        },
    })
}

function submitLinkPiso(floorId) {
    const f = selByPiso.value[floorId]
    if (!f) return
    lastSubmitKey.value = `p:${floorId}`
    linkForm.link_type = 'floor'
    linkForm.area_venta_id = null
    linkForm.sales_floor_id = floorId
    linkForm.fuente_id = f.id
    linkForm.post(route('pisos-venta.areas-qr.vinculos.store'), {
        preserveScroll: true,
        onSuccess: () => {
            selByPiso.value = { ...selByPiso.value, [floorId]: null }
            qByPiso.value = { ...qByPiso.value, [floorId]: '' }
            resultsByPiso.value = { ...resultsByPiso.value, [floorId]: [] }
            lastSubmitKey.value = null
        },
    })
}

async function unlinkArea(pivotId) {
    if (!(await confirmDanger({ title: 'Quitar vínculo', text: 'Se desvinculará esta fuente del área.', confirmText: 'Sí, quitar' }))) return
    router.delete(route('pisos-venta.areas-qr.vinculos-area.destroy', pivotId), { preserveScroll: true })
}

async function unlinkPiso(pivotId) {
    if (!(await confirmDanger({ title: 'Quitar vínculo', text: 'Se desvinculará esta fuente del piso de venta.', confirmText: 'Sí, quitar' }))) return
    router.delete(route('pisos-venta.areas-qr.vinculos-piso.destroy', pivotId), { preserveScroll: true })
}

function fuenteLabel(f) {
    const canal = f.canal_electronico?.nombre || 'Sin canal'
    const mon = f.moneda ? ` · ${f.moneda}` : ''
    const act = f.activo === false ? ' (inactiva)' : ''
    const areas = (f.areas_venta || []).map((a) => a.name).join(', ')
    const areasPart = areas ? ` · [${areas}]` : ''
    return `${f.source || '—'} — ${f.source_name || '—'} · ${canal}${mon}${areasPart}${act}`
}

function showLinkErr(key) {
    return linkForm.errors.fuente_id && lastSubmitKey.value === key
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-5xl space-y-3 px-1">
            <PageHeader
                eyebrow="Pisos de venta"
                title="QR por piso y por área"
                description="PV pequeños pueden llevar fuentes directas al piso; si hay áreas, también enlázalas aquí. Una fuente por canal en cada nivel."
            >
                <template #actions>
                    <Link :href="route('areas-venta.index')" class="app-button-secondary text-sm">Áreas de venta</Link>
                    <Link :href="route('pisos-venta.index')" class="app-button-secondary text-sm">Pisos</Link>
                </template>
            </PageHeader>

            <div class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200/80 bg-white/70 px-3 py-2 dark:border-slate-700/70 dark:bg-slate-900/40">
                <div class="min-w-[12rem] flex-1">
                    <label class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Buscar PV</label>
                    <input v-model="search" type="search" class="app-input py-1.5 text-sm" placeholder="Nombre…" autocomplete="off" />
                </div>
            </div>

            <p v-if="!floors?.data?.length" class="text-xs text-slate-500">Sin resultados.</p>

            <BaseCard v-else class="overflow-hidden p-0">
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <details v-for="floor in floors.data" :key="floor.id" class="group">
                        <summary
                            class="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-sm marker:content-none hover:bg-slate-50/80 dark:hover:bg-slate-900/50 [&::-webkit-details-marker]:hidden"
                        >
                            <span v-if="floor.network_type" class="shrink-0 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-800 dark:bg-violet-900/40 dark:text-violet-200">{{ floor.network_type.name }}</span>
                            <span class="font-semibold text-slate-900 dark:text-white">{{ floor.name }}</span>
                            <span class="text-[11px] text-slate-500">
                                {{ floor.entity ? `[${floor.entity.code || '—'}] ${floor.entity.name}` : '—' }}
                            </span>
                            <span class="ml-auto flex flex-wrap items-center gap-1">
                                <span class="text-[10px] text-slate-400">{{ (floor.areas_venta || []).length }} área(s)</span>
                                <template v-for="src in floor.piso_datacell_fuentes || []" :key="'p' + (src.pivot?.id || src.id)">
                                    <span
                                        class="max-w-[12rem] truncate rounded bg-cyan-500/15 px-1.5 py-0.5 text-[10px] font-medium text-cyan-900 dark:text-cyan-200"
                                        :title="[src.source_name, src.moneda].filter(Boolean).join(' · ')"
                                    >
                                        {{ src.source }}{{ src.moneda ? ' · ' + src.moneda : '' }}
                                    </span>
                                </template>
                            </span>
                            <svg
                                class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </summary>

                        <div class="space-y-2 border-t border-slate-100 bg-slate-50/40 px-3 py-2 dark:border-slate-800 dark:bg-slate-900/25">
                            <!-- QR a nivel piso -->
                            <div class="rounded-lg border border-dashed border-cyan-200/80 bg-white/60 px-2 py-1.5 dark:border-cyan-500/20 dark:bg-slate-900/30">
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-cyan-800 dark:text-cyan-300">Piso (sin área)</p>
                                <div class="flex flex-wrap gap-1">
                                    <template v-for="src in floor.piso_datacell_fuentes || []" :key="'pb' + src.pivot?.id">
                                        <span
                                            class="inline-flex max-w-full items-center gap-1 rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[11px] dark:border-slate-600 dark:bg-slate-900"
                                        >
                                            <code class="truncate font-mono text-[10px] text-brand-700 dark:text-brand-300">{{ src.source }}</code>
                                            <button
                                                type="button"
                                                class="text-[10px] text-red-600 hover:text-red-800 dark:text-red-400"
                                                @click.stop="unlinkPiso(src.pivot.id)"
                                            >
                                                ×
                                            </button>
                                        </span>
                                    </template>
                                    <span v-if="!(floor.piso_datacell_fuentes || []).length" class="text-[11px] text-slate-400">Ninguna</span>
                                </div>
                                <div class="mt-1.5 flex flex-wrap items-start gap-1.5">
                                    <input
                                        :value="qByPiso[floor.id] || ''"
                                        class="app-input min-w-[8rem] flex-1 py-1 text-[11px]"
                                        type="search"
                                        placeholder="Buscar fuente…"
                                        autocomplete="off"
                                        @click.stop
                                        @input="onSearchPiso(floor.id, $event.target.value)"
                                    />
                                    <button
                                        type="button"
                                        class="rounded-lg bg-brand-600 px-2 py-1 text-[11px] font-medium text-white disabled:opacity-40"
                                        :disabled="linkForm.processing || !selByPiso[floor.id]"
                                        @click.stop="submitLinkPiso(floor.id)"
                                    >
                                        + Piso
                                    </button>
                                </div>
                                <ul
                                    v-if="resultsByPiso[floor.id]?.length"
                                    class="mt-1 max-h-28 overflow-auto rounded border border-slate-200 bg-white text-[11px] dark:border-slate-700 dark:bg-slate-900"
                                    @click.stop
                                >
                                    <li
                                        v-for="f in resultsByPiso[floor.id]"
                                        :key="f.id"
                                        class="cursor-pointer border-b border-slate-100 px-2 py-1 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"
                                        @click="pickFuentePiso(floor.id, f)"
                                    >
                                        {{ fuenteLabel(f) }}
                                    </li>
                                </ul>
                                <p v-if="showLinkErr('p:' + floor.id)" class="mt-1 text-[11px] text-red-600">{{ linkForm.errors.fuente_id }}</p>
                            </div>

                            <!-- Áreas -->
                            <div v-if="!(floor.areas_venta || []).length" class="text-[11px] text-slate-500">Sin áreas declaradas; use QR a nivel piso.</div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="area in floor.areas_venta"
                                    :key="area.id"
                                    class="rounded-lg border border-slate-200/80 bg-white/80 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900/40"
                                    @click.stop
                                >
                                    <div class="mb-1 flex items-center justify-between gap-2">
                                        <span class="text-xs font-medium text-slate-800 dark:text-slate-100">{{ area.name }}</span>
                                        <span class="font-mono text-[10px] text-slate-400">#{{ area.id }}</span>
                                    </div>
                                    <div class="mb-1 flex flex-wrap gap-1">
                                        <template v-for="src in area.datacell_sources || []" :key="'a' + src.pivot?.id">
                                            <span
                                                class="inline-flex max-w-full items-center gap-1 rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[11px] dark:border-slate-600 dark:bg-slate-800"
                                            >
                                                <code class="truncate font-mono text-[10px]">{{ src.source }}</code>
                                                <button type="button" class="text-[10px] text-red-600" @click="unlinkArea(src.pivot.id)">×</button>
                                            </span>
                                        </template>
                                        <span v-if="!(area.datacell_sources || []).length" class="text-[11px] text-slate-400">Sin QR</span>
                                    </div>
                                    <div class="flex flex-wrap items-start gap-1.5">
                                        <input
                                            :value="qByArea[area.id] || ''"
                                            class="app-input min-w-[6rem] flex-1 py-1 text-[11px]"
                                            type="search"
                                            placeholder="Fuente…"
                                            autocomplete="off"
                                            @input="onSearchArea(area.id, $event.target.value)"
                                        />
                                        <button
                                            type="button"
                                            class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-[11px] font-medium dark:border-slate-600 dark:bg-slate-800"
                                            :disabled="linkForm.processing || !selByArea[area.id]"
                                            @click="submitLinkArea(area.id)"
                                        >
                                            + Área
                                        </button>
                                    </div>
                                    <ul
                                        v-if="resultsByArea[area.id]?.length"
                                        class="mt-1 max-h-24 overflow-auto rounded border border-slate-200 bg-white text-[11px] dark:border-slate-700"
                                    >
                                        <li
                                            v-for="f in resultsByArea[area.id]"
                                            :key="f.id"
                                            class="cursor-pointer border-b border-slate-100 px-2 py-1 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"
                                            @click="pickFuenteArea(area.id, f)"
                                        >
                                            {{ fuenteLabel(f) }}
                                        </li>
                                    </ul>
                                    <p v-if="showLinkErr('a:' + area.id)" class="mt-1 text-[11px] text-red-600">{{ linkForm.errors.fuente_id }}</p>
                                </div>
                            </div>
                        </div>
                    </details>
                </div>
            </BaseCard>

            <div
                v-if="floors?.links?.length > 3"
                class="flex flex-col gap-2 border-t border-slate-200/80 pt-3 text-xs dark:border-slate-700/70 md:flex-row md:items-center md:justify-between"
            >
                <p class="text-slate-500">{{ floors.from }}–{{ floors.to }} / {{ floors.total }}</p>
                <div class="flex flex-wrap gap-1">
                    <template v-for="link in floors.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-state
                            preserve-scroll
                            class="inline-flex min-w-[2rem] items-center justify-center rounded-lg border border-slate-200 px-2 py-1 dark:border-slate-700"
                            :class="
                                link.active
                                    ? 'bg-slate-900 text-white dark:bg-cyan-400 dark:text-slate-950'
                                    : 'text-slate-600 dark:text-slate-300'
                            "
                            v-html="link.label"
                        />
                        <span v-else class="inline-flex min-w-[2rem] justify-center px-1 text-slate-400" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
