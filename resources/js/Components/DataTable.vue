<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
    columns: { type: Array, required: true },
    data: { type: Object, required: true },
    /** Ordenación solo en cliente, sobre la página cargada (no nuevas consultas al servidor). */
    clientTable: { type: Boolean, default: false },
})

const PER_PAGE_OPTIONS = [
    { label: '5', value: 5 },
    { label: '10', value: 10 },
    { label: '15', value: 15 },
    { label: '20', value: 20 },
    { label: '25', value: 25 },
    { label: '50', value: 50 },
    { label: '100', value: 100 },
    { label: 'Todos', value: 'all' },
]

const currentPerPage = computed(() => {
    const qp = new URLSearchParams(window.location.search).get('per_page')
    if (qp === 'all') return 'all'
    if (qp !== null && qp !== '') {
        const n = Number(qp)
        return Number.isNaN(n) ? (props.data?.per_page ?? 25) : n
    }
    return props.data?.per_page ?? 25
})

const changePerPage = (event) => {
    const value = event.target.value
    const params = Object.fromEntries(new URLSearchParams(window.location.search))
    params.per_page = value
    params.page = 1
    router.get(window.location.pathname, params, {
        preserveScroll: true,
        preserveState: false,
    })
}

const from = computed(() => props.data?.from ?? 0)
const to = computed(() => props.data?.to ?? 0)
const total = computed(() => props.data?.total ?? 0)

function getNested(obj, path) {
    if (path == null || path === '' || !obj) return undefined
    return String(path).split('.').reduce((o, k) => o?.[k], obj)
}

function cellTextForSort(row, col) {
    if (typeof col.sortValue === 'function') return col.sortValue(row)
    const v = getNested(row, col.key)
    if (v == null) return ''
    if (typeof v === 'object') return JSON.stringify(v)
    return String(v)
}

const sortKey = ref(null)
const sortDir = ref('asc')

watch(
    () => props.data?.data,
    () => {
        if (!props.clientTable) return
        sortKey.value = null
        sortDir.value = 'asc'
    }
)

const baseRows = computed(() => props.data?.data ?? [])

const displayRows = computed(() => {
    let rows = [...baseRows.value]
    if (!props.clientTable) return rows

    if (sortKey.value) {
        const col = props.columns.find((c) => c.key === sortKey.value)
        if (col && col.sortable !== false) {
            const mult = sortDir.value === 'desc' ? -1 : 1
            rows = [...rows].sort((a, b) => {
                const sa = cellTextForSort(a, col)
                const sb = cellTextForSort(b, col)
                const na = Number(sa)
                const nb = Number(sb)
                if (sa !== '' && sb !== '' && !Number.isNaN(na) && !Number.isNaN(nb) && String(sa).trim() === String(na) && String(sb).trim() === String(nb)) {
                    return (na - nb) * mult
                }
                return String(sa).localeCompare(String(sb), 'es', { numeric: true, sensitivity: 'base' }) * mult
            })
        }
    }

    return rows
})

function toggleSort(key) {
    const col = props.columns.find((c) => c.key === key)
    if (!col || col.sortable === false || key === 'actions') return
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortKey.value = key
        sortDir.value = 'asc'
    }
}

function sortIndicator(key) {
    if (sortKey.value !== key) return ''
    return sortDir.value === 'asc' ? ' ▲' : ' ▼'
}
</script>

<template>
    <div class="space-y-4">
        <div class="surface-card relative overflow-x-auto rounded-2xl">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50/80 text-xs uppercase text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                    <tr>
                        <th v-for="col in columns" :key="col.key" class="px-6 py-3">
                            <button
                                v-if="clientTable && col.sortable !== false && col.key !== 'actions'"
                                type="button"
                                class="inline-flex items-center gap-1 font-semibold tracking-wide text-slate-600 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-400"
                                @click="toggleSort(col.key)"
                            >
                                {{ col.label }}{{ sortIndicator(col.key) }}
                            </button>
                            <span v-else>{{ col.label }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, index) in displayRows"
                        :key="row.id ?? index"
                        class="border-b border-slate-200/70 bg-white/70 transition hover:bg-brand-50/40 dark:border-slate-700/70 dark:bg-slate-900/35 dark:hover:bg-brand-500/5"
                    >
                        <td v-for="col in columns" :key="col.key" class="px-6 py-4">
                            <slot :name="'cell-' + col.key" :row="row" :value="row[col.key]">
                                {{ row[col.key] }}
                            </slot>
                        </td>
                    </tr>
                    <tr v-if="!displayRows || displayRows.length === 0">
                        <td :colspan="columns.length" class="px-6 py-8 text-center text-slate-400 dark:text-slate-500">
                            <slot name="empty">No se encontraron registros.</slot>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/70 px-4 py-3 dark:border-slate-700/70">
                <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
                    <span v-if="total > 0">
                        {{ from }}–{{ to }} de {{ total }} registros
                    </span>
                    <span v-if="clientTable" class="text-xs text-slate-400 dark:text-slate-500">
                        Orden por columnas: solo los registros visibles en esta página.
                    </span>
                    <label class="flex items-center gap-1.5">
                        <span class="text-xs">Filas:</span>
                        <select
                            :value="currentPerPage"
                            class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            @change="changePerPage"
                        >
                            <option v-for="opt in PER_PAGE_OPTIONS" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </label>
                </div>

                <nav v-if="data.links && data.links.length > 3" class="flex items-center gap-1">
                    <Link
                        v-for="(link, index) in data.links"
                        :key="index"
                        :href="link.url ?? ''"
                        class="rounded-xl border px-3 py-1 text-sm"
                        :class="{
                            'border-ink-900 bg-ink-900 text-white dark:border-brand-400 dark:bg-brand-400 dark:text-slate-950': link.active,
                            'border-slate-300 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800': !link.active && link.url,
                            'pointer-events-none border-slate-200 text-slate-300 dark:border-slate-800 dark:text-slate-600': !link.url,
                        }"
                        v-html="link.label"
                        preserve-scroll
                    />
                </nav>
            </div>
        </div>
    </div>
</template>
