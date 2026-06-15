<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import DataTable from '@/Components/DataTable.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import SalesFloorMap from '@/Components/SalesFloorMap.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({
    floors: Object,
    filters: Object,
    entities: Array,
    cashRegisterModels: Array,
    networkTypes: Array,
    establishmentTypes: Array,
    establishmentStatuses: Array,
    mapPoints: Array,
})

const search = ref(props.filters?.search || '')
const activeTab = ref('lista')
const showModal = ref(false)
const editing = ref(null)

const form = useForm({
    entity_id: '',
    name: '',
    address: '',
    phone: '',
    active: true,
    network_type_id: '',
    establishment_type_id: '',
    establishment_status_id: '',
    latitude: '',
    longitude: '',
    codigo_golden: '',
    almacen_golden: '',
    cash_registers: [],
})

const columns = [
    { key: 'name',    label: 'Piso de venta' },
    { key: 'entity',  label: 'Entidad' },
    { key: 'areas',   label: 'Áreas de venta' },
    { key: 'network', label: 'Tipo de red' },
    { key: 'address', label: 'Dirección/Teléfono' },
    { key: 'models',  label: 'Resumen POS/TPV' },
    { key: 'active',  label: 'Estado' },
    { key: 'actions', label: 'Acciones' },
]

/** Agrupa filas de PV con mismo nombre + entidad (duplicados en BD → una fila en lista). */
function groupFloorsByNameEntity(rows) {
    if (!rows?.length) return []
    const map = new Map()
    for (const row of rows) {
        const name = String(row.name ?? '').trim().toLowerCase()
        const eid = row.entity_id ?? 'null'
        const key = `${eid}|${name}`
        if (!map.has(key)) map.set(key, [])
        map.get(key).push(row)
    }
    return [...map.entries()].map(([groupKey, floors]) => {
        const primary = floors[0]
        const seenArea = new Set()
        const allAreas = []
        for (const f of floors) {
            for (const a of f.areas_venta || []) {
                if (a?.id != null && !seenArea.has(a.id)) {
                    seenArea.add(a.id)
                    allAreas.push(a)
                }
            }
        }
        const seenNet = new Set()
        const networkTypes = []
        for (const f of floors) {
            const nt = f.network_type
            if (nt?.id != null && !seenNet.has(nt.id)) {
                seenNet.add(nt.id)
                networkTypes.push(nt)
            }
        }
        const addrSig = (f) => `${f.address ?? ''}|${f.phone ?? ''}`
        const addrVaries = floors.some((f) => addrSig(f) !== addrSig(primary))

        return {
            id: `grp-${groupKey}`,
            _groupKey: groupKey,
            _groupCount: floors.length,
            _floors: floors,
            name: primary.name,
            entity: primary.entity,
            entity_id: primary.entity_id,
            municipio: primary.municipio,
            areas_venta: allAreas,
            network_type: networkTypes[0] ?? null,
            network_types: networkTypes,
            address: primary.address,
            phone: primary.phone,
            _addressVaries: addrVaries,
            _floorsAddresses: floors.map((f) => ({
                id: f.id,
                address: f.address,
                phone: f.phone,
                name: f.name,
            })),
            active: floors.some((f) => f.active),
            _activeMixed: floors.some((f) => !!f.active) && floors.some((f) => !f.active),
        }
    })
}

const floorsTableData = computed(() => {
    const src = props.floors
    if (!src) return src
    return {
        ...src,
        data: groupFloorsByNameEntity(src.data || []),
    }
})

const areasResume = (row) => {
    const areas = row.areas_venta || []
    if (!areas.length) return 'Sin áreas'

    const total = areas.reduce(
        (acc, a) => {
            acc.tpv += Number(a.tpv_boxes || 0)
            acc.tel += Number(a.pos_phone_qty || 0)
            acc.ip += Number(a.pos_ip_qty || 0)
            acc.gprs += Number(a.pos_gprs_qty || 0)
            return acc
        },
        { tpv: 0, tel: 0, ip: 0, gprs: 0 }
    )

    return `Áreas ${areas.length} · TPV ${total.tpv} · Tel ${total.tel} · IP ${total.ip} · GPRS ${total.gprs}`
}

const entityOptions = computed(() =>
    (props.entities || []).map(e => ({
        id: e.id,
        label: [e.municipio?.name, e.code ? `[${e.code}]` : null, e.name].filter(Boolean).join(' · ') || e.name,
    }))
)

const networkTypeOptions = computed(() =>
    (props.networkTypes || []).map(nt => ({ id: nt.id, label: nt.name }))
)
const establishmentTypeOptions = computed(() =>
    (props.establishmentTypes || []).map(et => ({ id: et.id, label: et.name }))
)
const establishmentStatusOptions = computed(() =>
    (props.establishmentStatuses || []).map(es => ({ id: es.id, label: es.name }))
)

