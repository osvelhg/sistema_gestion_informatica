<script setup>
import { ref, watch, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DynamicRow from '@/Components/DynamicRow.vue'
import PageHeader from '@/Components/PageHeader.vue'
import BaseCard from '@/Components/BaseCard.vue'
import ExpedienteResponsiblesEditor from '@/Components/ExpedienteResponsiblesEditor.vue'
import AidaImportPanel from '@/Components/AidaImportPanel.vue'
import { notifyError, notifySuccess, notifyWarning } from '@/Composables/useNotifications'
import axios from 'axios'

const props = defineProps({
    entities: Array,
    componentTypes: Array,
    statuses: Array,
    rodas_lookup_enabled: { type: Boolean, default: false },
    ldap_responsible_search_enabled: { type: Boolean, default: false },
})

const activeTab = ref('caracteristicas')
const departments = ref([])
const rodasLookupLoading = ref(false)
const rodasLookupBanner = ref(null)

const caracteristicaTypes = computed(() => props.componentTypes.filter(type => type.category === 'caracteristica'))
const perifericoTypes = computed(() => props.componentTypes.filter(type => type.category === 'periferico'))
const deviceTypes = computed(() => props.componentTypes.filter(type => type.category === 'dispositivo'))
const errorEntries = computed(() => Object.entries(form.errors || {}))

const tabErrorCount = (tab) => errorEntries.value.filter(([key]) => key.startsWith(`${tab}.`)).length
const responsiblesErrorCount = computed(() =>
    errorEntries.value.filter(([key]) => key.startsWith('responsibles.')).length,
)
const rowErrors = (tab, index) => errorEntries.value
    .filter(([key]) => key.startsWith(`${tab}.${index}.`))
    .map(([, message]) => message)

const emptyRow = () => ({
    component_type_slug: '',
    brand: '',
    model: '',
    inventory_number: '',
    serial_number: '',
    status: '',
})

const form = useForm({
    entity_id: '',
    department_id: '',
    type: 'PC',
    inventory_number: '',
    chassis: '',
    ip_address: '',
    station_name: '',
    operating_system: '',
    status: '',
    repairable: 'Si',
    responsibles: [
        { display_name: '', samaccountname: '', mail: '', source: 'manual' },
    ],
    seal_code: '',
    caracteristicas: [],
    perifericos: [],
    dispositivos: [],
})

const loadDepartments = async (entityId, clearDepartment = true) => {
    if (!entityId) {
        departments.value = []
        if (clearDepartment) form.department_id = ''
        return
    }
    try {
        const response = await axios.get(`/departamentos/por-entidad/${entityId}`)
        departments.value = response.data
        if (clearDepartment) form.department_id = ''
    } catch {
        departments.value = []
        notifyError('Departamentos', 'No se pudieron cargar los departamentos de la entidad.')
    }
}

const onEntityChange = () => {
    loadDepartments(form.entity_id, true)
}

const consultarRodas = async () => {
    rodasLookupBanner.value = null
    const inv = (form.inventory_number || '').trim()
    if (!inv) {
        notifyWarning('Inventario', 'Escribe el número de inventario antes de consultar RODAS.')
        return
    }
    rodasLookupLoading.value = true
    try {
        const { data } = await axios.post('/expedientes/lookup-inventory', { inventory_number: inv })
        if (!data.success) {
            rodasLookupBanner.value = { type: 'warn', text: data.message || 'Consulta a RODAS no disponible.' }
            return
        }
        if (!data.found) {
            rodasLookupBanner.value = {
                type: 'warn',
                text: data.message || 'Inventario no encontrado en RODAS. Puedes indicar entidad y departamento manualmente; al crear el expediente se registrará una alerta.',
            }
            return
        }
        const deptRes = await axios.get(`/departamentos/por-entidad/${data.entity_id}`)
        departments.value = deptRes.data
        form.entity_id = String(data.entity_id)
        if (data.department_id) {
            form.department_id = String(data.department_id)
            rodasLookupBanner.value = {
                type: 'ok',
                text: `Datos desde RODAS: ${data.entity_name || ''}${data.department_name ? ' — ' + data.department_name : ''}. Si cambias entidad, departamento o inventario, se comprobará al guardar.`,
            }
            notifySuccess('RODAS', 'Entidad y departamento sugeridos según el inventario.')
        } else {
            form.department_id = ''
            rodasLookupBanner.value = {
                type: 'warn',
                text: 'El activo existe en RODAS pero no hay un departamento local para el código de área. Elige el departamento manualmente; puede generarse una alerta al guardar.',
            }
        }
    } catch (e) {
        const msg = e.response?.data?.message || e.message
        notifyError('RODAS', msg || 'Error al consultar el inventario.')
    } finally {
        rodasLookupLoading.value = false
    }
}

const addCaracteristica = () => form.caracteristicas.push(emptyRow())
const removeCaracteristica = (index) => form.caracteristicas.splice(index, 1)
const addPeriferico = () => form.perifericos.push(emptyRow())
const removePeriferico = (index) => form.perifericos.splice(index, 1)
const addDevice = () => form.dispositivos.push(emptyRow())
const removeDevice = (index) => form.dispositivos.splice(index, 1)

// Aplicar datos del panel AIDA al formulario
const applyAida = (payload) => {
    // Expediente
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

    // Periféricos: reemplazar lista actual con los seleccionados del informe
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
const submit = () => form.post('/expedientes', {
    onError: (errors) => {
        const firstMessage = Object.values(errors || {})[0]
        notifyError('No se pudo crear el expediente', firstMessage || 'Revisa los campos marcados e intenta nuevamente.')
    },
})

const tabs = [
    { key: 'caracteristicas', label: 'Caracteristicas' },
    { key: 'perifericos', label: 'Perifericos' },
    { key: 'dispositivos', label: 'Otros dispositivos' },
    { key: 'responsables', label: 'Responsables' },
]

</script>

<template>
    <AppLayout>
        <PageHeader
            eyebrow="Expedientes"
            title="Nuevo expediente tecnico"
            description="Registra el activo principal y agrega solo los perifericos o dispositivos que existan realmente, con una experiencia visual consistente en light y dark mode."
        >
            <template #actions>
                <Link href="/expedientes" class="app-button-secondary">&larr; Volver</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="space-y-6">
            <BaseCard
                title="Inventario"
                :subtitle="rodas_lookup_enabled
                    ? 'Indica el número de inventario y consulta RODAS para rellenar entidad y departamento según el área de responsabilidad.'
                    : 'Indica el número de inventario del activo. Activa la BD de entidades en Configuración para consultar RODAS automáticamente.'"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">No. inventario *</label>
                        <input v-model="form.inventory_number" type="text" class="app-input" autocomplete="off" />
                        <p v-if="form.errors.inventory_number" class="mt-1 text-xs text-red-500">{{ form.errors.inventory_number }}</p>
                    </div>
                    <button
                        v-if="rodas_lookup_enabled"
                        type="button"
                        class="app-button-secondary shrink-0"
                        :disabled="rodasLookupLoading"
                        @click="consultarRodas"
                    >
                        {{ rodasLookupLoading ? 'Consultando...' : 'Consultar en RODAS' }}
                    </button>
                </div>
                <div
                    v-if="rodasLookupBanner"
                    class="mt-4 rounded-xl border px-4 py-3 text-sm"
                    :class="rodasLookupBanner.type === 'ok'
                        ? 'border-emerald-200 bg-emerald-50/90 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-900/20 dark:text-emerald-100'
                        : 'border-amber-200 bg-amber-50/90 text-amber-950 dark:border-amber-500/30 dark:bg-amber-900/20 dark:text-amber-100'"
                >
                    {{ rodasLookupBanner.text }}
                </div>
            </BaseCard>

            <!-- Importar desde AIDA64 -->
            <AidaImportPanel @apply="applyAida" />

            <BaseCard
                v-if="errorEntries.length"
                title="Hay errores en el formulario"
                subtitle="Corrige los campos indicados antes de volver a intentar crear el expediente."
                class="border-red-200/80 bg-red-50/80 dark:border-red-500/20 dark:bg-red-500/10"
            >
                <ul class="space-y-2 text-sm text-red-700 dark:text-red-200">
                    <li v-for="([field, message], index) in errorEntries" :key="`${field}-${index}`">
                        {{ message }}
                    </li>
                </ul>
            </BaseCard>

            <BaseCard title="Datos generales" subtitle="Informacion principal del expediente y su contexto institucional.">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Entidad *</label>
                        <select v-model="form.entity_id" class="app-select" @change="onEntityChange">
                            <option value="">Seleccionar...</option>
                            <option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option>
                        </select>
                        <p v-if="form.errors.entity_id" class="mt-1 text-xs text-red-500">{{ form.errors.entity_id }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Departamento *</label>
                        <select v-model="form.department_id" class="app-select">
                            <option value="">Seleccionar...</option>
                            <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                        </select>
                        <p v-if="form.errors.department_id" class="mt-1 text-xs text-red-500">{{ form.errors.department_id }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Tipo *</label>
                        <select v-model="form.type" class="app-select">
                            <option value="PC">PC</option>
                            <option value="Laptop">Laptop</option>
                        </select>
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
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Sello aplicado inicial</label>
                        <input v-model="form.seal_code" type="text" class="app-input" />
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Al crear el expediente se generara un control de sello con motivo "Creacion de expediente en el nuevo sistema".</p>
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
                            Agrega tantas filas como piezas internas tengas (varias memorias RAM, varios discos, etc.). Puedes omitir tipos que no existan en el equipo.
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
                    {{ form.processing ? 'Guardando...' : 'Crear expediente' }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
