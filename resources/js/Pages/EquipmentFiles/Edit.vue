<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DynamicRow from '@/Components/DynamicRow.vue'
import PageHeader from '@/Components/PageHeader.vue'
import BaseCard from '@/Components/BaseCard.vue'
import ExpedienteResponsiblesEditor from '@/Components/ExpedienteResponsiblesEditor.vue'
import AidaImportPanel from '@/Components/AidaImportPanel.vue'
import axios from 'axios'

const props = defineProps({
    file: Object,
    entities: Array,
    componentTypes: Array,
    statuses: Array,
    rodas_lookup_enabled: { type: Boolean, default: false },
    ldap_responsible_search_enabled: { type: Boolean, default: false },
})

const activeTab = ref('caracteristicas')
const departments = ref([])

const caracteristicaTypes = computed(() => props.componentTypes.filter(type => type.category === 'caracteristica'))
const perifericoTypes = computed(() => props.componentTypes.filter(type => type.category === 'periferico'))
const deviceTypes = computed(() => props.componentTypes.filter(type => type.category === 'dispositivo'))

const buildCaracteristicasRows = () => {
    return (props.file.components || [])
        .filter(component => component.category === 'caracteristica')
        .map(component => ({
            component_type_slug: component.type || '',
            brand: component.brand || '',
            model: component.model || '',
            serial_number: component.serial_number || '',
            status: component.status || '',
        }))
}

const buildResponsibles = () => {
    const r = props.file.responsibles
    if (r && r.length) {
        return r.map((x) => ({
            display_name: x.display_name || '',
            samaccountname: x.samaccountname || '',
            mail: x.mail || '',
            source: x.source === 'ad' ? 'ad' : 'manual',
        }))
    }
    if (props.file.responsible) {
        return [{ display_name: props.file.responsible, samaccountname: '', mail: '', source: 'manual' }]
    }
    return [{ display_name: '', samaccountname: '', mail: '', source: 'manual' }]
}

const buildDynamicRows = (category) => {
    return (props.file.components || [])
        .filter(component => component.category === category)
        .filter(component => component.brand || component.model || component.serial_number || component.inventory_number)
        .map(component => ({
            component_type_slug: component.type || '',
            brand: component.brand || '',
            model: component.model || '',
            inventory_number: component.inventory_number || '',
            serial_number: component.serial_number || '',
            status: component.status || '',
        }))
}

const form = useForm({
    entity_id: props.file.entity_id,
    department_id: props.file.department_id,
    type: props.file.type,
    inventory_number: props.file.inventory_number,
    chassis: props.file.chassis || '',
    ip_address: props.file.ip_address || '',
    station_name: props.file.station_name || '',
    operating_system: props.file.operating_system || '',
    status: props.file.status,
    repairable: props.file.repairable,
    responsibles: buildResponsibles(),
    seal_code: props.file.seal_code || '',
    caracteristicas: buildCaracteristicasRows(),
    perifericos: buildDynamicRows('periferico'),
    dispositivos: buildDynamicRows('dispositivo'),
})

onMounted(async () => {
    if (!form.entity_id) return
    const response = await axios.get(`/departamentos/por-entidad/${form.entity_id}`)
    departments.value = response.data
})

watch(() => form.entity_id, async (id) => {
    form.department_id = ''
    if (!id) {
        departments.value = []
        return
    }

    const response = await axios.get(`/departamentos/por-entidad/${id}`)
    departments.value = response.data
})

const emptyRow = () => ({
    component_type_slug: '',
    brand: '',
    model: '',
    inventory_number: '',
    serial_number: '',
    status: '',
})

const addCaracteristica = () => form.caracteristicas.push(emptyRow())
const removeCaracteristica = (index) => form.caracteristicas.splice(index, 1)
const addPeriferico = () => form.perifericos.push(emptyRow())
const removePeriferico = (index) => form.perifericos.splice(index, 1)

