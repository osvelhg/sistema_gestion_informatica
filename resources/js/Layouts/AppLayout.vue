<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import ThemeToggle from '@/Components/ThemeToggle.vue'
import { useTheme } from '@/Composables/useTheme'
import { notifyError, notifyInfo, notifySuccess, notifyWarning } from '@/Composables/useNotifications'

const page = usePage()
const branding = computed(() => page.props.branding || {})
const user = computed(() => page.props.auth?.user)
const isAdmin = computed(() => (user.value?.roles || []).includes('Administrador'))
const flash = computed(() => page.props.flash || {})
const sidebarOpen = ref(true)
const mobileSidebarOpen = ref(false)
const showUserMenu = ref(false)
const { resolvedTheme } = useTheme()

const has = (permission) => {
    if (isAdmin.value) return true
    const perms = user.value?.permissions || []
    return perms.includes(permission)
}
const moduleEnabled = (slug) => {
    const modules = page.props.modules || {}
    return modules[slug] !== false
}

// Auto-expandir el subgrupo que contiene la ruta activa
const subgroupRoutes = {
    entidades:     ['/entidades', '/departamentos', '/pisos-venta', '/areas-venta', '/pisos-venta/areas-qr'],
    organizacion:  ['/provincias', '/municipios', '/tipos-red', '/tipos-establecimiento', '/estados-establecimiento'],
    equipamiento:  ['/marcas', '/tipos-componentes', '/modelos', '/estados'],
    seguridad:     ['/tipos-incidencias', '/aspectos-hoja'],
    conectividad:  ['/fincimex', '/modos-adsl', '/velocidades'],
    qr:            ['/canales-electronicos', '/tipos-fuente', '/monedas'],
}
const activeSubgroup = Object.entries(subgroupRoutes).find(([, routes]) =>
    routes.some(r => page.url.startsWith(r))
)?.[0] ?? null

const expandedSubgroups = ref({
    entidades:    activeSubgroup === 'entidades',
    organizacion: activeSubgroup === 'organizacion',
    equipamiento: activeSubgroup === 'equipamiento',
    seguridad:    activeSubgroup === 'seguridad',
    conectividad: activeSubgroup === 'conectividad',
    qr:           activeSubgroup === 'qr',
})

const toggleSubgroup = (key) => {
    expandedSubgroups.value[key] = !expandedSubgroups.value[key]
}

