<?php

namespace App\Http\Controllers;

use App\Services\AidaReportParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AidaImportController extends Controller
{
    public function parse(Request $request): JsonResponse
    {
        $request->validate(
            ['report' => 'required|file|max:10240'],
            ['report.required' => 'Selecciona un archivo de informe AIDA64.']
        );

        // Verificar extensión manualmente (mimes:txt falla en Windows por MIME type)
        if (strtolower($request->file('report')->getClientOriginalExtension()) !== 'txt') {
            return response()->json(['error' => 'El archivo debe ser un informe AIDA64 en formato .txt.'], 422);
        }

        $content = file_get_contents($request->file('report')->getRealPath());

        if (empty(trim($content))) {
            return response()->json(['error' => 'El archivo está vacío.'], 422);
        }

        $parsed = (new AidaReportParser)->parse($content);

        return response()->json($parsed);
    }
}
