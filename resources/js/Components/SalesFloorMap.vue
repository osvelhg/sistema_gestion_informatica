<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
// CSS del mapa va con el chunk del componente (no import dinámico: evita error de preload en producción)
import 'maplibre-gl/dist/maplibre-gl.css'

// Carga diferida — JS de MapLibre solo al montar
let maplibregl = null
const loadMapLibre = async () => {
    if (maplibregl) return
    const lib = await import('maplibre-gl')
    maplibregl = lib.default
}

const props = defineProps({
    points:        { type: Array, default: () => [] },
    colorClass:    { type: Function, default: () => '' },
    /** Nomenclador de tipos de red (nombre + color semántico) para la leyenda; opcional */
    networkTypes:  { type: Array, default: () => [] },
    /** false cuando el contenedor está oculto (p. ej. v-show); MapLibre debe hacer resize al mostrarse */
    mapVisible:    { type: Boolean, default: true },
})

/** Misma clave semántica que en tipos_red / badges de la tabla → hex visible sobre mapa oscuro */
const NETWORK_COLOR_HEX = {
    blue:   '#3b82f6',
    green:  '#22c55e',
    cyan:   '#06b6d4',
    yellow: '#eab308',
    violet: '#8b5cf6',
    slate:  '#64748b',
    red:    '#ef4444',
    orange: '#f97316',
}

const hexForNetworkColor = (key) => {
    if (!key || typeof key !== 'string') return '#94a3b8'
    return NETWORK_COLOR_HEX[key.toLowerCase()] ?? '#94a3b8'
}

const CUBA_CENTER = [-79.5, 22.0]
const CUBA_ZOOM     = 6.5

const mapRef      = ref(null)
const activeStyle = ref('dark')
let map           = null
let markers       = []

// ── Estilos de mapa disponibles (CartoCDN, sin API key) ───────────────────────
const mapStyles = [
    {
        id:    'dark',
        label: 'Oscuro',
        url:   'https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json',
    },
    {
        id:    'light',
        label: 'Claro',
        url:   'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json',
    },
    {
        id:    'sat',
        label: 'Satélite',
        url:   'https://basemaps.cartocdn.com/gl/voyager-gl-style/style.json',
    },
]

const setStyle = (style) => {
    activeStyle.value = style.id
    map?.setStyle(style.url)
    // Re-pintar marcadores tras el cambio de estilo
    map?.once('styledata', renderMarkers)
}

// ── Helpers de coordenadas ────────────────────────────────────────────────────

const toLat = (val) => {
    if (val === null || val === undefined || val === '') return null
    const n = parseFloat(String(val).replace(',', '.'))
    return isFinite(n) && Math.abs(n) <= 90 ? n : null
}

const toLon = (val) => {
    if (val === null || val === undefined || val === '') return null
    const n = parseFloat(String(val).replace(',', '.'))
    return isFinite(n) && Math.abs(n) <= 180 ? n : null
}

const validPoints = () =>
    props.points.filter(p => toLat(p.latitude) !== null && toLon(p.longitude) !== null)

/** Leyenda: tipos de red que tienen al menos un PV en el mapa (orden del nomenclador si viene) */
const legendNetworkTypes = computed(() => {
    const pts   = validPoints()
    const onMap = new Set(pts.map(p => p.network_type?.id).filter(id => id != null))

    if (props.networkTypes?.length) {
        const listed = props.networkTypes.filter(nt => nt?.name && onMap.has(nt.id))
        if (listed.length) return listed
    }
    const seen = new Map()
    for (const p of pts) {
        const nt = p.network_type
        if (nt?.id != null && !seen.has(nt.id)) seen.set(nt.id, nt)
    }
    return [...seen.values()].sort((a, b) => String(a.name).localeCompare(String(b.name), 'es'))
})

const hasUnclassifiedNetwork = computed(() =>
    validPoints().some(p => !p.network_type?.id)
)

// ── Color por tipo de red comercial (campo color en tipos_red) ─────────────────

