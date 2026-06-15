<script setup>
import { reactive, watch, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: { type: Array, required: true },
    ldapEnabled: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])

const emptyRow = () => ({
    display_name: '',
    samaccountname: '',
    mail: '',
    source: 'manual',
    trabajador_id: null,
})

/** Por índice de fila: búsqueda en trabajadores (local) y LDAP */
const localQuery = reactive({})
const localResults = reactive({})
const localLoading = reactive({})
const localOpen = reactive({})

const ldapQuery = reactive({})
const ldapResults = reactive({})
const ldapLoading = reactive({})
const ldapOpen = reactive({})

const ensureAtLeastOne = () => {
    if (!props.modelValue?.length) {
        emit('update:modelValue', [emptyRow()])
    }
}

onMounted(() => {
    ensureAtLeastOne()
})

watch(
    () => props.modelValue?.length,
    (len) => {
        if (!len) ensureAtLeastOne()
    },
)

const setRows = (rows) => emit('update:modelValue', rows)

const addRow = () => setRows([...props.modelValue, emptyRow()])

const removeRow = (index) => {
    if (props.modelValue.length <= 1) return
    const next = [...props.modelValue]
    next.splice(index, 1)
    setRows(next)
}

const patchRow = (index, patch) => {
    const next = [...props.modelValue]
    next[index] = { ...next[index], ...patch }
    setRows(next)
}

let timers = {}
let localTimers = {}

const scheduleLocalSearch = (index) => {
    clearTimeout(localTimers[index])
    localTimers[index] = setTimeout(() => runLocalSearch(index), 350)
}

const runLocalSearch = async (index) => {
    const q = (localQuery[index] || '').trim()
    if (q.length < 2) {
        localResults[index] = []
        localOpen[index] = false
        localLoading[index] = false
        return
    }
    localLoading[index] = true
    try {
        const { data } = await axios.post('/expedientes/buscar-responsable-trabajadores', { query: q })
        localResults[index] = data.trabajadores || []
        localOpen[index] = (localResults[index] || []).length > 0
    } catch {
        localResults[index] = []
        localOpen[index] = false
    } finally {
        localLoading[index] = false
    }
}

const pickTrabajador = (index, t) => {
    patchRow(index, {
        display_name: t.nombre || '',
        samaccountname: t.samaccountname || '',
        mail: t.mail || '',
        source: 'ad',
        trabajador_id: t.id,
    })
    localQuery[index] = ''
    localResults[index] = []
    localOpen[index] = false
    ldapQuery[index] = ''
    ldapResults[index] = []
    ldapOpen[index] = false
}

const scheduleLdapSearch = (index) => {
    clearTimeout(timers[index])
    timers[index] = setTimeout(() => runLdapSearch(index), 400)
}

const runLdapSearch = async (index) => {
    const q = (ldapQuery[index] || '').trim()
    if (q.length < 2) {
        ldapResults[index] = []
        ldapOpen[index] = false
        ldapLoading[index] = false
        return
    }
    ldapLoading[index] = true
    try {
        const { data } = await axios.post('/expedientes/buscar-responsable-ldap', { query: q })
        ldapResults[index] = data.users || []
        ldapOpen[index] = (data.users || []).length > 0
    } catch {
        ldapResults[index] = []
        ldapOpen[index] = false
    } finally {
        ldapLoading[index] = false
    }
}

const pickLdapUser = (index, u) => {
    patchRow(index, {
        display_name: u.displayname || u.samaccountname || '',
        samaccountname: u.samaccountname || '',
        mail: u.mail || '',
        source: 'ad',
        trabajador_id: u.trabajador_existente?.id ?? null,
    })
    ldapQuery[index] = ''
    ldapResults[index] = []
    ldapOpen[index] = false
}

const toManual = (index) => {
    patchRow(index, { source: 'manual', samaccountname: '', mail: '', trabajador_id: null })
    ldapOpen[index] = false
}