const workspaceNavItems = computed(() => {
    const expItems = [
        { name: 'Expedientes', href: '/expedientes', icon: 'computer', show: moduleEnabled('expedientes') && has('expedientes.index'), hint: 'Listado' },
        { name: 'Control de sellos', href: '/sellos', icon: 'tag', show: moduleEnabled('expedientes') && has('sellos.index'), hint: 'Sellos' },
        { name: 'Alertas', href: '/expedientes/alertas', icon: 'bell', show: moduleEnabled('expedientes') && has('expedientes.index'), hint: 'RODAS' },
    ].filter((i) => i.show)

    const connItems = [
        { name: 'Conectividad', href: '/conectividad', icon: 'cpu', show: moduleEnabled('conectividad') && has('conectividad.index'), hint: 'Red y ADSL por PV' },
        { name: 'Comprobaciones', href: '/conectividad/comprobaciones', icon: 'activity', show: moduleEnabled('conectividad') && has('conectividad.index'), hint: 'Ping y segmentos' },
        { name: 'FINCIMEX', href: '/fincimex', icon: 'tag', show: moduleEnabled('conectividad') && has('fincimex.index'), hint: 'POS / QR' },
    ].filter((i) => i.show)

    const facturacionItems = [
        { name: 'Facturas ETECSA', href: '/facturacion-etecsa', icon: 'receipt', show: moduleEnabled('facturacion-etecsa') && has('etecsa.index'), hint: 'Importar y consultar facturas PDF' },
        { name: 'Dashboard ETECSA', href: '/facturacion-etecsa/dashboard', icon: 'activity', show: moduleEnabled('facturacion-etecsa') && has('etecsa.index'), hint: 'Análisis y KPIs' },
    ].filter((i) => i.show)

    const entItems = [
        { name: 'Entidades', href: '/entidades', icon: 'building', show: has('entidades.index'), hint: 'Organizaciones' },
        { name: 'Departamentos', href: '/departamentos', icon: 'folder', show: has('departamentos.index'), hint: 'Estructura interna' },
        { name: 'Pisos de venta', href: '/pisos-venta', icon: 'building', show: moduleEnabled('conectividad') && has('pisos-venta.index'), hint: 'PV y geolocalización' },
        { name: 'Áreas de venta', href: '/areas-venta', icon: 'list', show: moduleEnabled('conectividad') && has('areas-venta.index'), hint: 'CRUD y vínculos QR' },
    ].filter((i) => i.show)

    const out = []
    out.push({ type: 'link', name: 'Dashboard', href: '/dashboard', icon: 'home', show: true, hint: 'Resumen' })
    if (entItems.length) {
        out.push({ type: 'dropdown', key: 'ent', name: 'Entidades', icon: 'building', hint: 'Org., PV y áreas', items: entItems })
    }
    if (expItems.length) {
        out.push({ type: 'dropdown', key: 'exp', name: 'Expedientes', icon: 'computer', hint: 'Equipos, sellos, alertas', items: expItems })
    }
    if (connItems.length) {
        out.push({ type: 'dropdown', key: 'conn', name: 'Conectividad', icon: 'cpu', hint: 'Red y FINCIMEX', items: connItems })
    }
    if (facturacionItems.length) {
        out.push({ type: 'dropdown', key: 'fact', name: 'Facturación', icon: 'receipt', hint: 'Facturas ETECSA', items: facturacionItems })
    }
    out.push(
        { type: 'link', name: 'Reportes', href: '/reportes', icon: 'shield', show: has('reportes.index'), hint: 'Tickets' },
        { type: 'link', name: 'Códigos QR', href: '/codigos-qr', icon: 'qr', show: moduleEnabled('codigos-qr') && has('codigos-qr.index'), hint: 'Datacell' },
        { type: 'link', name: 'Trabajadores', href: '/trabajadores', icon: 'users', show: has('trabajadores.index'), hint: 'Personal' },
    )
    return out.filter((i) => i.type === 'dropdown' || i.show)
})

const workspaceDropOpen = ref({ ent: false, exp: true, conn: false, fact: false })

const syncWorkspaceDrops = () => {
    const u = page.url
    workspaceDropOpen.value.ent =
        u.startsWith('/entidades') ||
        u.startsWith('/departamentos') ||
        u.startsWith('/pisos-venta') ||
        u.startsWith('/areas-venta')
    workspaceDropOpen.value.exp = u.startsWith('/expedientes') || u.startsWith('/sellos')
    workspaceDropOpen.value.conn = u.startsWith('/fincimex') || u.startsWith('/conectividad')
    workspaceDropOpen.value.fact = u.startsWith('/facturacion-etecsa')
}

watch(() => page.url, syncWorkspaceDrops, { immediate: true })

const toggleWorkspaceDrop = (key) => {
    workspaceDropOpen.value[key] = !workspaceDropOpen.value[key]
}

/** Evita que "Expedientes" marque activo en /expedientes/alertas */
const isWorkspaceChildActive = (href) => {
    const u = page.url
    if (href === '/expedientes') {
        if (u.startsWith('/expedientes/alertas')) return false
        return u.startsWith('/expedientes')
    }
    return u.startsWith(href)
}

const isWorkspaceDropdownActive = (key) => {
    if (key === 'ent') {
        return (
            page.url.startsWith('/entidades') ||
            page.url.startsWith('/departamentos') ||
            page.url.startsWith('/pisos-venta') ||
            page.url.startsWith('/areas-venta')
        )
    }
    if (key === 'exp') return page.url.startsWith('/expedientes') || page.url.startsWith('/sellos')
    if (key === 'conn') return page.url.startsWith('/fincimex') || page.url.startsWith('/conectividad')
    if (key === 'fact') return page.url.startsWith('/facturacion-etecsa')
    return false
}

