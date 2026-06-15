import { computed, onMounted, ref, watch } from 'vue'

const STORAGE_KEY = 'sgi-theme'
const theme = ref('system')
const resolvedTheme = ref('light')
let mediaQuery

const applyTheme = (value) => {
    const root = document.documentElement
    const shouldUseDark = value === 'dark' || (value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)

    root.classList.toggle('dark', shouldUseDark)
    root.dataset.theme = shouldUseDark ? 'dark' : 'light'
    resolvedTheme.value = shouldUseDark ? 'dark' : 'light'
}

const setTheme = (value) => {
    theme.value = value
    localStorage.setItem(STORAGE_KEY, value)
    applyTheme(value)
}

const cycleTheme = () => {
    if (theme.value === 'light') {
        setTheme('dark')
        return
    }

    if (theme.value === 'dark') {
        setTheme('system')
        return
    }

    setTheme('light')
}

export function initializeTheme() {
    const saved = localStorage.getItem(STORAGE_KEY) || 'system'
    theme.value = saved
    applyTheme(saved)

    mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    mediaQuery.addEventListener('change', () => {
        if (theme.value === 'system') {
            applyTheme('system')
        }
    })
}

export function useTheme() {
    onMounted(() => {
        if (!document.documentElement.dataset.theme) {
            initializeTheme()
        }
    })

    watch(theme, (value) => applyTheme(value))

    return {
        theme,
        resolvedTheme: computed(() => resolvedTheme.value),
        isDark: computed(() => resolvedTheme.value === 'dark'),
        setTheme,
        cycleTheme,
    }
}
