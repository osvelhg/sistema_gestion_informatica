<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import BaseCard from '@/Components/BaseCard.vue'

const props = defineProps({
    source:     Object,
    asignacion: Object,
    roles:      Array,
})

const rolOptions = (props.roles || []).map(r => ({ id: r.id, label: r.nombre }))

const selectedRol = ref(
    props.asignacion.rolqr_id
        ? rolOptions.find(r => r.id === props.asignacion.rolqr_id) ?? null
        : null
)

const form = useForm({
    rolqr_id:   props.asignacion.rolqr_id ?? '',
    fecha_alta: props.asignacion.fecha_alta ?? '',
    fecha_baja: props.asignacion.fecha_baja ?? '',
    estado:     props.asignacion.estado ?? true,
})

function onRolChange(val) {
    form.rolqr_id = val ? val.id : ''
}

function submit() {
    form.put(route('codigos-qr.trabajadores.update', { source: props.source.id, pivotId: props.asignacion.pivot_id }))
}

const inputClass = 'w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent'
</script>

<template>
    <AppLayout title="Editar asignación">
        <PageHeader title="Editar asignación de trabajador" :subtitle="`QR: ${source.source}`">
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

        <form @submit.prevent="submit" class="max-w-lg mx-auto pb-8">
            <BaseCard>
                <div class="p-5 space-y-4">
                    <!-- Trabajador (solo lectura) -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Trabajador</label>
                        <input type="text" :value="`${asignacion.ci} | ${asignacion.nombre}`"
                            class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400"
                            disabled/>
                    </div>

                    <!-- Rol QR -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Rol QR</label>
                        <VSelect
                            v-model="selectedRol"
                            :options="rolOptions"
                            placeholder="— Sin rol —"
                            :clearable="true"
                            @update:modelValue="onRolChange"
                        />
                        <p v-if="form.errors.rolqr_id" class="text-xs text-red-500 mt-1">{{ form.errors.rolqr_id }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Fecha Alta -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha Alta <span class="text-red-500">*</span></label>
                            <input v-model="form.fecha_alta" type="date" :class="inputClass" required/>
                            <p v-if="form.errors.fecha_alta" class="text-xs text-red-500 mt-1">{{ form.errors.fecha_alta }}</p>
                        </div>
                        <!-- Fecha Baja -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha Baja</label>
                            <input v-model="form.fecha_baja" type="date" :class="inputClass"/>
                            <p v-if="form.errors.fecha_baja" class="text-xs text-red-500 mt-1">{{ form.errors.fecha_baja }}</p>
                        </div>
                    </div>

                    <!-- Estado -->
                    <div class="flex items-center gap-3">
                        <input id="estado" v-model="form.estado" type="checkbox"
                            class="w-4 h-4 text-blue-600 rounded border-gray-300 dark:border-gray-600"/>
                        <label for="estado" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Activo</label>
                    </div>
                </div>
            </BaseCard>

            <div class="flex justify-end gap-3 mt-4">
                <Link :href="route('codigos-qr.trabajadores.index', source.id)"
                    class="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Cancelar
                </Link>
                <button type="submit" :disabled="form.processing"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-60 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Actualizar
                </button>
            </div>
        </form>
    </AppLayout>
</template>
