<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import PageHeader from '@/Components/PageHeader.vue'

const selectedFloor = ref(null)
const floorOptions = ref([])
const floorLoading = ref(false)
const payload = ref(null)
const loadError = ref('')

const analyzeCidr = ref('')
const analyzeLoading = ref(false)
const analyzeResult = ref(null)
const analyzeError = ref('')

const pingTarget = ref('')
const pingLoading = ref(false)
const pingOk = ref(null)
const pingMessage = ref('')
const pingOutput = ref('')

async function searchFloors(query, loading) {
    if (!query || query.length < 1) return
    loading(true)
    try {
        const { data } = await axios.get(route('pisos-venta.search'), {
            params: { q: query, entity_code_labels: 1 },
        })
        floorOptions.value = (data.floors || []).map(f => ({ id: f.id, label: f.label || f.name }))
    } catch {
        floorOptions.value = []
    } finally {
        loading(false)
    }
}

watch(selectedFloor, async (v) => {
    payload.value = null
    loadError.value = ''
    analyzeResult.value = null
    analyzeError.value = ''
    pingMessage.value = ''
    pingOutput.value = ''
    pingOk.value = null
    pingTarget.value = ''
    const id = v?.id
    if (!id) return
    try {
        const { data } = await axios.get(route('conectividad.comprobaciones.registro'), {
            params: { sales_floor_id: id },
        })
        if (data.success) {
            payload.value = data
        } else {
            loadError.value = data.message || 'No se pudo cargar el registro.'
        }
    } catch (e) {
        loadError.value = e.response?.data?.message || 'Error al cargar datos del piso.'
    }
})

async function runAnalyze() {
    analyzeError.value = ''
    analyzeResult.value = null
    const c = (analyzeCidr.value || '').trim()
    if (!c) {
        analyzeError.value = 'Indique un CIDR válido (ej. 10.0.0.0/24).'
        return
    }
    analyzeLoading.value = true
    try {
        const { data } = await axios.post(route('conectividad.red.analizar'), { cidr: c })
        if (data.success && data.data) {
            analyzeResult.value = data.data
        } else {
            analyzeError.value = data.message || 'No se pudo analizar.'
        }
    } catch (e) {
        analyzeError.value = e.response?.data?.message || 'Error al analizar.'
    } finally {
        analyzeLoading.value = false
    }
}

function prefillAnalyze(cidr) {
    if (cidr) {
        analyzeCidr.value = cidr
        runAnalyze()
    }
}