const rowError = (index) => props.errors[`responsibles.${index}.display_name`]
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="(row, index) in modelValue"
            :key="index"
            class="rounded-xl border border-slate-200/90 bg-slate-50/50 p-4 dark:border-slate-700/80 dark:bg-slate-900/40"
        >
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Responsable {{ index + 1 }}
                </span>
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-lg px-2 py-0.5 text-[11px] font-semibold"
                        :class="
                            row.source === 'ad'
                                ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-500/15 dark:text-emerald-200'
                                : 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200'
                        "
                    >
                        {{ row.source === 'ad' ? 'Verificado AD' : 'Manual (alerta si no hay AD)' }}
                    </span>
                    <button
                        v-if="modelValue.length > 1"
                        type="button"
                        class="text-xs text-red-600 hover:underline dark:text-red-400"
                        @click="removeRow(index)"
                    >
                        Quitar
                    </button>
                </div>
            </div>

            <div class="relative mb-3">
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">
                    1. Buscar primero en trabajadores del sistema
                </label>
                <input
                    v-model="localQuery[index]"
                    type="text"
                    class="app-input"
                    placeholder="Nombre, usuario de red, CI o correo (mín. 2 caracteres)…"
                    autocomplete="off"
                    @input="scheduleLocalSearch(index)"
                />
                <p v-if="localLoading[index]" class="mt-1 text-xs text-slate-500">Buscando en el sistema…</p>
                <div
                    v-if="localOpen[index] && (localResults[index] || []).length"
                    class="absolute z-30 mt-1 max-h-48 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-600 dark:bg-slate-900"
                >
                    <button
                        v-for="(t, ti) in localResults[index]"
                        :key="ti"
                        type="button"
                        class="flex w-full flex-col items-start gap-0.5 border-b border-slate-100 px-3 py-2 text-left text-sm last:border-0 hover:bg-sky-50 dark:border-slate-700 dark:hover:bg-sky-500/10"
                        @click="pickTrabajador(index, t)"
                    >
                        <span class="font-medium text-slate-900 dark:text-slate-100">{{ t.nombre }}</span>
                        <span class="text-xs text-slate-500">
                            {{ t.samaccountname || 'sin usuario de red' }} · {{ t.mail || 'sin correo' }}
                        </span>
                    </button>
                </div>
            </div>

            <div v-if="ldapEnabled" class="relative mb-3">
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">
                    2. Si no está en el listado, buscar en Active Directory
                </label>
                <input
                    v-model="ldapQuery[index]"
                    type="text"
                    class="app-input"
                    placeholder="Escribe al menos 2 caracteres (usuario, nombre o correo)…"
                    autocomplete="off"
                    @input="scheduleLdapSearch(index)"
                />
                <p v-if="ldapLoading[index]" class="mt-1 text-xs text-slate-500">Buscando en directorio…</p>
                <div
                    v-if="ldapOpen[index] && (ldapResults[index] || []).length"
                    class="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-600 dark:bg-slate-900"
                >
                    <button
                        v-for="(u, ui) in ldapResults[index]"
                        :key="ui"
                        type="button"
                        class="flex w-full flex-col items-start gap-0.5 border-b border-slate-100 px-3 py-2 text-left text-sm last:border-0 hover:bg-brand-50 dark:border-slate-700 dark:hover:bg-brand-500/10"
                        @click="pickLdapUser(index, u)"
                    >
                        <span class="font-medium text-slate-900 dark:text-slate-100">{{ u.displayname || u.samaccountname }}</span>
                        <span class="text-xs text-slate-500">{{ u.samaccountname }} · {{ u.mail || 'sin correo' }}</span>
                        <span v-if="u.trabajador_existente" class="text-[11px] font-medium text-amber-700 dark:text-amber-300">
                            Ya en sistema (ID {{ u.trabajador_existente.id }})
                        </span>
                    </button>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Nombre completo *</label>
                    <input
                        :value="row.display_name"
                        type="text"
                        class="app-input"
                        placeholder="Nombre y apellidos"
                        @input="patchRow(index, { display_name: $event.target.value })"
                    />
                    <p v-if="rowError(index)" class="mt-1 text-xs text-red-500">{{ rowError(index) }}</p>
                </div>
            </div>

            <div class="mt-2 flex flex-wrap gap-2">
                <button v-if="ldapEnabled" type="button" class="app-button-secondary !py-1.5 !text-xs" @click="toManual(index)">
                    Usar solo texto manual (sin AD)
                </button>
            </div>
        </div>

        <button type="button" class="app-button-secondary w-full sm:w-auto" @click="addRow">+ Agregar otro responsable</button>
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Primero conviene elegir un trabajador ya registrado (menos consultas al directorio). Si no aparece, búsqueda en AD: al guardar el expediente se crea el trabajador en el módulo si hace falta. Si solo escribes el nombre a mano, se generará una alerta para revisión.
        </p>
    </div>
</template>
