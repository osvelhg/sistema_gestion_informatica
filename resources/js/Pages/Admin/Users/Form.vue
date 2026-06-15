<script setup>
import { computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import PageHeader from '@/Components/PageHeader.vue'

const props = defineProps({
    user: { type: Object, default: null },
    roles: Array,
    entities: Array,
    entityAccessModes: { type: Array, default: () => [] },
})
const isEdit = computed(() => !!props.user)

const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    active: props.user?.active ?? true,
    role: props.user?.roles?.[0]?.name || '',
    entity_access_mode: props.user?.entity_access_mode || 'restricted_entities',
    entity_ids: props.user?.entities?.map((entity) => entity.id) || [],
})

const showEntityPicker = computed(() => form.entity_access_mode === 'restricted_entities')

const submit = () => {
    if (isEdit.value) return form.put(`/admin/usuarios/${props.user.id}`)
    form.post('/admin/usuarios')
}

const toggleEntity = (id) => {
    const index = form.entity_ids.indexOf(id)
    if (index >= 0) form.entity_ids.splice(index, 1)
    else form.entity_ids.push(id)
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <PageHeader eyebrow="Administracion" :title="isEdit ? 'Editar Usuario' : 'Nuevo Usuario'" description="Credenciales, rol y alcance: directorio provincial según AD o entidades concretas para mayor confidencialidad.">
                <template #actions>
                    <Link href="/admin/usuarios" class="app-button-secondary">Volver</Link>
                </template>
            </PageHeader>

            <BaseCard>
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nombre</label>
                            <input v-model="form.name" type="text" class="app-input" />
                            <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                            <input v-model="form.email" type="email" class="app-input" />
                            <p v-if="form.errors.email" class="text-xs text-rose-500">{{ form.errors.email }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Contrasena{{ isEdit ? ' (dejar vacio para mantener)' : '' }}</label>
                            <input v-model="form.password" type="password" class="app-input" />
                            <p v-if="form.errors.password" class="text-xs text-rose-500">{{ form.errors.password }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Rol</label>
                            <select v-model="form.role" class="app-select">
                                <option value="">Seleccionar rol...</option>
                                <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                            </select>
                            <p v-if="form.errors.role" class="text-xs text-rose-500">{{ form.errors.role }}</p>
                        </div>
                    </div>

                    <div v-if="isEdit && user?.ad_provincia_sigla" class="rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/60">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Provincia (Active Directory)</p>
                        <p class="mt-1 font-mono text-sm text-slate-800 dark:text-slate-200">{{ user.ad_provincia_sigla }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Solo lectura; se actualiza al iniciar sesión por LDAP según el DN.</p>
                    </div>

                    <label class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300">
                        <input v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800" />
                        Usuario activo
                    </label>

                    <div class="space-y-3">
                        <div>
                            <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Alcance de datos</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Modo «directorio provincial» usa la sigla del AD y todas las entidades de esa provincia. «Entidades concretas» restringe a las marcadas abajo.</p>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Modo de acceso</label>
                            <select v-model="form.entity_access_mode" class="app-select">
                                <option v-for="opt in entityAccessModes" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <p v-if="form.errors.entity_access_mode" class="text-xs text-rose-500">{{ form.errors.entity_access_mode }}</p>
                        </div>
                    </div>

                    <div v-show="showEntityPicker" class="space-y-3">
                        <div>
                            <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Entidades asignadas</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Solo aplica en modo entidades concretas.</p>
                        </div>
                        <div class="grid gap-2 rounded-2xl border border-slate-200/80 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-900/60 sm:grid-cols-2 xl:grid-cols-3">
                            <label v-for="entity in entities" :key="entity.id" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-slate-700 transition hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800/70">
                                <input type="checkbox" :checked="form.entity_ids.includes(entity.id)" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800" @change="toggleEntity(entity.id)" />
                                <span><span v-if="entity.code" class="font-mono text-xs text-slate-500 dark:text-slate-400">[{{ entity.code }}]</span> {{ entity.name }}</span>
                            </label>
                            <p v-if="!entities.length" class="text-sm text-slate-400 dark:text-slate-500">No hay entidades registradas.</p>
                        </div>
                        <p v-if="form.errors.entity_ids" class="text-xs text-rose-500">{{ form.errors.entity_ids }}</p>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800">
                        <Link href="/admin/usuarios" class="app-button-secondary">Cancelar</Link>
                        <button type="submit" :disabled="form.processing" class="app-button-primary">{{ form.processing ? 'Guardando...' : 'Guardar' }}</button>
                    </div>
                </form>
            </BaseCard>
        </div>
    </AppLayout>
</template>