const colorClass = (color) => ({
    blue:   'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
    green:  'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300',
    cyan:   'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300',
    yellow: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
    slate:  'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300',
    red:    'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
    orange: 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
}[color] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300')

let timeout
watch(search, (value) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => router.get('/pisos-venta', { search: value || undefined }, { preserveState: true, replace: true }), 300)
})

const setRegisterQuantity = (modelId, quantity) => {
    const idx = form.cash_registers.findIndex(r => Number(r.model_id) === Number(modelId))
    if (idx >= 0) form.cash_registers[idx].quantity = Number(quantity || 0)
    else form.cash_registers.push({ model_id: modelId, quantity: Number(quantity || 0) })
}
const registerQuantity = (modelId) =>
    form.cash_registers.find(r => Number(r.model_id) === Number(modelId))?.quantity || 0

const openCreate = () => {
    editing.value = null
    form.reset()
    form.active = true
    form.cash_registers = props.cashRegisterModels.map(m => ({ model_id: m.id, quantity: 0 }))
    form.clearErrors()
    showModal.value = true
}

const openEdit = (row) => {
    editing.value = row
    form.entity_id               = row.entity_id || ''
    form.name                    = row.name || ''
    form.address                 = row.address || ''
    form.phone                   = row.phone || ''
    form.active                  = row.active
    form.network_type_id         = row.network_type_id || ''
    form.establishment_type_id   = row.establishment_type_id || ''
    form.establishment_status_id = row.establishment_status_id || ''
    form.latitude                = row.latitude ?? ''
    form.longitude               = row.longitude ?? ''
    form.codigo_golden           = row.codigo_golden ?? ''
    form.almacen_golden          = row.almacen_golden ?? ''
    const base = props.cashRegisterModels.map(m => ({ model_id: m.id, quantity: 0 }))
    row.cash_register_models?.forEach(m => {
        const item = base.find(b => Number(b.model_id) === Number(m.id))
        if (item) item.quantity = Number(m.pivot?.quantity || 0)
    })
    form.cash_registers = base
    form.clearErrors()
    showModal.value = true
}

const submit = () =>
    editing.value
        ? form.put(`/pisos-venta/${editing.value.id}`, { onSuccess: () => (showModal.value = false) })
        : form.post('/pisos-venta', { onSuccess: () => (showModal.value = false) })

