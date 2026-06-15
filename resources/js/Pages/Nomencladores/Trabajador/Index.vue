<script setup>
import { computed, ref, reactive } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import BaseCard from '@/Components/BaseCard.vue'

const props = defineProps({
    trabajadores: Array,
    municipios: Array,
    ldapEnabled: Boolean,
    externalCiEnabled: { type: Boolean, default: false },
})

const page = usePage()
const isAdmin = computed(() => (page.props.auth?.user?.roles || []).includes('Administrador'))

const municipioOptions = computed(() =>
    (props.municipios || []).map(m => ({ id: m.id, label: m.name }))
)

// ─── Drawer crear ───────────────────────────────────────────────────
const showCreate = ref(false)
const createMode = ref('manual') // 'manual' | 'ad'
const createForm = useForm({
    nombre: '',
    ci: '',
    telefono: '',
    direccion: '',
    municipio_id: '',
    estado: true,
    origen: 'manual',
    samaccountname: '',
    cargo: '',
    email: '',
})
const createMunicipio = ref(null)
const ciLookupLoading = ref(false)
const ciLookupMessage = ref(null)

// ─── AD search en crear ──────────────────────────────────────────────
const adQuery = ref('')
const adResults = ref([])
const adLoading = ref(false)
const adOpen = ref(false)

function onCreateMunicipioChange(val) {
    createForm.municipio_id = val ? val.id : ''
}

function submitCreate() {
    if (createMode.value === 'manual') {
        createForm.ci = (createForm.ci || '').replace(/\D/g, '').slice(0, 11)
        createForm.origen = 'manual'
    }
    createForm.post(route('trabajadores.store'), {
        onSuccess: () => closeCreateDrawer(),
    })
}

function openCreateDrawer(mode = 'manual') {
    createMode.value = mode
    showCreate.value = true
    if (mode === 'ad') {
        createForm.origen = 'active_directory'
    } else {
        createForm.origen = 'manual'
    }
}

function closeCreateDrawer() {
    showCreate.value = false
    createForm.reset()
    createMunicipio.value = null
    ciLookupMessage.value = null
    createMode.value = 'manual'
    adQuery.value = ''
    adResults.value = []
    adOpen.value = false
}

async function buscarCiExternoCreate() {
    const ci = (createForm.ci || '').replace(/\D/g, '').slice(0, 11)
    createForm.ci = ci
    ciLookupMessage.value = null

    if (ci.length !== 11) {
        ciLookupMessage.value = { success: false, message: 'Ingrese un CI válido de 11 dígitos.' }
        return
    }

    ciLookupLoading.value = true
    try {
        const { data } = await axios.get(route('trabajadores.buscarPorCi', ci))
        if (data.success && data.trabajador) {
            createForm.nombre = data.trabajador.nombre || createForm.nombre
            createForm.telefono = data.trabajador.telefono || createForm.telefono
            createForm.direccion = data.trabajador.direccion || createForm.direccion
            createForm.origen = 'ci_externo'
        }
        ciLookupMessage.value = data
    } catch (e) {
        ciLookupMessage.value = { success: false, message: e.response?.data?.message || 'Error consultando CI.' }
    } finally {
        ciLookupLoading.value = false
    }
}

let adTimer = null
function scheduleAdSearch() {
    clearTimeout(adTimer)
    adTimer = setTimeout(runAdSearch, 400)
}

async function runAdSearch() {
    const q = (adQuery.value || '').trim()
    if (q.length < 2) {
        adResults.value = []
        adOpen.value = false
        return
    }
    adLoading.value = true
    try {
        const { data } = await axios.post(route('trabajadores.buscarPorAd'), { query: q })
        adResults.value = data.users || []
        adOpen.value = adResults.value.length > 0
    } catch {
        adResults.value = []
        adOpen.value = false
    } finally {
        adLoading.value = false
    }
}

function pickAdUser(u) {
    createForm.nombre = u.displayname || u.samaccountname || ''
    createForm.samaccountname = u.samaccountname || ''
    createForm.cargo = u.title || ''
    createForm.email = u.mail || ''
    createForm.origen = 'active_directory'
    adQuery.value = ''
    adResults.value = []
    adOpen.value = false
}

