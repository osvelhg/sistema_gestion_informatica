# Plan: Áreas de Venta + Importaciones Separadas ETECSA/FINCIMEX

## Contexto

El sistema actual trata cada piso de venta como unidad atómica con un solo registro FINCIMEX (POS/caja). En la realidad, un piso tiene **múltiples áreas de venta** (Mercado, Electro, Perfumería), cada una con su propia caja/POS. Además, la importación de conectividad actualmente lee el Excel FINCIMEX (64 columnas) para todo. Se necesita separar:
- **Conectividad** → importa Excel ETECSA (14 columnas, datos de red)
- **FINCIMEX** → importa Excel FINCIMEX (64 columnas, datos POS/áreas)

---

## Fase 1: Migraciones

### 1.1 Crear tabla `areas_venta`
**Archivo:** `database/migrations/2026_03_30_150000_create_areas_venta_table.php`

```
areas_venta:
  id, sales_floor_id (FK pisos_venta CASCADE), name (string),
  tpv_boxes, pos_phone_qty, pos_ip_qty, pos_ip_demand,
  pos_gprs_qty, pos_gprs_demand, has_ip_connectivity (bool),
  broken_pos_qty, cash_register_model_code (nullable),
  pos_currency_mlc (bool), pos_currency_cup (bool),
  qr_fincimex_mlc (bool), qr_fincimex_cup (bool),
  src_fincimex_mlc, src_fincimex_cup, terminal_id,
  timestamps
  UNIQUE(sales_floor_id, name)
```

### 1.2 Agregar campos ETECSA a `registros_conectividad`
**Archivo:** `database/migrations/2026_03_30_150100_add_etecsa_fields_to_registros_conectividad.php`

```
Nuevos campos (todas las columnas ETECSA):
  tipo_enlace (string, nullable) — "ADSL" / "Sin Conexión"
  ed (string, nullable) — columna ED del Excel
  ina (string, nullable) — columna INA del Excel
  id_facturacion (string, nullable) — ID de facturación ETECSA
  velocidad_etecsa (string, nullable) — velocidad raw tal como viene ("2048/1024 Kb")
  cuota (decimal 10,2, nullable) — cuota mensual
  ip_wan (string 45, nullable) — IP WAN asignada por ETECSA
  ip_lan (string 45, nullable) — IP LAN asignada por ETECSA
```

### 1.3 Eliminar tabla `registros_fincimex`
**Archivo:** `database/migrations/2026_03_30_150200_drop_registros_fincimex_table.php`

Como estamos en desarrollo, se puede hacer `migrate:fresh`. Eliminar la tabla y el modelo `FincimexRecord`.

---

## Fase 2: Modelos

### 2.1 Nuevo: `app/Models/AreaVenta.php`
- `belongsTo(SalesFloor::class)`
- Fillable: todos los campos POS + `name`
- Casts booleanos para currency/QR fields

### 2.2 Actualizar: `app/Models/SalesFloor.php`
- Agregar: `hasMany(AreaVenta::class)` → `areasVenta()`
- Eliminar relación con FincimexRecord

### 2.3 Actualizar: `app/Models/ConnectivityRecord.php`
- Agregar a `$fillable`: `tipo_enlace`, `ed`, `ina`, `id_facturacion`, `velocidad_etecsa`, `cuota`, `ip_wan`, `ip_lan`
- Cast: `'cuota' => 'decimal:2'`

### 2.4 Eliminar: `app/Models/FincimexRecord.php`

---

## Fase 3: Trait de helpers compartidos

### 3.1 Crear: `app/Http/Controllers/Concerns/ExcelImportHelpers.php`
Extraer de `ConnectivityRecordController`:
- `toInt()`, `toCoordString()`, `normalizeName()`, `parseIpAndCidr()`, `resolveSpeed()`

Ambos controllers (`ConnectivityRecordController` y `FincimexController`) usarán este trait.

---

## Fase 4: Importación ETECSA (Conectividad)

### 4.1 Reescribir `ConnectivityRecordController::parseExcel()`
**Archivo:** `app/Http/Controllers/ConnectivityRecordController.php`

Mapeo Excel ETECSA (14 columnas, sheet "ART"):
| Col | Idx | Campo BD |
|-----|-----|----------|
| B | 1 | entity_code → buscar entidad → buscar pisos_venta |
| C | 2 | nombre piso (para matching) |
| D | 3 | municipio (display) |
| E | 4 | dirección |
| F | 5 | tipo_enlace |
| G | 6 | ed |
| H | 7 | ina |
| I | 8 | id_facturacion |
| J | 9 | velocidad_etecsa (raw) + contracted_speed (nomenclador) |
| K | 10 | cuota |
| L | 11 | ip_wan |
| M | 12 | ip_lan |
| N | 13 | observaciones → notes |

### 4.2 Lógica de matching con modal de resolución

El `preview()` ahora retorna 3 tipos de estado por registro:
- **`matched`**: nombre Excel coincide con un piso existente de la entidad
- **`unmatched`**: nombre no coincide → el frontend muestra modal para:
  - Seleccionar un piso existente de esa entidad (autocomplete)
  - Crear un nuevo piso con los datos del Excel
- **`skipped`**: entidad no encontrada

Flujo:
1. `POST /conectividad/importar-preview` → parsea, intenta match automático
2. Frontend muestra preview. Los `unmatched` abren modal de resolución
3. Usuario resuelve cada conflicto (asociar a existente o crear nuevo)
4. `POST /conectividad/importar-aplicar` → recibe registros con `sales_floor_id` resuelto o `create_new: true`