const menuGroups = computed(() => [
    {
        label: 'Workspace',
        workspace: true,
    },
    {
        label: 'Nomencladores',
        subgroups: [
            {
                key: 'organizacion',
                label: 'Organización',
                icon: 'building',
                items: [
                    { name: 'Provincias',    href: '/provincias',    icon: 'map',    show: has('provincias.index'),    hint: 'Ubicaciones' },
                    { name: 'Municipios',    href: '/municipios',    icon: 'mapPin', show: has('municipios.index'),    hint: 'Desglose territorial' },
                    { name: 'Tipos de Red',       href: '/tipos-red',               icon: 'flag', show: moduleEnabled('conectividad') && has('tipos-red.index'),               hint: 'Redes comerciales POS' },
                    { name: 'Tipo Establecim.',   href: '/tipos-establecimiento',    icon: 'list', show: moduleEnabled('conectividad') && has('tipos-establecimiento.index'),   hint: 'CUP / MLC / MIXTO' },
                    { name: 'Estado Establecim.', href: '/estados-establecimiento',  icon: 'flag', show: moduleEnabled('conectividad') && has('estados-establecimiento.index'), hint: 'Abierto / Cerrado' },
                ],
            },
            {
                key: 'equipamiento',
                label: 'Equipamiento',
                icon: 'cpu',
                items: [
                    { name: 'Marcas',  href: '/marcas',           icon: 'tag',  show: has('marcas.index'), hint: 'Catálogo de marcas' },
                    { name: 'Tipos',   href: '/tipos-componentes', icon: 'list', show: has('tipos-componentes.index'), hint: 'Periféricos y dispositivos' },
                    { name: 'Modelos', href: '/modelos',           icon: 'cpu',  show: has('modelos.index'), hint: 'Base de modelos' },
                    { name: 'Estados', href: '/estados',           icon: 'flag', show: has('estados.index'), hint: 'Semáforos operativos' },
                ],
            },
            {
                key: 'seguridad',
                label: 'Seguridad Informática',
                icon: 'shield',
                items: [
                    { name: 'Tipos Incidencias', href: '/tipos-incidencias', icon: 'flag',      show: has('tipos-incidencias.index') || has('reportes.index'), hint: 'Nomenclador de incidencias' },
                    { name: 'Aspectos Hoja',     href: '/aspectos-hoja',     icon: 'clipboard', show: has('aspectos-hoja.index'),                              hint: 'Checklist hoja de trabajo' },
                ],
            },
            {
                key: 'qr',
                label: 'QR',
                icon: 'qr',
                items: [
                    { name: 'Canales Electrónicos', href: '/canales-electronicos', icon: 'qr',  show: moduleEnabled('codigos-qr') && has('canales-electronicos.index'), hint: 'Tipos de canal de cobro digital' },
                    { name: 'Tipos de Fuente',      href: '/tipos-fuente',         icon: 'list', show: moduleEnabled('codigos-qr') && has('tipos-fuente.index'), hint: 'Tipos de fuente QR' },
                    { name: 'Monedas',              href: '/monedas',              icon: 'tag',  show: moduleEnabled('codigos-qr') && has('monedas.index'), hint: 'Catálogo de monedas y tasas' },
                ],
            },
            {
                key: 'conectividad',
                label: 'Conectividad',
                icon: 'cpu',
                items: [
                    { name: 'Modos ADSL',         href: '/modos-adsl',              icon: 'cpu',  show: moduleEnabled('conectividad') && has('modos-adsl.index'),               hint: 'ED / LC / FR y otros' },
                    { name: 'Velocidades',        href: '/velocidades',             icon: 'zap',  show: moduleEnabled('conectividad') && has('velocidades.index'),               hint: 'Velocidades contratadas' },
                ],
            },
        ],
    },
    {
        label: 'Control',
        items: [
            { name: 'Usuarios',  href: '/admin/usuarios', icon: 'users',  show: has('admin.usuarios.index'),  hint: 'Accesos' },
            { name: 'Roles',     href: '/admin/roles',    icon: 'key',    show: has('admin.roles.index'),     hint: 'Permisos' },
            { name: 'Auditoria', href: '/auditoria',      icon: 'shield', show: has('auditoria.index'), hint: 'Eventos del sistema' },
            { name: 'Configuraciones', href: '/configuracion', icon: 'key', show: has('configuracion.index'), hint: 'Logo, textos de documentos y modulos' },
        ],
    },
])

