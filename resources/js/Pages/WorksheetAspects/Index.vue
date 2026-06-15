<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { confirmDanger } from '@/Composables/useNotifications'

const props = defineProps({ aspects: Array, sections: Object })

const showModal = ref(false)
const editing = ref(null)
const form = useForm({ section: 'equipamiento', label: '', order: 0, active: true })

const grouped = computed(() => {
    const result = {}
    for (const key of Object.keys(props.sections)) {
        result[key] = props.aspects.filter(a => a.section === key)
    }
    return result
})

const openCreate = (section = 'equipamiento') => {
    editing.value = null
    form.reset()
    form.section = section
    form.active = true
    const sectionItems = props.aspects.filter(a => a.section === section)
    form.order = sectionItems.length ? Math.max(...sectionItems.map(a => a.order)) + 1 : 1
    form.clearErrors()
    showModal.value = true
}

const openEdit = (aspect) => {
    editing.value = aspect
    form.section = aspect.section
    form.label = aspect.label
    form.order = aspect.order
    form.active = aspect.active
    form.clearErrors()
    showModal.value = true
}

const submit = () => {
    const options = { onSuccess: () => { showModal.value = false } }
    if (editing.value) return form.put(`/aspectos-hoja/${editing.value.id}`, options)
    form.post('/aspectos-hoja', options)
}

const destroy = async (aspect) => {
    if (!await confirmDanger({
        title: 'Eliminar aspecto',
        text: `Se eliminará el aspecto "${aspect.label}".`,
        confirmText: 'Sí, eliminar',
    })) return
    router.delete(`/aspectos-hoja/${aspect.id}`)
}

const toggleActive = (aspect) => {
    router.put(`/aspectos-hoja/${aspect.id}`, {
        section: aspect.section,
        label: aspect.label,
        order: aspect.order,
        active: !aspect.active,
    }, { preserveScroll: true })
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <PageHeader
                eyebrow="Nomenclador"
                title="Aspectos de Hoja de Trabajo"
                description="Gestiona los elementos evaluables del checklist B/R/M en la Hoja de Trabajo. Los cambios aplican a nuevos registros."
            >
                <template #actions>
                    <button type="button" class="app-button-primary" @click="openCreate()">Nuevo aspecto</button>
                </template>
            </PageHeader>

            <div class="space-y-4">
                <div v-for="(sectionLabel, sectionKey) in sections" :key="sectionKey">
                    <BaseCard :padded="false">
                        <!-- Header de sección -->
                        <div class="flex items-center justify-between border-b border-slate-200/80 bg-slate-50/80 px-5 py-3 dark:border-slate-700/70 dark:bg-slate-900/60">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand-600 dark:text-brand-300">Sección</p>
                                <h3 class="mt-0.5 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ sectionLabel }}</h3>
                            </div>
                            <button type="button" class="app-button-secondary px-3 py-1.5 text-xs" @click="openCreate(sectionKey)">
                                + Agregar
                            </button>
                        </div>

                        <!-- Tabla de aspectos -->
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200/60 dark:border-slate-700/60">
                                    <th class="px-5 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400">#</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400">Elemento</th>
                                    <th class="w-28 px-3 py-2 text-center text-xs font-medium text-slate-500 dark:text-slate-400">Estado</th>
                                    <th class="w-28 px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="aspect in grouped[sectionKey]"
                                    :key="aspect.id"
                                    class="border-b border-slate-200/50 last:border-0 dark:border-slate-700/50"
                                    :class="!aspect.active && 'opacity-50'"
                                >
                                    <td class="px-5 py-2.5 font-mono text-xs text-slate-400 dark:text-slate-500">{{ aspect.order }}</td>
                                    <td class="px-3 py-2.5 text-slate-700 dark:text-slate-300">{{ aspect.label }}</td>
                                    <td class="px-3 py-2.5 text-center">
                                        <button type="button" @click="toggleActive(aspect)">
                                            <StatusBadge
                                                :status="aspect.active ? 'Activo' : 'Inactivo'"
                                                :color="aspect.active ? 'green' : 'red'"
                                            />
                                        </button>
                                    </td>
                                    <td class="px-3 py-2.5 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="app-button-secondary px-3 py-1.5 text-xs" @click="openEdit(aspect)">Editar</button>
                                            <button type="button" class="app-button-danger px-3 py-1.5 text-xs" @click="destroy(aspect)">Eliminar</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!grouped[sectionKey]?.length">
                                    <td colspan="4" class="px-5 py-4 text-sm text-slate-400 dark:text-slate-500">
                                        Sin aspectos en esta sección.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </BaseCard>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" @click="showModal = false" />
                <div class="surface-card relative z-10 w-full max-w-lg p-6">
                    <h3 class="mb-5 text-xl font-semibold text-slate-950 dark:text-slate-100">
                        {{ editing ? 'Editar aspecto' : 'Nuevo aspecto' }}
                    </h3>
                    <form class="space-y-5" @submit.prevent="submit">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Sección *</label>
                            <select v-model="form.section" class="app-select">
                                <option v-for="(label, key) in sections" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <p v-if="form.errors.section" class="text-xs text-rose-500">{{ form.errors.section }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Descripción del elemento *</label>
                            <textarea v-model="form.label" rows="2" class="app-input" placeholder="Ej: Antivirus actualizado y configurado correctamente" />
                            <p v-if="form.errors.label" class="text-xs text-rose-500">{{ form.errors.label }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Orden</label>
                            <input v-model.number="form.order" type="number" min="0" class="app-input w-28" />
                            <p v-if="form.errors.order" class="text-xs text-rose-500">{{ form.errors.order }}</p>
                        </div>

                        <label class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300">
                            <input v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800" />
                            Aspecto activo (se mostrará en nuevas hojas de trabajo)
                        </label>

                        <div class="flex justify-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800">
                            <button type="button" class="app-button-secondary" @click="showModal = false">Cancelar</button>
                            <button type="submit" class="app-button-primary" :disabled="form.processing">
                                {{ form.processing ? 'Guardando...' : 'Guardar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
