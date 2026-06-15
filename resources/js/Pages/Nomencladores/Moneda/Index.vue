<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import BaseCard from '@/Components/BaseCard.vue'

const props = defineProps({
    monedas: Array,
})

const showCreate = ref(false)
const createForm = useForm({
    nombre: '',
    sigla: '',
    simbolo: '',
    tasa_cambio: 1,
    estado: true,
})

function submitCreate() {
    createForm.post(route('monedas.store'), {
        onSuccess: () => { showCreate.value = false; createForm.reset() },
    })
}

const editId = ref(null)
const editForm = useForm({
    nombre: '',
    sigla: '',
    simbolo: '',
    tasa_cambio: 1,
    estado: true,
})

function startEdit(m) {
    editId.value = m.id
    editForm.nombre = m.nombre
    editForm.sigla = m.sigla
    editForm.simbolo = m.simbolo ?? ''
    editForm.tasa_cambio = m.tasa_cambio
    editForm.estado = m.estado
}
function cancelEdit() { editId.value = null }

function submitEdit(id) {
    editForm.put(route('monedas.update', id), {
        onSuccess: () => { editId.value = null },
    })
}

function confirmDelete(id) {
    if (!confirm('¿Eliminar esta moneda?')) return
    router.delete(route('monedas.destroy', id), { preserveScroll: true })
}

const inputClass = 'w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent'
</script>

<template>
    <AppLayout title="Monedas">
        <PageHeader title="Monedas" subtitle="Nomenclador de monedas con sigla, simbolo y tasa de cambio">
            <template #actions>
                <button @click="showCreate = !showCreate"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nueva Moneda
                </button>
            </template>
        </PageHeader>

        <BaseCard class="max-w-5xl mx-auto">
            <div v-if="showCreate" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-green-50 dark:bg-green-900/10">
                <form @submit.prevent="submitCreate" class="grid grid-cols-1 md:grid-cols-[1.5fr_120px_100px_140px_auto] gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nombre <span class="text-red-500">*</span></label>
                        <input v-model="createForm.nombre" type="text" :class="inputClass" required autofocus/>
                        <p v-if="createForm.errors.nombre" class="text-xs text-red-500 mt-1">{{ createForm.errors.nombre }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Sigla <span class="text-red-500">*</span></label>
                        <input v-model="createForm.sigla" type="text" :class="inputClass" maxlength="10" required/>
                        <p v-if="createForm.errors.sigla" class="text-xs text-red-500 mt-1">{{ createForm.errors.sigla }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Símbolo</label>
                        <input v-model="createForm.simbolo" type="text" :class="inputClass" maxlength="10"/>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tasa cambio <span class="text-red-500">*</span></label>
                        <input v-model="createForm.tasa_cambio" type="number" step="0.0001" min="0" :class="inputClass" required/>
                        <p v-if="createForm.errors.tasa_cambio" class="text-xs text-red-500 mt-1">{{ createForm.errors.tasa_cambio }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" :disabled="createForm.processing"
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-60 text-white text-sm rounded-lg transition">Guardar</button>
                        <button type="button" @click="showCreate = false"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancelar</button>
                    </div>
                    <div class="md:col-span-5 flex items-center gap-2">
                        <input id="createEstadoMoneda" v-model="createForm.estado" type="checkbox" class="w-4 h-4 text-blue-600 rounded border-gray-300"/>
                        <label for="createEstadoMoneda" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Activo</label>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Sigla</th>
                            <th class="px-4 py-3">Símbolo</th>
                            <th class="px-4 py-3">Tasa de cambio</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-if="!monedas.length">
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">Sin monedas registradas.</td>
                        </tr>

                        <template v-for="m in monedas" :key="m.id">
                            <tr v-if="editId === m.id" class="bg-blue-50 dark:bg-blue-900/10">
                                <td class="px-4 py-2"><input v-model="editForm.nombre" type="text" :class="inputClass" required/></td>
                                <td class="px-4 py-2"><input v-model="editForm.sigla" type="text" :class="inputClass" maxlength="10" required/></td>
                                <td class="px-4 py-2"><input v-model="editForm.simbolo" type="text" :class="inputClass" maxlength="10"/></td>
                                <td class="px-4 py-2"><input v-model="editForm.tasa_cambio" type="number" step="0.0001" min="0" :class="inputClass" required/></td>
                                <td class="px-4 py-2"><input v-model="editForm.estado" type="checkbox" class="w-4 h-4 text-blue-600 rounded"/></td>
                                <td class="px-4 py-2 text-right whitespace-nowrap space-x-2">
                                    <button @click="submitEdit(m.id)" :disabled="editForm.processing"
                                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white text-xs rounded-lg transition">Guardar</button>
                                    <button @click="cancelEdit"
                                        class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 text-xs rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancelar</button>
                                </td>
                            </tr>

                            <tr v-else class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors" :class="m.deleted_at ? 'opacity-50' : ''">
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ m.nombre }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 font-mono text-xs">{{ m.sigla }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ m.simbolo || '—' }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ m.tasa_cambio }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="m.estado && !m.deleted_at" class="inline-flex items-center gap-1 text-xs text-green-700 dark:text-green-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Activo
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 text-xs text-gray-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-300 inline-block"></span> Inactivo
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                                    <button @click="startEdit(m)" v-if="!m.deleted_at"
                                        class="text-xs px-2 py-1.5 text-gray-600 hover:text-gray-800 dark:text-gray-400 border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button @click="confirmDelete(m.id)" v-if="!m.deleted_at"
                                        class="text-xs px-2 py-1.5 text-red-500 hover:text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </BaseCard>
    </AppLayout>
</template>
