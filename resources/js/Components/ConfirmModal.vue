<script setup>
defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Confirmar accion' },
    message: { type: String, default: 'Estas seguro de que deseas continuar?' },
})

const emit = defineEmits(['confirm', 'close'])
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
                <div class="surface-card relative w-full max-w-lg p-6 md:p-7">
                    <div class="mb-5 flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zM12 15.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-950 dark:text-white">{{ title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ message }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" class="app-button-secondary" @click="emit('close')">Cancelar</button>
                        <button type="button" class="app-button-danger" @click="emit('confirm')">Confirmar</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