const markerColor = (point) => hexForNetworkColor(point.network_type?.color)

// ── HTML del popup ────────────────────────────────────────────────────────────

const buildPopupHtml = (point) => {
    const network = point.network_type?.name         ?? '—'
    const status  = point.establishment_status?.name ?? '—'
    const type    = point.establishment_type?.name   ?? '—'
    const entCode = point.entity?.code               ?? '—'
    const esc     = (s) =>
        String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
    const title = esc(point.name)
    const sub   = esc(point.address || '')
    return `
        <div class="sgi-map-popup-inner" style="font-family:system-ui,sans-serif;min-width:210px;padding:4px 0;background:#fff;color:#0f172a">
            <p style="font-weight:700;font-size:13px;margin:0 0 3px;color:#0f172a">${title}</p>
            <p style="font-size:11px;color:#64748b;margin:0 0 7px">${sub || '—'}</p>
            <table style="font-size:11px;border-collapse:collapse;width:100%;color:#0f172a">
                <tr><td style="color:#64748b;padding:2px 8px 2px 0;white-space:nowrap">Red</td>
                    <td style="font-weight:600;color:#334155">${esc(network)}</td></tr>
                <tr><td style="color:#64748b;padding:2px 8px 2px 0;white-space:nowrap">Tipo</td>
                    <td style="font-weight:600;color:#334155">${esc(type)}</td></tr>
                <tr><td style="color:#64748b;padding:2px 8px 2px 0;white-space:nowrap">Estado</td>
                    <td style="font-weight:600;color:#334155">${esc(status)}</td></tr>
                <tr><td style="color:#64748b;padding:2px 8px 2px 0;white-space:nowrap">Cód. entidad</td>
                    <td style="font-weight:600;color:#334155;font-family:ui-monospace,monospace">${esc(entCode)}</td></tr>
            </table>
        </div>`
}

// ── Marcadores ────────────────────────────────────────────────────────────────

const clearMarkers = () => {
    markers.forEach(m => m.remove())
    markers = []
}

const renderMarkers = () => {
    if (!map) return
    clearMarkers()

    const pts = validPoints()

    pts.forEach(point => {
        const lat   = toLat(point.latitude)
        const lon   = toLon(point.longitude)
        const color = markerColor(point)

        // MapLibre usa style.transform en el nodo raíz del marcador para proyectar coords.
        // Nunca modificar ese transform: el hover va en un hijo.
        const root = document.createElement('div')
        root.style.cssText = 'display:flex;align-items:center;justify-content:center;width:28px;height:28px;cursor:pointer;pointer-events:auto'
        const dot = document.createElement('div')
        dot.style.cssText = [
            'width:14px', 'height:14px', 'border-radius:50%',
            `background:${color}`, 'border:2.5px solid white',
            'box-shadow:0 1px 5px rgba(0,0,0,.5)',
            'transition:transform .15s ease',
        ].join(';')
        dot.addEventListener('mouseenter', () => { dot.style.transform = 'scale(1.45)' })
        dot.addEventListener('mouseleave', () => { dot.style.transform = 'scale(1)' })
        root.appendChild(dot)

        const popup = new maplibregl.Popup({
            offset:    12,
            maxWidth:  '290px',
            className: 'sgi-map-popup',
        }).setHTML(buildPopupHtml(point))

        const marker = new maplibregl.Marker({ element: root, anchor: 'center' })
            .setLngLat([lon, lat])
            .setPopup(popup)
            .addTo(map)

        markers.push(marker)
    })

    // Sin puntos válidos: vista fija sobre Cuba (no fitBounds con bounds vacíos / mal dimensionado)
    if (pts.length === 0) {
        map.jumpTo({ center: CUBA_CENTER, zoom: CUBA_ZOOM })
        return
    }

    if (pts.length === 1) {
        map.flyTo({ center: [toLon(pts[0].longitude), toLat(pts[0].latitude)], zoom: 13 })
        return
    }

    const bounds = pts.reduce(
        (b, p) => b.extend([toLon(p.longitude), toLat(p.latitude)]),
        new maplibregl.LngLatBounds(
            [toLon(pts[0].longitude), toLat(pts[0].latitude)],
            [toLon(pts[0].longitude), toLat(pts[0].latitude)]
        )
    )
    map.fitBounds(bounds, { padding: 60, maxZoom: 14, duration: 800 })
}

