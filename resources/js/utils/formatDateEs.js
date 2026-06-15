/**
 * Fechas desde Laravel (Carbon) suelen llegar como ISO 8601 completo.
 * No concatenar 'T00:00:00' a esos strings (rompe el parseo en JS).
 */
export function parseDateValue(v) {
    if (v == null || v === '') return null
    const s = String(v).trim()
    if (!s) return null
    const d = new Date(s)
    return Number.isNaN(d.getTime()) ? null : d
}

/** Fecha corta es-CU o em dash si no es válida */
export function formatDateEs(v) {
    const d = parseDateValue(v)
    if (!d) return '—'
    return d.toLocaleDateString('es-CU', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