async function crearDesdeAdDirecto(u) {
    try {
        const { data } = await axios.post(route('trabajadores.crearDesdeAd'), {
            nombre: u.displayname || u.samaccountname,
            samaccountname: u.samaccountname,
            cargo: u.title || null,
            email: u.mail || null,
        })
        if (data.success) {
            router.reload({ only: ['trabajadores'] })
            closeCreateDrawer()
        }
    } catch (e) {
        alert(e.response?.data?.message || 'Error creando trabajador desde AD.')
    }
}

// ─── Editar ──────────────────────────────────────────────────────────
const editId = ref(null)
const editForm = useForm({
    nombre: '',
    ci: '',
    telefono: '',
    direccion: '',
    municipio_id: '',
    estado: true,
    origen: 'manual',
    samaccountname: '',
    cargo: '',
    email: '',
})
const editMunicipio = ref(null)

function startEdit(t) {
    editId.value = t.id
    editForm.nombre = t.nombre
    editForm.ci = t.ci ?? ''
    editForm.telefono = t.telefono ?? ''
    editForm.direccion = t.direccion ?? ''
    editForm.municipio_id = t.municipio_id ?? ''
    editForm.estado = t.estado
    editForm.origen = t.origen ?? 'manual'
    editForm.samaccountname = t.samaccountname ?? ''
    editForm.cargo = t.cargo ?? ''
    editForm.email = t.email ?? ''
    editMunicipio.value = t.municipio_id
        ? municipioOptions.value.find(m => m.id === t.municipio_id) ?? null
        : null
}

function onEditMunicipioChange(val) {
    editForm.municipio_id = val ? val.id : ''
}

function cancelEdit() {
    editId.value = null
    editMunicipio.value = null
}

function submitEdit(id) {
    editForm.ci = (editForm.ci || '').replace(/\D/g, '').slice(0, 11)
    editForm.put(route('trabajadores.update', id), {
        onSuccess: () => {
            editId.value = null
            editMunicipio.value = null
        },
    })
}

function confirmDelete(id) {
    if (!confirm('¿Dar de baja este trabajador? Quedará inactivo y en papelera (soft delete).')) return
    router.delete(route('trabajadores.destroy', id), { preserveScroll: true })
}

function reactivarTrabajador(id) {
    if (!confirm('¿Reactivar este trabajador? Se restaurará del soft delete y quedará activo.')) return
    router.post(route('trabajadores.reactivar', id), {}, { preserveScroll: true })
}

function eliminarDefinitivamente(id) {
    if (
        !confirm(
            '¿ELIMINAR DEFINITIVAMENTE? Esta acción no se puede deshacer. El trabajador debe estar en la papelera (dado de baja). Se borrarán también sus asignaciones en fuentes QR (cascade).',
        )
    ) {
        return
    }
    router.delete(route('trabajadores.forceDestroy', id), { preserveScroll: true })
}

// ─── Vincular CI (AD → base externa) ─────────────────────────────────
const showLinkCi = ref(false)
const linkCiTrabajador = ref(null)
const linkCiInput = ref('')
const linkCiLoading = ref(false)
const linkCiError = ref(null)
const linkCiLookupLoading = ref(false)
const linkCiPreview = ref(null)
const linkCiLookupMessage = ref(null)

function openLinkCi(t) {
    linkCiTrabajador.value = t
    linkCiInput.value = t.ci ? String(t.ci).replace(/\D/g, '').slice(0, 11) : ''
    linkCiError.value = null
    linkCiPreview.value = null
    linkCiLookupMessage.value = null
    showLinkCi.value = true
}

function closeLinkCi() {
    showLinkCi.value = false
    linkCiTrabajador.value = null
    linkCiInput.value = ''
    linkCiError.value = null
    linkCiLookupLoading.value = false
    linkCiPreview.value = null
    linkCiLookupMessage.value = null
}

