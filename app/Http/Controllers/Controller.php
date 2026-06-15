<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Devuelve el número de registros por página validado.
     * Opciones permitidas: 5, 10, 20, 50, 100, 'all'.
     * 'all' devuelve PHP_INT_MAX para que ->paginate() traiga todo.
     */
    protected function perPage(Request $request, int $default = 5): int
    {
        $value = $request->input('per_page', $default);

        if ($value === 'all') {
            return PHP_INT_MAX;
        }

        $int = (int) $value;

        return in_array($int, [5, 10, 20, 50, 100]) ? $int : $default;
    }
}
