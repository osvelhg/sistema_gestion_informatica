<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import BaseCard from '@/Components/BaseCard.vue'

const props = defineProps({
    source:       Object,
    trabajadores: Array,
    roles:        Array,
})

const today = new Date().toISOString().split('T')[0]

// Build static options
const trabajadorOptions = (props.trabajadores || []).map(t => ({
    id:    t.id,
    label: `${t.ci} | ${t.nombre}`,
}))
const rolOptions = (props.roles || []).map(r => ({
    id:    r.id,
    label: r.nombre,
}))

const filas = ref([
    { trabajador: null, rol: null, fecha_alta: today }
])

function addFila() {
    filas.value.push({ trabajador: null, rol: null, fecha_alta: today })
}
function removeFila(idx) {
    if (filas.value.length === 1) return
    filas.value.splice(idx, 1)
}

const form = useForm({})
const submitting = ref(false)

async function submit() {
    submitting.value = true
    form.trabajadores = filas.value.map(f => ({
        trabajador_id: f.trabajador ? f.trabajador.id : '',
        rolqr_id:      f.rol        ? f.rol.id        : '',
        fecha_alta:    f.fecha_alta,
    }))
    form.post(route('codigos-qr.trabajadores.store', props.source.id), {
        onFinish: () => { submitting.value = false },
    })
}

const inputClass = 'w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent'
</script>

<template>
    <AppLayout :title="`Asignar trabajadores — ${source.source}`">
        <PageHeader title="Asignar trabajadores" :subtitle="`QR: ${source.source} — ${source.source_name || ''}`">
            <template #actions>
                <Link :href="route('codigos-qr.trabajadores.index', source.id)"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Regresar
                </Link>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="max-w-3xl mx-auto pb-8">
            <BaseCard>
                <div class="p-5 space-y-3">
                    <!-- Filas de asignación -->
                    <div v-for="(fila, idx) in filas" :key="idx"
                        class="grid grid-cols-1 sm:grid-cols-[1fr_180px_160px_auto] gap-3 items-end pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0 last:pb-0">
                        <!-- Trabajador -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Trabajador <span class="text-red-500">*</span></label>
                            <VSelect
                                v-model="fila.trabajador"
                                :options="trabajadorOptions"
                                placeholder="— Seleccione —"
                                :clearable="false"
                            />
                        </div>
                        <!-- Rol -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Rol QR</label>
                            <VSelect
                                v-model="fila.rol"
                                :options="rolOptions"
                                placeholder="— Sin rol —"
                                :clearable="true"
                            />
                        </div>
                        <!-- Fecha Alta -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha Alta <span class="text-red-500">*</span></label>
                            <input v-model="fila.fecha_alta" type="date" :class="inputClass" required/>
                        </div>
                        <!-- Eliminar fila -->
                        <div>
                            <button type="button" @click="removeFila(idx)" :disabled="filas.length === 1"
                                class="px-3 py-2 text-sm text-red-500 hover:text-red-700 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/30 disabled:opacity-30 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Agregar fila -->
                    <button type="button" @click="addFila"
                        class="inline-flex items-center gap-2 px-3 py-2 text-sm text-green-600 hover:text-green-800 dark:text-green-400 border border-green-200 dark:border-green-800 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/30 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Agregar fila
                    </button>
                </div>
            </BaseCard>

            <div class="flex justify-end gap-3 mt-4">
                <Link :href="route('codigos-qr.trabajadores.index', source.id)"
                    class="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Cancelar
                </Link>
                <button type="submit" :disabled="submitting"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-60 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar asignaciones
                </button>
            </div>
        </form>
    </AppLayout>
</template>