async function buscarLinkCiExterno() {
    const ci = (linkCiInput.value || '').replace(/\D/g, '').slice(0, 11)
    linkCiInput.value = ci
    linkCiPreview.value = null
    linkCiLookupMessage.value = null
    linkCiError.value = null
    if (ci.length !== 11) {
        linkCiLookupMessage.value = { success: false, message: 'Ingrese un CI de 11 dígitos.' }
        return
    }
    linkCiLookupLoading.value = true
    try {
        const { data } = await axios.get(route('trabajadores.buscarPorCi', ci))
        linkCiLookupMessage.value = data
        if (data.success && data.trabajador) {
            linkCiPreview.value = data.trabajador
        }
    } catch (e) {
        linkCiLookupMessage.value = { success: false, message: e.response?.data?.message || 'Error al consultar el CI.' }
    } finally {
        linkCiLookupLoading.value = false
    }
}

async function submitLinkCi() {
    const t = linkCiTrabajador.value
    if (!t) return
    const ci = (linkCiInput.value || '').replace(/\D/g, '').slice(0, 11)
    linkCiInput.value = ci
    linkCiError.value = null
    if (ci.length !== 11) {
        linkCiError.value = 'CI debe tener 11 dígitos.'
        return
    }
    linkCiLoading.value = true
    try {
        const { data } = await axios.post(route('trabajadores.vincularCi', t.id), { ci })
        if (data.success) {
            router.reload({ only: ['trabajadores'] })
            closeLinkCi()
        }
    } catch (e) {
        linkCiError.value = e.response?.data?.message || 'No se pudo vincular el CI.'
    } finally {
        linkCiLoading.value = false
    }
}

// ─── Vincular AD (manual / CI externo) ───────────────────────────────
const showLinkAd = ref(false)
const linkAdTrabajador = ref(null)
const linkAdQuery = ref('')
const linkAdResults = ref([])
const linkAdLoading = ref(false)
const linkAdOpen = ref(false)
const linkAdError = ref(null)
let linkAdTimer = null

function openLinkAd(t) {
    linkAdTrabajador.value = t
    linkAdQuery.value = ''
    linkAdResults.value = []
    linkAdOpen.value = false
    linkAdError.value = null
    showLinkAd.value = true
}

function closeLinkAd() {
    showLinkAd.value = false
    linkAdTrabajador.value = null
    linkAdQuery.value = ''
    linkAdResults.value = []
    linkAdOpen.value = false
    linkAdError.value = null
}

function scheduleLinkAdSearch() {
    clearTimeout(linkAdTimer)
    linkAdTimer = setTimeout(runLinkAdSearch, 400)
}

async function runLinkAdSearch() {
    const q = (linkAdQuery.value || '').trim()
    if (q.length < 2) {
        linkAdResults.value = []
        linkAdOpen.value = false
        return
    }
    linkAdLoading.value = true
    try {
        const { data } = await axios.post(route('trabajadores.buscarPorAd'), { query: q })
        linkAdResults.value = data.users || []
        linkAdOpen.value = linkAdResults.value.length > 0
    } catch {
        linkAdResults.value = []
        linkAdOpen.value = false
    } finally {
        linkAdLoading.value = false
    }
}

async function submitLinkAdFromUser(u) {
    const t = linkAdTrabajador.value
    if (!t) return
    linkAdError.value = null
    linkAdLoading.value = true
    try {
        const { data } = await axios.post(route('trabajadores.vincularAd', t.id), {
            samaccountname: u.samaccountname,
            cargo: u.title || null,
            email: u.mail || null,
        })
        if (data.success) {
            router.reload({ only: ['trabajadores'] })
            closeLinkAd()
        }
    } catch (e) {
        linkAdError.value = e.response?.data?.message || 'No se pudo vincular con AD.'
    } finally {
        linkAdLoading.value = false
        linkAdOpen.value = false
    }
}

/** Trabajador creado desde AD: completar CI, dirección y teléfono desde SQL Server CI. */
const canLinkCi = (t) =>
    props.externalCiEnabled && t && !t.deleted_at && t.origen === 'active_directory'