const visibleGroups = computed(() =>
    menuGroups.value
        .map((group) => {
            if (group.workspace) {
                return { ...group }
            }
            if (group.subgroups) {
                const subgroups = group.subgroups
                    .map((sub) => ({ ...sub, items: sub.items.filter((i) => i.show) }))
                    .filter((sub) => sub.items.length)
                return { ...group, subgroups }
            }
            return { ...group, items: group.items.filter((item) => item.show) }
        })
        .filter((group) => {
            if (group.workspace) return workspaceNavItems.value.length > 0
            if (group.subgroups) return group.subgroups.length
            return group.items.length
        }),
)

const isActive = (href) => page.url.startsWith(href)
const currentDate = computed(() => new Intl.DateTimeFormat('es-CU', {
    dateStyle: 'full',
}).format(new Date()))

const closeMenus = () => {
    showUserMenu.value = false
    mobileSidebarOpen.value = false
}

const logout = () => router.post('/logout')

let lastFlashSignature = ''

watch(
    () => page.props.flash,
    (value) => {
        const payload = value || {}
        const entries = [
            ['success', payload.success],
            ['error', payload.error],
            ['warning', payload.warning],
            ['info', payload.info],
        ].filter(([, message]) => !!message)

        if (!entries.length) return

        const signature = JSON.stringify(entries)
        if (signature === lastFlashSignature) return
        lastFlashSignature = signature

        entries.forEach(([type, message]) => {
            if (type === 'success') notifySuccess('Operacion completada', message)
            if (type === 'error') notifyError('Se produjo un problema', message)
            if (type === 'warning') notifyWarning('Atencion', message)
            if (type === 'info') notifyInfo('Informacion', message)
        })
    },
    { deep: true, immediate: true },
)

