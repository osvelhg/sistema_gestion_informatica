<script setup>
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import BaseCard from '@/Components/BaseCard.vue'
import PageHeader from '@/Components/PageHeader.vue'

const props = defineProps({ file: Object, historyModules: Array, movementEntities: Array, incidentTypes: Array })
const activeTab = ref('caracteristicas')
const sealForm = useForm({
    entity_id: props.file.entity_id || '',
    equipment_file_id: props.file.id,
    incident_type_id: '',
    inventory_number: props.file.inventory_number || '',
    removed_seal: '',
    applied_seal: '',
    reason: '',
    date: new Date().toISOString().slice(0, 10),
    time: new Date().toTimeString().slice(0, 5),
    performed_by: '',
})
const movementForm = useForm({
    to_entity_id: '',
    to_department_id: '',
})
const availableDepartments = computed(() => {
    const entity = (props.movementEntities || []).find(e => String(e.id) === String(movementForm.to_entity_id))
    return entity?.departments || []
})

const caracteristicas = computed(() =>
    (props.file.components || [])
        .filter(component => component.category === 'caracteristica')
        .map(component => ({
            ...component,
            label: component.custom_name || component.component_type?.name || component.type,
        })),
)

const perifericos = computed(() =>
    (props.file.components || [])
        .filter(component => component.category === 'periferico')
        .map(component => ({
            ...component,
            label: component.custom_name || component.component_type?.name || component.type,
        })),
)

const dispositivos = computed(() =>
    (props.file.components || [])
        .filter(component => component.category === 'dispositivo')
        .map(component => ({
            ...component,
            label: component.custom_name || component.component_type?.name || component.type,
        })),
)

const tabs = [
    { key: 'caracteristicas', label: 'Caracteristicas' },
    { key: 'perifericos', label: 'Perifericos' },
    { key: 'dispositivos', label: 'Otros dispositivos' },
    { key: 'sellos', label: 'Sellos' },
    { key: 'movimientos', label: 'Movimientos' },
    { key: 'anexos', label: 'Anexos historicos' },
]
const createSeal = () => sealForm.post('/sellos')
const createMovement = () => movementForm.post(`/expedientes/${props.file.id}/mover`)

const alertRodasTitle = (type) => {
    if (type === 'rodas_inventario_inexistente') return 'Inventario no localizado en RODAS'
    if (type === 'rodas_incongruencia') return 'Datos distintos al registro en RODAS'
    if (type === 'rodas_medio_inventario_inexistente') return 'Medio básico: inventario no verificado en RODAS'
    if (type === 'rodas_medio_incongruencia') return 'Medio básico: incongruencia con RODAS'
    if (type === 'responsible_sin_ad') return 'Responsable sin verificar en AD'
    return 'Alerta'
}
</script>

