<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AssertsAllowedEntityAccess;
use Illuminate\Foundation\Http\FormRequest;

class MoveEquipmentRequest extends FormRequest
{
    use AssertsAllowedEntityAccess;

    public function authorize(): bool
    {
        return $this->user()->can('expedientes.move');
    }

    public function withValidator($validator): void
    {
        $validator->after(function () {
            $this->assertEntityAllowedForUser($this->input('to_entity_id'), 'to_entity_id');
        });
    }

    public function rules(): array
    {
        return [
            'to_entity_id'     => 'required|exists:entidades,id',
            'to_department_id' => 'required|exists:departamentos,id',
        ];
    }

    public function messages(): array
    {
        return [
            'to_entity_id.required'     => 'Debe seleccionar la entidad destino.',
            'to_department_id.required' => 'Debe seleccionar el departamento destino.',
        ];
    }
}
