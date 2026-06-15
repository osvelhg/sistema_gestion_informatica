<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import PageHeader from '@/Components/PageHeader.vue'

const props = defineProps({ user: Object })
const profileForm = useForm({ name: props.user?.name || '', email: props.user?.email || '' })
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' })
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <PageHeader eyebrow="Usuario" title="Mi perfil" description="Actualiza tu informacion personal y cambia la contrasena." />
            <BaseCard>
                <form class="space-y-4" @submit.prevent="profileForm.put('/perfil')">
                    <input v-model="profileForm.name" type="text" class="app-input" placeholder="Nombre" />
                    <input v-model="profileForm.email" type="email" class="app-input" placeholder="Correo" />
                    <button class="app-button-primary" :disabled="profileForm.processing">Actualizar informacion</button>
                </form>
            </BaseCard>
            <BaseCard>
                <form class="space-y-4" @submit.prevent="passwordForm.put('/perfil/password', { onSuccess: () => passwordForm.reset() })">
                    <input v-model="passwordForm.current_password" type="password" class="app-input" placeholder="Contrasena actual" />
                    <input v-model="passwordForm.password" type="password" class="app-input" placeholder="Nueva contrasena" />
                    <input v-model="passwordForm.password_confirmation" type="password" class="app-input" placeholder="Confirmar nueva contrasena" />
                    <button class="app-button-primary" :disabled="passwordForm.processing">Cambiar contrasena</button>
                </form>
            </BaseCard>
        </div>
    </AppLayout>
</template>
