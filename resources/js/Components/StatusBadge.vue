<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: { type: String, required: true },
    color: { type: String, default: '' },
})

const palette = {
    Bien: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300',
    Regular: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300',
    Mal: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300',
    green: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300',
    yellow: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300',
    red: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300',
    blue: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300',
    gray: 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-600/40 dark:bg-slate-700/30 dark:text-slate-300',
    orange: 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-500/20 dark:bg-orange-500/10 dark:text-orange-300',
    purple: 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-300',
}

const badgeClass = computed(() => palette[props.color] || palette[props.status] || palette.gray)
</script>

<template>
    <span class="app-badge" :class="badgeClass">
        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80" />
        {{ status }}
    </span>
</template>