// ── Ciclo de vida ─────────────────────────────────────────────────────────────

onMounted(async () => {
    await loadMapLibre()
    await nextTick()

    map = new maplibregl.Map({
        container: mapRef.value,
        style:     mapStyles.find(s => s.id === activeStyle.value).url,
        center:    CUBA_CENTER,
        zoom:      CUBA_ZOOM,
        attributionControl: false,
    })

    map.addControl(new maplibregl.NavigationControl(), 'top-right')
    map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left')
    map.addControl(
        new maplibregl.AttributionControl({ compact: true }),
        'bottom-right'
    )

    map.on('load', () => {
        renderMarkers()
        nextTick(() => {
            if (props.mapVisible) map.resize()
        })
    })
})

onBeforeUnmount(() => {
    clearMarkers()
    map?.remove()
    map = null
})

watch(() => props.points, () => {
    if (map?.isStyleLoaded()) renderMarkers()
    else map?.once('load', renderMarkers)
}, { deep: true })

watch(() => props.mapVisible, (visible) => {
    if (!visible || !map) return
    nextTick(() => {
        map.resize()
        if (map.isStyleLoaded()) renderMarkers()
    })
})
</script>

<template>
    <div class="surface-card overflow-hidden">
        <!-- Encabezado + leyenda + selector de estilo -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-3 dark:border-slate-700/70">
            <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Mapa de puntos de venta</p>
                <p class="text-xs text-slate-400 dark:text-slate-500">
                    {{ validPoints().length }} de {{ points.length }} con coordenadas ·
                    <span class="text-slate-500 dark:text-slate-400">Color = tipo de red</span>
                    <template v-for="nt in legendNetworkTypes" :key="nt.id ?? nt.name">
                        <span class="ml-2 inline-flex items-center gap-1">
                            <span
                                class="inline-block h-2 w-2 shrink-0 rounded-full ring-1 ring-white/40"
                                :style="{ background: hexForNetworkColor(nt.color) }"
                            />
                            {{ nt.name }}
                        </span>
                    </template>
                    <span v-if="hasUnclassifiedNetwork" class="ml-2 inline-flex items-center gap-1">
                        <span
                            class="inline-block h-2 w-2 shrink-0 rounded-full ring-1 ring-white/40"
                            :style="{ background: hexForNetworkColor(null) }"
                        />
                        Sin clasificar
                    </span>
                    <span v-if="!legendNetworkTypes.length && !hasUnclassifiedNetwork" class="ml-1 text-slate-500">(sin tipos en datos)</span>
                </p>
            </div>

            <!-- Selector de estilo de fondo -->
            <div class="flex gap-1">
                <button
                    v-for="style in mapStyles"
                    :key="style.id"
                    type="button"
                    class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
                    :class="activeStyle === style.id
                        ? 'bg-indigo-600 text-white shadow'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700/70 dark:text-slate-300 dark:hover:bg-slate-600'"
                    @click="setStyle(style)"
                >
                    {{ style.label }}
                </button>
            </div>
        </div>

        <!-- Contenedor del mapa -->
        <div ref="mapRef" style="height: 520px; width: 100%;" />
    </div>
</template>

<!-- Sin scoped: el popup se monta en el body y el dark mode global afecta .maplibregl-popup-content -->
<style>
.maplibregl-popup.sgi-map-popup .maplibregl-popup-content {
    background: #ffffff !important;
    color: #0f172a !important;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.2) !important;
}
.maplibregl-popup.sgi-map-popup .maplibregl-popup-close-button {
    color: #475569 !important;
    font-size: 20px;
    padding: 4px 8px;
}
</style>