<template>
    <AppLayout>
        <PageHeader
            eyebrow="Expedientes"
            :title="file.file_number"
            description="Consulta informacion general, componentes y trazabilidad con mejor legibilidad para light y dark mode."
        >
            <template #actions>
                <Link href="/expedientes" class="app-button-secondary">&larr; Volver</Link>
                <a :href="`/reportes/expediente-pdf/${file.id}`" target="_blank" class="app-button-secondary">PDF</a>
                <Link :href="`/expedientes/${file.id}/edit`" class="app-button-primary">Editar</Link>
            </template>
        </PageHeader>

        <BaseCard
            v-if="file.expediente_alertas?.length"
            title="Alertas de inventario / RODAS"
            subtitle="Incluye el equipo principal y, si aplica, periféricos y otros dispositivos con número de inventario. No bloquean el registro; sirven para control normativo."
            class="mb-4 border-amber-300/80 bg-amber-50/90 dark:border-amber-500/30 dark:bg-amber-950/25"
        >
            <ul class="space-y-3 text-sm">
                <li
                    v-for="a in file.expediente_alertas"
                    :key="a.id"
                    class="rounded-xl border border-amber-200/90 bg-white/70 px-4 py-3 dark:border-amber-500/25 dark:bg-slate-900/50"
                >
                    <p class="font-semibold text-amber-950 dark:text-amber-100">{{ alertRodasTitle(a.type) }}</p>
                    <p class="mt-1 text-amber-950/90 dark:text-amber-50/90">{{ a.message }}</p>
                </li>
            </ul>
        </BaseCard>

        <BaseCard title="Datos generales" class="mb-4">
            <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                <div><span class="text-slate-500 dark:text-slate-400">Entidad:</span><p class="font-medium text-slate-900 dark:text-slate-100">{{ file.entity?.name }}</p></div>
                <div><span class="text-slate-500 dark:text-slate-400">Departamento:</span><p class="font-medium text-slate-900 dark:text-slate-100">{{ file.department?.name }}</p></div>
                <div><span class="text-slate-500 dark:text-slate-400">Tipo:</span><p class="font-medium text-slate-900 dark:text-slate-100">{{ file.type }}</p></div>
                <div><span class="text-slate-500 dark:text-slate-400">Inventario:</span><p class="font-mono font-medium text-slate-900 dark:text-slate-100">{{ file.inventory_number }}</p></div>
                <div><span class="text-slate-500 dark:text-slate-400">Chasis:</span><p class="font-medium text-slate-900 dark:text-slate-100">{{ file.chassis || '-' }}</p></div>
                <div><span class="text-slate-500 dark:text-slate-400">Estado:</span><p><StatusBadge :status="file.status" /></p></div>
                <div><span class="text-slate-500 dark:text-slate-400">Reparable:</span><p class="font-medium text-slate-900 dark:text-slate-100">{{ file.repairable }}</p></div>
                <div class="md:col-span-2">
                    <span class="text-slate-500 dark:text-slate-400">Responsables:</span>
                    <ul v-if="file.responsibles?.length" class="mt-1 space-y-1 font-medium text-slate-900 dark:text-slate-100">
                        <li v-for="r in file.responsibles" :key="r.id">
                            {{ r.display_name }}
                            <span v-if="r.source === 'ad' && r.samaccountname" class="text-xs font-normal text-slate-500">({{ r.samaccountname }})</span>
                        </li>
                    </ul>
                    <p v-else class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ file.responsible || '—' }}</p>
                </div>
                <div><span class="text-slate-500 dark:text-slate-400">Sello:</span><p class="font-medium text-slate-900 dark:text-slate-100">{{ file.seal_code || '-' }}</p></div>
                <div><span class="text-slate-500 dark:text-slate-400">Creado por:</span><p class="font-medium text-slate-900 dark:text-slate-100">{{ file.creator?.name }}</p></div>
            </div>
        </BaseCard>

        <BaseCard :padded="false">
            <div class="border-b border-slate-200/80 dark:border-slate-700/70">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                    <li v-for="tab in tabs" :key="tab.key" class="mr-2">
                        <button
                            @click="activeTab = tab.key"
                            :class="[
                                'inline-block rounded-t-2xl border-b-2 px-4 py-4 transition',
                                activeTab === tab.key
                                    ? 'border-brand-500 text-brand-600 dark:text-brand-300'
                                    : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200',
                            ]"
                        >
                            {{ tab.label }}
                        </button>
                    </li>
                </ul>
            </div>

            <div class="p-6">
                <div v-show="activeTab === 'caracteristicas'">
                    <table v-if="caracteristicas.length" class="w-full text-sm">
                        <thead class="bg-slate-50/80 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Componente</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Marca</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Modelo</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Serie</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in caracteristicas" :key="item.id" class="border-b border-slate-200/70 dark:border-slate-700/70">
                                <td class="px-3 py-2 font-medium text-slate-900 dark:text-slate-100">{{ item.label }}</td>
                                <td class="px-3 py-2">{{ item.brand || '-' }}</td>
                                <td class="px-3 py-2">{{ item.model || '-' }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ item.serial_number || '-' }}</td>
                                <td class="px-3 py-2"><StatusBadge v-if="item.status" :status="item.status" /></td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="py-8 text-center text-slate-400 dark:text-slate-500">Sin componentes internos registrados.</p>
                </div>

                <div v-show="activeTab === 'perifericos'">
                    <table v-if="perifericos.length" class="w-full text-sm">
                        <thead class="bg-slate-50/80 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Componente</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Marca</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Modelo</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Inventario</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Serie</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in perifericos" :key="item.id" class="border-b border-slate-200/70 dark:border-slate-700/70">
                                <td class="px-3 py-2 font-medium text-slate-900 dark:text-slate-100">{{ item.label }}</td>
                                <td class="px-3 py-2">{{ item.brand || '-' }}</td>
                                <td class="px-3 py-2">{{ item.model || '-' }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ item.inventory_number || '-' }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ item.serial_number || '-' }}</td>
                                <td class="px-3 py-2"><StatusBadge v-if="item.status" :status="item.status" /></td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="py-4 text-center text-slate-400 dark:text-slate-500">Sin perifericos registrados.</p>
                </div>

                <div v-show="activeTab === 'dispositivos'">
                    <table v-if="dispositivos.length" class="w-full text-sm">
                        <thead class="bg-slate-50/80 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Componente</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Marca</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Modelo</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Inventario</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Serie</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in dispositivos" :key="item.id" class="border-b border-slate-200/70 dark:border-slate-700/70">
                                <td class="px-3 py-2 font-medium text-slate-900 dark:text-slate-100">{{ item.label }}</td>
                                <td class="px-3 py-2">{{ item.brand || '-' }}</td>
                                <td class="px-3 py-2">{{ item.model || '-' }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ item.inventory_number || '-' }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ item.serial_number || '-' }}</td>
                                <td class="px-3 py-2"><StatusBadge v-if="item.status" :status="item.status" /></td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="py-4 text-center text-slate-400 dark:text-slate-500">Sin otros dispositivos registrados.</p>
                </div>

                <div v-show="activeTab === 'sellos'">
                    <div class="mb-4 rounded-2xl border border-slate-200/80 p-4 dark:border-slate-700/70">
                        <h4 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Agregar sello desde esta vista</h4>
                        <form class="grid gap-3 md:grid-cols-3" @submit.prevent="createSeal">
                            <select v-model="sealForm.incident_type_id" class="app-select"><option value="">Tipo incidencia</option><option v-for="type in incidentTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select>
                            <input v-model="sealForm.removed_seal" type="text" class="app-input" placeholder="Sello retirado" />
                            <input v-model="sealForm.applied_seal" type="text" class="app-input" placeholder="Sello aplicado" />
                            <input v-model="sealForm.date" type="date" class="app-input" />
                            <input v-model="sealForm.time" type="time" class="app-input" />
                            <input v-model="sealForm.performed_by" type="text" class="app-input" placeholder="Realizado por" />
                            <textarea v-model="sealForm.reason" rows="2" class="app-input md:col-span-3" placeholder="Motivo"></textarea>
                            <div class="md:col-span-3 flex justify-end"><button type="submit" class="app-button-primary" :disabled="sealForm.processing">Guardar sello</button></div>
                        </form>
                    </div>
                    <table v-if="file.seals?.length" class="w-full text-sm">
                        <thead class="bg-slate-50/80 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Incidencia</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Sello retirado</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Sello aplicado</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Motivo</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Fecha y hora</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Realizado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="seal in file.seals" :key="seal.id" class="border-b border-slate-200/70 dark:border-slate-700/70">
                                <td class="px-3 py-2">
                                    <StatusBadge :status="seal.incident_type?.name || 'Sin clasificar'" :color="seal.incident_type ? 'blue' : 'gray'" />
                                </td>
                                <td class="px-3 py-2 font-mono">{{ seal.removed_seal || '-' }}</td>
                                <td class="px-3 py-2 font-mono">{{ seal.applied_seal || seal.code || '-' }}</td>
                                <td class="px-3 py-2">{{ seal.reason }}</td>
                                <td class="px-3 py-2">{{ seal.date }} {{ seal.time?.slice(0, 5) }}</td>
                                <td class="px-3 py-2">{{ seal.performed_by || 'Sistema' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="py-4 text-center text-slate-400 dark:text-slate-500">Sin sellos registrados.</p>
                </div>

                <div v-show="activeTab === 'movimientos'">
                    <div class="mb-4 rounded-2xl border border-slate-200/80 p-4 dark:border-slate-700/70">
                        <h4 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Registrar movimiento desde esta vista</h4>
                        <form class="grid gap-3 md:grid-cols-2" @submit.prevent="createMovement">
                            <select v-model="movementForm.to_entity_id" class="app-select">
                                <option value="">Entidad destino</option>
                                <option v-for="entity in movementEntities" :key="entity.id" :value="entity.id">{{ entity.name }}</option>
                            </select>
                            <select v-model="movementForm.to_department_id" class="app-select">
                                <option value="">Departamento destino</option>
                                <option v-for="department in availableDepartments" :key="department.id" :value="department.id">{{ department.name }}</option>
                            </select>
                            <div class="md:col-span-2 flex justify-end"><button type="submit" class="app-button-primary" :disabled="movementForm.processing">Registrar movimiento</button></div>
                        </form>
                    </div>
                    <table v-if="file.movements?.length" class="w-full text-sm">
                        <thead class="bg-slate-50/80 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Desde</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Hacia</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Por</th>
                                <th class="px-3 py-2 text-left text-slate-600 dark:text-slate-300">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="movement in file.movements" :key="movement.id" class="border-b border-slate-200/70 dark:border-slate-700/70">
                                <td class="px-3 py-2">{{ movement.from_entity?.name }}</td>
                                <td class="px-3 py-2">{{ movement.to_entity?.name }}</td>
                                <td class="px-3 py-2">{{ movement.moved_by_user?.name }}</td>
                                <td class="px-3 py-2">{{ movement.moved_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="py-4 text-center text-slate-400 dark:text-slate-500">Sin movimientos registrados.</p>
                </div>

                <div v-show="activeTab === 'anexos'" class="space-y-6">
                    <div class="rounded-2xl border border-brand-200/70 bg-brand-50/70 p-4 text-sm text-brand-900 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-100">
                        Estos anexos ahora funcionan como historial vivo del equipo. Aqui se acumula la informacion operativa y luego podra exportarse con el formato institucional correspondiente.
                    </div>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <div
                            v-for="module in historyModules"
                            :key="module.key"
                            class="surface-card-muted rounded-2xl p-5"
                        >
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600 dark:text-brand-300">Anexo del expediente</p>
                                        <h3 class="mt-2 text-base font-semibold text-slate-900 dark:text-slate-100">{{ module.title }}</h3>
                                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ module.description }}</p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 text-center shadow-sm dark:border-slate-700/70 dark:bg-slate-900/70">
                                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Registros</p>
                                        <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ module.count }}</p>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <div
                                        v-for="item in module.latest"
                                        :key="item.id"
                                        class="rounded-2xl border border-slate-200/70 bg-white/80 p-4 dark:border-slate-700/70 dark:bg-slate-900/60"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ item.title || 'Sin titulo' }}</p>
                                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ item.subtitle }}</p>
                                            </div>
                                        </div>
                                        <p class="mt-3 line-clamp-3 text-sm text-slate-600 dark:text-slate-300">{{ item.detail }}</p>
                                    </div>

                                    <div
                                        v-if="!module.latest?.length"
                                        class="rounded-2xl border border-dashed border-slate-300/80 p-5 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
                                    >
                                        Aun no existen registros acumulados en este anexo.
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <Link :href="module.manage_url" class="app-button-primary">
                                        Gestionar registros
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </BaseCard>
    </AppLayout>
</template>
