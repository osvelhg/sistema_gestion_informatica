<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    show: { type: Boolean, default: false },
    entities: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'submit'])

const entityId = ref('')
const departmentId = ref('')
const departments = ref([])

const fetchDepartments = async (id) => {
    if (!id) {
        departments.value = []
        return
    }

    try {
        const { data } = await axios.get(`/departamentos/por-entidad/${id}`)
        departments.value = data
    } catch {
        departments.value = []
    }
}

watch(entityId, (value) => {
    departmentId.value = ''
    fetchDepartments(value)
})

watch(() => props.show, (visible) => {
    if (!visible) {
        entityId.value = ''
        departmentId.value = ''
        departments.value = []
    }
})

const submit = () => {
    emit('submit', {
        to_entity_id: entityId.value,
        to_department_id: departmentId.value,
    })
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" @click="emit('close')" />
                <div class="surface-card relative w-full max-w-xl p-6 md:p-7">
                    <div class="mb-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-600 dark:text-brand-300">Movimiento seguro</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">Mover expediente</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Selecciona la nueva entidad y el departamento de destino para mantener la trazabilidad del activo.</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Entidad destino</label>
                            <select v-model="entityId" class="app-select">
                                <option value="">Seleccionar entidad</option>
                                <option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Departamento destino</label>
                            <select v-model="departmentId" :disabled="!entityId" class="app-select disabled:cursor-not-allowed disabled:opacity-50">
                                <option value="">Seleccionar departamento</option>
                                <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" class="app-button-secondary" @click="emit('close')">Cancelar</button>
                        <button type="button" class="app-button-primary" :disabled="!entityId || !departmentId" @click="submit">
                            Confirmar traslado
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