### 4.3 Reescribir `persistRecord()`
- Solo crea/actualiza `SalesFloor` + `ConnectivityRecord`
- Eliminar toda lógica de FincimexRecord

### 4.4 Eliminar parsing de columnas FINCIMEX
Quitar: `$networkColMap`, `ipFieldsFromExcelRow()` (adaptar para ip_wan/ip_lan), toda la lógica de cols 5-62.

---

## Fase 5: Importación FINCIMEX (Áreas de Venta)

### 5.1 Agregar import a `FincimexController`
**Archivo:** `app/Http/Controllers/FincimexController.php`

Nuevos métodos:
- `preview(Request)` → parsea Excel FINCIMEX, retorna preview
- `applySelected(Request)` → persiste registros seleccionados
- `parseExcel(string $path)` → lee sheet "ART" (64 cols)

### 5.2 Mapeo clave del Excel FINCIMEX
| Col | Idx | Campo |
|-----|-----|-------|
| B | 1 | entity_code → buscar entidad |
| C | 2 | unit_name → nombre piso de venta |
| **D** | **3** | **area_name → nombre del área de venta** |
| E | 4 | address |
| F-onwards | 5+ | POS/caja/QR/currency (igual que parseExcel actual) |

### 5.3 Lógica de persistencia
```
Por cada fila del Excel:
  1. Buscar/crear SalesFloor por (entity_id + unit_name)
  2. Crear/actualizar AreaVenta por (sales_floor_id + area_name)
     → llenar campos POS, caja, QR, currency de esa fila
```

Un piso con 3 áreas = 3 filas en Excel = 3 AreaVenta en BD, un solo SalesFloor.

### 5.4 Mismo patrón preview→modal→apply
- Matching por entity_code + unit_name para el piso
- Si no matchea → modal de resolución (mismo componente Vue reutilizable)

---

## Fase 6: Rutas

**Archivo:** `routes/web.php`

```php
// Dentro del grupo de conectividad (ya existente)
Route::post('/fincimex/importar-preview', [FincimexController::class, 'preview']);
Route::post('/fincimex/importar-aplicar', [FincimexController::class, 'applySelected']);
```

---

## Fase 7: Vue — Frontend

### 7.1 Componente reutilizable: `MatchResolverModal.vue`
**Archivo:** `resources/js/Components/MatchResolverModal.vue`

Modal que muestra registros sin match y permite:
- Buscar pisos existentes (autocomplete, endpoint `/pisos-venta/search`)
- Botón "Crear nuevo" que usa datos del Excel para crear piso
- Se usa en AMBAS importaciones (ETECSA y FINCIMEX)

### 7.2 `Connectivity/Index.vue`
- Cambiar label: "Importar Excel ETECSA"
- Preview table: mostrar cols ETECSA (tipo enlace, velocidad, IP WAN, IP LAN, cuota, id facturación)
- Integrar `MatchResolverModal` para registros unmatched
- Tabla principal: agregar columnas ETECSA (tipo_enlace, ed, ina, id_facturacion, velocidad_etecsa, cuota, ip_wan, ip_lan)

### 7.3 `Fincimex/Index.vue`
- Agregar sección de importación Excel (copiar patrón de Connectivity)
- Preview table: mostrar piso, **área**, POS, caja, monedas, QR
- Integrar `MatchResolverModal`
- Renombrar/adaptar para que el CRUD manual ahora gestione AreaVenta (no FincimexRecord)

### 7.4 `SalesFloors/Index.vue`
- Agregar sección expandible por piso mostrando sus áreas de venta
- Eager-load `areasVenta` en `SalesFloorController::index()`

---

## Fase 8: Limpieza

- Eliminar `app/Models/FincimexRecord.php`
- Eliminar migración de `registros_fincimex` o dejar el drop migration
- Actualizar `FincimexController` CRUD: ahora opera sobre `AreaVenta`
- Actualizar exportación en `FincimexController::export()` para usar AreaVenta
- Ejecutar `php artisan migrate:fresh --seed`

---

## Archivos críticos a modificar

| Archivo | Acción |
|---------|--------|
| `database/migrations/` | 3 nuevas migraciones |
| `app/Models/AreaVenta.php` | **Crear** |
| `app/Models/SalesFloor.php` | Agregar relación `areasVenta()` |
| `app/Models/ConnectivityRecord.php` | Agregar campos ETECSA |
| `app/Models/FincimexRecord.php` | **Eliminar** |
| `app/Http/Controllers/Concerns/ExcelImportHelpers.php` | **Crear** trait |
| `app/Http/Controllers/ConnectivityRecordController.php` | Reescribir parseExcel para ETECSA |
| `app/Http/Controllers/FincimexController.php` | Agregar import + CRUD sobre AreaVenta |
| `app/Http/Controllers/SalesFloorController.php` | Eager-load áreas |
| `routes/web.php` | Nuevas rutas fincimex import |
| `resources/js/Components/MatchResolverModal.vue` | **Crear** |
| `resources/js/Pages/Connectivity/Index.vue` | Adaptar a ETECSA |
| `resources/js/Pages/Fincimex/Index.vue` | Agregar import + CRUD áreas |
| `resources/js/Pages/SalesFloors/Index.vue` | Mostrar áreas |

---

## Verificación

1. `php artisan migrate:fresh --seed`
2. Importar Excel ETECSA desde Conectividad → verificar preview con matches/unmatched → resolver modal → aplicar → ver registros con campos ETECSA
3. Importar Excel FINCIMEX desde Fincimex → verificar que se crean áreas por piso → verificar que un piso con 3 filas genera 3 AreaVenta
4. Ver en Pisos de Venta que cada piso muestra sus áreas expandibles
5. Exportar desde ambos módulos y verificar datos correctos