/** Manual o CI externo: completar usuario de red, cargo y correo desde LDAP. */
const canLinkAd = (t) =>
    props.ldapEnabled && t && !t.deleted_at && (t.origen === 'manual' || t.origen === 'ci_externo')

const origenBadge = (origen) => {
    const map = {
        manual: { label: 'Manual', cls: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' },
        ci_externo: { label: 'CI Externo', cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' },
        active_directory: { label: 'Active Directory', cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' },
    }
    return map[origen] || map.manual
}

const inputClass = 'w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent'
</script>

<template>
    <AppLayout title="Trabajadores">
        <PageHeader title="Trabajadores" subtitle="Gestión de trabajadores (manual, CI externo y Active Directory)">
            <template #actions>
                <div class="flex gap-2">
                    <button @click="openCreateDrawer('manual')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Nuevo Trabajador
                    </button>
                    <button v-if="ldapEnabled" @click="openCreateDrawer('ad')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Buscar en AD
                    </button>
                </div>
            </template>
        </PageHeader>

        <!-- Drawer Crear -->
        <Teleport to="body">
            <div v-if="showCreate" class="fixed inset-0 z-50 flex">
                <div class="absolute inset-0 bg-black/50" @click="closeCreateDrawer" />
                <div class="relative ml-auto h-full w-full max-w-2xl bg-white dark:bg-gray-800 shadow-2xl">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ createMode === 'ad' ? 'Nuevo Trabajador desde AD' : 'Nuevo Trabajador' }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ createMode === 'ad' ? 'Busca en Active Directory y agrega al módulo' : 'Registro manual con consulta por CI' }}
                            </p>
                        </div>
                        <button @click="closeCreateDrawer" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitCreate" class="h-[calc(100%-73px)] flex flex-col">
                        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">

                            <!-- Búsqueda AD -->
                            <div v-if="createMode === 'ad'" class="relative">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Buscar en Active Directory</label>
                                <input v-model="adQuery" type="text" :class="inputClass"
                                    placeholder="Escribe al menos 2 caracteres (usuario, nombre o correo)..."
                                    autocomplete="off"
                                    @input="scheduleAdSearch" />
                                <p v-if="adLoading" class="mt-1 text-xs text-gray-500">Buscando...</p>
                                <div v-if="adOpen && adResults.length"
                                    class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-900">
                                    <div v-for="(u, ui) in adResults" :key="ui"
                                        class="flex items-center justify-between gap-2 border-b border-gray-100 px-3 py-2 last:border-0 hover:bg-blue-50 dark:border-gray-700 dark:hover:bg-blue-900/10">
                                        <button type="button" class="flex-1 text-left" @click="pickAdUser(u)">
                                            <span class="block font-medium text-sm text-gray-900 dark:text-gray-100">{{ u.displayname || u.samaccountname }}</span>
                                            <span class="text-xs text-gray-500">{{ u.samaccountname }} · {{ u.mail || 'sin correo' }}</span>
                                            <span v-if="u.trabajador_existente" class="ml-2 text-xs text-amber-600 dark:text-amber-400">
                                                (ya existe: {{ u.trabajador_existente.nombre }})
                                            </span>
                                        </button>
                                        <button type="button" @click="crearDesdeAdDirecto(u)"
                                            class="shrink-0 px-2 py-1 text-xs bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition"
                                            :title="u.trabajador_existente ? 'Vincular existente' : 'Crear rápido'">
                                            {{ u.trabajador_existente ? 'Vincular' : 'Crear' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- CI -->
                            <div v-if="createMode === 'manual'">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">CI <span class="text-red-500">*</span></label>
                                <div class="flex gap-2">
                                    <input v-model="createForm.ci" type="text" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" :class="inputClass" required/>
                                    <button type="button" @click="buscarCiExternoCreate" :disabled="ciLookupLoading"
                                        class="px-3 py-2 text-xs border border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 whitespace-nowrap">
                                        {{ ciLookupLoading ? 'Buscando...' : 'Buscar CI' }}
                                    </button>
                                </div>
                                <p v-if="createForm.errors.ci" class="text-xs text-red-500 mt-1">{{ createForm.errors.ci }}</p>
                                <p v-if="ciLookupMessage" class="text-xs mt-1" :class="ciLookupMessage.success ? 'text-green-600' : 'text-red-500'">
                                    {{ ciLookupMessage.message }}
                                </p>
                            </div>

                            <!-- CI para AD (opcional) -->
                            <div v-if="createMode === 'ad'">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">CI <span class="text-gray-400">(opcional)</span></label>
                                <input v-model="createForm.ci" type="text" inputmode="numeric" maxlength="11" :class="inputClass" />
                                <p v-if="createForm.errors.ci" class="text-xs text-red-500 mt-1">{{ createForm.errors.ci }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nombre <span class="text-red-500">*</span></label>
                                <input v-model="createForm.nombre" type="text" :class="inputClass" required autofocus/>
                                <p v-if="createForm.errors.nombre" class="text-xs text-red-500 mt-1">{{ createForm.errors.nombre }}</p>
                            </div>

                            <!-- Campos AD -->
                            <div v-if="createMode === 'ad'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Usuario de Red</label>
                                    <input v-model="createForm.samaccountname" type="text" :class="inputClass" readonly class="bg-gray-50 dark:bg-gray-800"/>
                                    <p v-if="createForm.errors.samaccountname" class="text-xs text-red-500 mt-1">{{ createForm.errors.samaccountname }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Cargo</label>
                                    <input v-model="createForm.cargo" type="text" :class="inputClass" />
                                    <p v-if="createForm.errors.cargo" class="text-xs text-red-500 mt-1">{{ createForm.errors.cargo }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Email</label>
                                    <input v-model="createForm.email" type="email" :class="inputClass" />
                                    <p v-if="createForm.errors.email" class="text-xs text-red-500 mt-1">{{ createForm.errors.email }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                        Teléfono <span v-if="createMode === 'manual'" class="text-red-500">*</span>
                                    </label>
                                    <input v-model="createForm.telefono" type="text" maxlength="15" :class="inputClass" :required="createMode === 'manual'"/>
                                    <p v-if="createForm.errors.telefono" class="text-xs text-red-500 mt-1">{{ createForm.errors.telefono }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                        Municipio <span v-if="createMode === 'manual'" class="text-red-500">*</span>
                                    </label>
                                    <VSelect
                                        v-model="createMunicipio"
                                        :options="municipioOptions"
                                        placeholder="— Seleccione —"
                                        :clearable="true"
                                        @update:modelValue="onCreateMunicipioChange"
                                    />
                                    <p v-if="createForm.errors.municipio_id" class="text-xs text-red-500 mt-1">{{ createForm.errors.municipio_id }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Dirección Particular</label>
                                <input v-model="createForm.direccion" type="text" maxlength="255" :class="inputClass" />
                                <p v-if="createForm.errors.direccion" class="text-xs text-red-500 mt-1">{{ createForm.errors.direccion }}</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <input id="createEstadoTrabajador" v-model="createForm.estado" type="checkbox" class="w-4 h-4 text-blue-600 rounded border-gray-300"/>
                                <label for="createEstadoTrabajador" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Activo</label>
                            </div>
                        </div>

                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
                            <button type="button" @click="closeCreateDrawer"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="createForm.processing"
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-60 text-white text-sm rounded-lg transition">
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal vincular CI (desde AD → SQL Server) -->
        <Teleport to="body">
            <div v-if="showLinkCi" class="fixed inset-0 z-[60] flex items-center justify-center px-4">
                <div class="absolute inset-0 bg-black/50" @click="closeLinkCi" />
                <div class="relative z-10 w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-600 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Vincular datos desde CI externo</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Para trabajadores cargados por <strong>Active Directory</strong>: busque el CI en la base externa y confirme. Se completarán <strong>CI</strong>, <strong>dirección</strong> y <strong>teléfono</strong> (si el origen los tiene). El nombre que usa el sistema no se sustituye automáticamente.
                    </p>
                    <div class="mt-4 space-y-2">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">CI (11 dígitos)</label>
                        <div class="flex flex-wrap gap-2">
                            <input
                                v-model="linkCiInput"
                                type="text"
                                inputmode="numeric"
                                maxlength="11"
                                class="min-w-[11rem] flex-1"
                                :class="inputClass"
                                autocomplete="off"
                                @keyup.enter="buscarLinkCiExterno"
                                @input="linkCiPreview = null; linkCiLookupMessage = null"
                            />
                            <button
                                type="button"
                                class="rounded-lg border border-blue-300 px-3 py-2 text-xs font-medium text-blue-700 hover:bg-blue-50 dark:border-blue-700 dark:text-blue-300 dark:hover:bg-blue-900/30"
                                :disabled="linkCiLookupLoading"
                                @click="buscarLinkCiExterno"
                            >
                                {{ linkCiLookupLoading ? 'Consultando…' : 'Buscar en CI externo' }}
                            </button>
                        </div>
                    </div>
                    <div
                        v-if="linkCiPreview"
                        class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-300"
                    >
                        <p class="font-medium text-slate-800 dark:text-slate-200">Vista previa (origen externo)</p>
                        <p class="mt-1"><span class="text-slate-500">Nombre:</span> {{ linkCiPreview.nombre || '—' }}</p>
                        <p><span class="text-slate-500">Dirección:</span> {{ linkCiPreview.direccion || '—' }}</p>
                        <p v-if="linkCiPreview.telefono"><span class="text-slate-500">Teléfono:</span> {{ linkCiPreview.telefono }}</p>
                    </div>
                    <p
                        v-if="linkCiLookupMessage && !linkCiLookupMessage.success"
                        class="mt-2 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ linkCiLookupMessage.message }}
                    </p>
                    <p v-if="linkCiError" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ linkCiError }}</p>
                    <div class="mt-6 flex flex-wrap justify-end gap-2">
                        <button type="button" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300" @click="closeLinkCi">Cancelar</button>
                        <button
                            type="button"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 disabled:opacity-60"
                            :disabled="linkCiLoading || !linkCiPreview"
                            title="Aplica CI, dirección y teléfono al trabajador"
                            @click="submitLinkCi"
                        >
                            {{ linkCiLoading ? 'Vinculando…' : 'Aplicar datos al trabajador' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal vincular AD -->
        <Teleport to="body">
            <div v-if="showLinkAd" class="fixed inset-0 z-[60] flex items-center justify-center px-4">
                <div class="absolute inset-0 bg-black/50" @click="closeLinkAd" />
                <div class="relative z-10 w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-600 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Vincular con Active Directory</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Para trabajadores <strong>manual</strong> o <strong>CI externo</strong>: busque la cuenta en el directorio y confirme. Se completarán <strong>usuario de red</strong> (samAccountName), <strong>cargo</strong> y <strong>correo</strong> desde el AD.
                    </p>
                    <div class="relative mt-4">
                        <input
                            v-model="linkAdQuery"
                            type="text"
                            :class="inputClass"
                            placeholder="Mínimo 2 caracteres…"
                            autocomplete="off"
                            @input="scheduleLinkAdSearch"
                        />
                        <p v-if="linkAdLoading" class="mt-1 text-xs text-gray-500">Buscando…</p>
                        <div
                            v-if="linkAdOpen && linkAdResults.length"
                            class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-900"
                        >
                            <div
                                v-for="(u, ui) in linkAdResults"
                                :key="ui"
                                class="flex items-center justify-between gap-2 border-b border-gray-100 px-3 py-2 last:border-0 dark:border-gray-700"
                            >
                                <div class="min-w-0 flex-1 text-left text-sm">
                                    <span class="block font-medium text-gray-900 dark:text-gray-100">{{ u.displayname || u.samaccountname }}</span>
                                    <span class="text-xs text-gray-500">{{ u.samaccountname }} · {{ u.mail || 'sin correo' }}</span>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg bg-emerald-600 px-2 py-1 text-xs text-white hover:bg-emerald-700"
                                    :disabled="linkAdLoading"
                                    @click="submitLinkAdFromUser(u)"
                                >
                                    Usar
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-if="linkAdError" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ linkAdError }}</p>
                    <div class="mt-6 flex justify-end">
                        <button type="button" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300" @click="closeLinkAd">Cerrar</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <BaseCard class="max-w-7xl mx-auto">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">CI</th>
                            <th class="px-4 py-3">Origen</th>
                            <th class="px-4 py-3">Teléfono</th>
                            <th class="px-4 py-3">Dirección</th>
                            <th class="px-4 py-3">Municipio</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-if="!trabajadores.length">
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400">Sin trabajadores registrados.</td>
                        </tr>

                        <template v-for="t in trabajadores" :key="t.id">
                            <!-- Fila edición -->
                            <tr v-if="editId === t.id" class="bg-blue-50 dark:bg-blue-900/10">
                                <td class="px-4 py-2"><input v-model="editForm.nombre" type="text" :class="inputClass" required/></td>
                                <td class="px-4 py-2">
                                    <input v-model="editForm.ci" type="text" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" :class="inputClass" :required="editForm.origen !== 'active_directory'"/>
                                    <p v-if="editForm.errors.ci" class="text-xs text-red-500 mt-1">{{ editForm.errors.ci }}</p>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="text-xs px-2 py-0.5 rounded-full" :class="origenBadge(editForm.origen).cls">
                                        {{ origenBadge(editForm.origen).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <input v-model="editForm.telefono" type="text" maxlength="15" :class="inputClass" :required="editForm.origen !== 'active_directory'"/>
                                    <p v-if="editForm.errors.telefono" class="text-xs text-red-500 mt-1">{{ editForm.errors.telefono }}</p>
                                </td>
                                <td class="px-4 py-2 min-w-[220px]">
                                    <input v-model="editForm.direccion" type="text" maxlength="255" :class="inputClass" />
                                    <p v-if="editForm.errors.direccion" class="text-xs text-red-500 mt-1">{{ editForm.errors.direccion }}</p>
                                </td>
                                <td class="px-4 py-2 min-w-[220px]">
                                    <VSelect
                                        v-model="editMunicipio"
                                        :options="municipioOptions"
                                        placeholder="— Seleccione —"
                                        :clearable="true"
                                        @update:modelValue="onEditMunicipioChange"
                                    />
                                    <p v-if="editForm.errors.municipio_id" class="text-xs text-red-500 mt-1">{{ editForm.errors.municipio_id }}</p>
                                </td>
                                <td class="px-4 py-2"><input v-model="editForm.estado" type="checkbox" class="w-4 h-4 text-blue-600 rounded"/></td>
                                <td class="px-4 py-2 text-right whitespace-nowrap space-x-2">
                                    <button @click="submitEdit(t.id)" :disabled="editForm.processing"
                                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white text-xs rounded-lg transition">Guardar</button>
                                    <button @click="cancelEdit"
                                        class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 text-xs rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancelar</button>
                                </td>
                            </tr>
                            <tr v-if="editId === t.id" class="bg-blue-50/80 dark:bg-blue-900/20">
                                <td colspan="8" class="border-t border-blue-200/80 px-4 py-3 dark:border-blue-800/50">
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">
                                        Usuario de red, cargo y correo (editable)
                                    </p>
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div>
                                            <label class="mb-0.5 block text-[11px] text-gray-500 dark:text-gray-400">Usuario de red (samAccountName)</label>
                                            <input v-model="editForm.samaccountname" type="text" :class="inputClass" autocomplete="off" />
                                            <p v-if="editForm.errors.samaccountname" class="mt-1 text-xs text-red-500">{{ editForm.errors.samaccountname }}</p>
                                        </div>
                                        <div>
                                            <label class="mb-0.5 block text-[11px] text-gray-500 dark:text-gray-400">Cargo</label>
                                            <input v-model="editForm.cargo" type="text" :class="inputClass" />
                                            <p v-if="editForm.errors.cargo" class="mt-1 text-xs text-red-500">{{ editForm.errors.cargo }}</p>
                                        </div>
                                        <div>
                                            <label class="mb-0.5 block text-[11px] text-gray-500 dark:text-gray-400">Correo</label>
                                            <input v-model="editForm.email" type="email" :class="inputClass" />
                                            <p v-if="editForm.errors.email" class="mt-1 text-xs text-red-500">{{ editForm.errors.email }}</p>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- Fila lectura -->
                            <tr v-else class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors" :class="t.deleted_at ? 'opacity-50' : ''">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ t.nombre }}</div>
                                    <div v-if="t.samaccountname" class="text-xs text-gray-400">{{ t.samaccountname }}</div>
                                    <div v-if="t.cargo" class="text-xs text-gray-400">{{ t.cargo }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 font-mono text-xs">{{ t.ci || '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-xs px-2 py-0.5 rounded-full" :class="origenBadge(t.origen).cls">
                                        {{ origenBadge(t.origen).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ t.telefono || '—' }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 max-w-[240px]">
                                    <span class="line-clamp-1" :title="t.direccion">{{ t.direccion || '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ t.municipio?.name || '—' }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="t.estado && !t.deleted_at" class="inline-flex items-center gap-1 text-xs text-green-700 dark:text-green-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Activo
                                    </span>
                                    <span v-else-if="t.deleted_at" class="inline-flex flex-col gap-0.5 text-xs text-amber-800 dark:text-amber-200">
                                        <span class="inline-flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block"></span> Inactivo
                                        </span>
                                        <span class="text-[10px] font-medium uppercase tracking-wide text-amber-700/90 dark:text-amber-300/90">En papelera</span>
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 text-xs text-gray-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-300 inline-block"></span> Inactivo
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex flex-wrap items-center justify-end gap-1">
                                    <button
                                        v-if="canLinkCi(t)"
                                        type="button"
                                        class="text-xs px-2 py-1.5 text-blue-700 hover:text-blue-900 dark:text-blue-300 border border-blue-200 dark:border-blue-800 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition max-w-[11rem] leading-tight"
                                        title="Completar CI, dirección y teléfono desde SQL Server"
                                        @click="openLinkCi(t)"
                                    >
                                        Vincular CI externo
                                    </button>
                                    <button
                                        v-if="canLinkAd(t)"
                                        type="button"
                                        class="text-xs px-2 py-1.5 text-emerald-700 hover:text-emerald-900 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-lg transition max-w-[11rem] leading-tight"
                                        title="Completar usuario AD, cargo y correo"
                                        @click="openLinkAd(t)"
                                    >
                                        Vincular AD
                                    </button>
                                    <button
                                        v-if="isAdmin && (t.deleted_at || !t.estado)"
                                        type="button"
                                        class="text-xs px-2 py-1.5 text-violet-700 hover:text-violet-900 dark:text-violet-300 border border-violet-200 dark:border-violet-800 hover:bg-violet-50 dark:hover:bg-violet-900/30 rounded-lg transition"
                                        title="Solo administrador: restaurar del soft delete y marcar activo"
                                        @click="reactivarTrabajador(t.id)"
                                    >
                                        Reactivar
                                    </button>
                                    <button
                                        v-if="isAdmin && t.deleted_at"
                                        type="button"
                                        class="text-xs px-2 py-1.5 text-red-700 hover:text-red-900 dark:text-red-400 border border-red-300 dark:border-red-900 hover:bg-red-50 dark:hover:bg-red-950/40 rounded-lg transition"
                                        title="Solo administrador: borrar de la base de datos (irreversible). Debe estar en papelera."
                                        @click="eliminarDefinitivamente(t.id)"
                                    >
                                        Borrar definitivo
                                    </button>
                                    <button @click="startEdit(t)" v-if="!t.deleted_at"
                                        class="text-xs px-2 py-1.5 text-gray-600 hover:text-gray-800 dark:text-gray-400 border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button @click="confirmDelete(t.id)" v-if="!t.deleted_at"
                                        class="text-xs px-2 py-1.5 text-red-500 hover:text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </BaseCard>
    </AppLayout>
</template>