const destroy = async (row) => {
    if (!await confirmDanger({ title: 'Eliminar piso de venta', text: `Se eliminará "${row.name}".`, confirmText: 'Sí, eliminar' })) return
    router.delete(`/pisos-venta/${row.id}`)
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <PageHeader eyebrow="Nomenclador" title="Pisos de venta" description="Puntos de venta con clasificación comercial, estado y geolocalización.">
                <template #actions>
                    <Link :href="route('areas-venta.index')" class="app-button-secondary">Áreas de venta</Link>
                    <Link :href="route('pisos-venta.areas-qr.index')" class="app-button-secondary">QR piso / área</Link>
                    <button class="app-button-primary" @click="openCreate">Nuevo PV</button>
                </template>
            </PageHeader>

            <!-- Tabs Lista / Mapa -->
            <div class="flex gap-1 w-fit rounded-2xl border border-slate-200/80 bg-white/60 p-1 dark:border-slate-700/70 dark:bg-slate-900/50">
                <button
                    v-for="tab in [{ key: 'lista', label: 'Lista' }, { key: 'mapa', label: 'Mapa' }]"
                    :key="tab.key"
                    type="button"
                    :class="['rounded-xl px-4 py-2 text-sm font-semibold transition', activeTab === tab.key ? 'bg-white shadow text-slate-950 dark:bg-slate-800 dark:text-white' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white']"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                    <span v-if="tab.key === 'mapa' && mapPoints?.length" class="ml-1.5 rounded-full bg-brand-100 px-1.5 py-0.5 text-xs font-bold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">
                        {{ mapPoints.length }}
                    </span>
                </button>
            </div>

            <!-- Lista -->
            <template v-if="activeTab === 'lista'">
                <BaseCard>
                    <input v-model="search" type="text" class="app-input" placeholder="Buscar PV..." />
                </BaseCard>
                <DataTable client-table :columns="columns" :data="floorsTableData">
                    <template #cell-name="{ row, value }">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ value }}</span>
                            <span
                                v-if="row._groupCount > 1"
                                class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                                :title="`${row._groupCount} registros de piso con el mismo nombre en esta entidad; se muestran áreas y POS agrupados.`"
                            >
                                {{ row._groupCount }} PV
                            </span>
                        </div>
                    </template>
                    <template #cell-entity="{ row }">
                        <div>
                            <p>{{ row.entity?.name || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.municipio?.name || '-' }}</p>
                        </div>
                    </template>
                    <template #cell-areas="{ row }">
                        <details v-if="row.areas_venta?.length" class="group max-w-sm">
                            <summary class="cursor-pointer text-xs font-medium text-indigo-600 dark:text-indigo-400">
                                {{ row.areas_venta.length }} área(s)
                            </summary>
                            <div class="mt-2 space-y-1 text-xs text-slate-600 dark:text-slate-300">
                                <p
                                    v-for="area in row.areas_venta"
                                    :key="area.id"
                                    class="rounded-lg bg-slate-50 px-2 py-1 dark:bg-slate-800/80"
                                >
                                    <span class="font-medium">{{ area.name }}</span>
                                    <span class="text-slate-400"> · TPV {{ area.tpv_boxes || 0 }}</span>
                                    <span class="text-slate-400"> · Tel {{ area.pos_phone_qty || 0 }}</span>
                                    <span class="text-slate-400"> · IP {{ area.pos_ip_qty || 0 }}</span>
                                    <span class="text-slate-400"> · GPRS {{ area.pos_gprs_qty || 0 }}</span>
                                </p>
                            </div>
                        </details>
                        <span v-else class="text-xs text-slate-400">Sin áreas</span>
                    </template>
                    <template #cell-network="{ row }">
                        <div v-if="row.network_types?.length" class="flex flex-wrap gap-1">
                            <span
                                v-for="nt in row.network_types"
                                :key="nt.id"
                                :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold', colorClass(nt.color)]"
                            >
                                {{ nt.name }}
                            </span>
                        </div>
                        <span v-else class="text-xs text-slate-400">—</span>
                    </template>
                    <template #cell-address="{ row }">
                        <div>
                            <p>{{ row.address || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.phone || '-' }}</p>
                            <details v-if="row._addressVaries" class="mt-1 text-[10px] text-amber-700 dark:text-amber-300">
                                <summary class="cursor-pointer font-medium">Otros datos en registros duplicados</summary>
                                <ul class="mt-1 space-y-0.5 pl-2 text-slate-600 dark:text-slate-400">
                                    <li v-for="fa in row._floorsAddresses" :key="fa.id">
                                        <span class="font-mono text-slate-400">#{{ fa.id }}</span>
                                        {{ fa.address || '—' }} · {{ fa.phone || '—' }}
                                    </li>
                                </ul>
                            </details>
                        </div>
                    </template>
                    <template #cell-models="{ row }">
                        <span class="text-xs">{{ areasResume(row) }}</span>
                    </template>
                    <template #cell-active="{ row, value }">
                        <div class="flex flex-col gap-0.5">
                            <StatusBadge :status="value ? 'Activo' : 'Inactivo'" :color="value ? 'green' : 'red'" />
                            <span v-if="row._activeMixed" class="text-[10px] text-amber-700 dark:text-amber-300">Varía entre PV</span>
                        </div>
                    </template>
                    <template #cell-actions="{ row }">
                        <div v-if="row._groupCount <= 1" class="flex justify-end gap-2">
                            <button class="app-button-secondary px-3 py-2 text-xs" @click="openEdit(row._floors[0])">Editar</button>
                            <button class="app-button-danger px-3 py-2 text-xs" @click="destroy(row._floors[0])">Eliminar</button>
                        </div>
                        <details v-else class="text-right">
                            <summary class="cursor-pointer text-xs font-medium text-brand-600 dark:text-brand-400">Acciones por PV ({{ row._groupCount }})</summary>
                            <div class="mt-2 flex flex-col items-end gap-1.5">
                                <div
                                    v-for="f in row._floors"
                                    :key="f.id"
                                    class="flex flex-wrap items-center justify-end gap-1 rounded border border-slate-200/80 bg-slate-50/80 px-2 py-1 dark:border-slate-600 dark:bg-slate-800/50"
                                >
                                    <span class="text-[10px] text-slate-500">#{{ f.id }}</span>
                                    <button type="button" class="app-button-secondary px-2 py-1 text-[10px]" @click="openEdit(f)">Editar</button>
                                    <button type="button" class="app-button-danger px-2 py-1 text-[10px]" @click="destroy(f)">Eliminar</button>
                                </div>
                            </div>
                        </details>
                    </template>
                </DataTable>
            </template>

            <!-- Mapa: v-if para que el contenedor tenga tamaño real al crear MapLibre (evita coords rotas) -->
            <div v-if="activeTab === 'mapa'">
                <SalesFloorMap :points="mapPoints || []" :color-class="colorClass" :network-types="networkTypes || []" />
            </div>
        </div>

        <!-- Modal crear/editar -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-8">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                <div class="surface-card relative z-10 w-full max-w-4xl p-6">
                    <h3 class="mb-1 text-xl font-semibold text-slate-950 dark:text-slate-100">
                        {{ editing ? 'Editar piso de venta' : 'Nuevo piso de venta' }}
                    </h3>
                    <p class="mb-5 text-xs text-slate-400 dark:text-slate-500">Información del punto de venta, clasificación y coordenadas</p>
                    <form class="space-y-5" @submit.prevent="submit">

                        <!-- Datos básicos -->
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-1.5 md:col-span-2">
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Entidad <span class="text-slate-400 font-normal">(incluye municipio en la jerarquía)</span></label>
                                <VSelect
                                    v-model="form.entity_id"
                                    :options="entityOptions"
                                    :reduce="opt => opt.id"
                                    placeholder="Buscar entidad…"
                                    :clearable="true"
                                    :filterable="true"
                                />
                            </div>
                            <input v-model="form.name" class="app-input" type="text" placeholder="Nombre del PV *" />
                            <input v-model="form.phone" class="app-input" type="text" placeholder="Teléfono" />
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <input v-model="form.address" class="app-input md:col-span-2" type="text" placeholder="Dirección" />
                            <div class="md:col-span-2 space-y-1">
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Código Golden (sistema contable)</label>
                                <input v-model="form.codigo_golden" class="app-input font-mono text-sm" type="text" placeholder="Identificador contable / integración futura" autocomplete="off" />
                                <p v-if="form.errors.codigo_golden" class="text-xs text-red-500">{{ form.errors.codigo_golden }}</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Almacén Golden</label>
                                <input v-model="form.almacen_golden" class="app-input text-sm" type="text" placeholder="Ej. Piso de Ventas MLC (JSON Almacen)" autocomplete="off" />
                                <p v-if="form.errors.almacen_golden" class="text-xs text-red-500">{{ form.errors.almacen_golden }}</p>
                            </div>
                        </div>

                        <!-- Clasificación -->
                        <div class="rounded-xl border border-slate-200/80 dark:border-slate-700/70">
                            <div class="border-b border-slate-200/80 bg-slate-50/80 px-4 py-2 dark:border-slate-700/70 dark:bg-slate-900/60">
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Clasificación del establecimiento</p>
                            </div>
                            <div class="grid gap-4 p-4 md:grid-cols-3">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Tipo de red comercial</label>
                                    <VSelect
                                        v-model="form.network_type_id"
                                        :options="networkTypeOptions"
                                        :reduce="opt => opt.id"
                                        placeholder="Sin clasificar"
                                        :clearable="true"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Tipo de establecimiento</label>
                                    <VSelect
                                        v-model="form.establishment_type_id"
                                        :options="establishmentTypeOptions"
                                        :reduce="opt => opt.id"
                                        placeholder="Sin clasificar"
                                        :clearable="true"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Estado del establecimiento</label>
                                    <VSelect
                                        v-model="form.establishment_status_id"
                                        :options="establishmentStatusOptions"
                                        :reduce="opt => opt.id"
                                        placeholder="Sin definir"
                                        :clearable="true"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Geolocalización -->
                        <div class="rounded-xl border border-slate-200/80 dark:border-slate-700/70">
                            <div class="border-b border-slate-200/80 bg-slate-50/80 px-4 py-2 dark:border-slate-700/70 dark:bg-slate-900/60">
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
                                    Geolocalización
                                    <span class="ml-1 text-xs font-normal text-slate-400">(se completa automáticamente al importar Excel)</span>
                                </p>
                            </div>
                            <div class="grid gap-4 p-4 md:grid-cols-2">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Latitud</label>
                                    <input v-model="form.latitude" type="number" step="0.0000001" min="-90" max="90" class="app-input" placeholder="Ej: 22.90325" />
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Longitud</label>
                                    <input v-model="form.longitude" type="number" step="0.0000001" min="-180" max="180" class="app-input" placeholder="Ej: -83.16209" />
                                </div>
                            </div>
                        </div>

                        <!-- Modelos de caja -->
                        <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-700/70">
                            <p class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Modelos de caja por PV</p>
                            <div class="grid gap-3 md:grid-cols-5">
                                <div v-for="m in cashRegisterModels" :key="m.id">
                                    <label class="text-xs text-slate-500 dark:text-slate-400">{{ m.code }} - {{ m.name }}</label>
                                    <input type="number" min="0" class="app-input" :value="registerQuantity(m.id)" @input="setRegisterQuantity(m.id, $event.target.value)" />
                                </div>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="form.active" type="checkbox" class="h-4 w-4" /> Activo
                        </label>

                        <div class="flex justify-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800">
                            <button type="button" class="app-button-secondary" @click="showModal = false">Cancelar</button>
                            <button type="submit" class="app-button-primary" :disabled="form.processing">
                                {{ form.processing ? 'Guardando...' : 'Guardar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
