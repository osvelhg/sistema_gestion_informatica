<script setup>
defineProps({
    rows: { type: Number, default: 5 },
})
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="index in rows"
            :key="index"
            class="animate-pulse rounded-2xl border border-slate-200/70 bg-white/70 p-4 dark:border-slate-700/70 dark:bg-slate-900/40"
        >
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-3">
                    <div class="h-3 w-28 rounded-full bg-slate-200 dark:bg-slate-700" />
                    <div class="h-4 w-48 rounded-full bg-slate-200 dark:bg-slate-700" />
                </div>
                <div class="h-9 w-24 rounded-xl bg-slate-200 dark:bg-slate-700" />
            </div>
        </div>
    </div>
</template>
