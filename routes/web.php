<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\AidaImportController;
use App\Http\Controllers\EquipmentFileController;
use App\Http\Controllers\LevantamientoEquiposImportController;
use App\Http\Controllers\IncidentTypeController;
use App\Http\Controllers\InspectionRecordController;
use App\Http\Controllers\MunicipioController;
use App\Http\Controllers\MonedaController;
use App\Http\Controllers\ProvinciaController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\ComponentTypeController;
use App\Http\Controllers\ComponentModelController;
use App\Http\Controllers\AdslModeController;
use App\Http\Controllers\ContractedSpeedController;
use App\Http\Controllers\ConnectivityNetworkToolController;
use App\Http\Controllers\ConnectivityRecordController;
use App\Http\Controllers\FincimexController;
use App\Http\Controllers\CashRegisterModelController;
use App\Http\Controllers\NetworkTypeController;
use App\Http\Controllers\EstablishmentTypeController;
use App\Http\Controllers\EstablishmentStatusController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AreaVentaController;
use App\Http\Controllers\AreaVentaImportController;
use App\Http\Controllers\SalesFloorAreaQrController;
use App\Http\Controllers\SalesFloorController;
use App\Http\Controllers\SecurityIncidentRecordController;
use App\Http\Controllers\SealController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\SupportControlRecordController;
use App\Http\Controllers\SupportReportController;
use App\Http\Controllers\CanalElectronicoController;
use App\Http\Controllers\CodigosQrTrabajadorController;
use App\Http\Controllers\DatacellController;
use App\Http\Controllers\TrabajadorController;
use App\Http\Controllers\TipoFuenteController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\WorkSheetRecordController;
use App\Http\Controllers\WorksheetAspectController;
use App\Http\Controllers\EtecsaFacturaController;
use App\Http\Controllers\EtecsaServicioController;
use App\Http\Controllers\EtecsaDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // ─────────────────────────────────────────────────────────────────────
    // WORKSPACE
    // ─────────────────────────────────────────────────────────────────────
    // Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Expedientes
    Route::middleware('module_enabled:expedientes')->group(function () {
        Route::get('/expedientes/alertas', [EquipmentFileController::class, 'alertsIndex'])->name('expedientes.alertas');
        Route::post('/expedientes/aida-parse', [AidaImportController::class, 'parse'])->name('expedientes.aida-parse');
        // Fuera de expedientes/* para evitar 404 por caché/proxy y colisiones con expedientes/{expediente}.
        Route::post('/levantamiento-equipos/vista-previa', [LevantamientoEquiposImportController::class, 'preview'])->name('expedientes.levantamiento-preview');
        Route::post('/levantamiento-equipos/aplicar', [LevantamientoEquiposImportController::class, 'apply'])->name('expedientes.levantamiento-aplicar');
        Route::get('/expedientes/buscar', [EquipmentFileController::class, 'search'])->name('expedientes.search');
        Route::get('/expedientes/estadisticas', [EquipmentFileController::class, 'statistics'])->name('expedientes.statistics');
        Route::get('/expedientes/exportar', [EquipmentFileController::class, 'export'])->name('expedientes.export');
        Route::post('/expedientes/lookup-inventory', [EquipmentFileController::class, 'lookupInventory'])->name('expedientes.lookup-inventory');
        Route::post('/expedientes/buscar-responsable-trabajadores', [EquipmentFileController::class, 'searchResponsibleTrabajadores'])->name('expedientes.buscar-responsable-trabajadores');
        Route::post('/expedientes/buscar-responsable-ldap', [EquipmentFileController::class, 'searchResponsibleLdap'])->name('expedientes.buscar-responsable-ldap');
        Route::post('/expedientes/validar-medio-inventario', [EquipmentFileController::class, 'validateMedioInventory'])->name('expedientes.validar-medio-inventario');
        Route::post('/expedientes/{expediente}/mover', [EquipmentFileController::class, 'move'])->name('expedientes.move');
        Route::resource('expedientes', EquipmentFileController::class)->parameters([
            'expedientes' => 'expediente',
        ]);
    });
    Route::middleware('module_enabled:conectividad')->group(function () {
        // FINCIMEX (Áreas de Venta)
        Route::post('/fincimex/importar-preview', [FincimexController::class, 'preview'])->name('fincimex.importar.preview');
        Route::post('/fincimex/importar-aplicar', [FincimexController::class, 'applySelected'])->name('fincimex.importar.apply');
        Route::get('/fincimex/exportar', [FincimexController::class, 'export'])->name('fincimex.export');
        Route::resource('fincimex', FincimexController::class)->parameters([
            'fincimex' => 'fincimex',
        ])->only(['index', 'store', 'update', 'destroy']);
        Route::post('/conectividad/importar-preview', [ConnectivityRecordController::class, 'preview'])->name('conectividad.importar.preview');
        Route::post('/conectividad/importar-aplicar', [ConnectivityRecordController::class, 'applySelected'])->name('conectividad.importar.apply');
        Route::get('/conectividad/entidades-buscar', [ConnectivityRecordController::class, 'searchEntitiesForImport'])->name('conectividad.entidades.search');
        Route::post('/conectividad/importar-vincular-entidad', [ConnectivityRecordController::class, 'rebindEntityForImportPreview'])->name('conectividad.importar.vincularEntidad');
        Route::get('/conectividad/exportar', [ConnectivityRecordController::class, 'export'])->name('conectividad.export');
        Route::get('/conectividad/comprobaciones', [ConnectivityRecordController::class, 'comprobaciones'])->name('conectividad.comprobaciones');
        Route::get('/conectividad/comprobaciones/registro', [ConnectivityRecordController::class, 'comprobacionesRegistro'])->name('conectividad.comprobaciones.registro');
        Route::post('/conectividad/red/analizar', [ConnectivityNetworkToolController::class, 'analyze'])->name('conectividad.red.analizar');
        Route::post('/conectividad/red/ping', [ConnectivityNetworkToolController::class, 'ping'])->name('conectividad.red.ping');
        Route::resource('conectividad', ConnectivityRecordController::class)->parameters([
            'conectividad' => 'conectividade',
        ])->except(['create', 'show', 'edit']);

        Route::get('pisos-venta/buscar', [SalesFloorController::class, 'search'])->name('pisos-venta.search');
        Route::get('pisos-venta/areas-qr', [SalesFloorAreaQrController::class, 'index'])->name('pisos-venta.areas-qr.index');
        Route::get('pisos-venta/areas-qr/buscar-fuentes', [SalesFloorAreaQrController::class, 'searchFuentes'])->name('pisos-venta.areas-qr.buscar-fuentes');
        Route::post('pisos-venta/areas-qr/vinculos', [SalesFloorAreaQrController::class, 'storeLink'])->name('pisos-venta.areas-qr.vinculos.store');
        Route::delete('pisos-venta/areas-qr/vinculos/area/{vinculo}', [SalesFloorAreaQrController::class, 'destroyAreaLink'])->name('pisos-venta.areas-qr.vinculos-area.destroy');
        Route::delete('pisos-venta/areas-qr/vinculos/piso/{vinculo}', [SalesFloorAreaQrController::class, 'destroyPisoLink'])->name('pisos-venta.areas-qr.vinculos-piso.destroy');
        Route::resource('pisos-venta', SalesFloorController::class)->parameters([
            'pisos-venta' => 'pisoVenta',
        ])->except(['create', 'show', 'edit']);

        Route::resource('areas-venta', AreaVentaController::class)->parameters([
            'areas-venta' => 'areaVenta',
        ])->except(['create', 'show', 'edit']);
        Route::post('areas-venta/importar-analizar', [AreaVentaImportController::class, 'analyze'])->name('areas-venta.importar.analizar');
        Route::post('areas-venta/importar-preview', [AreaVentaImportController::class, 'preview'])->name('areas-venta.importar.preview');
        Route::post('areas-venta/importar-aplicar', [AreaVentaImportController::class, 'apply'])->name('areas-venta.importar.aplicar');

        Route::resource('modelos-cajas', CashRegisterModelController::class)->parameters([
            'modelos-cajas' => 'modeloCaja',
        ])->except(['create', 'show', 'edit']);

        Route::resource('tipos-red', NetworkTypeController::class)->parameters([
            'tipos-red' => 'tipoRed',
        ])->except(['create', 'show', 'edit']);

        Route::resource('tipos-establecimiento', EstablishmentTypeController::class)->parameters([
            'tipos-establecimiento' => 'tipoEstablecimiento',
        ])->except(['create', 'show', 'edit']);

        Route::resource('estados-establecimiento', EstablishmentStatusController::class)->parameters([
            'estados-establecimiento' => 'estadoEstablecimiento',
        ])->except(['create', 'show', 'edit']);

        Route::resource('modos-adsl', AdslModeController::class)->parameters([
            'modos-adsl' => 'modoAdsl',
        ])->except(['create', 'show', 'edit']);

        Route::resource('velocidades', ContractedSpeedController::class)->parameters([
            'velocidades' => 'velocidade',
        ])->except(['create', 'show', 'edit']);
    });

    Route::resource('inspecciones', InspectionRecordController::class)->parameters([
        'inspecciones' => 'inspeccione',
    ])->except(['create', 'show', 'edit']);
    Route::resource('hojas-trabajo', WorkSheetRecordController::class)->parameters([
        'hojas-trabajo' => 'hojas_trabajo',
    ])->except(['create', 'show', 'edit']);
    Route::resource('incidencias-seguridad', SecurityIncidentRecordController::class)->parameters([
        'incidencias-seguridad' => 'incidencias_seguridad',
    ])->except(['create', 'show', 'edit']);
    Route::resource('control-soportes', SupportControlRecordController::class)->parameters([
        'control-soportes' => 'control_soporte',
    ])->except(['create', 'show', 'edit']);

    // Reportes
    Route::resource('reportes', SupportReportController::class)->parameters([
        'reportes' => 'reporte',
    ])->except(['create', 'show', 'edit']);
    Route::get('/reportes/exportar', [SupportReportController::class, 'export'])->name('reportes.export');
    Route::get('/reportes-incidencias', [ReportController::class, 'index'])->name('reportes.incidencias');
    Route::get('/reportes/expediente-pdf/{expediente}', [ReportController::class, 'expedientePdf'])
        ->name('reportes.expedientePdf');

    // Trabajadores (workspace)
    Route::resource('trabajadores', TrabajadorController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['trabajadores' => 'trabajador']);
    Route::get('/trabajadores/buscar/{ci}', [TrabajadorController::class, 'buscarPorCi'])->name('trabajadores.buscarPorCi');
    Route::post('/trabajadores/buscar-ad', [TrabajadorController::class, 'buscarPorAd'])->name('trabajadores.buscarPorAd');
    Route::post('/trabajadores/crear-desde-ad', [TrabajadorController::class, 'crearDesdeAd'])->name('trabajadores.crearDesdeAd');
    Route::post('/trabajadores/{trabajador}/vincular-ci', [TrabajadorController::class, 'vincularCi'])->name('trabajadores.vincularCi');
    Route::post('/trabajadores/{trabajador}/vincular-ad', [TrabajadorController::class, 'vincularAd'])->name('trabajadores.vincularAd');
    Route::post('/trabajadores/{id}/reactivar', [TrabajadorController::class, 'reactivar'])
        ->whereNumber('id')
        ->name('trabajadores.reactivar');
    Route::delete('/trabajadores/{id}/forzar-eliminacion', [TrabajadorController::class, 'forceDestroy'])
        ->whereNumber('id')
        ->name('trabajadores.forceDestroy');

    Route::middleware('module_enabled:codigos-qr')->group(function () {
        // Códigos QR (workspace)
        Route::get('/codigos-qr', [DatacellController::class, 'index'])->name('codigos-qr.index');
        // Tres segmentos para no colisionar con codigos-qr/{source} (PUT/DELETE) si el orden o la cache de rutas difiere.
        Route::get('/codigos-qr/exportar/trabajadores-excel', [DatacellController::class, 'exportTrabajadoresExcel'])->name('codigos-qr.export-trabajadores-excel');
        Route::get('/codigos-qr/create', [DatacellController::class, 'create'])->name('codigos-qr.create');
        Route::post('/codigos-qr', [DatacellController::class, 'store'])->name('codigos-qr.store');
        Route::post('/codigos-qr/import-json', [DatacellController::class, 'importJson'])->name('codigos-qr.import-json');
        Route::get('/codigos-qr/pisos', [DatacellController::class, 'salesFloors'])->name('codigos-qr.salesFloors');
        Route::get('/codigos-qr/{source}/edit', [DatacellController::class, 'edit'])->name('codigos-qr.edit');
        Route::put('/codigos-qr/{source}', [DatacellController::class, 'update'])->name('codigos-qr.update');
        Route::delete('/codigos-qr/{source}', [DatacellController::class, 'destroy'])->name('codigos-qr.destroy');
        Route::put('/codigos-qr/{source}/link', [DatacellController::class, 'link'])->name('codigos-qr.link');

        // Trabajadores asignados a QR
        Route::get('/codigos-qr/{source}/trabajadores', [CodigosQrTrabajadorController::class, 'index'])->name('codigos-qr.trabajadores.index');
        Route::get('/codigos-qr/{source}/trabajadores/create', [CodigosQrTrabajadorController::class, 'create'])->name('codigos-qr.trabajadores.create');
        Route::post('/codigos-qr/{source}/trabajadores', [CodigosQrTrabajadorController::class, 'store'])->name('codigos-qr.trabajadores.store');
        Route::get('/codigos-qr/{source}/trabajadores/{pivotId}/edit', [CodigosQrTrabajadorController::class, 'edit'])->name('codigos-qr.trabajadores.edit');
        Route::put('/codigos-qr/{source}/trabajadores/{pivotId}', [CodigosQrTrabajadorController::class, 'update'])->name('codigos-qr.trabajadores.update');
        Route::delete('/codigos-qr/{source}/trabajadores/{pivotId}', [CodigosQrTrabajadorController::class, 'destroy'])->name('codigos-qr.trabajadores.destroy');

        // Redirect rutas antiguas
        Route::redirect('/fuentes-qr', '/codigos-qr');

        // Nomencladores QR
        Route::resource('canales-electronicos', CanalElectronicoController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['canales-electronicos' => 'canalElectronico']);
        Route::resource('tipos-fuente', TipoFuenteController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['tipos-fuente' => 'tipoFuente']);
        Route::resource('monedas', MonedaController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['monedas' => 'moneda']);
    });

    // ─────────────────────────────────────────────────────────────────────
    // NOMENCLADORES
    // ─────────────────────────────────────────────────────────────────────

    Route::resource('provincias', ProvinciaController::class)->parameters([
        'provincias' => 'provincia',
    ])->except(['create', 'show', 'edit']);

    Route::resource('municipios', MunicipioController::class)->parameters([
        'municipios' => 'municipio',
    ])->except(['create', 'show', 'edit']);
    Route::get('/municipios/por-provincia/{provincia}', [MunicipioController::class, 'byProvincia'])
        ->name('municipios.byProvincia');

    Route::resource('entidades', EntityController::class)->parameters([
        'entidades' => 'entidade',
    ])->except(['create', 'show', 'edit']);
    Route::post('/entidades/sync-external', [EntityController::class, 'syncExternal'])->name('entidades.syncExternal');

    Route::get('/departamentos/exportar', [DepartmentController::class, 'export'])->name('departamentos.export');
    Route::resource('departamentos', DepartmentController::class)->parameters([
        'departamentos' => 'departamento',
    ])->except(['create', 'show', 'edit']);
    Route::get('/departamentos/por-entidad/{entity}', [DepartmentController::class, 'byEntity'])
        ->name('departamentos.byEntity');

    Route::resource('estados', StatusController::class)->parameters([
        'estados' => 'estado',
    ])->except(['create', 'show', 'edit']);

    Route::resource('sellos', SealController::class)->parameters([
        'sellos' => 'sello',
    ])->except(['create', 'show', 'edit']);
    Route::get('/sellos/exportar', [SealController::class, 'export'])->name('sellos.export');

    Route::resource('marcas', BrandController::class)->parameters([
        'marcas' => 'marca',
    ])->except(['create', 'show', 'edit']);

    Route::resource('tipos-componentes', ComponentTypeController::class)->parameters([
        'tipos-componentes' => 'tipo',
    ])->except(['create', 'show', 'edit']);

    Route::resource('tipos-incidencias', IncidentTypeController::class)->parameters([
        'tipos-incidencias' => 'tipoIncidencia',
    ])->except(['create', 'show', 'edit']);

    Route::resource('modelos', ComponentModelController::class)->parameters([
        'modelos' => 'modelo',
    ])->except(['create', 'show', 'edit']);
    Route::get('/modelos/por-tipo/{componentType}', [ComponentModelController::class, 'byType'])
        ->name('modelos.byType');
    Route::get('/modelos/marcas-por-tipo/{componentType}', [ComponentModelController::class, 'brandsByType'])
        ->name('modelos.brandsByType');

    Route::resource('aspectos-hoja', WorksheetAspectController::class)->parameters([
        'aspectos-hoja' => 'aspectoHoja',
    ])->except(['create', 'show', 'edit']);

    // ─────────────────────────────────────────────────────────────────────
    // CONTROL
    // ─────────────────────────────────────────────────────────────────────

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('usuarios', UserController::class)->parameters([
            'usuarios' => 'usuario',
        ]);

        Route::resource('roles', RoleController::class)->parameters([
            'roles' => 'role',
        ])->except(['create', 'show', 'edit']);
    });

    Route::redirect('/configuracion/modulos', '/configuracion')->name('modulos.index');
    Route::get('/configuracion', [SystemSettingsController::class, 'index'])->name('configuracion.index');
    Route::put('/configuracion/apariencia', [SystemSettingsController::class, 'updateAppearance'])->name('configuracion.appearance');
    Route::post('/configuracion/logo', [SystemSettingsController::class, 'uploadLogo'])->name('configuracion.logo');
    Route::delete('/configuracion/logo', [SystemSettingsController::class, 'deleteLogo'])->name('configuracion.logo.delete');
    Route::put('/configuracion/modulos/{modulo}', [SystemSettingsController::class, 'updateModule'])->name('modulos.update');
    Route::put('/configuracion/ldap', [SystemSettingsController::class, 'updateLdap'])->name('configuracion.ldap');
    Route::post('/configuracion/ldap/test', [SystemSettingsController::class, 'testLdap'])->name('configuracion.ldap.test');
    Route::post('/configuracion/ldap/search', [SystemSettingsController::class, 'searchLdap'])->name('configuracion.ldap.search');
    Route::put('/configuracion/external-ci', [SystemSettingsController::class, 'updateExternalCi'])->name('configuracion.external-ci');
    Route::post('/configuracion/external-ci/test', [SystemSettingsController::class, 'testExternalCi'])->name('configuracion.external-ci.test');
    Route::post('/configuracion/external-ci/columns', [SystemSettingsController::class, 'columnsExternalCi'])->name('configuracion.external-ci.columns');
    Route::put('/configuracion/external-entities-pg', [SystemSettingsController::class, 'updateExternalEntitiesPg'])->name('configuracion.external-entities-pg');
    Route::post('/configuracion/external-entities-pg/test', [SystemSettingsController::class, 'testExternalEntitiesPg'])->name('configuracion.external-entities-pg.test');
    Route::post('/configuracion/external-entities-pg/tables', [SystemSettingsController::class, 'tablesExternalEntitiesPg'])->name('configuracion.external-entities-pg.tables');
    Route::post('/configuracion/external-entities-pg/columns', [SystemSettingsController::class, 'columnsExternalEntitiesPg'])->name('configuracion.external-entities-pg.columns');
    Route::post('/configuracion/external-entities-pg/test-sync', [SystemSettingsController::class, 'testAndSyncExternalEntitiesPg'])->name('configuracion.external-entities-pg.test-sync');
    Route::post('/configuracion/external-entities-pg/preview', [SystemSettingsController::class, 'previewExternalEntitiesPg'])->name('configuracion.external-entities-pg.preview');
    Route::post('/configuracion/external-entities-pg/apply-selected', [SystemSettingsController::class, 'applySelectedChanges'])->name('configuracion.external-entities-pg.apply-selected');
    Route::put('/configuracion/external-entity-db', [SystemSettingsController::class, 'updateExternalEntityDb'])->name('configuracion.external-entity-db');
    Route::post('/configuracion/external-entity-db/test', [SystemSettingsController::class, 'testExternalEntityDb'])->name('configuracion.external-entity-db.test');
    Route::post('/configuracion/external-entity-db/preview', [SystemSettingsController::class, 'previewExternalEntityDb'])->name('configuracion.external-entity-db.preview');
    Route::post('/configuracion/external-entity-db/apply-selected', [SystemSettingsController::class, 'applySelectedDepartmentChanges'])->name('configuracion.external-entity-db.apply-selected');
    Route::post('/configuracion/external-entity-db/sync', [SystemSettingsController::class, 'syncExternalEntityDb'])->name('configuracion.external-entity-db.sync');
    Route::post('/configuracion/external-entity-db/browse-databases', [SystemSettingsController::class, 'browseExternalEntityDatabases'])->name('configuracion.external-entity-db.browse-databases');
    Route::post('/configuracion/external-entity-db/browse-tables', [SystemSettingsController::class, 'browseExternalEntityTables'])->name('configuracion.external-entity-db.browse-tables');
    Route::put('/configuracion/external-almacenes', [SystemSettingsController::class, 'updateExternalAlmacenes'])->name('configuracion.external-almacenes');
    Route::post('/configuracion/external-almacenes/test', [SystemSettingsController::class, 'testExternalAlmacenes'])->name('configuracion.external-almacenes.test');
    Route::post('/configuracion/external-almacenes/raw', [SystemSettingsController::class, 'rawAlmacenes'])->name('configuracion.external-almacenes.raw');
    Route::post('/configuracion/external-almacenes/preview', [SystemSettingsController::class, 'previewSyncAlmacenes'])->name('configuracion.external-almacenes.preview');
    Route::post('/configuracion/external-almacenes/apply', [SystemSettingsController::class, 'applySyncAlmacenes'])->name('configuracion.external-almacenes.apply');

    // ─────────────────────────────────────────────────────────────────────
    // FACTURACIÓN ETECSA
    // ─────────────────────────────────────────────────────────────────────
    Route::middleware('module_enabled:facturacion-etecsa')->group(function () {
        Route::get('/facturacion-etecsa/dashboard', EtecsaDashboardController::class)->name('etecsa.dashboard');
        Route::post('/facturacion-etecsa/importar-preview', [EtecsaFacturaController::class, 'preview'])->name('etecsa.importar.preview');
        Route::post('/facturacion-etecsa/importar-aplicar', [EtecsaFacturaController::class, 'apply'])->name('etecsa.importar.aplicar');
        Route::get('/facturacion-etecsa/buscar-servicio', [EtecsaFacturaController::class, 'buscarServicio'])->name('etecsa.buscar-servicio');
        Route::get('/facturacion-etecsa/exportar', [EtecsaFacturaController::class, 'export'])->name('etecsa.exportar');
        Route::get('/facturacion-etecsa/{factura}/exportar-entidad', [EtecsaFacturaController::class, 'exportByEntity'])->name('etecsa.exportar-entidad');
        Route::get('/facturacion-etecsa/servicios/{servicio}', [EtecsaServicioController::class, 'show'])->name('etecsa.servicios.show');
        Route::resource('facturacion-etecsa', EtecsaFacturaController::class)
            ->parameters(['facturacion-etecsa' => 'factura'])
            ->only(['index', 'show', 'destroy'])
            ->names(['index' => 'etecsa.index', 'show' => 'etecsa.show', 'destroy' => 'etecsa.destroy']);
    });

    // Perfil del usuario (no se incluye en permisos por rol)
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/perfil/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Auditoría
    Route::get('/auditoria', [AuditLogController::class, 'index'])->name('auditoria.index');
});