async function runPing() {
    pingMessage.value = ''
    pingOutput.value = ''
    pingOk.value = null
    const rec = payload.value?.record
    if (!rec?.id) {
        pingMessage.value = 'Este piso no tiene registro de conectividad; no se puede hacer ping contextualizado.'
        pingOk.value = false
        return
    }
    const ip = (pingTarget.value || '').trim()
    if (!ip) {
        pingMessage.value = 'Indique la IPv4.'
        pingOk.value = false
        return
    }
    pingLoading.value = true
    try {
        const extraCidr = (analyzeCidr.value || '').trim()
        const { data } = await axios.post(route('conectividad.red.ping'), {
            conectividade_id: rec.id,
            target_ip: ip,
            additional_cidr: extraCidr || undefined,
        })
        pingOk.value = data.ok === true
        pingMessage.value = data.message || ''
        pingOutput.value = data.output || ''
    } catch (e) {
        pingOk.value = false
        pingMessage.value = e.response?.data?.message || 'Error al ejecutar ping.'
        pingOutput.value = e.response?.data?.output || ''
    } finally {
        pingLoading.value = false
    }
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl space-y-6">
            <PageHeader
                eyebrow="Conectividad"
                title="Comprobaciones"
                description="Seleccione un piso de venta: analice segmentos WAN/LAN (CIDR) y ejecute ping ICMP permitido dentro de esos segmentos o hacia las IPs guardadas."
            />

            <div class="grid gap-6 lg:grid-cols-2">
                <BaseCard>
                    <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-200">Piso de venta</h2>
                    <VSelect
                        v-model="selectedFloor"
                        :options="floorOptions"
                        :filterable="false"
                        :loading="floorLoading"
                        placeholder="Buscar por entidad, nombre de PV, código…"
                        :clearable="true"
                        @search="searchFloors"
                    >
                        <template #no-options="{ search: q, searching }">
                            <span v-if="searching" class="text-sm text-slate-400">Sin resultados para "{{ q }}"</span>
                            <span v-else class="text-sm text-slate-400">Escriba para buscar…</span>
                        </template>
                    </VSelect>
                    <p v-if="loadError" class="mt-3 text-sm text-rose-600 dark:text-rose-400">{{ loadError }}</p>

                    <div v-if="payload?.sales_floor" class="mt-5 space-y-3 rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 text-sm dark:border-slate-700 dark:bg-slate-900/40">
                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ payload.sales_floor.name }}</p>
                        <p v-if="payload.sales_floor.entity" class="text-slate-600 dark:text-slate-300">
                            <span class="font-mono text-xs text-slate-500">{{ payload.sales_floor.entity.code }}</span>
                            {{ payload.sales_floor.entity.name }}
                        </p>
                        <template v-if="payload.record">
                            <div class="border-t border-slate-200/70 pt-3 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-400">
                                <p><span class="font-semibold">WAN:</span> <span class="font-mono">{{ payload.record.wan_cidr || payload.record.ip_wan || '—' }}</span></p>
                                <p><span class="font-semibold">LAN:</span> <span class="font-mono">{{ payload.record.lan_cidr || payload.record.ip_lan || '—' }}</span></p>
                                <p class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-if="payload.record.wan_cidr"
                                        type="button"
                                        class="app-button-secondary px-2 py-1 text-xs"
                                        @click="prefillAnalyze(payload.record.wan_cidr)"
                                    >
                                        Analizar WAN
                                    </button>
                                    <button
                                        v-if="payload.record.lan_cidr"
                                        type="button"
                                        class="app-button-secondary px-2 py-1 text-xs"
                                        @click="prefillAnalyze(payload.record.lan_cidr)"
                                    >
                                        Analizar LAN
                                    </button>
                                </p>
                            </div>
                        </template>
                        <p v-else class="border-t border-dashed border-slate-200 pt-3 text-xs text-amber-700 dark:border-slate-700 dark:text-amber-400">
                            No hay registro de conectividad para este piso.
                        </p>
                    </div>
                </BaseCard>

                <BaseCard>
                    <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-200">Cálculo de subred y ping</h2>
                    <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                        El ping solo admite destinos en los CIDR WAN/LAN guardados, segmentos inferidos /24 desde la IP WAN/LAN si no hay CIDR, la IP usada en «CIDR a analizar» de esta pantalla, o las IPs WAN/LAN exactas.
                    </p>

                    <div class="space-y-3 rounded-xl border border-slate-200/80 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">CIDR a analizar</label>
                        <div class="flex flex-wrap gap-2">
                            <input v-model="analyzeCidr" type="text" class="app-input min-w-[12rem] flex-1 font-mono text-sm" placeholder="x.x.x.x/prefijo" @keyup.enter="runAnalyze" />
                            <button type="button" class="app-button-secondary shrink-0" :disabled="analyzeLoading" @click="runAnalyze">
                                {{ analyzeLoading ? '…' : 'Calcular' }}
                            </button>
                        </div>
                        <p v-if="analyzeError" class="text-xs text-rose-600 dark:text-rose-400">{{ analyzeError }}</p>
                        <dl v-if="analyzeResult" class="space-y-1 text-xs text-slate-700 dark:text-slate-300">
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Red</dt><dd class="font-mono">{{ analyzeResult.network_address }}/{{ analyzeResult.prefix }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Broadcast</dt><dd class="font-mono">{{ analyzeResult.broadcast_address }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Máscara</dt><dd class="font-mono">{{ analyzeResult.netmask }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Hosts útiles</dt><dd class="font-mono">{{ analyzeResult.usable_hosts }}</dd></div>
                            <div v-if="analyzeResult.first_host" class="flex justify-between gap-2">
                                <dt class="text-slate-500">Rango útil</dt>
                                <dd class="font-mono">{{ analyzeResult.first_host }} – {{ analyzeResult.last_host }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="mt-5 space-y-3 rounded-xl border border-slate-200/80 p-4 dark:border-slate-700">
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Ping ICMP (IPv4)</label>
                        <div class="flex flex-wrap gap-2">
                            <input v-model="pingTarget" type="text" class="app-input min-w-[10rem] flex-1 font-mono text-sm" placeholder="IP destino" @keyup.enter="runPing" />
                            <button type="button" class="app-button-primary shrink-0" :disabled="pingLoading" @click="runPing">
                                {{ pingLoading ? 'Ping…' : 'Ping' }}
                            </button>
                        </div>
                        <p
                            v-if="pingMessage"
                            class="text-sm"
                            :class="pingOk ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'"
                        >
                            {{ pingMessage }}
                        </p>
                        <pre v-if="pingOutput" class="max-h-40 overflow-auto rounded-lg bg-slate-900 p-3 font-mono text-[11px] text-slate-100">{{ pingOutput }}</pre>
                    </div>
                </BaseCard>
            </div>
        </div>
    </AppLayout>
</template>
