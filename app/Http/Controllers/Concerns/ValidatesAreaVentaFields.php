<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ValidatesAreaVentaFields
{
    protected function validateAreaVentaFields(Request $request): array
    {
        return $request->validate([
            'sales_floor_id'           => 'required|exists:pisos_venta,id',
            'name'                     => 'required|string|max:255',
            'tpv_boxes'                => 'nullable|integer|min:0',
            'pos_phone_qty'            => 'nullable|integer|min:0',
            'pos_ip_qty'               => 'nullable|integer|min:0',
            'pos_ip_demand'            => 'nullable|integer|min:0',
            'pos_gprs_qty'             => 'nullable|integer|min:0',
            'pos_gprs_demand'          => 'nullable|integer|min:0',
            'has_ip_connectivity'      => 'boolean',
            'broken_pos_qty'           => 'nullable|integer|min:0',
            'cash_register_model_code' => 'nullable|integer|in:1,2,3,4,5',
            'pos_currency_mlc'         => 'boolean',
            'pos_currency_cup'         => 'boolean',
            'qr_fincimex_mlc'          => 'boolean',
            'qr_fincimex_cup'          => 'boolean',
            'src_fincimex_mlc'         => 'nullable|string|max:100',
            'src_fincimex_cup'         => 'nullable|string|max:100',
            'terminal_id'              => 'nullable|string|max:100',
            'terminal_ip'              => 'nullable|ip',
        ]);
    }
}
