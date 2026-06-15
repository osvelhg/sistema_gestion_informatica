<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AssertsAllowedEntityAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentFileRequest extends FormRequest
{
    use AssertsAllowedEntityAccess;

    public function authorize(): bool
    {
        return $this->user()->can('expedientes.store');
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('responsibles')) {
            return;
        }

        $clean = collect($this->input('responsibles', []))->map(function ($row) {
            return [
                'display_name'   => isset($row['display_name']) ? trim((string) $row['display_name']) : '',
                'samaccountname' => isset($row['samaccountname']) ? trim((string) $row['samaccountname']) : null,
                'mail'           => isset($row['mail']) ? trim((string) $row['mail']) : null,
                'source'         => (($row['source'] ?? '') === 'ad') ? 'ad' : 'manual',
                'trabajador_id'  => isset($row['trabajador_id']) ? (int) $row['trabajador_id'] ?: null : null,
            ];
        })->all();

        $this->merge(['responsibles' => $clean]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function () {
            $this->assertEntityAllowedForUser($this->input('entity_id'));
        });
    }

    public function rules(): array
    {
        return [
            'entity_id'        => 'required|exists:entidades,id',
            'department_id'    => 'required|exists:departamentos,id',
            'type'             => 'required|in:PC,Laptop',
            'inventory_number' => 'required|string|max:50|unique:expedientes_equipos,inventory_number',
            'chassis'           => 'nullable|string|max:100',
            'ip_address'        => 'nullable|string|max:45',
            'station_name'      => 'nullable|string|max:100',
            'operating_system'  => 'nullable|string|max:200',
            'status'            => 'required|string|exists:estados,name',
            'repairable'       => 'required|in:Si,No',
            'responsibles'                        => 'required|array|min:1|max:30',
            'responsibles.*.display_name'         => 'required|string|max:255',
            'responsibles.*.samaccountname'       => 'nullable|string|max:100',
            'responsibles.*.mail'                 => 'nullable|string|max:255',
            'responsibles.*.source'               => 'required|in:ad,manual',
            'responsibles.*.trabajador_id'        => 'nullable|integer|exists:trabajadores,id',
            'seal_code'        => 'nullable|string|max:50',

            'caracteristicas'                       => 'nullable|array',
            'caracteristicas.*.component_type_slug' => 'nullable|string|max:50',
            'caracteristicas.*.brand'               => 'nullable|string|max:100',
            'caracteristicas.*.model'               => 'nullable|string|max:100',
            'caracteristicas.*.serial_number'       => 'nullable|string|max:100',
            'caracteristicas.*.status'              => 'nullable|string',
            'perifericos'                           => 'nullable|array',
            'perifericos.*.component_type_slug'    => 'required_with:perifericos.*|string|max:50',
            'perifericos.*.brand'                  => 'nullable|string|max:100',
            'perifericos.*.model'                  => 'nullable|string|max:100',
            'perifericos.*.inventory_number'       => 'nullable|string|max:50',
            'perifericos.*.serial_number'          => 'nullable|string|max:100',
            'perifericos.*.status'                 => 'nullable|string',
            'dispositivos'                          => 'nullable|array',
            'dispositivos.*.component_type_slug'    => 'required_with:dispositivos.*|string|max:50',
            'dispositivos.*.brand'                  => 'nullable|string|max:100',
            'dispositivos.*.model'                  => 'nullable|string|max:100',
            'dispositivos.*.inventory_number'       => 'nullable|string|max:50',
            'dispositivos.*.serial_number'          => 'nullable|string|max:100',
            'dispositivos.*.status'                 => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_number.unique' => 'Este número de inventario ya está registrado.',
            'entity_id.required'      => 'Debe seleccionar una entidad.',
            'department_id.required'  => 'Debe seleccionar un departamento.',
        ];
    }
}