// Aplicar datos del panel AIDA al formulario (en edición, sobrescribe siempre)
const applyAida = (payload) => {
    if (payload.equipment?.type)             form.type             = payload.equipment.type
    if (payload.equipment?.chassis)          form.chassis          = payload.equipment.chassis
    if (payload.equipment?.ip_address)       form.ip_address       = payload.equipment.ip_address
    if (payload.equipment?.station_name)     form.station_name     = payload.equipment.station_name
    if (payload.equipment?.operating_system) form.operating_system = payload.equipment.operating_system

    const desdeAida = []
    for (const [type, fields] of Object.entries(payload.components || {})) {
        const row = {
            component_type_slug: type,
            brand: '',
            model: '',
            serial_number: '',
            status: '',
        }
        for (const [field, value] of Object.entries(fields || {})) {
            if (value === null || value === undefined || value === '') continue
            if (field === 'brand') row.brand = value
            else if (field === 'model') row.model = value
            else if (field === 'serial_number') row.serial_number = value
            else if (field === 'status') row.status = value
        }
        if (row.brand || row.model || row.serial_number) {
            desdeAida.push(row)
        }
    }
    if (desdeAida.length) {
        form.caracteristicas = [...form.caracteristicas, ...desdeAida]
    }

    if (payload.perifericos?.length) {
        form.perifericos = payload.perifericos.map(p => ({
            component_type_slug: p.component_type_slug || '',
            brand:               p.brand || '',
            model:               p.model || '',
            inventory_number:    p.inventory_number || '',
            serial_number:       p.serial_number || '',
            status:              p.status || '',
        }))
    }
}
const addDevice = () => form.dispositivos.push(emptyRow())
const removeDevice = (index) => form.dispositivos.splice(index, 1)
const submit = () => form.put(`/expedientes/${props.file.id}`)

const errorEntries = computed(() => Object.entries(form.errors || {}))
const tabErrorCount = (tab) => errorEntries.value.filter(([key]) => key.startsWith(`${tab}.`)).length
const responsiblesErrorCount = computed(() =>
    errorEntries.value.filter(([key]) => key.startsWith('responsibles.')).length,
)

const tabs = [
    { key: 'caracteristicas', label: 'Caracteristicas' },
    { key: 'perifericos', label: 'Perifericos' },
    { key: 'dispositivos', label: 'Otros dispositivos' },
    { key: 'responsables', label: 'Responsables' },
]
const rowErrors = (tab, index) => errorEntries.value
    .filter(([key]) => key.startsWith(`${tab}.${index}.`))
    .map(([, message]) => message)

</script>

