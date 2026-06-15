<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import BaseCard from '@/Components/BaseCard.vue'

const props = defineProps({
    entities: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const entityId = ref(props.filters.entity_id ?? '')
const departmentId = ref(props.filters.department_id ?? '')
const status = ref(props.filters.status ?? '')
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

if (entityId.value) {
    fetchDepartments(entityId.value)
}

watch(entityId, (value) => {
    departmentId.value = ''
    fetchDepartments(value)
})

const applyFilters = () => {
    const params = {}
    if (entityId.value) params.entity_id = entityId.value
    if (departmentId.value) params.department_id = departmentId.value
    if (status.value) params.status = status.value

    router.get(window.location.pathname, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

watch([entityId, departmentId, status], applyFilters)

const resetFilters = () => {
    entityId.value = ''
    departmentId.value = ''
    status.value = ''
}
</script>

<template>
    <BaseCard title="Filtros inteligentes" subtitle="Refina el universo visible de expedientes en tiempo real.">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end">
            <div class="grid flex-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Entidad</label>
                    <select v-model="entityId" class="app-select">
                        <option value="">Todas las entidades</option>
                        <option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Departamento</label>
                    <select v-model="departmentId" :disabled="!entityId" class="app-select disabled:cursor-not-allowed disabled:opacity-50">
                        <option value="">Todos los departamentos</option>
                        <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Estado</label>
                    <select v-model="status" class="app-select">
                        <option value="">Todos los estados</option>
                        <option value="Bien">Bien</option>
                        <option value="Regular">Regular</option>
                        <option value="Mal">Mal</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" class="app-button-secondary" @click="resetFilters">
                    Limpiar filtros
                </button>
            </div>
        </div>
    </BaseCard>
</template>
