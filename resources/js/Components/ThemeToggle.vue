<script setup>
import { computed } from 'vue'
import { useTheme } from '@/Composables/useTheme'

const { theme, resolvedTheme, cycleTheme, setTheme } = useTheme()

const label = computed(() => {
    if (theme.value === 'system') return 'Sistema'
    return resolvedTheme.value === 'dark' ? 'Oscuro' : 'Claro'
})
</script>

<template>
    <div class="relative flex items-center gap-2 rounded-2xl border border-slate-200/80 bg-white/80 p-1 shadow-sm backdrop-blur dark:border-slate-700/80 dark:bg-slate-900/70">
        <button
            type="button"
            class="group flex h-10 w-10 items-center justify-center rounded-xl text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
            :title="`Tema actual: ${label}`"
            @click="cycleTheme"
        >
            <svg v-if="resolvedTheme === 'light'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25M12 18.75V21M4.97 4.97l1.59 1.59M17.44 17.44l1.59 1.59M3 12h2.25M18.75 12H21M4.97 19.03l1.59-1.59M17.44 6.56l1.59-1.59M15.75 12A3.75 3.75 0 1112 8.25 3.75 3.75 0 0115.75 12z" />
            </svg>
            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0112 21.75 9.75 9.75 0 018.25 2.752 7.5 7.5 0 0021.752 15.002z" />
            </svg>
        </button>

        <div class="relative">
            <select
                :value="theme"
                class="min-w-[124px] appearance-none rounded-xl border border-slate-200/80 bg-slate-50/90 py-2 pl-3 pr-9 text-sm font-medium text-slate-700 outline-none transition focus:border-brand-400 focus:ring-4 focus:ring-brand-100 dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-200 dark:focus:border-brand-400 dark:focus:ring-brand-500/10"
                aria-label="Seleccionar tema"
                @change="setTheme($event.target.value)"
            >
                <option value="light">Claro</option>
                <option value="dark">Oscuro</option>
                <option value="system">Sistema</option>
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400 dark:text-slate-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                </svg>
            </span>
        </div>
    </div>
</template>