<template>
    <AppLayout>
        <PageHeader
            eyebrow="Expedientes"
            :title="`Editar expediente ${file.file_number}`"
            description="Actualiza la informacion del activo con mejor contraste visual para formularios y componentes en dark mode."
        >
            <template #actions>
                <Link href="/expedientes" class="app-button-secondary">&larr; Volver</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Importar desde AIDA64 -->
            <AidaImportPanel @apply="applyAida" />

            <BaseCard title="Datos generales" subtitle="Contexto institucional y estado operativo del expediente.">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Entidad *</label>
                        <select v-model="form.entity_id" class="app-select">
                            <option value="">Seleccionar...</option>
                            <option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Departamento *</label>
                        <select v-model="form.department_id" class="app-select">
                            <option value="">Seleccionar...</option>
                            <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Tipo *</label>
                        <select v-model="form.type" class="app-select">
                            <option value="PC">PC</option>
                            <option value="Laptop">Laptop</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">No. Inventario *</label>
                        <input v-model="form.inventory_number" type="text" class="app-input" />
                        <p v-if="form.errors.inventory_number" class="mt-1 text-xs text-red-500">{{ form.errors.inventory_number }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Chasis</label>
                        <input v-model="form.chassis" type="text" class="app-input" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Dirección IP</label>
                        <input v-model="form.ip_address" type="text" class="app-input" placeholder="192.168.1.x" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Nombre de estación</label>
                        <input v-model="form.station_name" type="text" class="app-input" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Sistema operativo</label>
                        <input v-model="form.operating_system" type="text" class="app-input" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Estado *</label>
                        <select v-model="form.status" class="app-select">
                            <option value="">Seleccionar...</option>
                            <option v-for="status in statuses" :key="status.id" :value="status.name">{{ status.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Reparable *</label>
                        <select v-model="form.repairable" class="app-select">
                            <option value="Si">Si</option>
                            <option value="No">No</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Sello actual</label>
                        <input v-model="form.seal_code" type="text" class="app-input" />
                    </div>
                </div>
            </BaseCard>

            <BaseCard :padded="false">
                <div class="border-b border-slate-200/80 px-2 dark:border-slate-700/70">
                    <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                        <li v-for="tab in tabs" :key="tab.key" class="mr-2">
                            <button
                                type="button"
                                @click="activeTab = tab.key"
                                :class="[
                                    'inline-block rounded-t-2xl border-b-2 px-4 py-4 transition',
                                    activeTab === tab.key
                                        ? 'border-brand-500 text-brand-600 dark:text-brand-300'
                                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-slate-200',
                                ]"
                            >
                                {{ tab.label }}
                                <span
                                    v-if="tab.key === 'caracteristicas' && form.caracteristicas.length"
                                    class="ml-1 rounded-full bg-brand-50 px-1.5 py-0.5 text-xs text-brand-700 dark:bg-brand-500/10 dark:text-brand-300"
                                >
                                    {{ form.caracteristicas.length }}
                                </span>
                                <span
                                    v-if="tab.key === 'caracteristicas' && tabErrorCount('caracteristicas')"
                                    class="ml-1 rounded-full bg-red-50 px-1.5 py-0.5 text-xs text-red-700 dark:bg-red-500/10 dark:text-red-300"
                                >
                                    {{ tabErrorCount('caracteristicas') }}
                                </span>
                                <span
                                    v-if="tab.key === 'perifericos' && form.perifericos.length"
                                    class="ml-1 rounded-full bg-brand-50 px-1.5 py-0.5 text-xs text-brand-700 dark:bg-brand-500/10 dark:text-brand-300"
                                >
                                    {{ form.perifericos.length }}
                                </span>
                                <span
                                    v-if="tab.key === 'perifericos' && tabErrorCount('perifericos')"
                                    class="ml-1 rounded-full bg-red-50 px-1.5 py-0.5 text-xs text-red-700 dark:bg-red-500/10 dark:text-red-300"
                                >
                                    {{ tabErrorCount('perifericos') }}
                                </span>
                                <span
                                    v-if="tab.key === 'dispositivos' && form.dispositivos.length"
                                    class="ml-1 rounded-full bg-brand-50 px-1.5 py-0.5 text-xs text-brand-700 dark:bg-brand-500/10 dark:text-brand-300"
                                >
                                    {{ form.dispositivos.length }}
                                </span>
                                <span
                                    v-if="tab.key === 'dispositivos' && tabErrorCount('dispositivos')"
                                    class="ml-1 rounded-full bg-red-50 px-1.5 py-0.5 text-xs text-red-700 dark:bg-red-500/10 dark:text-red-300"
                                >
                                    {{ tabErrorCount('dispositivos') }}
                                </span>
                                <span
                                    v-if="tab.key === 'responsables' && form.responsibles.length"
                                    class="ml-1 rounded-full bg-brand-50 px-1.5 py-0.5 text-xs text-brand-700 dark:bg-brand-500/10 dark:text-brand-300"
                                >
                                    {{ form.responsibles.length }}
                                </span>
                                <span
                                    v-if="tab.key === 'responsables' && responsiblesErrorCount"
                                    class="ml-1 rounded-full bg-red-50 px-1.5 py-0.5 text-xs text-red-700 dark:bg-red-500/10 dark:text-red-300"
                                >
                                    {{ responsiblesErrorCount }}
                                </span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="p-6">
                    <div v-show="activeTab === 'caracteristicas'" class="space-y-4">
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Agrega o quita filas para reflejar el hardware interno (varias RAM, varios HDD, sin lector, etc.).
                        </p>
                        <div v-if="!form.caracteristicas.length" class="py-8 text-center text-slate-400 dark:text-slate-500">
                            <p class="text-sm">No hay componentes internos registrados.</p>
                            <p class="mt-1 text-xs">Haz clic en &quot;Agregar componente&quot; para comenzar.</p>
                        </div>

                        <DynamicRow
                            v-for="(row, index) in form.caracteristicas"
                            :key="index"
                            v-model="form.caracteristicas[index]"
                            :types="caracteristicaTypes"
                            :statuses="statuses"
                            :show-inventory="false"
                            :errors="rowErrors('caracteristicas', index)"
                            @remove="removeCaracteristica(index)"
                        />

                        <button type="button" class="app-button-secondary" @click="addCaracteristica">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Agregar componente
                        </button>
                    </div>

                    <!-- Periféricos -->
                    <div v-show="activeTab === 'perifericos'" class="space-y-4">
                        <div v-if="!form.perifericos.length" class="py-8 text-center text-slate-400 dark:text-slate-500">
                            <p class="text-sm">No hay perifericos agregados.</p>
                            <p class="mt-1 text-xs">Haz clic en "Agregar periferico" para comenzar.</p>
                        </div>

                        <DynamicRow
                            v-for="(per, index) in form.perifericos"
                            :key="index"
                            v-model="form.perifericos[index]"
                            :types="perifericoTypes"
                            :statuses="statuses"
                            :rodas-lookup-enabled="rodas_lookup_enabled"
                            :context-entity-id="form.entity_id"
                            :context-department-id="form.department_id"
                            :equipment-file-id="file.id"
                            medio-category="periferico"
                            :errors="rowErrors('perifericos', index)"
                            @remove="removePeriferico(index)"
                        />

                        <button type="button" class="app-button-secondary" @click="addPeriferico">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Agregar periferico
                        </button>
                    </div>

                    <!-- Dispositivos -->
                    <div v-show="activeTab === 'dispositivos'" class="space-y-4">
                        <div v-if="!form.dispositivos.length" class="py-8 text-center text-slate-400 dark:text-slate-500">
                            <p class="text-sm">No hay dispositivos agregados.</p>
                            <p class="mt-1 text-xs">Haz clic en "Agregar dispositivo" para comenzar.</p>
                        </div>

                        <DynamicRow
                            v-for="(device, index) in form.dispositivos"
                            :key="index"
                            v-model="form.dispositivos[index]"
                            :types="deviceTypes"
                            :statuses="statuses"
                            :rodas-lookup-enabled="rodas_lookup_enabled"
                            :context-entity-id="form.entity_id"
                            :context-department-id="form.department_id"
                            :equipment-file-id="file.id"
                            medio-category="dispositivo"
                            :errors="rowErrors('dispositivos', index)"
                            @remove="removeDevice(index)"
                        />

                        <button type="button" class="app-button-secondary" @click="addDevice">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Agregar dispositivo
                        </button>
                    </div>

                    <div v-show="activeTab === 'responsables'" class="space-y-4">
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Personas asociadas al equipo. Puedes buscar en Active Directory o indicar el nombre manualmente (se generará alerta de revisión si no viene del AD).
                        </p>
                        <ExpedienteResponsiblesEditor
                            v-model="form.responsibles"
                            :ldap-enabled="ldap_responsible_search_enabled"
                            :errors="form.errors"
                        />
                    </div>
                </div>
            </BaseCard>

            <div class="flex items-center justify-end gap-3">
                <Link href="/expedientes" class="app-button-secondary">Cancelar</Link>
                <button type="submit" :disabled="form.processing" class="app-button-primary disabled:opacity-50">
                    {{ form.processing ? 'Guardando...' : 'Actualizar expediente' }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
