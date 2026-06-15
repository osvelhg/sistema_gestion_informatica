<?php

namespace App\Http\Controllers;

use App\Models\CanalElectronico;
use App\Models\DatacellSource;
use App\Models\Moneda;
use App\Models\SalesFloor;
use App\Models\TipoFuente;
use App\Services\QrSourcesImportService;
use App\Support\QrCodigosTrabajadoresExcelExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatacellController extends Controller
{
    /**
     * Listado principal de Códigos QR.
     */
    public function index(Request $request): Response
    {
        $sources = $this->codigosQrFilteredQuery($request)
            ->orderBy('source_name')
            ->orderBy('source')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $lastSynced = DatacellSource::query()->whereNotNull('synced_at')->max('synced_at');

        return Inertia::render('CodigosQR/Index', [
            'sources' => $sources,
            'filters' => $request->only(['canal', 'tipo', 'moneda', 'solo_activos', 'search', 'sales_floor_id']),
            'canales' => CanalElectronico::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'tipos' => TipoFuente::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'monedas' => Moneda::where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'sigla', 'simbolo']),
            'last_synced' => $lastSynced ? Carbon::parse($lastSynced)->format('d/m/Y H:i') : null,
        ]);
    }

    /**
     * Exporta Excel: hoja Resumen (QR sin trabajadores + listado consolidado) y una hoja por codigo con trabajadores.
     */
    public function exportTrabajadoresExcel(Request $request): StreamedResponse
    {
        $sources = $this->codigosQrFilteredQuery($request)
            ->with(['trabajadores' => fn ($q) => $q->orderBy('nombre')])
            ->orderBy('source_name')
            ->orderBy('source')
            ->get();

        return QrCodigosTrabajadoresExcelExport::download($sources);
    }

    /**
     * Consulta base del listado / exportacion de codigos QR (mismos filtros).
     */
    protected function codigosQrFilteredQuery(Request $request): Builder
    {
        $query = DatacellSource::with(['salesFloor:id,name,codigo_golden', 'canalElectronico', 'tipoFuente']);

        if ($request->filled('canal')) {
            $query->byCanal((int) $request->canal);
        }
        if ($request->filled('tipo')) {
            $query->byTipo((int) $request->tipo);
        }
        if ($request->filled('moneda')) {
            $query->byMoneda($request->moneda);
        }
        if ($request->boolean('solo_activos')) {
            $query->active();
        }
        if ($request->filled('sales_floor_id')) {
            $query->where('sales_floor_id', $request->sales_floor_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('source', 'ilike', "%{$s}%")
                    ->orWhere('source_name', 'ilike', "%{$s}%")
                    ->orWhereHas('salesFloor', function ($sq) use ($s) {
                        $sq->where('name', 'ilike', "%{$s}%");
                    })
                    ->orWhereHas('areasVenta', function ($aq) use ($s) {
                        $aq->where('name', 'ilike', "%{$s}%");
                    })
                    ->orWhereHas('areasVenta.salesFloor', function ($sfq) use ($s) {
                        $sfq->where('name', 'ilike', "%{$s}%");
                    });
            });
        }

        return $query;
    }

    /**
     * Formulario para crear un QR manualmente.
     */
    public function create(): Response
    {
        return Inertia::render('CodigosQR/Create', [
            'canales' => CanalElectronico::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'tipos' => TipoFuente::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'monedas' => Moneda::where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'sigla', 'simbolo']),
        ]);
    }

    /**
     * Guardar nuevo QR creado manualmente.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sales_floor_id' => 'required|exists:pisos_venta,id',
            'source' => 'required|string|max:100',
            'source_name' => 'nullable|string|max:200',
            'canal_electronico_id' => 'nullable|exists:canales_electronicos,id',
            'tipo_fuente_id' => 'nullable|exists:tipo_fuentes,id',
            'moneda' => 'required|string|exists:monedas,sigla',
            'activo' => 'boolean',
        ]);

        $data['moneda'] = strtoupper($data['moneda']);
        $data['id_unidad'] = (int) $data['sales_floor_id'];
        DatacellSource::create($data);

        return redirect()->route('codigos-qr.index')->with('success', 'Código QR creado correctamente.');
    }

    /**
     * Formulario de edición.
     */
    public function edit(DatacellSource $source): Response
    {
        $source->load([
            'salesFloor.municipio:id,name',
            'salesFloor.entity:id,name',
            'canalElectronico',
            'tipoFuente',
        ]);

        return Inertia::render('CodigosQR/Edit', [
            'source' => $source,
            'canales' => CanalElectronico::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'tipos' => TipoFuente::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'monedas' => Moneda::where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'sigla', 'simbolo']),
        ]);
    }

    /**
     * Actualizar QR existente.
     */
    public function update(Request $request, DatacellSource $source): RedirectResponse
    {
        $data = $request->validate([
            'sales_floor_id' => 'required|exists:pisos_venta,id',
            'source' => 'required|string|max:100',
            'source_name' => 'nullable|string|max:200',
            'canal_electronico_id' => 'nullable|exists:canales_electronicos,id',
            'tipo_fuente_id' => 'nullable|exists:tipo_fuentes,id',
            'moneda' => 'required|string|exists:monedas,sigla',
            'activo' => 'boolean',
        ]);

        $data['moneda'] = strtoupper($data['moneda']);
        $data['id_unidad'] = (int) $data['sales_floor_id'];
        $source->update($data);

        return redirect()->route('codigos-qr.index')->with('success', 'Código QR actualizado correctamente.');
    }

    /**
     * Eliminar un QR.
     */
    public function destroy(DatacellSource $source): RedirectResponse
    {
        $source->delete();

        return redirect()->route('codigos-qr.index')->with('success', 'Código QR eliminado.');
    }

    /**
     * Importar fuentes QR desde un archivo JSON.
     */
    public function importJson(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'json_file' => 'required|file|mimes:json,txt|max:51200',
            'mapping' => 'nullable|string',
            'canal_filter' => 'nullable|string|max:120',
        ]);

        $content = file_get_contents($request->file('json_file')->getRealPath());
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $err = 'El archivo no es un JSON válido: '.json_last_error_msg();
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $err], 422);
            }

            return back()->with('error', $err);
        }

        $fieldMap = null;
        if ($request->filled('mapping')) {
            $mapDecoded = json_decode($request->input('mapping'), true);
            if (! is_array($mapDecoded)) {
                $err = 'El mapeo de columnas no es un JSON válido.';
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'error' => $err], 422);
                }

                return back()->with('error', $err);
            }
            $allowed = array_keys(QrSourcesImportService::DEFAULT_FIELD_MAP);
            $fieldMap = [];
            foreach ($mapDecoded as $logical => $jsonKey) {
                if (! in_array($logical, $allowed, true)) {
                    continue;
                }
                if (! is_string($jsonKey) || $jsonKey === '') {
                    continue;
                }
                if (! preg_match('/^[A-Za-z0-9_]+$/', $jsonKey)) {
                    $err = 'Nombre de campo JSON no válido en el mapeo: '.$jsonKey;
                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'error' => $err], 422);
                    }

                    return back()->with('error', $err);
                }
                $fieldMap[$logical] = $jsonKey;
            }
        }

        $canalNombreFilter = $request->input('canal_filter');
        $canalNombreFilter = is_string($canalNombreFilter) && trim($canalNombreFilter) !== ''
            ? trim($canalNombreFilter)
            : null;

        $result = QrSourcesImportService::syncFromJson($decoded, $fieldMap, $canalNombreFilter);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        if (! $result['success']) {
            return back()->with('error', $result['error']);
        }

        $msg = "Importación completada: {$result['created']} nuevas, {$result['updated']} actualizadas ({$result['total']} total).";
        if (($result['skipped'] ?? 0) > 0) {
            $msg .= " Omitidas sin código fuente: {$result['skipped']}.";
        }
        if (($result['skipped_canal'] ?? 0) > 0) {
            $msg .= " Omitidas por filtro de canal: {$result['skipped_canal']}.";
        }

        return back()->with('success', $msg);
    }

    /**
     * Vincular manualmente un source a un piso de venta.
     */
    public function link(Request $request, DatacellSource $source): JsonResponse
    {
        $request->validate([
            'sales_floor_id' => 'nullable|exists:pisos_venta,id',
        ]);

        $payload = [
            'sales_floor_id' => $request->sales_floor_id,
        ];
        if ($request->filled('sales_floor_id')) {
            $payload['id_unidad'] = (int) $request->sales_floor_id;
        }

        $source->update($payload);

        return response()->json([
            'success' => true,
            'sales_floor' => $source->fresh()->salesFloor,
        ]);
    }

    /**
     * Buscar pisos de venta (AJAX para autocomplete).
     */
    public function salesFloors(Request $request): JsonResponse
    {
        return response()->json([
            'floors' => SalesFloor::searchForAutocomplete($request->get('q'))->values()->all(),
        ]);
    }
}
