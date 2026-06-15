<script setup>
import { computed, ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import BaseCard from '@/Components/BaseCard.vue'
import PageHeader from '@/Components/PageHeader.vue'

const page = usePage()
const props = defineProps({
    appearance: Object,
    logo_url: { type: String, default: null },
    modules: Array,
    ldap: Object,
    external_ci: Object,
    external_entities_pg: Object,
    external_entity_db: Object,
    external_almacenes: Object,
    entities_for_sync: Array,
})

const activeTab = ref('apariencia')
const activeIntegrationTab = ref('ldap')

/** Subir/quitar logo: requiere modulos.editar */
const canEditLogo = computed(() => {
    const roles = page.props.auth?.user?.roles || []
    const perms = page.props.auth?.permissions || []
    return roles.includes('Administrador') || perms.includes('configuracion.logo')
})

/** Textos encabezado/pie (PDF y Excel): modulos.ver */
const canEditAppearance = computed(() => {
    const roles = page.props.auth?.user?.roles || []
    const perms = page.props.auth?.permissions || []
    return roles.includes('Administrador') || perms.includes('configuracion.appearance')
})

/** Modulos on/off: modulos.ver */
const canToggleModules = computed(() => {
    const roles = page.props.auth?.user?.roles || []
    const perms = page.props.auth?.permissions || []
    return roles.includes('Administrador') || perms.includes('modulos.update')
})

// ── Apariencia ──────────────────────────────────────────────────────────────

const appearanceForm = useForm({
    organization_name: props.appearance?.organization_name ?? '',
    system_name: props.appearance?.system_name ?? '',
    header_title: props.appearance?.header_title ?? '',
    footer_left: props.appearance?.footer_left ?? '',
    footer_right: props.appearance?.footer_right ?? '',
})

const saveAppearance = () => {
    appearanceForm.put('/configuracion/apariencia', { preserveScroll: true })
}

const logoForm = useForm({ logo: null })
const onLogoFile = (e) => {
    logoForm.logo = e.target.files?.[0] ?? null
}
const uploadLogo = () => {
    if (!logoForm.logo) return
    logoForm.post('/configuracion/logo', { forceFormData: true, preserveScroll: true })
}

const removeLogo = () => {
    router.delete('/configuracion/logo', { preserveScroll: true })
}

// ── Módulos ──────────────────────────────────────────────────────────────────

const moduleForm = useForm({ enabled: false })

const toggleModule = (module) => {
    moduleForm.enabled = !module.enabled
    moduleForm.put(`/configuracion/modulos/${module.id}`, { preserveScroll: true })
}

// ── LDAP / Directorio Activo ─────────────────────────────────────────────────

const showPasswordInput = ref(false)

const ldapForm = useForm({
    enabled: props.ldap?.enabled ?? false,
    host: props.ldap?.host ?? '',
    port: props.ldap?.port ?? 389,
    base_dn: props.ldap?.base_dn ?? '',
    bind_username: props.ldap?.bind_username ?? '',
    bind_password: '',
    use_ssl: props.ldap?.use_ssl ?? false,
    use_tls: props.ldap?.use_tls ?? false,
    timeout: props.ldap?.timeout ?? 5,
    user_search_base: props.ldap?.user_search_base ?? '',
})

const saveLdap = () => {
    ldapForm.put('/configuracion/ldap', { preserveScroll: true, onSuccess: () => {
        showPasswordInput.value = false
        ldapForm.bind_password = ''
    }})
}

// Test connection
const testResult = ref(null)
const testLoading = ref(false)

const testLdap = async () => {
    testResult.value = null
    testLoading.value = true
    try {
        const { data } = await axios.post('/configuracion/ldap/test', {
            host: ldapForm.host,
            port: ldapForm.port,
            base_dn: ldapForm.base_dn,
            bind_username: ldapForm.bind_username,
            bind_password: ldapForm.bind_password || undefined,
            use_ssl: ldapForm.use_ssl,
            use_tls: ldapForm.use_tls,
            timeout: ldapForm.timeout,
        })
        testResult.value = data
    } catch (e) {
        testResult.value = { success: false, message: e.response?.data?.message || 'Error al conectar.' }
    } finally {
        testLoading.value = false
    }
}

// User search
const ldapSearch = ref('')
const ldapUsers = ref([])
const ldapSearchLoading = ref(false)
const ldapSearchError = ref(null)

const searchLdap = async () => {
    if (ldapSearch.value.trim().length < 2) return
    ldapUsers.value = []
    ldapSearchError.value = null
    ldapSearchLoading.value = true
    try {
        const { data } = await axios.post('/configuracion/ldap/search', { query: ldapSearch.value })
        ldapUsers.value = data.users || []
        // Diagnóstico LDAP (servidor): con APP_DEBUG=true la API devuelve ldap_debug; siempre logueamos la respuesta de búsqueda.
        console.info('[LDAP búsqueda] respuesta', data)
        if (data.ldap_debug) {
            console.info('[LDAP búsqueda] diagnóstico (APP_DEBUG)', data.ldap_debug)
        }
        if (!ldapUsers.value.length && data.ldap_debug) {
            console.warn('[LDAP búsqueda] 0 resultados. Revisa base_dn_effective / user_search_base y filter_approx en el objeto anterior.')
        }
    } catch (e) {
        ldapSearchError.value = e.response?.data?.message || 'Error al buscar usuarios.'
    } finally {
        ldapSearchLoading.value = false
    }
}

// ── SQL Server / Consulta CI Externa ─────────────────────────────────────────
const showExtCiPasswordInput = ref(false)
const externalCiForm = useForm({
    enabled: props.external_ci?.enabled ?? false,
    odbc_dsn: props.external_ci?.odbc_dsn ?? '',
    host: props.external_ci?.host ?? '',
    port: props.external_ci?.port ?? 1433,
    database_name: props.external_ci?.database_name ?? '',
    username: props.external_ci?.username ?? '',
    password: '',
    table_name: props.external_ci?.table_name ?? 'TRABAJADOR',
    ci_column: props.external_ci?.ci_column ?? 'UT_ID',
    nombre_column: props.external_ci?.nombre_column ?? 'UT_NOMBRE',
    apellido1_column: props.external_ci?.apellido1_column ?? 'UT_APELLIDO1',
    apellido2_column: props.external_ci?.apellido2_column ?? 'UT_APELLIDO2',
    telefono_column: props.external_ci?.telefono_column ?? 'UT_TELEFONO',
    direccion_columns: props.external_ci?.direccion_columns ?? ['UT_CALLE', 'UT_NO', 'UT_APTO', 'UT_ETRE', 'UT_RPTO'],
    timeout: props.external_ci?.timeout ?? 5,
})

const extCiTestResult = ref(null)
const extCiTestLoading = ref(false)
const extCiColumns = ref([])
const extCiColumnsLoading = ref(false)
const showMapModal = ref(false)
const mappingDraft = ref({
    ci_column: '',
    nombre_column: '',
    apellido1_column: '',
    apellido2_column: '',
    telefono_column: '',
    direccion_columns: [],
})

function openMapModal() {
    mappingDraft.value = {
        ci_column: externalCiForm.ci_column,
        nombre_column: externalCiForm.nombre_column,
        apellido1_column: externalCiForm.apellido1_column,
        apellido2_column: externalCiForm.apellido2_column,
        telefono_column: externalCiForm.telefono_column,
        direccion_columns: [...(externalCiForm.direccion_columns || [])],
    }
    showMapModal.value = true
}

function applyColumnMapping() {
    externalCiForm.ci_column = mappingDraft.value.ci_column
    externalCiForm.nombre_column = mappingDraft.value.nombre_column
    externalCiForm.apellido1_column = mappingDraft.value.apellido1_column
    externalCiForm.apellido2_column = mappingDraft.value.apellido2_column
    externalCiForm.telefono_column = mappingDraft.value.telefono_column
    externalCiForm.direccion_columns = [...mappingDraft.value.direccion_columns]
    showMapModal.value = false
}

const saveExternalCi = () => {
    externalCiForm.put('/configuracion/external-ci', {
        preserveScroll: true,
        onSuccess: () => {
            showExtCiPasswordInput.value = false
            externalCiForm.password = ''
        },
    })
}

const testExternalCi = async () => {
    extCiTestResult.value = null
    extCiTestLoading.value = true
    try {
        const { data } = await axios.post('/configuracion/external-ci/test', {
            enabled: externalCiForm.enabled,
            odbc_dsn: externalCiForm.odbc_dsn || undefined,
            host: externalCiForm.host,
            port: externalCiForm.port,
            database_name: externalCiForm.database_name,
            username: externalCiForm.username,
            password: externalCiForm.password || undefined,
            table_name: externalCiForm.table_name,
            ci_column: externalCiForm.ci_column,
            nombre_column: externalCiForm.nombre_column,
            apellido1_column: externalCiForm.apellido1_column,
            apellido2_column: externalCiForm.apellido2_column,
            telefono_column: externalCiForm.telefono_column,
            direccion_columns: externalCiForm.direccion_columns,
            timeout: externalCiForm.timeout,
        })
        extCiTestResult.value = data
    } catch (e) {
        extCiTestResult.value = { success: false, message: e.response?.data?.message || 'Error al conectar.' }
    } finally {
        extCiTestLoading.value = false
    }
}

const loadExternalCiColumns = async () => {
    extCiColumnsLoading.value = true
    try {
        const { data } = await axios.post('/configuracion/external-ci/columns', {
            odbc_dsn: externalCiForm.odbc_dsn || undefined,
            host: externalCiForm.host,
            port: externalCiForm.port,
            database_name: externalCiForm.database_name,
            username: externalCiForm.username,
            password: externalCiForm.password || undefined,
            table_name: externalCiForm.table_name,
            timeout: externalCiForm.timeout,
        })
        if (data.success) {
            extCiColumns.value = data.columns || []
        } else {
            extCiColumns.value = []
            extCiTestResult.value = { success: false, message: data.message || 'No se pudieron cargar columnas.' }
        }
    } catch (e) {
        extCiColumns.value = []
        extCiTestResult.value = { success: false, message: e.response?.data?.message || 'Error al cargar columnas.' }
    } finally {
        extCiColumnsLoading.value = false
    }
}

// ── BD externa / sincronización de Provincias, Municipios y Entidades ────────
const showExtPgPasswordInput = ref(false)

function defaultPortForDriver(driver) {
    if (driver === 'mysql' || driver === 'mariadb') return 3306
    if (driver === 'sqlsrv') return 1433
    return 5432
}

const externalEntitiesPgForm = useForm({
    driver: props.external_entities_pg?.driver ?? 'pgsql',
    enabled: props.external_entities_pg?.enabled ?? false,
    host: props.external_entities_pg?.host ?? '',
    port: props.external_entities_pg?.port ?? 5432,
    database_name: props.external_entities_pg?.database_name ?? '',
    schema_name: props.external_entities_pg?.schema_name ?? 'public',
    username: props.external_entities_pg?.username ?? '',
    password: '',
    table_name: props.external_entities_pg?.table_name ?? 'entities',
    name_column: props.external_entities_pg?.name_column ?? 'name',
    code_column: props.external_entities_pg?.code_column ?? 'code',
    municipio_code_column: props.external_entities_pg?.municipio_code_column ?? '',
    provincia_column: props.external_entities_pg?.provincia_column ?? '',
    timeout: props.external_entities_pg?.timeout ?? 5,
})

// Actualiza el puerto por defecto cuando cambia el driver
watch(
    () => externalEntitiesPgForm.driver,
    (driver) => {
        const knownDefaults = [5432, 3306, 1433]
        if (!externalEntitiesPgForm.port || knownDefaults.includes(externalEntitiesPgForm.port)) {
            externalEntitiesPgForm.port = defaultPortForDriver(driver)
        }
    }
)

function pickFirstPgColumn(columns, candidates) {
    if (!Array.isArray(columns) || !columns.length) return ''
    const map = new Map(columns.map((c) => [String(c).toLowerCase(), c]))
    for (const cand of candidates) {
        const v = map.get(String(cand).toLowerCase())
        if (v) return v
    }
    return ''
}

/** Preset de mapeo solo en UI: cu | en | mun_cu | prov_cu | manual */
function inferExtPgMappingPreset(m) {
    const t = m?.target || 'entity'
    const hasAny =
        (m?.name_column || '') +
        (m?.code_column || '') +
        (m?.municipio_code_column || '') +
        (m?.provincia_code_column || '')
    if (!hasAny) {
        if (t === 'entity') return 'cu'
        if (t === 'municipio') return 'mun_cu'
        if (t === 'provincia') return 'prov_cu'
    }
    if (t === 'provincia') {
        const n = (m?.name_column || '').toLowerCase()
        if (n === 'nombre' || n === 'name') return 'prov_cu'
        return 'manual'
    }
    if (t === 'municipio') {
        const p = (m?.provincia_code_column || '').toLowerCase()
        if (p === 'provincia' || p === 'prov' || p === 'cod_provincia') return 'mun_cu'
        return 'manual'
    }
    const c = (m?.code_column || '').toLowerCase()
    if (c === 'codigo') return 'cu'
    if (c === 'code') return 'en'
    return 'manual'
}

function extPgPresetSelectOptions(target) {
    if (target === 'entity') {
        return [
            { value: 'cu', label: 'codigo · nombre · municipio · provincia' },
            { value: 'en', label: 'code · name · municipality · province' },
            { value: 'manual', label: 'Personalizado (escribir cada columna)' },
        ]
    }
    if (target === 'municipio') {
        return [
            { value: 'mun_cu', label: 'nombre · codigo · provincia' },
            { value: 'manual', label: 'Personalizado' },
        ]
    }
    return [
        { value: 'prov_cu', label: 'nombre · codigo · sigla_2 · sigla_3' },
        { value: 'manual', label: 'Personalizado' },
    ]
}

function applyExtPgMappingPreset(idx) {
    const row = extPgTableMappings.value[idx]
    if (!row || row.mapping_preset === 'manual') return
    const cols = row._columns || []
    if (row.target === 'entity') {
        if (row.mapping_preset === 'cu') {
            row.code_column = pickFirstPgColumn(cols, ['codigo', 'code'])
            row.name_column = pickFirstPgColumn(cols, ['nombre', 'name', 'denominacion'])
            row.municipio_code_column = pickFirstPgColumn(cols, ['municipio', 'mun'])
            row.provincia_code_column = pickFirstPgColumn(cols, ['provincia', 'prov'])
        } else if (row.mapping_preset === 'en') {
            row.code_column = pickFirstPgColumn(cols, ['code', 'codigo'])
            row.name_column = pickFirstPgColumn(cols, ['name', 'nombre'])
            row.municipio_code_column = pickFirstPgColumn(cols, ['municipality', 'municipio', 'mun'])
            row.provincia_code_column = pickFirstPgColumn(cols, ['province', 'provincia'])
        }
    } else if (row.target === 'municipio' && row.mapping_preset === 'mun_cu') {
        row.name_column = pickFirstPgColumn(cols, ['nombre', 'name'])
        row.code_column = pickFirstPgColumn(cols, ['codigo', 'code'])
        row.provincia_code_column = pickFirstPgColumn(cols, ['provincia', 'prov', 'cod_provincia', 'provincia_codigo'])
    } else if (row.target === 'provincia' && row.mapping_preset === 'prov_cu') {
        row.name_column = pickFirstPgColumn(cols, ['nombre', 'name'])
        row.code_column = pickFirstPgColumn(cols, ['codigo', 'code'])
        row.sigla_2_column = pickFirstPgColumn(cols, ['sigla_2', 'sigla2', 'sigla_ii'])
        row.sigla_3_column = pickFirstPgColumn(cols, ['sigla_3', 'sigla3', 'sigla_iii'])
    }
}

function onExtPgMappingTargetChange(idx) {
    const row = extPgTableMappings.value[idx]
    if (row.target === 'entity') {
        row.mapping_preset = 'cu'
    } else if (row.target === 'municipio') {
        row.mapping_preset = 'mun_cu'
    } else {
        row.mapping_preset = 'prov_cu'
    }
    if (row._columns?.length) applyExtPgMappingPreset(idx)
}

function normalizeExtPgMappingRow(m) {
    return {
        target: m?.target || 'entity',
        schema_name: m?.schema_name || '',
        table_name: m?.table_name || '',
        name_column: m?.name_column || '',
        code_column: m?.code_column || '',
        municipio_code_column: m?.municipio_code_column || '',
        provincia_code_column: m?.provincia_code_column || '',
        sigla_2_column: m?.sigla_2_column || '',
        sigla_3_column: m?.sigla_3_column || '',
        mapping_preset: inferExtPgMappingPreset(m),
        _columns: [],
        _columnsLoading: false,
    }
}

const extPgAdvanced = ref((props.external_entities_pg?.table_mappings?.length ?? 0) > 0)
const extPgTableMappings = ref(
    (props.external_entities_pg?.table_mappings?.length
        ? props.external_entities_pg.table_mappings
        : []).map(normalizeExtPgMappingRow),
)
const extPgTablesList = ref([])
const extPgTablesLoading = ref(false)

watch(
    () => props.external_entities_pg?.table_mappings,
    (tm) => {
        extPgTableMappings.value = (tm || []).map(normalizeExtPgMappingRow)
        extPgAdvanced.value = (tm || []).length > 0
    },
)

function addExtPgMapping() {
    extPgTableMappings.value.push(normalizeExtPgMappingRow({ target: 'entity' }))
    extPgAdvanced.value = true
}

function removeExtPgMapping(i) {
    extPgTableMappings.value.splice(i, 1)
}

function onExtPgAdvancedToggle() {
    if (extPgAdvanced.value && extPgTableMappings.value.length === 0) {
        addExtPgMapping()
    }
}

async function loadExtPgTables() {
    extPgTablesLoading.value = true
    extPgTablesList.value = []
    try {
        const { data } = await axios.post('/configuracion/external-entities-pg/tables', {
            driver: externalEntitiesPgForm.driver,
            host: externalEntitiesPgForm.host,
            port: externalEntitiesPgForm.port,
            database_name: externalEntitiesPgForm.database_name,
            schema_name: externalEntitiesPgForm.schema_name || undefined,
            username: externalEntitiesPgForm.username,
            password: externalEntitiesPgForm.password || undefined,
            timeout: externalEntitiesPgForm.timeout,
        })
        if (data.success) {
            extPgTablesList.value = data.tables || []
        }
    } catch {
        extPgTablesList.value = []
    } finally {
        extPgTablesLoading.value = false
    }
}

async function loadExtPgColumnsForRow(i) {
    const row = extPgTableMappings.value[i]
    if (!row?.table_name) return
    row._columnsLoading = true
    try {
        const { data } = await axios.post('/configuracion/external-entities-pg/columns', {
            driver: externalEntitiesPgForm.driver,
            host: externalEntitiesPgForm.host,
            port: externalEntitiesPgForm.port,
            database_name: externalEntitiesPgForm.database_name,
            schema_name: (row.schema_name || externalEntitiesPgForm.schema_name) || undefined,
            username: externalEntitiesPgForm.username,
            password: externalEntitiesPgForm.password || undefined,
            timeout: externalEntitiesPgForm.timeout,
            table_name: row.table_name,
        })
        if (data.success) {
            row._columns = (data.columns || []).map((c) => c.name)
            applyExtPgMappingPreset(i)
        }
    } catch {
        row._columns = []
    } finally {
        row._columnsLoading = false
    }
}

const extPgTestResult = ref(null)
const extPgTestLoading = ref(false)
const extPgSyncLoading = ref(false)
const extPgPreviewResult = ref(null)
const extPgPreviewLoading = ref(false)
const extPgApplyLoading = ref(false)
const extPgApplyResult = ref(null)

// IDs de mapeos seleccionados para la vista previa (null = todos)
const extPgPreviewMappingIds = ref([])  // [] = todos marcados por defecto al abrir

// Sincroniza la lista cuando cambian los mapeos guardados en props
watch(
    () => props.external_entities_pg?.table_mappings,
    (tm) => {
        extPgPreviewMappingIds.value = (tm || []).map((m) => m.id).filter(Boolean)
    },
    { immediate: true }
)

const targetLabel = (target) => ({ provincia: 'Provincia', municipio: 'Municipio', entity: 'Entidad' }[target] ?? target)
const savedMappings = computed(() => props.external_entities_pg?.table_mappings ?? [])

// Cambios seleccionados por el usuario tras la vista previa
const extPgSelectedChanges = ref({ provincias: [], municipios: [], entities: [] })

watch(extPgPreviewResult, (result) => {
    if (result?.records) {
        extPgSelectedChanges.value = {
            provincias: (result.records.provincias || []).map((r) => ({ ...r, _selected: true })),
            municipios: (result.records.municipios || []).map((r) => ({ ...r, _selected: true })),
            entities:   (result.records.entities   || []).map((r) => ({ ...r, _selected: true })),
        }
    }
})

function toggleAllInGroup(group) {
    const rows = extPgSelectedChanges.value[group]
    const allSelected = rows.every((r) => r._selected)
    rows.forEach((r) => { r._selected = !allSelected })
}

const applySelectedChanges = async () => {
    extPgApplyResult.value = null
    extPgApplyLoading.value = true
    try {
        const payload = {
            changes: {
                provincias: extPgSelectedChanges.value.provincias.filter((r) => r._selected),
                municipios: extPgSelectedChanges.value.municipios.filter((r) => r._selected),
                entities:   extPgSelectedChanges.value.entities.filter((r) => r._selected),
            },
        }
        const { data } = await axios.post('/configuracion/external-entities-pg/apply-selected', payload)
        extPgApplyResult.value = data
        extPgPreviewResult.value = null
        router.reload({ preserveScroll: true, only: ['external_entities_pg'] })
    } catch (e) {
        extPgApplyResult.value = { success: false, message: e.response?.data?.message || 'Error al aplicar cambios.' }
    } finally {
        extPgApplyLoading.value = false
    }
}

const selectedCount = computed(() => {
    const s = extPgSelectedChanges.value
    return (s.provincias.filter((r) => r._selected).length)
         + (s.municipios.filter((r) => r._selected).length)
         + (s.entities.filter((r) => r._selected).length)
})

const saveExternalEntitiesPg = () => {
    externalEntitiesPgForm
        .transform((data) => ({
            ...data,
            table_mappings: extPgAdvanced.value
                ? extPgTableMappings.value.map((m) => ({
                    target: m.target,
                    schema_name: m.schema_name || null,
                    table_name: m.table_name,
                    name_column: m.name_column,
                    code_column: m.code_column,
                    municipio_code_column: m.municipio_code_column || null,
                    provincia_code_column: m.provincia_code_column || null,
                    sigla_2_column: m.sigla_2_column || null,
                    sigla_3_column: m.sigla_3_column || null,
                }))
                : [],
        }))
        .put('/configuracion/external-entities-pg', {
            preserveScroll: true,
            onSuccess: () => {
                showExtPgPasswordInput.value = false
                externalEntitiesPgForm.password = ''
            },
        })
}

const testExternalEntitiesPg = async () => {
    extPgTestResult.value = null
    extPgTestLoading.value = true
    try {
        const { data } = await axios.post('/configuracion/external-entities-pg/test', {
            driver: externalEntitiesPgForm.driver,
            host: externalEntitiesPgForm.host,
            port: externalEntitiesPgForm.port,
            database_name: externalEntitiesPgForm.database_name,
            schema_name: externalEntitiesPgForm.schema_name || undefined,
            username: externalEntitiesPgForm.username,
            password: externalEntitiesPgForm.password || undefined,
            timeout: externalEntitiesPgForm.timeout,
        })
        extPgTestResult.value = data
    } catch (e) {
        extPgTestResult.value = { success: false, message: e.response?.data?.message || 'Error al conectar.' }
    } finally {
        extPgTestLoading.value = false
    }
}

const previewExternalEntitiesPg = async () => {
    extPgPreviewResult.value = null
    extPgApplyResult.value = null
    extPgPreviewLoading.value = true
    try {
        const payload = {}
        // Si no están todos seleccionados, enviar solo los elegidos
        const allIds = savedMappings.value.map((m) => m.id).filter(Boolean)
        const selectedIds = extPgPreviewMappingIds.value
        if (allIds.length > 0 && selectedIds.length < allIds.length) {
            payload.mapping_ids = selectedIds
        }
        const { data } = await axios.post('/configuracion/external-entities-pg/preview', payload)
        extPgPreviewResult.value = data
    } catch (e) {
        extPgPreviewResult.value = { success: false, message: e.response?.data?.message || 'Error al generar vista previa.' }
    } finally {
        extPgPreviewLoading.value = false
    }
}

const testAndSyncExternalEntitiesPg = async () => {
    extPgTestResult.value = null
    extPgSyncLoading.value = true
    try {
        const { data } = await axios.post('/configuracion/external-entities-pg/test-sync')
        extPgTestResult.value = data
        router.reload({ preserveScroll: true, only: ['external_entities_pg'] })
    } catch (e) {
        extPgTestResult.value = { success: false, message: e.response?.data?.message || 'Error al probar/sincronizar.' }
        router.reload({ preserveScroll: true, only: ['external_entities_pg'] })
    } finally {
        extPgSyncLoading.value = false
    }
}

const extPgLastSummary = computed(() => props.external_entities_pg?.last_sync_summary ?? null)

function formatSyncAt(iso) {
    if (!iso) return ''
    try {
        return new Date(iso).toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' })
    } catch {
        return String(iso)
    }
}

// ── BD Entidades (Departamentos) ─────────────────────────────────────────────

const extEntityDbForm = useForm({
    enabled:         props.external_entity_db?.enabled ?? false,
    driver:          props.external_entity_db?.driver ?? 'mysql',
    host:            props.external_entity_db?.host ?? '',
    port:            props.external_entity_db?.port ?? 3306,
    username:        props.external_entity_db?.username ?? '',
    password:        '',
    db_prefix:        props.external_entity_db?.db_prefix ?? 'r4_',
    code_padding:     props.external_entity_db?.code_padding ?? 0,
    table_name:       props.external_entity_db?.table_name ?? 'activos',
    inventory_lookup_column: props.external_entity_db?.inventory_lookup_column ?? 'codigo',
    areas_table:      props.external_entity_db?.areas_table ?? 'areas_responsabilidad',
    area_code_column: props.external_entity_db?.area_code_column ?? 'codigo',
    area_name_column: props.external_entity_db?.area_name_column ?? 'nombre',
    area_column:      props.external_entity_db?.area_column ?? 'area_responsabilidad',
    grupo_column:     props.external_entity_db?.grupo_column ?? 'grupo',
    subgrupo_column:  props.external_entity_db?.subgrupo_column ?? 'subgrupo',
    grupo_value:      props.external_entity_db?.grupo_value ?? 2,
    subgrupo_value:   props.external_entity_db?.subgrupo_value ?? 3,
    timeout:          props.external_entity_db?.timeout ?? 5,
})

watch(() => extEntityDbForm.driver, (driver) => {
    const defaults = { mysql: 3306, mariadb: 3306, pgsql: 5432, sqlsrv: 1433 }
    if (defaults[driver] !== undefined) {
        extEntityDbForm.port = defaults[driver]
    }
})

const saveExternalEntityDb = () => {
    extEntityDbForm.put('/configuracion/external-entity-db', { preserveScroll: true })
}

// ── Explorador de BD ──────────────────────────────────────────────────────────
const dbBrowserLoading = ref(false)
const dbBrowserDatabases = ref([])
const dbBrowserSelectedDb = ref(null)
const dbBrowserTablesLoading = ref(false)
const dbBrowserTables = ref([])
const dbBrowserTablesMeta = ref(null)
const dbBrowserError = ref(null)

const dbBrowserConnectionPayload = () => ({
    driver:       extEntityDbForm.driver,
    host:         extEntityDbForm.host,
    port:         extEntityDbForm.port,
    username:     extEntityDbForm.username,
    password:     extEntityDbForm.password || undefined,
    db_prefix:    extEntityDbForm.db_prefix,
    code_padding: extEntityDbForm.code_padding,
    timeout:      extEntityDbForm.timeout,
})

const loadDatabases = async () => {
    dbBrowserError.value = null
    dbBrowserDatabases.value = []
    dbBrowserSelectedDb.value = null
    dbBrowserTables.value = []
    dbBrowserTablesMeta.value = null
    dbBrowserLoading.value = true
    try {
        const { data } = await axios.post('/configuracion/external-entity-db/browse-databases', dbBrowserConnectionPayload())
        dbBrowserDatabases.value = data.databases ?? []
    } catch (e) {
        dbBrowserError.value = e.response?.data?.message || 'Error al conectar con el servidor.'
    } finally {
        dbBrowserLoading.value = false
    }
}

const selectDatabase = async (dbName) => {
    dbBrowserSelectedDb.value = dbName
    dbBrowserTables.value = []
    dbBrowserTablesMeta.value = null
    dbBrowserTablesLoading.value = true
    dbBrowserError.value = null
    try {
        const { data } = await axios.post('/configuracion/external-entity-db/browse-tables', {
            ...dbBrowserConnectionPayload(),
            db_name: dbName,
        })
        dbBrowserTables.value = data.tables ?? []
        dbBrowserTablesMeta.value = data.meta ?? null
    } catch (e) {
        dbBrowserError.value = e.response?.data?.message || 'Error al cargar tablas.'
    } finally {
        dbBrowserTablesLoading.value = false
    }
}

const assignTable = (tableName, field) => {
    if (field === 'table_name') extEntityDbForm.table_name = tableName
    else if (field === 'areas_table') extEntityDbForm.areas_table = tableName
}

// Test connection
const extEntityDbTestEntityCode = ref('')
const extEntityDbTestResult = ref(null)
const extEntityDbTestLoading = ref(false)

const testExternalEntityDb = async () => {
    extEntityDbTestResult.value = null
    extEntityDbTestLoading.value = true
    try {
        const payload = {
            driver:      extEntityDbForm.driver,
            host:        extEntityDbForm.host,
            port:        extEntityDbForm.port,
            username:    extEntityDbForm.username,
            password:    extEntityDbForm.password || undefined,
            db_prefix:    extEntityDbForm.db_prefix,
            code_padding: extEntityDbForm.code_padding,
            timeout:      extEntityDbForm.timeout,
            entity_code:  extEntityDbTestEntityCode.value,
        }
        const { data } = await axios.post('/configuracion/external-entity-db/test', payload)
        extEntityDbTestResult.value = data
    } catch (e) {
        extEntityDbTestResult.value = { success: false, message: e.response?.data?.message || 'Error al conectar.' }
    } finally {
        extEntityDbTestLoading.value = false
    }
}

// Preview
const extEntityDbPreviewResult = ref(null)
const extEntityDbPreviewLoading = ref(false)
const extEntityDbSelectedChanges = ref([])

watch(extEntityDbPreviewResult, (result) => {
    if (result?.records) {
        const all = []
        for (const group of Object.values(result.records)) {
            for (const rec of group.departments ?? []) {
                all.push({ ...rec, _selected: true, _entity_name: group.entity_name })
            }
        }
        extEntityDbSelectedChanges.value = all
    } else {
        extEntityDbSelectedChanges.value = []
    }
})

const extEntityDbSelectedCount = computed(() => extEntityDbSelectedChanges.value.filter((r) => r._selected).length)

// Entidades seleccionadas para preview/sync
// Convención: array con los IDs a consultar; vacío = consultar todas (no se envía entity_ids al backend)
// Se inicializa con todas seleccionadas para que el usuario vea el estado completo
const extEntityDbSelectedEntities = ref([])
// true cuando el array vacío representa "todas" (estado inicial)
const extEntityDbAllSelected = ref(true)

const allEntitiesSelected = computed(() => extEntityDbAllSelected.value)

const entitySelectionPayload = computed(() => {
    if (extEntityDbAllSelected.value) return {}
    if (extEntityDbSelectedEntities.value.length === 0) return { entity_ids: [] }
    return { entity_ids: extEntityDbSelectedEntities.value }
})

const toggleEntitySelection = (id) => {
    if (extEntityDbAllSelected.value) {
        // Pasar de "todas" a selección explícita, excluyendo esta
        extEntityDbAllSelected.value = false
        extEntityDbSelectedEntities.value = (props.entities_for_sync ?? [])
            .map((e) => e.id)
            .filter((eid) => eid !== id)
        return
    }
    const idx = extEntityDbSelectedEntities.value.indexOf(id)
    if (idx === -1) {
        extEntityDbSelectedEntities.value.push(id)
        // Si ahora están todas, volver al estado "todas"
        if (extEntityDbSelectedEntities.value.length === (props.entities_for_sync?.length ?? 0)) {
            extEntityDbAllSelected.value = true
            extEntityDbSelectedEntities.value = []
        }
    } else {
        extEntityDbSelectedEntities.value.splice(idx, 1)
    }
}
const selectAllEntities = () => {
    extEntityDbAllSelected.value = true
    extEntityDbSelectedEntities.value = []
}
const deselectAllEntities = () => {
    extEntityDbAllSelected.value = false
    extEntityDbSelectedEntities.value = []
}

const previewExternalEntityDb = async () => {
    extEntityDbPreviewResult.value = null
    extEntityDbPreviewLoading.value = true
    try {
        const { data } = await axios.post('/configuracion/external-entity-db/preview', entitySelectionPayload.value)
        extEntityDbPreviewResult.value = data
    } catch (e) {
        extEntityDbPreviewResult.value = { success: false, message: e.response?.data?.message || 'Error al generar vista previa.' }
    } finally {
        extEntityDbPreviewLoading.value = false
    }
}

const extEntityDbApplyLoading = ref(false)
const extEntityDbApplyResult = ref(null)

const applySelectedEntityDbChanges = async () => {
    extEntityDbApplyResult.value = null
    extEntityDbApplyLoading.value = true
    try {
        const selected = extEntityDbSelectedChanges.value.filter((r) => r._selected)
        const { data } = await axios.post('/configuracion/external-entity-db/apply-selected', { changes: selected })
        extEntityDbApplyResult.value = { success: true, message: `Aplicados: ${data.created} creados, ${data.reactivated} reactivados.` }
        extEntityDbPreviewResult.value = null
        router.reload({ preserveScroll: true, only: ['external_entity_db'] })
    } catch (e) {
        extEntityDbApplyResult.value = { success: false, message: e.response?.data?.message || 'Error al aplicar cambios.' }
    } finally {
        extEntityDbApplyLoading.value = false
    }
}

const extEntityDbSyncLoading = ref(false)
const extEntityDbSyncResult = ref(null)

const syncAllExternalEntityDb = async () => {
    extEntityDbSyncResult.value = null
    extEntityDbSyncLoading.value = true
    try {
        const { data } = await axios.post('/configuracion/external-entity-db/sync', entitySelectionPayload.value)
        const s = data.summary ?? {}
        extEntityDbSyncResult.value = {
            success: true,
            message: `Sincronizacion completada: ${s.created ?? 0} creados, ${s.reactivated ?? 0} reactivados, ${s.skipped ?? 0} sin cambios.`,
        }
        router.reload({ preserveScroll: true, only: ['external_entity_db'] })
    } catch (e) {
        extEntityDbSyncResult.value = { success: false, message: e.response?.data?.message || 'Error al sincronizar.' }
    } finally {
        extEntityDbSyncLoading.value = false
    }
}

const extEntityDbLastSummary = computed(() => props.external_entity_db?.last_sync_summary ?? null)

// ── BD Almacenes Externos ────────────────────────────────────────────────────

const extAlmacenesForm = useForm({
    enabled:               props.external_almacenes?.enabled ?? false,
    host:                  props.external_almacenes?.host ?? '',
    port:                  props.external_almacenes?.port ?? 1433,
    username:              props.external_almacenes?.username ?? '',
    password:              '',
    database_name:         props.external_almacenes?.database_name ?? 'UnidadesComerciales',
    table_name:            props.external_almacenes?.table_name ?? 'Almacenes',
    schema_name:           props.external_almacenes?.schema_name ?? 'dbo',
    id_unidad_column:      props.external_almacenes?.id_unidad_column ?? 'IdUnidad',
    almacen_column:        props.external_almacenes?.almacen_column ?? 'Almacen',
    id_piso_column:        props.external_almacenes?.id_piso_column ?? 'IdPiso',
    id_almacen_pk_column:  props.external_almacenes?.id_almacen_pk_column ?? 'IdGerenciaIdAlmacen',
    import_solo_abierto:   props.external_almacenes?.import_solo_abierto ?? true,
    import_tipos:          props.external_almacenes?.import_tipos ?? ['MercanciaVenta', 'Exhibicion'],
    sync_creates_areas:    props.external_almacenes?.sync_creates_areas ?? true,
    timeout:               props.external_almacenes?.timeout ?? 10,
})

const saveExternalAlmacenes = () => {
    extAlmacenesForm.put('/configuracion/external-almacenes', { preserveScroll: true })
}

const almacenesTestResult  = ref(null)
const almacenesTestLoading = ref(false)

const testExternalAlmacenes = async () => {
    almacenesTestResult.value  = null
    almacenesTestLoading.value = true
    try {
        const payload = {
            host:          extAlmacenesForm.host,
            port:          extAlmacenesForm.port,
            username:      extAlmacenesForm.username,
            database_name: extAlmacenesForm.database_name,
            table_name:    extAlmacenesForm.table_name,
            schema_name:   extAlmacenesForm.schema_name,
            timeout:       extAlmacenesForm.timeout,
        }
        if (extAlmacenesForm.password) payload.password = extAlmacenesForm.password
        const { data } = await axios.post('/configuracion/external-almacenes/test', payload)
        almacenesTestResult.value = data
    } catch (e) {
        almacenesTestResult.value = { success: false, message: e.response?.data?.message || 'Error al conectar.' }
    } finally {
        almacenesTestLoading.value = false
    }
}

const almacenesRawData    = ref([])
const almacenesRawLoading = ref(false)
const almacenesRawIdUnidad = ref('')

const fetchRawAlmacenes = async () => {
    almacenesRawLoading.value = true
    almacenesRawData.value = []
    try {
        const payload = {}
        if (almacenesRawIdUnidad.value) payload.id_unidad = parseInt(almacenesRawIdUnidad.value)
        const { data } = await axios.post('/configuracion/external-almacenes/raw', payload)
        almacenesRawData.value = data.records ?? []
    } catch (e) {
        almacenesRawData.value = []
    } finally {
        almacenesRawLoading.value = false
    }
}

const almacenesPreviewData    = ref(null)
const almacenesPreviewLoading = ref(false)
const almacenesSelectedFloorId = ref('')
const almacenesOverrideIdUnidad = ref('')
const almacenesSelectedItems   = ref([])

const buildAlmacenesPreview = async () => {
    almacenesPreviewData.value    = null
    almacenesPreviewLoading.value = true
    almacenesSelectedItems.value  = []
    try {
        const payload = { sales_floor_id: parseInt(almacenesSelectedFloorId.value) }
        if (almacenesOverrideIdUnidad.value) payload.override_id_unidad = parseInt(almacenesOverrideIdUnidad.value)
        const { data } = await axios.post('/configuracion/external-almacenes/preview', payload)
        almacenesPreviewData.value = data
        // Pre-seleccionar todos los items accionables
        almacenesSelectedItems.value = (data.items ?? [])
            .filter(i => i.action === 'create' || i.action === 'update')
            .map(i => ({ ...i }))
    } catch (e) {
        almacenesPreviewData.value = { success: false, message: e.response?.data?.message || 'Error al generar preview.' }
    } finally {
        almacenesPreviewLoading.value = false
    }
}

const almacenesApplyLoading = ref(false)
const almacenesApplyResult  = ref(null)

const applyAlmacenesSync = async () => {
    almacenesApplyResult.value  = null
    almacenesApplyLoading.value = true
    try {
        const { data } = await axios.post('/configuracion/external-almacenes/apply', {
            sales_floor_id: parseInt(almacenesSelectedFloorId.value),
            items: almacenesSelectedItems.value,
        })
        almacenesApplyResult.value = data
        router.reload({ preserveScroll: true, only: ['external_almacenes'] })
    } catch (e) {
        almacenesApplyResult.value = { success: false, message: e.response?.data?.message || 'Error al aplicar.' }
    } finally {
        almacenesApplyLoading.value = false
    }
}

const toggleAlmacenItem = (item) => {
    const idx = almacenesSelectedItems.value.findIndex(
        s => s.almacen_id === item.almacen_id && s.action === item.action
    )
    if (idx >= 0) {
        almacenesSelectedItems.value.splice(idx, 1)
    } else {
        almacenesSelectedItems.value.push({ ...item })
    }
}

const isAlmacenItemSelected = (item) => {
    return almacenesSelectedItems.value.some(
        s => s.almacen_id === item.almacen_id && s.action === item.action
    )
}

const almacenesLastSummary = computed(() => props.external_almacenes?.last_sync_summary ?? null)

const toggleImportTipo = (flag) => {
    const tipos = [...(extAlmacenesForm.import_tipos ?? [])]
    const idx = tipos.indexOf(flag)
    if (idx >= 0) tipos.splice(idx, 1)
    else tipos.push(flag)
    extAlmacenesForm.import_tipos = tipos
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <PageHeader
                eyebrow="Control"
                title="Configuraciones"
                description="Logo del sistema, textos del encabezado y pie (PDF y Excel), activacion de modulos y configuracion del Directorio Activo."
            />

            <!-- Tabs -->
            <div class="flex gap-1 rounded-2xl border border-slate-200/80 bg-slate-100/60 p-1 dark:border-slate-700/60 dark:bg-slate-800/40">
                <button
                    v-for="tab in [
                        { key: 'apariencia', label: 'Apariencia' },
                        { key: 'modulos', label: 'Modulos' },
                        { key: 'conexiones', label: 'Conexiones externas' },
                    ]"
                    :key="tab.key"
                    type="button"
                    class="flex-1 rounded-xl px-4 py-2 text-sm font-medium transition"
                    :class="activeTab === tab.key
                        ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-slate-100'
                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- ── Pestaña Apariencia ── -->
            <div v-show="activeTab === 'apariencia'" class="space-y-6">
                <BaseCard>
                    <h3 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Logo del sistema</h3>
                    <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">Se muestra en la barra lateral y en el encabezado de PDF. Formatos: JPG, PNG, GIF, WebP o SVG (max. 4 MB).</p>
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                            <img v-if="logo_url" :src="logo_url" alt="Logo" class="max-h-full max-w-full object-contain" />
                            <span v-else class="text-xs text-slate-400">Sin logo</span>
                        </div>
                        <div v-if="canEditLogo" class="flex flex-wrap gap-3">
                            <input type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" class="app-input max-w-xs" @change="onLogoFile" />
                            <button type="button" class="app-button-primary" :disabled="!logoForm.logo || logoForm.processing" @click="uploadLogo">
                                {{ logoForm.processing ? 'Subiendo...' : 'Subir logo' }}
                            </button>
                            <button v-if="logo_url" type="button" class="app-button-secondary" @click="removeLogo">Quitar logo</button>
                        </div>
                    </div>
                    <p v-if="logoForm.errors.logo" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ logoForm.errors.logo }}</p>
                </BaseCard>

                <BaseCard>
                    <h3 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Encabezado y pie de documentos (3 lineas + pie)</h3>
                    <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                        Se aplican a <strong>PDF</strong> (incluido encabezado con logo si existe) y a las exportaciones <strong>Excel (.xlsx)</strong> generadas desde el sistema.
                        Las exportaciones <strong>CSV</strong> solo incluyen la tabla de datos. Deja un campo en blanco para usar el valor por defecto.
                    </p>
                    <form class="grid gap-4 md:grid-cols-2" @submit.prevent="saveAppearance">
                        <div class="space-y-2 md:col-span-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Linea 1 — Organizacion / institucion</label>
                            <input v-model="appearanceForm.organization_name" type="text" class="app-input" :disabled="!canEditAppearance" />
                            <p v-if="appearanceForm.errors.organization_name" class="text-sm text-red-600 dark:text-red-400">{{ appearanceForm.errors.organization_name }}</p>
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Linea 2 — Nombre del sistema</label>
                            <input v-model="appearanceForm.system_name" type="text" class="app-input" :disabled="!canEditAppearance" />
                            <p v-if="appearanceForm.errors.system_name" class="text-sm text-red-600 dark:text-red-400">{{ appearanceForm.errors.system_name }}</p>
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Linea 3 — Titulo documental</label>
                            <input v-model="appearanceForm.header_title" type="text" class="app-input" :disabled="!canEditAppearance" />
                            <p v-if="appearanceForm.errors.header_title" class="text-sm text-red-600 dark:text-red-400">{{ appearanceForm.errors.header_title }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Pie izquierdo (PDF y Excel)</label>
                            <input v-model="appearanceForm.footer_left" type="text" class="app-input" :disabled="!canEditAppearance" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Pie derecho (PDF y Excel)</label>
                            <input v-model="appearanceForm.footer_right" type="text" class="app-input" :disabled="!canEditAppearance" />
                        </div>
                        <div v-if="canEditAppearance" class="md:col-span-2 flex justify-end">
                            <button type="submit" class="app-button-primary" :disabled="appearanceForm.processing">
                                {{ appearanceForm.processing ? 'Guardando...' : 'Guardar textos' }}
                            </button>
                        </div>
                    </form>
                </BaseCard>
            </div>

            <!-- ── Pestaña Módulos ── -->
            <div v-show="activeTab === 'modulos'">
                <BaseCard>
                    <h3 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Modulos del sistema</h3>
                    <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">Activa o desactiva modulos segun disponibilidad para produccion.</p>
                    <div class="space-y-3">
                        <div
                            v-for="module in modules"
                            :key="module.id"
                            class="flex items-center justify-between rounded-2xl border border-slate-200/80 p-4 dark:border-slate-700/70"
                        >
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ module.name }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ module.description || 'Sin descripcion' }}</p>
                            </div>
                            <button
                                v-if="canToggleModules"
                                type="button"
                                class="app-button-secondary"
                                :disabled="moduleForm.processing"
                                @click="toggleModule(module)"
                            >
                                {{ module.enabled ? 'Desactivar' : 'Activar' }}
                            </button>
                            <span v-else class="text-sm text-slate-500">{{ module.enabled ? 'Activo' : 'Inactivo' }}</span>
                        </div>
                    </div>
                </BaseCard>
            </div>

            <div v-show="activeTab === 'conexiones'" class="space-y-4">
                <BaseCard>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in [
                                { key: 'ldap', label: 'Directorio Activo' },
                                { key: 'external_ci', label: 'SQL Server CI' },
                                { key: 'external_entities_pg', label: 'BD Externa Entidades' },
                                { key: 'external_entity_db', label: 'BD Entidades (Depts.)' },
                                { key: 'external_almacenes', label: 'BD Almacenes' },
                            ]"
                            :key="tab.key"
                            type="button"
                            class="rounded-xl px-3 py-2 text-sm font-medium transition"
                            :class="activeIntegrationTab === tab.key
                                ? 'bg-brand-600 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                            @click="activeIntegrationTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </BaseCard>
            </div>

            <!-- ── Pestaña Directorio Activo ── -->
            <div v-show="activeTab === 'conexiones' && activeIntegrationTab === 'ldap'" class="space-y-6">
                <!-- Configuración del servidor -->
                <BaseCard>
                    <h3 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Configuracion del servidor</h3>
                    <p class="mb-5 text-sm text-slate-600 dark:text-slate-400">Conexion con Active Directory de Windows Server. El fallback a contrasena local siempre esta activo.</p>

                    <form class="space-y-5" @submit.prevent="saveLdap">
                        <!-- Habilitar -->
                        <label class="flex cursor-pointer items-center gap-3">
                            <div
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                :class="ldapForm.enabled ? 'bg-cyan-500' : 'bg-slate-300 dark:bg-slate-600'"
                                @click="ldapForm.enabled = !ldapForm.enabled"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition"
                                    :class="ldapForm.enabled ? 'translate-x-6' : 'translate-x-1'"
                                />
                            </div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                Habilitar autenticacion con Directorio Activo
                            </span>
                        </label>

                        <!-- Host + Port -->
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="space-y-2 sm:col-span-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Servidor (host)</label>
                                <input v-model="ldapForm.host" type="text" class="app-input" placeholder="192.168.1.10 o ad.empresa.cu" />
                                <p v-if="ldapForm.errors.host" class="text-xs text-red-600 dark:text-red-400">{{ ldapForm.errors.host }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Puerto</label>
                                <input v-model.number="ldapForm.port" type="number" min="1" max="65535" class="app-input" placeholder="389" />
                                <p v-if="ldapForm.errors.port" class="text-xs text-red-600 dark:text-red-400">{{ ldapForm.errors.port }}</p>
                            </div>
                        </div>

                        <!-- Base DN -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">DN Base</label>
                            <input v-model="ldapForm.base_dn" type="text" class="app-input" placeholder="dc=empresa,dc=cu" />
                            <p v-if="ldapForm.errors.base_dn" class="text-xs text-red-600 dark:text-red-400">{{ ldapForm.errors.base_dn }}</p>
                        </div>

                        <!-- Bind credentials -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Usuario de servicio</label>
                                <input v-model="ldapForm.bind_username" type="text" class="app-input" placeholder="cn=ldap,dc=empresa,dc=cu" />
                                <p v-if="ldapForm.errors.bind_username" class="text-xs text-red-600 dark:text-red-400">{{ ldapForm.errors.bind_username }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Contrasena de servicio</label>
                                <div v-if="!showPasswordInput && ldap?.has_password" class="flex items-center gap-3">
                                    <span class="flex-1 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-900">••••••••</span>
                                    <button type="button" class="app-button-secondary shrink-0" @click="showPasswordInput = true">Cambiar</button>
                                </div>
                                <input v-else v-model="ldapForm.bind_password" type="password" class="app-input" placeholder="Contrasena del usuario de servicio" autocomplete="new-password" />
                                <p v-if="ldapForm.errors.bind_password" class="text-xs text-red-600 dark:text-red-400">{{ ldapForm.errors.bind_password }}</p>
                            </div>
                        </div>

                        <!-- SSL / TLS / Timeout -->
                        <div class="grid gap-4 sm:grid-cols-3">
                            <label class="flex cursor-pointer items-center gap-2">
                                <div
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition"
                                    :class="ldapForm.use_ssl ? 'bg-cyan-500' : 'bg-slate-300 dark:bg-slate-600'"
                                    @click="ldapForm.use_ssl = !ldapForm.use_ssl"
                                >
                                    <span class="inline-block h-3 w-3 transform rounded-full bg-white shadow transition" :class="ldapForm.use_ssl ? 'translate-x-5' : 'translate-x-1'" />
                                </div>
                                <span class="text-sm text-slate-700 dark:text-slate-300">Usar SSL (LDAPS)</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2">
                                <div
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition"
                                    :class="ldapForm.use_tls ? 'bg-cyan-500' : 'bg-slate-300 dark:bg-slate-600'"
                                    @click="ldapForm.use_tls = !ldapForm.use_tls"
                                >
                                    <span class="inline-block h-3 w-3 transform rounded-full bg-white shadow transition" :class="ldapForm.use_tls ? 'translate-x-5' : 'translate-x-1'" />
                                </div>
                                <span class="text-sm text-slate-700 dark:text-slate-300">Usar TLS (STARTTLS)</span>
                            </label>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Timeout (segundos)</label>
                                <input v-model.number="ldapForm.timeout" type="number" min="1" max="60" class="app-input" placeholder="5" />
                            </div>
                        </div>

                        <!-- User search base -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">OU de busqueda de usuarios <span class="font-normal text-slate-400">(opcional)</span></label>
                            <input v-model="ldapForm.user_search_base" type="text" class="app-input" placeholder="ou=usuarios,dc=empresa,dc=cu" />
                            <p class="text-xs text-slate-400">Si esta vacio, se busca en todo el DN Base.</p>
                        </div>

                        <!-- Actions: save + test -->
                        <div class="flex flex-wrap items-center gap-3 pt-1">
                            <button type="submit" class="app-button-primary" :disabled="ldapForm.processing">
                                {{ ldapForm.processing ? 'Guardando...' : 'Guardar configuracion' }}
                            </button>
                            <button type="button" class="app-button-secondary" :disabled="testLoading || !ldapForm.host" @click="testLdap">
                                <span v-if="testLoading" class="flex items-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                                    Probando...
                                </span>
                                <span v-else>Probar conexion</span>
                            </button>
                        </div>

                        <!-- Test result -->
                        <div v-if="testResult" class="flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm"
                            :class="testResult.success
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-700/50 dark:bg-emerald-900/20 dark:text-emerald-300'
                                : 'border-red-200 bg-red-50 text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300'"
                        >
                            <span class="mt-0.5 text-base">{{ testResult.success ? '✅' : '❌' }}</span>
                            <div>
                                <p class="font-medium">{{ testResult.message }}</p>
                                <p v-if="testResult.details" class="mt-0.5 text-xs opacity-75">{{ testResult.details }}</p>
                            </div>
                        </div>
                    </form>
                </BaseCard>

                <!-- Búsqueda de usuarios -->
                <BaseCard>
                    <h3 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Buscar usuarios en el Directorio Activo</h3>
                    <p class="mb-5 text-sm text-slate-600 dark:text-slate-400">Busca por nombre, usuario (sAMAccountName) o correo. Requiere que la conexion este configurada y activa.</p>

                    <div class="flex gap-3">
                        <input
                            v-model="ldapSearch"
                            type="text"
                            class="app-input flex-1"
                            placeholder="Ej: jperez o Juan..."
                            @keyup.enter="searchLdap"
                        />
                        <button type="button" class="app-button-primary shrink-0" :disabled="ldapSearchLoading || ldapSearch.trim().length < 2" @click="searchLdap">
                            <span v-if="ldapSearchLoading" class="flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                                Buscando...
                            </span>
                            <span v-else>Buscar</span>
                        </button>
                    </div>

                    <p v-if="ldapSearchError" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ ldapSearchError }}</p>

                    <div v-if="ldapUsers.length > 0" class="mt-4 overflow-x-auto rounded-2xl border border-slate-200/80 dark:border-slate-700/60">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-3">Nombre</th>
                                    <th class="px-4 py-3">Usuario</th>
                                    <th class="px-4 py-3">Correo</th>
                                    <th class="px-4 py-3">OU</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80 dark:divide-slate-700/60">
                                <tr v-for="u in ldapUsers" :key="u.samaccountname" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30">
                                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{{ u.displayname || '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ u.samaccountname || '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ u.mail || '—' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-400">{{ u.ou || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else-if="!ldapSearchLoading && ldapSearch.trim().length >= 2" class="mt-3 text-sm text-slate-400">
                        No se encontraron usuarios.
                    </p>
                </BaseCard>
            </div>

            <!-- ── Pestaña SQL Server CI ── -->
            <div v-show="activeTab === 'conexiones' && activeIntegrationTab === 'external_ci'" class="space-y-6">
                <BaseCard>
                    <h3 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Consulta externa por CI (SQL Server)</h3>
                    <p class="mb-5 text-sm text-slate-600 dark:text-slate-400">
                        Configura la base externa para autocompletar trabajadores por CI.
                    </p>

                    <form class="space-y-5" @submit.prevent="saveExternalCi">
                        <label class="flex cursor-pointer items-center gap-3">
                            <div class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                :class="externalCiForm.enabled ? 'bg-cyan-500' : 'bg-slate-300 dark:bg-slate-600'"
                                @click="externalCiForm.enabled = !externalCiForm.enabled">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition"
                                    :class="externalCiForm.enabled ? 'translate-x-6' : 'translate-x-1'" />
                            </div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Habilitar búsqueda externa por CI</span>
                        </label>

                        <!-- DSN ODBC (opcional) -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                DSN ODBC
                                <span class="ml-1 text-xs font-normal text-slate-400">(opcional — si se configura, omite Servidor/Puerto/BD)</span>
                            </label>
                            <input v-model="externalCiForm.odbc_dsn" type="text" class="app-input" placeholder="Ej: sql2008 (nombre en /etc/odbc.ini)" />
                        </div>

                        <!-- Servidor / Puerto / BD — opcionales cuando hay DSN -->
                        <div class="grid gap-4 sm:grid-cols-3" :class="externalCiForm.odbc_dsn ? 'opacity-50' : ''">
                            <div class="space-y-2 sm:col-span-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Servidor SQL Server</label>
                                <input v-model="externalCiForm.host" type="text" class="app-input" placeholder="192.168.1.20 o sqlserver.local" :disabled="!!externalCiForm.odbc_dsn" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Puerto</label>
                                <input v-model.number="externalCiForm.port" type="number" class="app-input" min="1" max="65535" :disabled="!!externalCiForm.odbc_dsn" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2" :class="externalCiForm.odbc_dsn ? 'opacity-50' : ''">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Base de datos</label>
                                <input v-model="externalCiForm.database_name" type="text" class="app-input" placeholder="RRHH_DB" :disabled="!!externalCiForm.odbc_dsn" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Usuario</label>
                                <input v-model="externalCiForm.username" type="text" class="app-input" placeholder="usuario_sql" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Contraseña</label>
                            <div v-if="!showExtCiPasswordInput && props.external_ci?.has_password" class="flex items-center gap-3">
                                <span class="flex-1 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-900">••••••••</span>
                                <button type="button" class="app-button-secondary shrink-0" @click="showExtCiPasswordInput = true">Cambiar</button>
                            </div>
                            <input v-else v-model="externalCiForm.password" type="password" class="app-input" placeholder="Contraseña SQL Server" autocomplete="new-password" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tabla</label>
                                <input v-model="externalCiForm.table_name" type="text" class="app-input" placeholder="TRABAJADOR" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Mapeo de columnas</label>
                                <div class="rounded-xl border border-slate-200/80 bg-slate-50/80 p-3 text-xs text-slate-600 dark:border-slate-700/60 dark:bg-slate-900/30 dark:text-slate-300">
                                    <p><span class="font-semibold">CI:</span> {{ externalCiForm.ci_column || '—' }}</p>
                                    <p><span class="font-semibold">Nombre:</span> {{ externalCiForm.nombre_column || '—' }}</p>
                                    <p><span class="font-semibold">Apellidos:</span> {{ externalCiForm.apellido1_column || '—' }} / {{ externalCiForm.apellido2_column || '—' }}</p>
                                    <p><span class="font-semibold">Teléfono:</span> {{ externalCiForm.telefono_column || '—' }}</p>
                                    <p><span class="font-semibold">Dirección:</span> {{ (externalCiForm.direccion_columns || []).join(', ') || '—' }}</p>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" class="app-button-secondary" :disabled="extCiColumnsLoading || (!externalCiForm.odbc_dsn && (!externalCiForm.host || !externalCiForm.database_name)) || !externalCiForm.table_name" @click="loadExternalCiColumns">
                                        {{ extCiColumnsLoading ? 'Cargando columnas...' : 'Cargar columnas' }}
                                    </button>
                                    <button type="button" class="app-button-secondary" :disabled="!extCiColumns.length" @click="openMapModal">
                                        Mapear columnas
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Timeout (segundos)</label>
                                <input v-model.number="externalCiForm.timeout" type="number" class="app-input" min="1" max="60" />
                            </div>
                            <div class="space-y-2">
                                <p class="text-xs text-slate-400 mt-7">
                                    Para dirección puedes seleccionar múltiples columnas en el modal de mapeo.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 pt-1">
                            <button type="submit" class="app-button-primary" :disabled="externalCiForm.processing">
                                {{ externalCiForm.processing ? 'Guardando...' : 'Guardar configuración' }}
                            </button>
                            <button type="button" class="app-button-secondary" :disabled="extCiTestLoading || (!externalCiForm.odbc_dsn && (!externalCiForm.host || !externalCiForm.database_name))" @click="testExternalCi">
                                <span v-if="extCiTestLoading">Probando...</span>
                                <span v-else>Probar conexión</span>
                            </button>
                        </div>

                        <div v-if="extCiTestResult" class="flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm"
                            :class="extCiTestResult.success
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-700/50 dark:bg-emerald-900/20 dark:text-emerald-300'
                                : 'border-red-200 bg-red-50 text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300'">
                            <span class="mt-0.5 text-base">{{ extCiTestResult.success ? '✅' : '❌' }}</span>
                            <div><p class="font-medium">{{ extCiTestResult.message }}</p></div>
                        </div>
                    </form>
                </BaseCard>
            </div>

            <!-- ── Pestaña BD Externa Entidades ── -->
            <div v-show="activeTab === 'conexiones' && activeIntegrationTab === 'external_entities_pg'" class="space-y-6">
                <BaseCard>
                    <h3 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Sincronización de Provincias, Municipios y Entidades</h3>
                    <p class="mb-5 text-sm text-slate-600 dark:text-slate-400">
                        Conecta una base de datos externa (PostgreSQL, MySQL o SQL Server) para importar y mantener sincronizados provincias, municipios y entidades.
                        Última sincronización:
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ props.external_entities_pg?.last_synced ?? 'Nunca' }}</span>.
                    </p>

                    <BaseCard class="mb-6 border border-slate-200/90 bg-slate-50/80 dark:border-slate-700/70 dark:bg-slate-900/40">
                        <h4 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Resumen de la última ejecución</h4>
                        <div v-if="!extPgLastSummary" class="text-sm text-slate-500 dark:text-slate-400">
                            Aún no hay un resumen guardado. Ejecute «Probar y sincronizar» o sincronice desde <strong class="font-medium text-slate-600 dark:text-slate-300">Entidades</strong>.
                        </div>
                        <template v-else>
                            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                                {{ formatSyncAt(extPgLastSummary.at) || '—' }}
                                <span v-if="extPgLastSummary.phase" class="ml-2 rounded bg-slate-200/80 px-1.5 py-0.5 font-mono text-[10px] text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ extPgLastSummary.phase }}</span>
                            </p>
                            <div v-if="extPgLastSummary.ok && extPgLastSummary.mode === 'mappings' && extPgLastSummary.provincias" class="mb-3 grid gap-2 sm:grid-cols-2 text-sm">
                                <div class="rounded-lg border border-amber-200/80 bg-amber-50/90 px-3 py-2 dark:border-amber-800/50 dark:bg-amber-950/30">
                                    <span class="text-amber-800 dark:text-amber-300">Provincias:</span>
                                    +{{ extPgLastSummary.provincias?.created ?? 0 }} / ~{{ extPgLastSummary.provincias?.updated ?? 0 }}
                                </div>
                                <div class="rounded-lg border border-amber-200/80 bg-amber-50/90 px-3 py-2 dark:border-amber-800/50 dark:bg-amber-950/30">
                                    <span class="text-amber-800 dark:text-amber-300">Municipios:</span>
                                    +{{ extPgLastSummary.municipios?.created ?? 0 }} / ~{{ extPgLastSummary.municipios?.updated ?? 0 }}
                                </div>
                            </div>
                            <div v-if="extPgLastSummary.ok" class="grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl border border-emerald-200/80 bg-white px-4 py-3 dark:border-emerald-800/50 dark:bg-slate-900/60">
                                    <p class="text-xs font-medium text-emerald-700 dark:text-emerald-400">{{ extPgLastSummary.mode === 'mappings' ? 'Entidades creadas' : 'Creadas' }}</p>
                                    <p class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ extPgLastSummary.created ?? 0 }}</p>
                                </div>
                                <div class="rounded-xl border border-sky-200/80 bg-white px-4 py-3 dark:border-sky-800/50 dark:bg-slate-900/60">
                                    <p class="text-xs font-medium text-sky-700 dark:text-sky-400">{{ extPgLastSummary.mode === 'mappings' ? 'Entidades: códigos' : 'Códigos actualizados' }}</p>
                                    <p class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ extPgLastSummary.updated_codes ?? 0 }}</p>
                                </div>
                                <div class="rounded-xl border border-violet-200/80 bg-white px-4 py-3 dark:border-violet-800/50 dark:bg-slate-900/60">
                                    <p class="text-xs font-medium text-violet-700 dark:text-violet-400">{{ extPgLastSummary.mode === 'mappings' ? 'Entidades: otros' : 'Otros ajustes' }}</p>
                                    <p class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ extPgLastSummary.updated_data ?? 0 }}</p>
                                </div>
                            </div>
                            <div v-else class="rounded-xl border border-red-200/80 bg-red-50/90 px-4 py-3 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-950/30 dark:text-red-300">
                                <p class="font-medium">Error</p>
                                <p class="mt-1 break-words">{{ extPgLastSummary.error || 'Error desconocido.' }}</p>
                            </div>
                        </template>
                    </BaseCard>

                    <form class="space-y-5" @submit.prevent="saveExternalEntitiesPg">
                        <label class="flex cursor-pointer items-center gap-3">
                            <div class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                :class="externalEntitiesPgForm.enabled ? 'bg-cyan-500' : 'bg-slate-300 dark:bg-slate-600'"
                                @click="externalEntitiesPgForm.enabled = !externalEntitiesPgForm.enabled">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition"
                                    :class="externalEntitiesPgForm.enabled ? 'translate-x-6' : 'translate-x-1'" />
                            </div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Habilitar sincronización externa de entidades</span>
                        </label>

                        <!-- Driver -->
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Motor de base de datos</label>
                                <select v-model="externalEntitiesPgForm.driver" class="app-input">
                                    <option value="pgsql">PostgreSQL</option>
                                    <option value="mysql">MySQL / MariaDB</option>
                                    <option value="sqlsrv">SQL Server</option>
                                </select>
                            </div>
                        </div>

                        <!-- Host + Puerto -->
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="space-y-2 sm:col-span-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Servidor (host)</label>
                                <input v-model="externalEntitiesPgForm.host" type="text" class="app-input" placeholder="192.168.1.30 o db.local" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Puerto</label>
                                <input v-model.number="externalEntitiesPgForm.port" type="number" class="app-input" min="1" max="65535" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="space-y-2 sm:col-span-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Base de datos</label>
                                <input v-model="externalEntitiesPgForm.database_name" type="text" class="app-input" placeholder="sistema_externo" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ (externalEntitiesPgForm.driver === 'mysql' || externalEntitiesPgForm.driver === 'mariadb') ? 'Schema (BD)' : 'Esquema' }}
                                </label>
                                <input
                                    v-model="externalEntitiesPgForm.schema_name"
                                    type="text"
                                    class="app-input"
                                    :placeholder="(externalEntitiesPgForm.driver === 'mysql' || externalEntitiesPgForm.driver === 'mariadb') ? 'nombre_bd' : 'public'"
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Usuario</label>
                                <input v-model="externalEntitiesPgForm.username" type="text" class="app-input" placeholder="usuario_pg" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Contraseña</label>
                                <div v-if="!showExtPgPasswordInput && props.external_entities_pg?.has_password" class="flex items-center gap-3">
                                    <span class="flex-1 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-900">••••••••</span>
                                    <button type="button" class="app-button-secondary shrink-0" @click="showExtPgPasswordInput = true">Cambiar</button>
                                </div>
                                <input v-else v-model="externalEntitiesPgForm.password" type="password" class="app-input" placeholder="Contraseña PostgreSQL" autocomplete="new-password" />
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:border-slate-700/60 dark:bg-slate-900/30">
                            <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                                <input
                                    type="checkbox"
                                    v-model="extPgAdvanced"
                                    class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                                    @change="onExtPgAdvancedToggle"
                                />
                                Mapear varias tablas PostgreSQL (provincias, municipios, entidades)
                            </label>
                            <button
                                v-if="extPgAdvanced"
                                type="button"
                                class="app-button-secondary text-xs"
                                :disabled="extPgTablesLoading || !externalEntitiesPgForm.host || !externalEntitiesPgForm.database_name"
                                @click="loadExtPgTables"
                            >
                                {{ extPgTablesLoading ? 'Cargando tablas…' : 'Listar tablas del esquema' }}
                            </button>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Timeout (segundos)</label>
                                <input v-model.number="externalEntitiesPgForm.timeout" type="number" class="app-input" min="1" max="60" />
                            </div>
                        </div>

                        <!-- Modo simple: una tabla → entidades -->
                        <template v-if="!extPgAdvanced">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tabla externa</label>
                                    <input v-model="externalEntitiesPgForm.table_name" type="text" class="app-input" placeholder="entities" />
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-700/60">
                                <p class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-200">Mapeo de columnas (entidades)</p>
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Columna nombre</label>
                                        <input v-model="externalEntitiesPgForm.name_column" type="text" class="app-input" placeholder="name" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Columna código</label>
                                        <input v-model="externalEntitiesPgForm.code_column" type="text" class="app-input" placeholder="code" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Columna municipio <span class="text-slate-400">(opcional)</span></label>
                                        <input v-model="externalEntitiesPgForm.municipio_code_column" type="text" class="app-input" placeholder="municipio" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Columna provincia <span class="text-slate-400">(opcional)</span></label>
                                        <input v-model="externalEntitiesPgForm.provincia_column" type="text" class="app-input" placeholder="provincia" />
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Modo avanzado: varias tablas y destinos SGI -->
                        <div v-else class="space-y-4">
                            <p class="text-sm text-slate-600 dark:text-slate-400">
                                1) Pulse «Listar tablas del esquema». 2) Añada un origen por destino (provincia → municipio → entidad recomendado). 3) Elija tabla y cargue columnas para mapear a los campos SGI.
                            </p>
                            <div
                                v-for="(row, idx) in extPgTableMappings"
                                :key="idx"
                                class="rounded-xl border border-cyan-200/70 bg-white p-4 dark:border-cyan-900/40 dark:bg-slate-900/50"
                            >
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-cyan-700 dark:text-cyan-400">Origen #{{ idx + 1 }}</span>
                                    <button type="button" class="text-xs text-red-600 hover:underline dark:text-red-400" @click="removeExtPgMapping(idx)">Quitar</button>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    <div class="space-y-1">
                                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Destino en SGI</label>
                                        <select v-model="row.target" class="app-input text-sm" @change="onExtPgMappingTargetChange(idx)">
                                            <option value="provincia">Provincia</option>
                                            <option value="municipio">Municipio</option>
                                            <option value="entity">Entidad</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Esquema (opcional)</label>
                                        <input v-model="row.schema_name" type="text" class="app-input text-sm" placeholder="Vacío = mismo que arriba" />
                                    </div>
                                    <div class="space-y-1 sm:col-span-2">
                                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Tabla PostgreSQL</label>
                                        <input
                                            v-model="row.table_name"
                                            type="text"
                                            class="app-input text-sm"
                                            :list="'ext-pg-tables-dl-' + idx"
                                            placeholder="Tras listar tablas, elija o escriba el nombre"
                                        />
                                        <datalist :id="'ext-pg-tables-dl-' + idx">
                                            <option v-for="t in extPgTablesList" :key="`tbl-${idx}-${t}`" :value="t" />
                                        </datalist>
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="app-button-secondary text-xs"
                                        :disabled="row._columnsLoading || !row.table_name"
                                        @click="loadExtPgColumnsForRow(idx)"
                                    >
                                        {{ row._columnsLoading ? 'Columnas…' : 'Cargar columnas' }}
                                    </button>
                                </div>
                                <div class="mt-3 space-y-3">
                                    <div class="space-y-1 sm:col-span-2">
                                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">
                                            Convención de columnas en PostgreSQL
                                        </label>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            Un solo criterio: elige cómo se llaman las columnas en tu tabla remota; el SGI rellena código, nombre, etc. Tras «Cargar columnas», se intentan coincidir nombres reales.
                                        </p>
                                        <select
                                            v-model="row.mapping_preset"
                                            class="app-input text-sm"
                                            @change="applyExtPgMappingPreset(idx)"
                                        >
                                            <option
                                                v-for="opt in extPgPresetSelectOptions(row.target)"
                                                :key="row.target + '-' + opt.value"
                                                :value="opt.value"
                                            >
                                                {{ opt.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div
                                        v-if="row.mapping_preset !== 'manual' && row._columns.length"
                                        class="rounded-lg border border-slate-200/80 bg-slate-50/90 px-3 py-2 text-xs text-slate-700 dark:border-slate-600 dark:bg-slate-800/60 dark:text-slate-300"
                                    >
                                        <template v-if="row.target === 'entity'">
                                            <span class="font-mono">codigo</span> → {{ row.code_column || '—' }} ·
                                            <span class="font-mono">nombre</span> → {{ row.name_column || '—' }} ·
                                            <span class="font-mono">municipio</span> → {{ row.municipio_code_column || '—' }} ·
                                            <span class="font-mono">provincia</span> → {{ row.provincia_code_column || '—' }}
                                        </template>
                                        <template v-else-if="row.target === 'municipio'">
                                            <span class="font-mono">nombre</span> → {{ row.name_column || '—' }} ·
                                            <span class="font-mono">codigo</span> → {{ row.code_column || '—' }} ·
                                            <span class="font-mono">provincia</span> → {{ row.provincia_code_column || '—' }}
                                        </template>
                                        <template v-else>
                                            <span class="font-mono">nombre</span> → {{ row.name_column || '—' }} ·
                                            <span class="font-mono">codigo</span> → {{ row.code_column || '—' }} ·
                                            <span class="font-mono">sigla_2</span> → {{ row.sigla_2_column || '—' }} ·
                                            <span class="font-mono">sigla_3</span> → {{ row.sigla_3_column || '—' }}
                                        </template>
                                    </div>
                                    <p v-else-if="row.mapping_preset !== 'manual' && !row._columns.length" class="text-xs text-amber-700 dark:text-amber-300">
                                        Pulse «Cargar columnas» para detectar nombres en el origen.
                                    </p>

                                    <div v-if="row.mapping_preset === 'manual'" class="grid gap-3 sm:grid-cols-2">
                                        <div v-if="row.target === 'entity' || row.target === 'municipio' || row.target === 'provincia'" class="space-y-1">
                                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Columna nombre</label>
                                            <input v-model="row.name_column" type="text" class="app-input text-sm" :list="'extpg-dl-' + idx" placeholder="nombre" />
                                        </div>
                                        <div v-if="row.target === 'entity' || row.target === 'municipio' || row.target === 'provincia'" class="space-y-1">
                                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Columna código</label>
                                            <input v-model="row.code_column" type="text" class="app-input text-sm" :list="'extpg-dl-' + idx" placeholder="codigo" />
                                        </div>
                                        <div v-if="row.target === 'entity'" class="space-y-1">
                                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Columna municipio</label>
                                            <input v-model="row.municipio_code_column" type="text" class="app-input text-sm" :list="'extpg-dl-' + idx" placeholder="municipio" />
                                        </div>
                                        <div v-if="row.target === 'entity'" class="space-y-1">
                                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Columna provincia</label>
                                            <input v-model="row.provincia_code_column" type="text" class="app-input text-sm" :list="'extpg-dl-' + idx" placeholder="provincia" />
                                        </div>
                                        <div v-if="row.target === 'municipio'" class="space-y-1 sm:col-span-2">
                                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Columna código provincia</label>
                                            <input v-model="row.provincia_code_column" type="text" class="app-input text-sm" :list="'extpg-dl-' + idx" placeholder="provincia" />
                                        </div>
                                    </div>
                                    <div v-if="row.target === 'provincia'" class="grid gap-3 border-t border-slate-200/80 pt-3 dark:border-slate-600 sm:grid-cols-2">
                                        <div class="space-y-1 sm:col-span-2">
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                Columnas opcionales en PostgreSQL para <span class="font-mono">sigla_2</span> y <span class="font-mono">sigla_3</span> en SGI.
                                            </p>
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Columna sigla_2</label>
                                            <input v-model="row.sigla_2_column" type="text" class="app-input text-sm" :list="'extpg-dl-' + idx" placeholder="sigla_2" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Columna sigla_3</label>
                                            <input v-model="row.sigla_3_column" type="text" class="app-input text-sm" :list="'extpg-dl-' + idx" placeholder="sigla_3" />
                                        </div>
                                    </div>
                                    <datalist :id="'extpg-dl-' + idx">
                                        <option v-for="c in row._columns" :key="`dl-${idx}-${c}`" :value="c" />
                                    </datalist>
                                </div>
                            </div>
                            <button type="button" class="app-button-secondary text-sm" @click="addExtPgMapping">+ Añadir origen</button>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 pt-1">
                            <button type="submit" class="app-button-primary" :disabled="externalEntitiesPgForm.processing">
                                {{ externalEntitiesPgForm.processing ? 'Guardando...' : 'Guardar configuración' }}
                            </button>
                            <button type="button" class="app-button-secondary" :disabled="extPgTestLoading || !externalEntitiesPgForm.host || !externalEntitiesPgForm.database_name" @click="testExternalEntitiesPg">
                                <span v-if="extPgTestLoading">Probando...</span>
                                <span v-else>Probar conexión</span>
                            </button>
                            <button
                                type="button"
                                class="app-button-secondary"
                                :disabled="extPgPreviewLoading || extPgSyncLoading || externalEntitiesPgForm.processing"
                                @click="previewExternalEntitiesPg"
                                title="Simula la sincronización sin modificar la base de datos"
                            >
                                <span v-if="extPgPreviewLoading">Calculando...</span>
                                <span v-else>Vista previa</span>
                            </button>
                            <button
                                type="button"
                                class="app-button-secondary"
                                :disabled="extPgSyncLoading || extPgPreviewLoading || externalEntitiesPgForm.processing"
                                @click="testAndSyncExternalEntitiesPg"
                            >
                                <span v-if="extPgSyncLoading">Sincronizando...</span>
                                <span v-else>Sincronizar ahora</span>
                            </button>
                        </div>
                        <!-- Selector de orígenes para vista previa (solo modo avanzado con mapeos guardados) -->
                        <div v-if="savedMappings.length > 1" class="rounded-xl border border-slate-200/80 bg-slate-50/60 px-4 py-3 dark:border-slate-700/60 dark:bg-slate-900/30">
                            <p class="mb-2 text-xs font-medium text-slate-600 dark:text-slate-400">
                                Orígenes a incluir en la vista previa
                                <span class="ml-1 font-normal text-slate-400">(selecciona uno o varios)</span>
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <label
                                    v-for="m in savedMappings"
                                    :key="m.id"
                                    class="flex cursor-pointer items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs transition"
                                    :class="extPgPreviewMappingIds.includes(m.id)
                                        ? 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-700 dark:bg-sky-900/30 dark:text-sky-300'
                                        : 'border-slate-200 bg-white text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400'"
                                >
                                    <input
                                        type="checkbox"
                                        :value="m.id"
                                        v-model="extPgPreviewMappingIds"
                                        class="h-3 w-3 rounded border-slate-300 text-sky-600"
                                    />
                                    <span class="font-mono font-medium">{{ m.table_name || '—' }}</span>
                                    <span class="rounded bg-slate-100 px-1 dark:bg-slate-800">{{ targetLabel(m.target) }}</span>
                                </label>
                            </div>
                        </div>

                        <p class="text-xs text-slate-400 dark:text-slate-500">
                            "Vista previa" y "Sincronizar ahora" usan la configuracion guardada en base de datos.
                            Pulsa <strong class="font-medium">Guardar configuracion</strong> primero si realizaste cambios.
                        </p>

                        <!-- Resultado: Probar conexión / Sincronizar -->
                        <div v-if="extPgTestResult" class="flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm"
                            :class="extPgTestResult.success
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-700/50 dark:bg-emerald-900/20 dark:text-emerald-300'
                                : 'border-red-200 bg-red-50 text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300'">
                            <span class="mt-0.5 text-base">{{ extPgTestResult.success ? '✅' : '❌' }}</span>
                            <div><p class="font-medium">{{ extPgTestResult.message }}</p></div>
                        </div>

                        <!-- Resultado: Vista previa (dry-run) con selección por registro -->
                        <div v-if="extPgPreviewResult">
                            <!-- Error -->
                            <div v-if="!extPgPreviewResult.success"
                                class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300"
                            >
                                <span class="mt-0.5 text-base">❌</span>
                                <p class="font-medium">{{ extPgPreviewResult.message }}</p>
                            </div>

                            <!-- Éxito: tablas de cambios -->
                            <div v-else class="space-y-4 rounded-2xl border border-sky-200 bg-sky-50/60 p-4 dark:border-sky-800/50 dark:bg-sky-900/10">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-semibold text-sky-900 dark:text-sky-200">
                                        Vista previa — ningún dato fue modificado
                                    </p>
                                    <span class="rounded-full bg-sky-200/80 px-3 py-0.5 text-xs font-medium text-sky-800 dark:bg-sky-800/60 dark:text-sky-200">
                                        {{ selectedCount }} seleccionados
                                    </span>
                                </div>

                                <!-- Diagnóstico importación municipios (fila leída vs provincia local) -->
                                <div
                                    v-if="extPgPreviewResult.municipios_diagnostics"
                                    class="rounded-xl border border-amber-200/90 bg-amber-50/90 px-4 py-3 text-xs text-amber-950 dark:border-amber-700/50 dark:bg-amber-950/30 dark:text-amber-100"
                                >
                                    <p class="font-semibold text-amber-900 dark:text-amber-200">Municipios — lectura de la tabla externa</p>
                                    <ul class="mt-2 list-inside list-disc space-y-1 text-amber-900/95 dark:text-amber-100/95">
                                        <li>Filas leídas: <strong>{{ extPgPreviewResult.municipios_diagnostics.source_rows }}</strong></li>
                                        <li>Omitidas (nombre/código/provincia vacíos): {{ extPgPreviewResult.municipios_diagnostics.skipped_empty }}</li>
                                        <li>
                                            Omitidas (sin provincia local que coincida con la columna provincia):
                                            <strong>{{ extPgPreviewResult.municipios_diagnostics.skipped_no_provincia }}</strong>
                                            <span v-if="extPgPreviewResult.municipios_diagnostics.sample_missing_prov_codes?.length">
                                                — ejemplos de códigos de provincia en el origen:
                                                <span class="font-mono">{{ extPgPreviewResult.municipios_diagnostics.sample_missing_prov_codes.join(', ') }}</span>
                                            </span>
                                        </li>
                                        <li>Ya existían en SGI sin cambios: {{ extPgPreviewResult.municipios_diagnostics.unchanged_existing }}</li>
                                    </ul>
                                    <p class="mt-2 text-[11px] leading-relaxed text-amber-800/90 dark:text-amber-200/85">
                                        Cada municipio debe enlazar con una <strong>provincia ya cargada en SGI</strong> (mismo código que en la columna de provincia del origen).
                                        Se comparan variantes con/sin ceros a la izquierda (p. ej. 01 y 1). Si el código del origen no existe en Provincias, la fila se omite.
                                        Sincronice primero el mapeo de <strong>Provincias</strong> o revise los códigos en Configuración → Provincias.
                                    </p>
                                </div>

                                <!-- Tabla por tipo -->
                                <template v-for="(group, key) in {
                                    provincias: { label: 'Provincias', color: 'amber' },
                                    municipios: { label: 'Municipios', color: 'violet' },
                                    entities:   { label: 'Entidades',  color: 'emerald' },
                                }" :key="key">
                                    <div v-if="extPgSelectedChanges[key]?.length" class="overflow-hidden rounded-xl border border-slate-200/80 dark:border-slate-700/60">
                                        <!-- Cabecera del grupo -->
                                        <div class="flex items-center justify-between border-b border-slate-200/80 bg-slate-100/80 px-3 py-2 dark:border-slate-700/60 dark:bg-slate-800/60">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                                                {{ group.label }}
                                                <span class="ml-1 font-normal text-slate-400">({{ extPgSelectedChanges[key].length }}
                                                    <template v-if="extPgPreviewResult.truncated?.[key]">— truncado a {{ extPgSelectedChanges[key].length }}</template>)
                                                </span>
                                            </span>
                                            <button
                                                type="button"
                                                class="text-xs text-sky-600 hover:underline dark:text-sky-400"
                                                @click="toggleAllInGroup(key)"
                                            >
                                                {{ extPgSelectedChanges[key].every((r) => r._selected) ? 'Deseleccionar todos' : 'Seleccionar todos' }}
                                            </button>
                                        </div>
                                        <!-- Tabla -->
                                        <div class="max-h-64 overflow-y-auto">
                                            <table class="w-full text-xs">
                                                <thead class="sticky top-0 bg-white text-left text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                                                    <tr>
                                                        <th class="w-8 px-2 py-2"></th>
                                                        <th class="px-3 py-2">Acción</th>
                                                        <th class="px-3 py-2">Nombre</th>
                                                        <th class="px-3 py-2">Código</th>
                                                        <th class="px-3 py-2">Cambios</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                    <tr
                                                        v-for="(rec, i) in extPgSelectedChanges[key]"
                                                        :key="i"
                                                        class="hover:bg-slate-50 dark:hover:bg-slate-800/40"
                                                        :class="!rec._selected ? 'opacity-40' : ''"
                                                    >
                                                        <td class="px-2 py-1.5 text-center">
                                                            <input
                                                                type="checkbox"
                                                                v-model="rec._selected"
                                                                class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600"
                                                            />
                                                        </td>
                                                        <td class="px-3 py-1.5">
                                                            <span
                                                                class="rounded px-1.5 py-0.5 font-medium"
                                                                :class="rec.action === 'create'
                                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'"
                                                            >
                                                                {{ rec.action === 'create' ? 'Nuevo' : 'Actualizar' }}
                                                            </span>
                                                        </td>
                                                        <td class="px-3 py-1.5 font-medium text-slate-800 dark:text-slate-200">
                                                            {{ rec.name }}
                                                            <span v-if="rec.action === 'update' && rec.current_name !== rec.name"
                                                                class="ml-1 text-slate-400 line-through">{{ rec.current_name }}</span>
                                                        </td>
                                                        <td class="px-3 py-1.5 font-mono text-slate-600 dark:text-slate-300">
                                                            {{ rec.code }}
                                                            <span v-if="rec.action === 'update' && rec.current_code !== rec.code"
                                                                class="ml-1 text-slate-400 line-through">{{ rec.current_code }}</span>
                                                        </td>
                                                        <td class="px-3 py-1.5 text-slate-500 dark:text-slate-400">
                                                            <template v-if="rec.action === 'update' && rec.changes">
                                                                <span v-for="(diff, field) in rec.changes" :key="field" class="mr-2">
                                                                    <span class="font-mono text-slate-400">{{ field }}:</span>
                                                                    <span class="ml-0.5 text-red-500 line-through">{{ diff.from ?? '—' }}</span>
                                                                    <span class="ml-0.5 text-emerald-600">→ {{ diff.to }}</span>
                                                                </span>
                                                            </template>
                                                            <template v-else-if="rec.action === 'create'">
                                                                <span class="italic text-slate-400">Registro nuevo</span>
                                                            </template>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </template>

                                <!-- Acción -->
                                <div class="flex flex-wrap items-center gap-3 pt-1">
                                    <button
                                        type="button"
                                        class="app-button-primary"
                                        :disabled="extPgApplyLoading || selectedCount === 0"
                                        @click="applySelectedChanges"
                                    >
                                        <span v-if="extPgApplyLoading">Aplicando...</span>
                                        <span v-else>Aplicar {{ selectedCount }} cambio(s) seleccionados</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="app-button-secondary"
                                        :disabled="extPgApplyLoading"
                                        @click="extPgPreviewResult = null"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Resultado: aplicar seleccionados -->
                        <div v-if="extPgApplyResult" class="flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm"
                            :class="extPgApplyResult.success
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-700/50 dark:bg-emerald-900/20 dark:text-emerald-300'
                                : 'border-red-200 bg-red-50 text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300'"
                        >
                            <span class="mt-0.5 text-base">{{ extPgApplyResult.success ? '✅' : '❌' }}</span>
                            <div><p class="font-medium">{{ extPgApplyResult.message }}</p></div>
                        </div>
                    </form>
                </BaseCard>
            </div>

            <!-- ── Pestaña BD Entidades (Departamentos) ── -->
            <div v-show="activeTab === 'conexiones' && activeIntegrationTab === 'external_entity_db'" class="space-y-6">
                <!-- Configuracion de conexion -->
                <BaseCard>
                    <h3 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">BD Entidades — Departamentos</h3>
                    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                        Conecta a las bases de datos individuales de cada entidad (<code class="rounded bg-slate-100 px-1 dark:bg-slate-800">r4_&lt;codigo&gt;</code>) para importar sus areas de responsabilidad como departamentos.
                    </p>
                    <form class="space-y-4" @submit.prevent="saveExternalEntityDb">
                        <!-- Habilitado + Driver -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1 sm:col-span-2 flex items-center gap-3">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Habilitar sincronizacion</label>
                                <button
                                    type="button"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                    :class="extEntityDbForm.enabled ? 'bg-brand-600' : 'bg-slate-300 dark:bg-slate-600'"
                                    @click="extEntityDbForm.enabled = !extEntityDbForm.enabled"
                                >
                                    <span
                                        class="inline-block h-4 w-4 rounded-full bg-white shadow transition"
                                        :class="extEntityDbForm.enabled ? 'translate-x-6' : 'translate-x-1'"
                                    />
                                </button>
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Driver</label>
                                <select v-model="extEntityDbForm.driver" class="app-input">
                                    <option value="mysql">MySQL</option>
                                    <option value="mariadb">MariaDB</option>
                                    <option value="pgsql">PostgreSQL</option>
                                    <option value="sqlsrv">SQL Server</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Host</label>
                                <input v-model="extEntityDbForm.host" type="text" class="app-input" placeholder="192.168.x.x" />
                                <p v-if="extEntityDbForm.errors.host" class="text-sm text-red-600 dark:text-red-400">{{ extEntityDbForm.errors.host }}</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Puerto</label>
                                <input v-model.number="extEntityDbForm.port" type="number" class="app-input" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Usuario</label>
                                <input v-model="extEntityDbForm.username" type="text" class="app-input" autocomplete="off" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Contraseña
                                    <span v-if="external_entity_db?.has_password" class="ml-1 text-xs text-slate-400">(guardada — dejar en blanco para no cambiar)</span>
                                </label>
                                <input v-model="extEntityDbForm.password" type="password" class="app-input" autocomplete="new-password" />
                            </div>
                        </div>

                        <!-- Configuracion de tablas -->
                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/50 p-4 dark:border-slate-700/60 dark:bg-slate-900/20">
                            <h4 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Configuracion de tabla y columnas</h4>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-1 sm:col-span-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Prefijo BD</label>
                                    <div class="flex items-center gap-2">
                                        <input v-model="extEntityDbForm.db_prefix" type="text" class="app-input w-28" placeholder="r4_" />
                                        <span class="text-sm text-slate-400">+ ceros hasta</span>
                                        <input v-model.number="extEntityDbForm.code_padding" type="number" class="app-input w-20" min="0" max="20" placeholder="0" />
                                        <span class="text-sm text-slate-400">digitos</span>
                                    </div>
                                    <p class="text-xs text-slate-400">
                                        Ejemplo con entidad <code class="bg-slate-100 px-1 rounded dark:bg-slate-800">819</code>:
                                        <strong class="text-slate-600 dark:text-slate-300">{{ extEntityDbForm.db_prefix }}{{ String(819).padStart(extEntityDbForm.code_padding || 0, '0') || '819' }}</strong>
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Timeout (s)</label>
                                    <input v-model.number="extEntityDbForm.timeout" type="number" class="app-input" min="1" max="60" />
                                </div>
                            </div>

                            <!-- Tabla activos -->
                            <p class="mt-3 mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Tabla de activos</p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tabla</label>
                                    <input v-model="extEntityDbForm.table_name" type="text" class="app-input" placeholder="activos" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Columna inventario (lookup)</label>
                                    <input v-model="extEntityDbForm.inventory_lookup_column" type="text" class="app-input" placeholder="codigo" />
                                    <p class="text-xs text-slate-400">Columna en activos que coincide con el No. de inventario del expediente (p. ej. codigo).</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Columna cod. area</label>
                                    <input v-model="extEntityDbForm.area_column" type="text" class="app-input" placeholder="area_responsabilidad" />
                                    <p class="text-xs text-slate-400">Contiene el codigo del area (FK a la tabla de areas)</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Columna grupo</label>
                                    <input v-model="extEntityDbForm.grupo_column" type="text" class="app-input" placeholder="grupo" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Valor grupo</label>
                                    <input v-model.number="extEntityDbForm.grupo_value" type="number" class="app-input" min="0" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Columna subgrupo</label>
                                    <input v-model="extEntityDbForm.subgrupo_column" type="text" class="app-input" placeholder="subgrupo" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Valor subgrupo</label>
                                    <input v-model.number="extEntityDbForm.subgrupo_value" type="number" class="app-input" min="0" />
                                </div>
                            </div>

                            <!-- Tabla areas_responsabilidad -->
                            <p class="mt-3 mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Tabla de areas (nomenclador)</p>
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tabla</label>
                                    <input v-model="extEntityDbForm.areas_table" type="text" class="app-input" placeholder="areas_responsabilidad" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Columna codigo</label>
                                    <input v-model="extEntityDbForm.area_code_column" type="text" class="app-input" placeholder="codigo" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Columna nombre</label>
                                    <input v-model="extEntityDbForm.area_name_column" type="text" class="app-input" placeholder="nombre" />
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="app-button-primary" :disabled="extEntityDbForm.processing">
                                {{ extEntityDbForm.processing ? 'Guardando...' : 'Guardar configuracion' }}
                            </button>
                        </div>
                    </form>
                </BaseCard>

                <!-- Explorador de bases de datos -->
                <BaseCard>
                    <h3 class="mb-1 text-base font-semibold text-slate-900 dark:text-slate-100">Explorador de bases de datos</h3>
                    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                        Carga las bases de datos del servidor (filtradas por prefijo, p. ej. r4_), selecciona la base de la entidad y elige tablas.
                        En PostgreSQL, antes solo se listaban tablas del esquema <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">public</code>;
                        ahora se muestran todos los esquemas como <span class="font-mono text-xs">esquema.tabla</span> si no es public.
                        En MySQL/MariaDB cada fila es una base de datos; las tablas salen de <span class="font-mono text-xs">information_schema.tables</span> para esa base.
                    </p>

                    <button
                        type="button"
                        class="app-button-secondary"
                        :disabled="dbBrowserLoading || !extEntityDbForm.host"
                        @click="loadDatabases"
                    >
                        {{ dbBrowserLoading ? 'Cargando...' : 'Cargar bases de datos' }}
                    </button>

                    <div v-if="dbBrowserError" class="mt-3 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300">
                        <span>❌</span>
                        <p>{{ dbBrowserError }}</p>
                    </div>

                    <div v-if="dbBrowserDatabases.length" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <!-- Lista de bases de datos -->
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                                Bases de datos ({{ dbBrowserDatabases.length }})
                            </p>
                            <ul class="max-h-56 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700 rounded-xl border border-slate-200 dark:border-slate-700 text-sm bg-white dark:bg-slate-800">
                                <li
                                    v-for="db in dbBrowserDatabases"
                                    :key="db"
                                    class="flex items-center justify-between gap-2 px-3 py-2 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50"
                                    :class="dbBrowserSelectedDb === db ? 'bg-indigo-50 dark:bg-indigo-900/20' : ''"
                                    @click="selectDatabase(db)"
                                >
                                    <span class="font-mono truncate" :class="dbBrowserSelectedDb === db ? 'text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-slate-700 dark:text-slate-300'">
                                        {{ db }}
                                    </span>
                                    <span v-if="dbBrowserSelectedDb === db && dbBrowserTablesLoading" class="text-xs text-slate-400 shrink-0">cargando...</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Tablas de la BD seleccionada -->
                        <div v-if="dbBrowserSelectedDb">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                                Tablas de <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ dbBrowserSelectedDb }}</span>
                                <span v-if="dbBrowserTables.length">({{ dbBrowserTables.length }})</span>
                            </p>
                            <div
                                v-if="dbBrowserTablesMeta?.listing_note"
                                class="mb-2 rounded-lg border border-slate-200/80 bg-slate-50/80 px-3 py-2 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400"
                            >
                                <p class="font-medium text-slate-700 dark:text-slate-300">Como se obtienen las tablas</p>
                                <p class="mt-1">{{ dbBrowserTablesMeta.listing_note }}</p>
                                <p v-if="dbBrowserTablesMeta.sql_tables" class="mt-1 font-mono text-[11px] text-slate-500 dark:text-slate-500">
                                    {{ dbBrowserTablesMeta.sql_tables }}
                                </p>
                            </div>
                            <div v-if="dbBrowserTablesLoading" class="text-sm text-slate-400 py-4 text-center">Cargando tablas...</div>
                            <ul v-else class="max-h-56 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700 rounded-xl border border-slate-200 dark:border-slate-700 text-sm bg-white dark:bg-slate-800">
                                <li
                                    v-for="table in dbBrowserTables"
                                    :key="table"
                                    class="flex items-center justify-between gap-2 px-3 py-2"
                                >
                                    <span class="font-mono text-slate-700 dark:text-slate-300 truncate">{{ table }}</span>
                                    <div class="flex gap-1 shrink-0">
                                        <button
                                            type="button"
                                            class="rounded px-2 py-0.5 text-xs font-medium transition"
                                            :class="extEntityDbForm.table_name === table
                                                ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                                : 'bg-slate-100 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-indigo-900/30'"
                                            :title="'Usar como tabla de activos'"
                                            @click="assignTable(table, 'table_name')"
                                        >
                                            {{ extEntityDbForm.table_name === table ? '✓ activos' : 'activos' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded px-2 py-0.5 text-xs font-medium transition"
                                            :class="extEntityDbForm.areas_table === table
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                : 'bg-slate-100 text-slate-600 hover:bg-emerald-50 hover:text-emerald-600 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-emerald-900/30'"
                                            :title="'Usar como tabla de areas'"
                                            @click="assignTable(table, 'areas_table')"
                                        >
                                            {{ extEntityDbForm.areas_table === table ? '✓ areas' : 'areas' }}
                                        </button>
                                    </div>
                                </li>
                                <li v-if="!dbBrowserTables.length" class="px-3 py-4 text-center text-slate-400 text-sm">Sin tablas</li>
                            </ul>
                            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                                Haz click en <span class="font-semibold text-indigo-600">activos</span> o <span class="font-semibold text-emerald-600">areas</span> para asignar la tabla al campo correspondiente. Los cambios se guardan con el boton "Guardar configuracion".
                            </p>
                        </div>
                    </div>
                </BaseCard>

                <!-- Probar conexion -->
                <BaseCard>
                    <h3 class="mb-3 text-base font-semibold text-slate-900 dark:text-slate-100">Probar conexion</h3>
                    <div class="flex flex-wrap gap-3 items-end">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Codigo de entidad</label>
                            <input v-model="extEntityDbTestEntityCode" type="text" class="app-input w-36" placeholder="802" />
                        </div>
                        <button
                            type="button"
                            class="app-button-secondary"
                            :disabled="extEntityDbTestLoading || !extEntityDbTestEntityCode"
                            @click="testExternalEntityDb"
                        >
                            {{ extEntityDbTestLoading ? 'Conectando...' : 'Probar' }}
                        </button>
                    </div>
                    <div v-if="extEntityDbTestResult" class="mt-3 flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm"
                        :class="extEntityDbTestResult.success
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-700/50 dark:bg-emerald-900/20 dark:text-emerald-300'
                            : 'border-red-200 bg-red-50 text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300'"
                    >
                        <span class="mt-0.5 text-base">{{ extEntityDbTestResult.success ? '✅' : '❌' }}</span>
                        <p class="font-medium">{{ extEntityDbTestResult.message }}</p>
                    </div>
                </BaseCard>

                <!-- Vista previa / sincronizar -->
                <BaseCard>
                    <h3 class="mb-1 text-base font-semibold text-slate-900 dark:text-slate-100">Importar departamentos</h3>
                    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                        Consulta las bases de datos de todas las entidades activas y muestra los departamentos nuevos o desactivados que se podrian importar.
                    </p>
                    <!-- Selector de entidades -->
                    <div v-if="entities_for_sync?.length" class="mb-4">
                        <div class="mb-1 flex items-center justify-between">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                Entidades a consultar
                                <span class="ml-1 text-slate-400 dark:text-slate-500 font-normal">
                                    ({{
                                        extEntityDbAllSelected
                                            ? 'todas'
                                            : extEntityDbSelectedEntities.length === 0
                                                ? 'ninguna'
                                                : `${extEntityDbSelectedEntities.length} de ${entities_for_sync.length}`
                                    }})
                                </span>
                            </span>
                            <div class="flex gap-3 text-xs">
                                <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" @click="selectAllEntities">Todas</button>
                                <button type="button" class="text-slate-500 dark:text-slate-400 hover:underline" @click="deselectAllEntities">Ninguna</button>
                            </div>
                        </div>
                        <div class="max-h-40 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                            <label
                                v-for="entity in entities_for_sync"
                                :key="entity.id"
                                class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-indigo-600 dark:border-slate-600"
                                    :checked="extEntityDbAllSelected || extEntityDbSelectedEntities.includes(entity.id)"
                                    @change="toggleEntitySelection(entity.id)"
                                />
                                <span class="font-mono text-slate-500 dark:text-slate-400 text-xs w-16 shrink-0">{{ entity.code }}</span>
                                <span class="text-slate-700 dark:text-slate-300 truncate">{{ entity.name }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="app-button-secondary"
                            :disabled="extEntityDbPreviewLoading || extEntityDbSyncLoading || (!extEntityDbAllSelected && extEntityDbSelectedEntities.length === 0)"
                            @click="previewExternalEntityDb"
                        >
                            {{ extEntityDbPreviewLoading ? 'Consultando...' : 'Vista previa' }}
                        </button>
                        <button
                            type="button"
                            class="app-button-primary"
                            :disabled="extEntityDbSyncLoading || extEntityDbPreviewLoading || (!extEntityDbAllSelected && extEntityDbSelectedEntities.length === 0)"
                            @click="syncAllExternalEntityDb"
                        >
                            {{ extEntityDbSyncLoading ? 'Sincronizando...' : 'Sincronizar todo' }}
                        </button>
                    </div>

                    <!-- Resultado preview -->
                    <div v-if="extEntityDbPreviewResult" class="mt-4 space-y-4">
                        <div v-if="!extEntityDbPreviewResult.success" class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300">
                            <span class="mt-0.5">❌</span>
                            <p class="font-medium">{{ extEntityDbPreviewResult.message }}</p>
                        </div>

                        <template v-else>
                            <!-- Errores por entidad -->
                            <div v-if="Object.keys(extEntityDbPreviewResult.errors ?? {}).length" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-300">
                                <p class="font-semibold mb-1">Entidades con errores de conexion:</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li v-for="(msg, code) in extEntityDbPreviewResult.errors" :key="code">
                                        <span class="font-mono">{{ code }}</span>: {{ msg }}
                                    </li>
                                </ul>
                            </div>

                            <div v-if="extEntityDbSelectedChanges.length === 0" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-700/50 dark:bg-emerald-900/20 dark:text-emerald-300">
                                Sin cambios pendientes — todos los departamentos estan actualizados.
                            </div>

                            <template v-else>
                                <div class="overflow-hidden rounded-xl border border-slate-200/80 dark:border-slate-700/60">
                                    <table class="w-full text-sm">
                                        <thead class="bg-slate-50 dark:bg-slate-800">
                                            <tr>
                                                <th class="w-10 px-3 py-2">
                                                    <input
                                                        type="checkbox"
                                                        :checked="extEntityDbSelectedChanges.every((r) => r._selected)"
                                                        class="h-4 w-4 rounded border-slate-300 text-brand-600"
                                                        @change="(e) => extEntityDbSelectedChanges.forEach((r) => r._selected = e.target.checked)"
                                                    />
                                                </th>
                                                <th class="px-3 py-2 text-left font-medium text-slate-600 dark:text-slate-300">Accion</th>
                                                <th class="px-3 py-2 text-left font-medium text-slate-600 dark:text-slate-300">Entidad</th>
                                                <th class="px-3 py-2 text-left font-medium text-slate-600 dark:text-slate-300">Cod. area</th>
                                                <th class="px-3 py-2 text-left font-medium text-slate-600 dark:text-slate-300">Nombre</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200/80 dark:divide-slate-700/60">
                                            <tr v-for="(rec, idx) in extEntityDbSelectedChanges" :key="idx"
                                                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30"
                                                :class="!rec._selected ? 'opacity-50' : ''"
                                            >
                                                <td class="px-3 py-2">
                                                    <input type="checkbox" v-model="rec._selected" class="h-4 w-4 rounded border-slate-300 text-brand-600" />
                                                </td>
                                                <td class="px-3 py-2">
                                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                                        :class="{
                                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400': rec.action === 'create',
                                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': rec.action === 'reactivate',
                                                            'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': rec.action === 'update_name',
                                                        }"
                                                    >
                                                        {{ rec.action === 'create' ? 'Crear' : rec.action === 'reactivate' ? 'Reactivar' : 'Actualizar nombre' }}
                                                    </span>
                                                    <template v-if="rec.action === 'update_name'">
                                                        <p class="text-xs text-slate-400 mt-0.5">Antes: {{ rec.current_name }}</p>
                                                    </template>
                                                </td>
                                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300 text-xs">{{ rec._entity_name }}</td>
                                                <td class="px-3 py-2 font-mono text-xs text-slate-500 dark:text-slate-400">{{ rec.codigo_area }}</td>
                                                <td class="px-3 py-2 text-slate-800 dark:text-slate-200">{{ rec.name }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="flex items-center justify-between">
                                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ extEntityDbSelectedCount }} de {{ extEntityDbSelectedChanges.length }} seleccionado(s)</p>
                                    <button
                                        type="button"
                                        class="app-button-primary"
                                        :disabled="extEntityDbApplyLoading || extEntityDbSelectedCount === 0"
                                        @click="applySelectedEntityDbChanges"
                                    >
                                        {{ extEntityDbApplyLoading ? 'Aplicando...' : `Aplicar ${extEntityDbSelectedCount} cambio(s) seleccionados` }}
                                    </button>
                                </div>
                            </template>
                        </template>
                    </div>

                    <!-- Resultado apply -->
                    <div v-if="extEntityDbApplyResult" class="mt-4 flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm"
                        :class="extEntityDbApplyResult.success
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-700/50 dark:bg-emerald-900/20 dark:text-emerald-300'
                            : 'border-red-200 bg-red-50 text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300'"
                    >
                        <span class="mt-0.5 text-base">{{ extEntityDbApplyResult.success ? '✅' : '❌' }}</span>
                        <p class="font-medium">{{ extEntityDbApplyResult.message }}</p>
                    </div>

                    <!-- Resultado sync -->
                    <div v-if="extEntityDbSyncResult" class="mt-4 flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm"
                        :class="extEntityDbSyncResult.success
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-700/50 dark:bg-emerald-900/20 dark:text-emerald-300'
                            : 'border-red-200 bg-red-50 text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300'"
                    >
                        <span class="mt-0.5 text-base">{{ extEntityDbSyncResult.success ? '✅' : '❌' }}</span>
                        <p class="font-medium">{{ extEntityDbSyncResult.message }}</p>
                    </div>

                    <!-- Ultima sincronizacion -->
                    <div v-if="external_entity_db?.last_synced" class="mt-4 text-xs text-slate-400 dark:text-slate-500">
                        Ultima sincronizacion: {{ external_entity_db.last_synced }}
                        <template v-if="extEntityDbLastSummary">
                            — {{ extEntityDbLastSummary.created ?? 0 }} creados, {{ extEntityDbLastSummary.reactivated ?? 0 }} reactivados
                        </template>
                    </div>
                </BaseCard>
            </div>

            <!-- ── Pestaña BD Almacenes ── -->
            <div v-show="activeTab === 'conexiones' && activeIntegrationTab === 'external_almacenes'" class="space-y-6">

                <!-- Última sincronización -->
                <BaseCard v-if="almacenesLastSummary">
                    <div class="flex flex-wrap items-center gap-4">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Última sincronización:</span>
                        <span class="text-sm text-slate-600 dark:text-slate-400">{{ external_almacenes?.last_synced }}</span>
                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                            {{ almacenesLastSummary.created ?? 0 }} creadas
                        </span>
                        <span class="rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">
                            {{ almacenesLastSummary.updated ?? 0 }} actualizadas
                        </span>
                    </div>
                </BaseCard>

                <!-- Configuración de conexión -->
                <BaseCard>
                    <h3 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">BD Almacenes — Conexión SQL Server</h3>
                    <p class="mb-5 text-sm text-slate-600 dark:text-slate-400">
                        Conecta con la base de datos contable que contiene la tabla de almacenes. Es la fuente autoritativa de nombres e identificadores de áreas de venta.
                    </p>

                    <form class="space-y-5" @submit.prevent="saveExternalAlmacenes">
                        <!-- Habilitar -->
                        <label class="flex cursor-pointer items-center gap-3">
                            <div
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                :class="extAlmacenesForm.enabled ? 'bg-cyan-500' : 'bg-slate-300 dark:bg-slate-600'"
                                @click="extAlmacenesForm.enabled = !extAlmacenesForm.enabled"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition"
                                    :class="extAlmacenesForm.enabled ? 'translate-x-6' : 'translate-x-1'"
                                />
                            </div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Habilitar conexión</span>
                        </label>

                        <!-- Host + Puerto -->
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="sm:col-span-2 space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Host</label>
                                <input v-model="extAlmacenesForm.host" type="text" class="app-input" placeholder="192.168.1.100" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Puerto</label>
                                <input v-model.number="extAlmacenesForm.port" type="number" class="app-input" />
                            </div>
                        </div>

                        <!-- Base de datos + Esquema -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Base de datos</label>
                                <input v-model="extAlmacenesForm.database_name" type="text" class="app-input" placeholder="UnidadesComerciales" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Esquema</label>
                                <input v-model="extAlmacenesForm.schema_name" type="text" class="app-input" placeholder="dbo" />
                            </div>
                        </div>

                        <!-- Usuario + Contraseña -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Usuario</label>
                                <input v-model="extAlmacenesForm.username" type="text" class="app-input" autocomplete="off" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Contraseña
                                    <span v-if="external_almacenes?.has_password" class="ml-1 text-xs text-slate-400">(guardada — dejar en blanco para no cambiar)</span>
                                </label>
                                <input v-model="extAlmacenesForm.password" type="password" class="app-input" autocomplete="new-password" placeholder="••••••••" />
                            </div>
                        </div>

                        <!-- Timeout -->
                        <div class="w-32 space-y-1">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Timeout (seg)</label>
                            <input v-model.number="extAlmacenesForm.timeout" type="number" min="1" max="60" class="app-input" />
                        </div>

                        <button type="submit" class="app-button-primary" :disabled="extAlmacenesForm.processing">
                            {{ extAlmacenesForm.processing ? 'Guardando...' : 'Guardar configuración' }}
                        </button>
                    </form>
                </BaseCard>

                <!-- Mapeo de columnas -->
                <BaseCard>
                    <h3 class="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Tabla y columnas</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tabla</label>
                            <input v-model="extAlmacenesForm.table_name" type="text" class="app-input" placeholder="Almacenes" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Columna PK (IdGerenciaIdAlmacen)</label>
                            <input v-model="extAlmacenesForm.id_almacen_pk_column" type="text" class="app-input" placeholder="IdGerenciaIdAlmacen" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Columna IdUnidad</label>
                            <input v-model="extAlmacenesForm.id_unidad_column" type="text" class="app-input" placeholder="IdUnidad" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Columna Nombre Almacén</label>
                            <input v-model="extAlmacenesForm.almacen_column" type="text" class="app-input" placeholder="Almacen" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Columna IdPiso</label>
                            <input v-model="extAlmacenesForm.id_piso_column" type="text" class="app-input" placeholder="IdPiso" />
                        </div>
                    </div>
                </BaseCard>

                <!-- Criterios de importación -->
                <BaseCard>
                    <h3 class="mb-1 text-base font-semibold text-slate-900 dark:text-slate-100">Criterios de importación</h3>
                    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Define qué almacenes se importarán. Solo se traerán los que cumplan los criterios marcados.</p>

                    <div class="space-y-4">
                        <!-- Solo abiertos -->
                        <label class="flex cursor-pointer items-center gap-3">
                            <div
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                :class="extAlmacenesForm.import_solo_abierto ? 'bg-cyan-500' : 'bg-slate-300 dark:bg-slate-600'"
                                @click="extAlmacenesForm.import_solo_abierto = !extAlmacenesForm.import_solo_abierto"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition"
                                    :class="extAlmacenesForm.import_solo_abierto ? 'translate-x-6' : 'translate-x-1'"
                                />
                            </div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Solo almacenes abiertos al público (<code class="text-xs bg-slate-100 dark:bg-slate-700 px-1 rounded">Abierto = 1</code>)</span>
                        </label>

                        <!-- Tipos de almacén -->
                        <div>
                            <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                                Tipos de almacén a importar
                                <span class="ml-1 text-xs font-normal text-slate-400">(se importan si cumplen al menos uno — dejar vacío para no filtrar por tipo)</span>
                            </p>
                            <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                                <label
                                    v-for="flag in (external_almacenes?.tipo_flags ?? [])"
                                    :key="flag"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition"
                                    :class="(extAlmacenesForm.import_tipos ?? []).includes(flag)
                                        ? 'border-brand-300 bg-brand-50 dark:border-brand-700/50 dark:bg-brand-900/20'
                                        : 'border-slate-200/80 dark:border-slate-700/60'"
                                    @click="toggleImportTipo(flag)"
                                >
                                    <span
                                        class="flex h-4 w-4 shrink-0 items-center justify-center rounded border-2 transition"
                                        :class="(extAlmacenesForm.import_tipos ?? []).includes(flag)
                                            ? 'border-brand-500 bg-brand-500 text-white'
                                            : 'border-slate-300 dark:border-slate-600'"
                                    >
                                        <svg v-if="(extAlmacenesForm.import_tipos ?? []).includes(flag)" class="h-2.5 w-2.5" fill="none" viewBox="0 0 10 8">
                                            <path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M1 4l3 3 5-6"/>
                                        </svg>
                                    </span>
                                    {{ flag }}
                                </label>
                            </div>
                        </div>

                        <!-- Crear nuevas -->
                        <label class="flex cursor-pointer items-center gap-3">
                            <div
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                :class="extAlmacenesForm.sync_creates_areas ? 'bg-cyan-500' : 'bg-slate-300 dark:bg-slate-600'"
                                @click="extAlmacenesForm.sync_creates_areas = !extAlmacenesForm.sync_creates_areas"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition"
                                    :class="extAlmacenesForm.sync_creates_areas ? 'translate-x-6' : 'translate-x-1'"
                                />
                            </div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Crear nuevas áreas de venta si no existen</span>
                        </label>
                    </div>
                </BaseCard>

                <!-- Acciones: probar + ver datos crudos -->
                <BaseCard>
                    <h3 class="mb-3 text-base font-semibold text-slate-900 dark:text-slate-100">Probar conexión</h3>
                    <div class="flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="app-button-secondary"
                            :disabled="almacenesTestLoading"
                            @click="testExternalAlmacenes"
                        >
                            {{ almacenesTestLoading ? 'Conectando...' : 'Probar conexión' }}
                        </button>
                    </div>
                    <div v-if="almacenesTestResult" class="mt-3 flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm"
                        :class="almacenesTestResult.success
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-700/50 dark:bg-emerald-900/20 dark:text-emerald-300'
                            : 'border-red-200 bg-red-50 text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300'"
                    >
                        <span class="mt-0.5 text-base">{{ almacenesTestResult.success ? '✅' : '❌' }}</span>
                        <p class="font-medium">{{ almacenesTestResult.message }}</p>
                    </div>
                </BaseCard>

                <!-- Ver almacenes crudos -->
                <BaseCard>
                    <h3 class="mb-3 text-base font-semibold text-slate-900 dark:text-slate-100">Ver almacenes (datos crudos)</h3>
                    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Muestra hasta 300 registros con los filtros configurados. Útil para inspeccionar antes de sincronizar.</p>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Filtrar por IdUnidad (opcional)</label>
                            <input v-model="almacenesRawIdUnidad" type="number" class="app-input w-32" placeholder="Ej: 114" />
                        </div>
                        <button
                            type="button"
                            class="app-button-secondary"
                            :disabled="almacenesRawLoading"
                            @click="fetchRawAlmacenes"
                        >
                            {{ almacenesRawLoading ? 'Cargando...' : 'Ver almacenes' }}
                        </button>
                    </div>
                    <div v-if="almacenesRawData.length > 0" class="mt-4 overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500">
                                    <th class="pb-2 pr-3 font-medium">ID</th>
                                    <th class="pb-2 pr-3 font-medium">IdUnidad</th>
                                    <th class="pb-2 pr-3 font-medium">Almacén</th>
                                    <th class="pb-2 pr-3 font-medium">IdPiso</th>
                                    <th class="pb-2 pr-3 font-medium">Abierto</th>
                                    <th class="pb-2 pr-3 font-medium">MLC</th>
                                    <th class="pb-2 font-medium">Tipo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="row in almacenesRawData" :key="row.almacen_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    <td class="py-1.5 pr-3 font-mono text-slate-500">{{ row.almacen_id }}</td>
                                    <td class="py-1.5 pr-3 font-mono text-slate-500">{{ row.id_unidad }}</td>
                                    <td class="py-1.5 pr-3 text-slate-800 dark:text-slate-200 font-medium">{{ row.almacen }}</td>
                                    <td class="py-1.5 pr-3 font-mono text-slate-500">{{ row.id_piso ?? '—' }}</td>
                                    <td class="py-1.5 pr-3">
                                        <span :class="row.abierto ? 'text-emerald-600 font-semibold' : 'text-slate-400'">{{ row.abierto ? 'Sí' : 'No' }}</span>
                                    </td>
                                    <td class="py-1.5 pr-3">
                                        <span :class="row.mlc ? 'text-amber-600 font-semibold' : 'text-slate-400'">{{ row.mlc ? 'MLC' : '—' }}</span>
                                    </td>
                                    <td class="py-1.5">
                                        <span v-if="row.tipo" class="rounded-full bg-brand-100 px-2 py-0.5 text-xs text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">{{ row.tipo }}</span>
                                        <span v-else class="text-slate-400">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="mt-2 text-xs text-slate-400">Mostrando {{ almacenesRawData.length }} registros.</p>
                    </div>
                    <p v-else-if="!almacenesRawLoading && almacenesRawData.length === 0 && almacenesRawIdUnidad !== ''" class="mt-3 text-sm text-slate-400">Sin resultados con los criterios actuales.</p>
                </BaseCard>

                <!-- Sincronización por piso -->
                <BaseCard>
                    <h3 class="mb-1 text-base font-semibold text-slate-900 dark:text-slate-100">Sincronización por piso de venta</h3>
                    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                        Selecciona un piso de venta. El sistema buscará sus almacenes en la BD externa usando el <code class="text-xs bg-slate-100 dark:bg-slate-700 px-1 rounded">datacell_unit_id</code> del piso como <code class="text-xs bg-slate-100 dark:bg-slate-700 px-1 rounded">IdUnidad</code>.
                    </p>

                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- Selector de piso -->
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Piso de venta</label>
                                <select v-model="almacenesSelectedFloorId" class="app-input">
                                    <option value="">Seleccione un piso...</option>
                                    <option v-for="floor in entities_for_sync" :key="'f-'+floor.id" :value="floor.id">
                                        {{ floor.name }}
                                    </option>
                                </select>
                            </div>
                            <!-- Override IdUnidad -->
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    IdUnidad manual
                                    <span class="ml-1 text-xs font-normal text-slate-400">(solo si el piso no tiene datacell_unit_id)</span>
                                </label>
                                <input v-model="almacenesOverrideIdUnidad" type="number" class="app-input" placeholder="Ej: 114" />
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <button
                                type="button"
                                class="app-button-secondary"
                                :disabled="almacenesPreviewLoading || !almacenesSelectedFloorId"
                                @click="buildAlmacenesPreview"
                            >
                                {{ almacenesPreviewLoading ? 'Generando preview...' : 'Preview sync' }}
                            </button>
                        </div>

                        <!-- Error de preview -->
                        <div v-if="almacenesPreviewData && !almacenesPreviewData.success" class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300">
                            <span class="mt-0.5">❌</span>
                            <p class="font-medium">{{ almacenesPreviewData.message }}</p>
                        </div>

                        <!-- Resultados del preview -->
                        <template v-if="almacenesPreviewData?.success">
                            <div class="flex flex-wrap gap-2 text-sm">
                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                    {{ almacenesPreviewData.totals?.create ?? 0 }} a crear
                                </span>
                                <span class="rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">
                                    {{ almacenesPreviewData.totals?.update ?? 0 }} a actualizar
                                </span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                    {{ almacenesPreviewData.totals?.skip ?? 0 }} sin cambios
                                </span>
                                <span v-if="almacenesPreviewData.totals?.skip_no_local > 0" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                    {{ almacenesPreviewData.totals.skip_no_local }} omitidos (no hay área local)
                                </span>
                            </div>

                            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                                <table class="w-full text-xs">
                                    <thead class="bg-slate-50 dark:bg-slate-800/50">
                                        <tr class="text-left text-slate-500">
                                            <th class="px-3 py-2 font-medium w-8">
                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300 text-brand-600"
                                                    :checked="almacenesSelectedItems.length > 0 && almacenesSelectedItems.length === (almacenesPreviewData.items?.filter(i => i.action === 'create' || i.action === 'update').length ?? 0)"
                                                    @change="(e) => {
                                                        if (e.target.checked) {
                                                            almacenesSelectedItems = (almacenesPreviewData.items ?? []).filter(i => i.action === 'create' || i.action === 'update').map(i => ({...i}))
                                                        } else {
                                                            almacenesSelectedItems = []
                                                        }
                                                    }"
                                                />
                                            </th>
                                            <th class="px-3 py-2 font-medium">Almacén (remoto)</th>
                                            <th class="px-3 py-2 font-medium">ID</th>
                                            <th class="px-3 py-2 font-medium">Tipo</th>
                                            <th class="px-3 py-2 font-medium">Abierto</th>
                                            <th class="px-3 py-2 font-medium">MLC</th>
                                            <th class="px-3 py-2 font-medium">Acción</th>
                                            <th class="px-3 py-2 font-medium">Área local actual</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <tr v-for="item in almacenesPreviewData.items" :key="item.almacen_id + '-' + item.action"
                                            class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                            :class="item.action === 'skip' || item.action === 'skip_no_local' ? 'opacity-50' : ''"
                                        >
                                            <td class="px-3 py-2">
                                                <input
                                                    v-if="item.action === 'create' || item.action === 'update'"
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300 text-brand-600"
                                                    :checked="isAlmacenItemSelected(item)"
                                                    @change="toggleAlmacenItem(item)"
                                                />
                                            </td>
                                            <td class="px-3 py-2 font-medium text-slate-800 dark:text-slate-200">{{ item.almacen_nombre }}</td>
                                            <td class="px-3 py-2 font-mono text-slate-500">{{ item.almacen_id }}</td>
                                            <td class="px-3 py-2">
                                                <span v-if="item.almacen_tipo" class="rounded-full bg-brand-100 px-2 py-0.5 text-xs text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">{{ item.almacen_tipo }}</span>
                                                <span v-else class="text-slate-400">—</span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span :class="item.almacen_abierto ? 'text-emerald-600 font-semibold' : 'text-slate-400'">{{ item.almacen_abierto ? 'Sí' : 'No' }}</span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span :class="item.almacen_mlc ? 'text-amber-600 font-semibold' : 'text-slate-400'">{{ item.almacen_mlc ? 'MLC' : '—' }}</span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span v-if="item.action === 'create'" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Crear</span>
                                                <span v-else-if="item.action === 'update'" class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">Actualizar</span>
                                                <span v-else-if="item.action === 'skip_no_local'" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">Omitir</span>
                                                <span v-else class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500">Sin cambios</span>
                                            </td>
                                            <td class="px-3 py-2 text-slate-500">{{ item.local_area_name ?? '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div v-if="almacenesSelectedItems.length > 0" class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="app-button-primary"
                                    :disabled="almacenesApplyLoading"
                                    @click="applyAlmacenesSync"
                                >
                                    {{ almacenesApplyLoading ? 'Aplicando...' : `Aplicar ${almacenesSelectedItems.length} cambio(s)` }}
                                </button>
                            </div>

                            <!-- Resultado apply -->
                            <div v-if="almacenesApplyResult" class="flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm"
                                :class="almacenesApplyResult.success
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-700/50 dark:bg-emerald-900/20 dark:text-emerald-300'
                                    : 'border-red-200 bg-red-50 text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300'"
                            >
                                <span class="mt-0.5 text-base">{{ almacenesApplyResult.success ? '✅' : '❌' }}</span>
                                <div>
                                    <p class="font-medium">
                                        {{ almacenesApplyResult.success
                                            ? `Listo: ${almacenesApplyResult.created ?? 0} áreas creadas, ${almacenesApplyResult.updated ?? 0} actualizadas.`
                                            : (almacenesApplyResult.message ?? 'Error al aplicar.') }}
                                    </p>
                                    <ul v-if="(almacenesApplyResult.errors ?? []).length > 0" class="mt-1 list-disc list-inside text-xs opacity-80">
                                        <li v-for="(err, i) in almacenesApplyResult.errors" :key="i">{{ err }}</li>
                                    </ul>
                                </div>
                            </div>
                        </template>
                    </div>
                </BaseCard>
            </div>

            <Teleport to="body">
                <div v-if="showMapModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="showMapModal = false" />
                    <div class="relative w-full max-w-3xl rounded-2xl bg-white dark:bg-slate-800 shadow-2xl">
                        <div class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-700/60">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Mapeo de columnas SQL Server</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Selecciona qué columnas externas se usan para cada campo interno.</p>
                        </div>
                        <div class="max-h-[70vh] overflow-y-auto px-5 py-4 space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">CI</label>
                                    <select v-model="mappingDraft.ci_column" class="app-input">
                                        <option value="">Seleccione...</option>
                                        <option v-for="c in extCiColumns" :key="'ci-'+c" :value="c">{{ c }}</option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nombre</label>
                                    <select v-model="mappingDraft.nombre_column" class="app-input">
                                        <option value="">Seleccione...</option>
                                        <option v-for="c in extCiColumns" :key="'nom-'+c" :value="c">{{ c }}</option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Apellido 1</label>
                                    <select v-model="mappingDraft.apellido1_column" class="app-input">
                                        <option value="">Seleccione...</option>
                                        <option v-for="c in extCiColumns" :key="'a1-'+c" :value="c">{{ c }}</option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Apellido 2</label>
                                    <select v-model="mappingDraft.apellido2_column" class="app-input">
                                        <option value="">Seleccione...</option>
                                        <option v-for="c in extCiColumns" :key="'a2-'+c" :value="c">{{ c }}</option>
                                    </select>
                                </div>
                                <div class="space-y-1 sm:col-span-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Teléfono</label>
                                    <select v-model="mappingDraft.telefono_column" class="app-input">
                                        <option value="">Seleccione...</option>
                                        <option v-for="c in extCiColumns" :key="'tel-'+c" :value="c">{{ c }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Dirección Particular (múltiples columnas)</label>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label v-for="c in extCiColumns" :key="'dir-'+c" class="flex items-center gap-2 rounded-lg border border-slate-200/80 px-3 py-2 dark:border-slate-700/60">
                                        <input
                                            type="checkbox"
                                            :value="c"
                                            :checked="mappingDraft.direccion_columns.includes(c)"
                                            @change="(e) => {
                                                if (e.target.checked) {
                                                    if (!mappingDraft.direccion_columns.includes(c)) mappingDraft.direccion_columns.push(c)
                                                } else {
                                                    mappingDraft.direccion_columns = mappingDraft.direccion_columns.filter((x) => x !== c)
                                                }
                                            }"
                                            class="h-4 w-4 rounded border-slate-300 text-brand-600"
                                        />
                                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ c }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-slate-200/80 px-5 py-4 dark:border-slate-700/60">
                            <button type="button" class="app-button-secondary" @click="showMapModal = false">Cancelar</button>
                            <button type="button" class="app-button-primary" @click="applyColumnMapping">Aplicar mapeo</button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </AppLayout>
</template>