const icons = {
    home: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    computer: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    building: 'M3.75 21h16.5M5.25 21V6.75A2.25 2.25 0 017.5 4.5h9A2.25 2.25 0 0118.75 6.75V21M9 9.75h.008v.008H9V9.75zm0 3.75h.008v.008H9V13.5zm0 3.75h.008v.008H9v-.008zm6-7.5h.008v.008H15V9.75zm0 3.75h.008v.008H15V13.5zm0 3.75h.008v.008H15v-.008z',
    folder: 'M3.75 6.75A2.25 2.25 0 016 4.5h4.19a2.25 2.25 0 011.59.659l1.061 1.06a2.25 2.25 0 001.59.66h3.57a2.25 2.25 0 012.25 2.25v6.75a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6.75z',
    shield: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    map: 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
    mapPin: 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z',
    users: 'M18 18.72a9.094 9.094 0 00-12 0M14.25 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zm7.5 8.25a9.76 9.76 0 00-4.5-2.902m-1.5-5.348a3 3 0 111.106 5.798',
    key: 'm15.75 5.25 2.25 2.25m0 0 2.25 2.25M18 7.5l-5.25 5.25m-2.25 2.25L9 16.5H6.75v2.25H4.5V21H3v-1.5l7.5-7.5m5.25-6.75a3.75 3.75 0 11-5.303 5.303 3.75 3.75 0 015.303-5.303z',
    tag: 'M9.568 3.25h6.337a2.25 2.25 0 011.591.659l2.345 2.344a2.25 2.25 0 010 3.182l-7.159 7.159a2.25 2.25 0 01-3.182 0L2.34 9.435a2.25 2.25 0 010-3.182L4.727 3.91a2.25 2.25 0 011.591-.659h3.25zM15 8.25h.008v.008H15V8.25z',
    list: 'M8.25 6.75h12m-12 5.25h12m-12 5.25h12M3.75 6.75h.008v.008H3.75V6.75zm0 5.25h.008v.008H3.75V12zm0 5.25h.008v.008H3.75v-.008z',
    cpu: 'M9 3v2.25m6-2.25v2.25M9 18.75V21m6-2.25V21M3 9h2.25m13.5 0H21M3 15h2.25m13.5 0H21M6.75 6.75h10.5v10.5H6.75V6.75z',
    flag:      'M3 3v18m0-12h12.75l-1.5 3 1.5 3H3',
    bell:      'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0018 9.75V9a6 6 0 10-12 0v.75a8.967 8.967 0 00-2.31 6.022 23.848 23.848 0 005.454 1.31m5.713 0a24.255 24.255 0 01-5.713 0m5.713 0a3 3 0 11-5.713 0',
    clipboard: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    receipt: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z',
    qr: 'M3 3h6v6H3V3zm2 2v2h2V5H5zm8-2h6v6h-6V3zm2 2v2h2V5h-2zM3 13h6v6H3v-6zm2 2v2h2v-2H5zm11 0h2v2h-2v-2zm-2 2h2v2h-2v-2zm4 0h2v2h-2v-2zm-4-4h2v2h-2v-2zm4 0h-2v2h4v-4h-2v2z',
}
</script>

<template>
    <div class="min-h-screen app-shell-bg">
        <div class="fixed inset-0 -z-10 bg-[radial-gradient(circle_at_20%_20%,rgba(34,211,238,0.16),transparent_20%),radial-gradient(circle_at_80%_0%,rgba(59,130,246,0.18),transparent_24%)] dark:bg-[radial-gradient(circle_at_20%_20%,rgba(34,211,238,0.12),transparent_16%),radial-gradient(circle_at_80%_0%,rgba(37,99,235,0.18),transparent_24%)]" />

        <div v-if="mobileSidebarOpen" class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden" @click="closeMenus" />

        <aside
            :class="[
                sidebarOpen ? 'lg:w-72' : 'lg:w-[104px]',
                mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            ]"
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-white/60 bg-white/80 px-3 py-4 shadow-2xl shadow-slate-900/5 backdrop-blur-2xl transition-all duration-300 dark:border-white/10 dark:bg-slate-950/75 dark:shadow-black/30"
        >
            <div class="mb-4 flex items-center justify-between gap-2">
                <Link href="/dashboard" class="flex min-w-0 items-center gap-2 overflow-hidden rounded-xl px-1.5 py-1.5">
                    <img
                        v-if="branding.logo_url"
                        :src="branding.logo_url"
                        alt=""
                        class="h-12 w-12 shrink-0 rounded-2xl object-contain shadow-lg ring-1 ring-slate-200/80 dark:ring-slate-700"
                    />
                    <div
                        v-else
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 via-cyan-400 to-blue-500 text-lg font-bold text-slate-950 shadow-lg shadow-cyan-500/30"
                    >
                        SG
                    </div>
                    <div v-if="sidebarOpen" class="min-w-0">
                        <p class="truncate font-display text-xl font-bold tracking-tight text-slate-950 dark:text-white">
                            {{ branding.organization_name || 'SGI' }}
                        </p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                            {{ branding.system_name || 'Infraestructura empresarial' }}
                        </p>
                    </div>
                </Link>
                <button
                    type="button"
                    class="hidden rounded-xl border border-slate-200 bg-white/70 p-2 text-slate-500 transition hover:text-slate-900 lg:block dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300 dark:hover:text-white"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15m-15 5.25h15m-15 5.25h15" />
                    </svg>
                </button>
            </div>

            <div v-if="sidebarOpen" class="surface-card-muted mb-3 p-3">
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-brand-600 dark:text-brand-300">Estado del sistema</p>
                <div class="mt-2 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">Operacion estable</p>
                        <p class="truncate text-[10px] text-slate-500 dark:text-slate-400">{{ currentDate }}</p>
                    </div>
                    <span class="app-badge border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
                        Online
                    </span>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto pr-1">
                <div v-for="group in visibleGroups" :key="group.label" class="mb-4">
                    <p v-if="sidebarOpen" class="mb-1.5 px-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">
                        {{ group.label }}
                    </p>

                    <!-- Workspace: compacto + desplegables -->
                    <div v-if="group.workspace" class="space-y-1.5">
                        <template v-for="(item, widx) in workspaceNavItems" :key="widx">
                            <Link
                                v-if="item.type === 'link'"
                                :href="item.href"
                                :title="item.hint"
                                :class="[
                                    'group flex items-center gap-3 rounded-2xl border px-3 py-3 transition-all',
                                    isActive(item.href)
                                        ? 'border-cyan-200 bg-cyan-50 text-slate-950 shadow-lg shadow-cyan-500/10 dark:border-cyan-500/20 dark:bg-cyan-500/10 dark:text-white'
                                        : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-white/70 hover:text-slate-950 dark:text-slate-300 dark:hover:border-slate-800 dark:hover:bg-slate-900/70 dark:hover:text-white',
                                    !sidebarOpen ? 'justify-center px-2' : '',
                                ]"
                                @click="mobileSidebarOpen = false"
                            >
                                <div
                                    :class="[
                                        isActive(item.href)
                                            ? 'bg-white text-brand-600 dark:bg-slate-900 dark:text-brand-300'
                                            : 'bg-slate-100 text-slate-500 group-hover:text-slate-900 dark:bg-slate-800 dark:text-slate-300',
                                    ]"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon]" />
                                    </svg>
                                </div>
                                <div v-if="sidebarOpen" class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ item.name }}</p>
                                </div>
                            </Link>

                            <div v-else-if="item.type === 'dropdown'" class="space-y-0.5">
                                <button
                                    type="button"
                                    :title="item.hint"
                                    :class="[
                                        'group flex w-full items-center gap-3 rounded-2xl border px-3 py-2.5 transition-all',
                                        isWorkspaceDropdownActive(item.key)
                                            ? 'border-cyan-200/90 bg-cyan-50/90 text-slate-950 dark:border-cyan-500/25 dark:bg-cyan-500/10 dark:text-white'
                                            : 'border-transparent hover:border-slate-200 hover:bg-white/60 dark:hover:border-slate-800 dark:hover:bg-slate-900/50',
                                        !sidebarOpen ? 'justify-center px-2' : '',
                                    ]"
                                    @click="toggleWorkspaceDrop(item.key)"
                                >
                                    <div
                                        :class="[
                                            isWorkspaceDropdownActive(item.key)
                                                ? 'bg-white text-brand-600 dark:bg-slate-900 dark:text-brand-300'
                                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                                        ]"
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon]" />
                                        </svg>
                                    </div>
                                    <div v-if="sidebarOpen" class="min-w-0 flex-1 text-left">
                                        <p class="truncate text-sm font-semibold">{{ item.name }}</p>
                                    </div>
                                    <svg
                                        v-if="sidebarOpen"
                                        :class="['h-3.5 w-3.5 shrink-0 text-slate-400 transition-transform', workspaceDropOpen[item.key] ? 'rotate-180' : '']"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        stroke-width="2"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                                <div
                                    v-show="sidebarOpen && workspaceDropOpen[item.key]"
                                    class="ml-2 space-y-0.5 border-l border-slate-200/70 pl-2 dark:border-slate-700/60"
                                >
                                    <Link
                                        v-for="sub in item.items"
                                        :key="sub.href"
                                        :href="sub.href"
                                        :title="sub.hint"
                                        :class="[
                                            'group flex items-center gap-2.5 rounded-xl border px-2.5 py-2 transition-all',
                                            isWorkspaceChildActive(sub.href)
                                                ? 'border-cyan-200 bg-cyan-50 text-slate-950 dark:border-cyan-500/20 dark:bg-cyan-500/10 dark:text-white'
                                                : 'border-transparent text-slate-500 hover:border-slate-200 hover:bg-white/70 hover:text-slate-900 dark:text-slate-400 dark:hover:border-slate-800 dark:hover:bg-slate-900/60 dark:hover:text-white',
                                        ]"
                                        @click="mobileSidebarOpen = false"
                                    >
                                        <svg
                                            class="h-5 w-5 shrink-0"
                                            :class="isWorkspaceChildActive(sub.href) ? 'text-brand-500 dark:text-brand-300' : 'text-slate-400'"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.8"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" :d="icons[sub.icon] || icons.list" />
                                        </svg>
                                        <span class="truncate text-xs font-medium">{{ sub.name }}</span>
                                    </Link>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Grupo con items directos -->
                    <div v-else-if="group.items" class="space-y-1.5">
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            :class="[
                                'group flex items-center gap-3 rounded-2xl border px-3 py-3 transition-all',
                                isActive(item.href)
                                    ? 'border-cyan-200 bg-cyan-50 text-slate-950 shadow-lg shadow-cyan-500/10 dark:border-cyan-500/20 dark:bg-cyan-500/10 dark:text-white'
                                    : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-white/70 hover:text-slate-950 dark:text-slate-300 dark:hover:border-slate-800 dark:hover:bg-slate-900/70 dark:hover:text-white',
                                !sidebarOpen ? 'justify-center px-2' : '',
                            ]"
                            @click="mobileSidebarOpen = false"
                        >
                            <div :class="[isActive(item.href) ? 'bg-white text-brand-600 dark:bg-slate-900 dark:text-brand-300' : 'bg-slate-100 text-slate-500 group-hover:text-slate-900 dark:bg-slate-800 dark:text-slate-300']" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon]" />
                                </svg>
                            </div>
                            <div v-if="sidebarOpen" class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">{{ item.name }}</p>
                                <p class="truncate text-xs text-slate-400 dark:text-slate-500">{{ item.hint }}</p>
                            </div>
                        </Link>
                    </div>

                    <!-- Grupo con subgrupos colapsables -->
                    <div v-else-if="group.subgroups" class="space-y-1">
                        <div v-for="sub in group.subgroups" :key="sub.key">
                            <!-- Botón cabecera del subgrupo -->
                            <button
                                type="button"
                                :class="[
                                    'group flex w-full items-center gap-3 rounded-2xl border px-3 py-2.5 transition-all',
                                    expandedSubgroups[sub.key]
                                        ? 'border-slate-200 bg-white/60 dark:border-slate-700/70 dark:bg-slate-900/50'
                                        : 'border-transparent hover:border-slate-200 hover:bg-white/50 dark:hover:border-slate-800 dark:hover:bg-slate-900/40',
                                    !sidebarOpen ? 'justify-center px-2' : '',
                                ]"
                                @click="toggleSubgroup(sub.key)"
                            >
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition group-hover:text-slate-900 dark:bg-slate-800 dark:text-slate-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="icons[sub.icon]" />
                                    </svg>
                                </div>
                                <div v-if="sidebarOpen" class="min-w-0 flex-1 text-left">
                                    <p class="truncate text-xs font-semibold text-slate-600 dark:text-slate-300">{{ sub.label }}</p>
                                </div>
                                <svg
                                    v-if="sidebarOpen"
                                    :class="['h-3.5 w-3.5 shrink-0 text-slate-400 transition-transform', expandedSubgroups[sub.key] ? 'rotate-180' : '']"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <!-- Items del subgrupo -->
                            <div v-show="expandedSubgroups[sub.key]" class="mt-1 ml-3 space-y-1 border-l border-slate-200/80 pl-2 dark:border-slate-700/60">
                                <Link
                                    v-for="item in sub.items.filter(i => i.show)"
                                    :key="item.href"
                                    :href="item.href"
                                    :class="[
                                        'group flex items-center gap-2.5 rounded-xl border px-2.5 py-2 transition-all',
                                        isActive(item.href)
                                            ? 'border-cyan-200 bg-cyan-50 text-slate-950 dark:border-cyan-500/20 dark:bg-cyan-500/10 dark:text-white'
                                            : 'border-transparent text-slate-500 hover:border-slate-200 hover:bg-white/70 hover:text-slate-900 dark:text-slate-400 dark:hover:border-slate-800 dark:hover:bg-slate-900/60 dark:hover:text-white',
                                    ]"
                                    @click="mobileSidebarOpen = false"
                                >
                                    <svg class="h-4 w-4 shrink-0" :class="isActive(item.href) ? 'text-brand-500 dark:text-brand-300' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon] || icons.list" />
                                    </svg>
                                    <div v-if="sidebarOpen" class="min-w-0">
                                        <p class="truncate text-xs font-medium">{{ item.name }}</p>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <div :class="[sidebarOpen ? 'lg:pl-72' : 'lg:pl-[104px]']" class="min-h-screen transition-all duration-300">
            <header class="sticky top-0 z-30 px-4 pb-4 pt-4 md:px-6 lg:px-8">
                <div class="surface-card flex items-center justify-between gap-4 px-4 py-3 md:px-5">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white/80 text-slate-500 transition hover:text-slate-900 lg:hidden dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300 dark:hover:text-white"
                            @click="mobileSidebarOpen = true"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15m-15 5.25h15m-15 5.25h15" />
                            </svg>
                        </button>

                        <div class="hidden md:block">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">SGI Workspace</p>
                            <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Gestion centralizada de activos y expedientes</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <ThemeToggle />

                        <button type="button" class="relative inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white/80 text-slate-500 transition hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300 dark:hover:text-white">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="icons.bell" />
                            </svg>
                            <span class="absolute right-2 top-2 h-2.5 w-2.5 rounded-full bg-brand-400" />
                        </button>

                        <div class="relative">
                            <button
                                type="button"
                                class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white/80 p-1.5 pr-3 text-left transition hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900/70 dark:hover:border-slate-600"
                                @click="showUserMenu = !showUserMenu"
                            >
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-900 to-slate-700 text-sm font-bold text-white dark:from-brand-400 dark:to-blue-500 dark:text-slate-950">
                                    {{ user?.name?.slice(0, 2)?.toUpperCase() || 'US' }}
                                </div>
                                <div class="hidden md:block">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ user?.name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ resolvedTheme === 'dark' ? 'Dark workspace' : 'Light workspace' }}</p>
                                </div>
                            </button>

                            <div v-if="showUserMenu" class="fixed inset-0 z-10" @click="showUserMenu = false" />
                            <div v-if="showUserMenu" class="surface-card absolute right-0 top-[calc(100%+0.75rem)] z-20 w-72 p-3">
                                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/80">
                                    <p class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ user?.name }}</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ user?.email }}</p>
                                    <span class="mt-3 inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                        {{ user?.roles?.[0] || 'Usuario' }}
                                    </span>
                                </div>
                                <Link href="/perfil" class="app-button-secondary mt-2 w-full justify-between" @click="showUserMenu = false">
                                    Mi perfil
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.118a7.5 7.5 0 0115 0A17.933 17.933 0 0112 21.75a17.933 17.933 0 01-7.5-1.632z" />
                                    </svg>
                                </Link>
                                <button class="app-button-secondary mt-2 w-full justify-between" @click="logout">
                                    Cerrar sesion
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="px-4 pb-8 md:px-6 lg:px-8">
                <slot />
            </main>
        </div>
    </div>
</template>
