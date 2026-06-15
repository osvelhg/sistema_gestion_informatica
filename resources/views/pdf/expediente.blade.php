@extends('pdf.layout')

@section('title', 'Expediente ' . $expediente->file_number)

@push('styles')
<style>
    .doc-header { text-align: center; margin: 0 0 8px; border-bottom: 2px solid #1e40af; padding-bottom: 5px; }
    .doc-header h1 { font-size: 12px; color: #1e40af; font-weight: 700; margin: 0; }
    .doc-header h2 { font-size: 9px; color: #555; margin: 2px 0 0; font-weight: normal; }

    /* Tarjeta de datos (pares etiqueta/valor en columnas) */
    table.kv { width: 100%; border-collapse: collapse; margin: 4px 0 8px; }
    table.kv td { padding: 2px 5px; font-size: 9px; vertical-align: top; word-wrap: break-word; }
    table.kv td.lbl { color: #6b7280; text-transform: uppercase; font-size: 7px; letter-spacing: .04em; padding-bottom: 0; }
    table.kv td.val { font-weight: 700; font-size: 9.5px; padding-top: 0; padding-bottom: 5px; border-bottom: 1px solid #f1f5f9; }
    table.kv td.full { font-weight: 700; font-size: 9.5px; padding: 0 5px 5px; border-bottom: 1px solid #f1f5f9; word-wrap: break-word; }

    /* Tablas de datos: layout automático para no romper con celdas largas */
    table.data { width: 100%; border-collapse: collapse; margin: 4px 0 8px; }
    table.data th { background: #1e3a8a; color: #fff; padding: 4px 5px; text-align: left; font-size: 8.5px; font-weight: 700; }
    table.data td { padding: 3px 5px; border-bottom: 1px solid #e5e7eb; font-size: 8.5px; vertical-align: top; word-wrap: break-word; }
    table.data tr:nth-child(even) td { background: #f8fafc; }

    .cat-c { color: #1e3a8a; font-weight: 700; font-size: 7.5px; text-transform: uppercase; }
    .cat-p { color: #166534; font-weight: 700; font-size: 7.5px; text-transform: uppercase; }
    .cat-d { color: #854d0e; font-weight: 700; font-size: 7.5px; text-transform: uppercase; }

    .section-title { font-size: 10.5px; font-weight: 700; color: #1e3a8a; margin: 8px 0 3px; border-bottom: 1px solid #e2e8f0; padding-bottom: 2px; }

    table.firma { width: 100%; margin-top: 24px; }
    table.firma td { width: 50%; text-align: center; padding-top: 32px; font-size: 9px; }
    .firma-line { border-top: 1px solid #333; width: 200px; margin: 0 auto; padding-top: 3px; }

    .acta-text { text-align: justify; line-height: 1.4; margin: 6px 0; font-size: 9.5px; }
    .date-center { text-align: center; margin-top: 12px; font-size: 9px; color: #555; }
    .generated { text-align: right; margin-top: 8px; font-size: 7.5px; color: #94a3b8; }
    .empty-note { font-size: 9px; color: #6b7280; font-style: italic; margin: 4px 0 8px; }

    .pb { page-break-before: always; }
</style>
@endpush

@section('content')

{{-- ════════ PÁGINA 1 — ACTA DE COMPROMISO ════════ --}}
<div class="doc-header">
    <h1>ACTA DE COMPROMISO Y CUSTODIA DE BIENES INFORMÁTICOS</h1>
    <h2>Expediente {{ $expediente->file_number }}</h2>
</div>

<p class="acta-text">
    Por medio de la presente, se hace constar que el/la ciudadano(a)
    <strong>{{ $expediente->responsible }}</strong>, adscrito(a) a la entidad
    <strong>{{ $expediente->entity?->name ?? 'N/A' }}</strong>, departamento
    <strong>{{ $expediente->department?->name ?? 'N/A' }}</strong>, recibe en custodia
    y bajo su responsabilidad los medios informáticos que a continuación se detallan,
    comprometiéndose a su uso adecuado, conservación y protección.
</p>

<table class="kv">
    <tr>
        <td class="lbl">Equipo</td>
        <td class="lbl">No. Inventario</td>
        <td class="lbl">Estado</td>
        <td class="lbl">Sello</td>
    </tr>
    <tr>
        <td class="val">{{ $expediente->type }}</td>
        <td class="val">{{ $expediente->inventory_number }}</td>
        <td class="val status-{{ strtolower($expediente->status ?? '') }}">{{ $expediente->status }}</td>
        <td class="val">{{ $expediente->seal_code ?: 'N/A' }}</td>
    </tr>
</table>

<div class="section-title">Bienes Bajo Custodia</div>
@if($componentes->count())
    <table class="data">
        <thead>
            <tr>
                <th width="11%">Categoría</th>
                <th width="22%">Componente</th>
                <th width="13%">Marca</th>
                <th width="20%">Modelo</th>
                <th width="11%">Inventario</th>
                <th width="13%">Serie</th>
                <th width="10%">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($componentes as $c)
                @php
                    $catClass = match($c->category) {
                        'caracteristica' => 'cat-c',
                        'periferico'     => 'cat-p',
                        'dispositivo'    => 'cat-d',
                        default          => '',
                    };
                    $catLabel = match($c->category) {
                        'caracteristica' => 'Característica',
                        'periferico'     => 'Periférico',
                        'dispositivo'    => 'Dispositivo',
                        default          => $c->category,
                    };
                @endphp
                <tr>
                    <td><span class="{{ $catClass }}">{{ $catLabel }}</span></td>
                    <td>{{ $c->label }}</td>
                    <td>{{ $c->brand ?: '-' }}</td>
                    <td>{{ $c->model ?: '-' }}</td>
                    <td>{{ $c->inventory_number ?: '-' }}</td>
                    <td>{{ $c->serial_number ?: '-' }}</td>
                    <td class="status-{{ strtolower($c->status ?? '') }}">{{ $c->status ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="empty-note">No hay componentes registrados para este expediente.</p>
@endif

<p class="acta-text" style="margin-top: 10px;">
    El incumplimiento de las normas de seguridad informática o el daño intencional a los equipos
    será sancionado conforme a la legislación vigente.
</p>

<table class="firma">
    <tr>
        <td><div class="firma-line">Responsable del Equipo</div></td>
        <td><div class="firma-line">Jefe de Informática</div></td>
    </tr>
</table>

<p class="date-center">Fecha: {{ now()->format('d/m/Y') }}</p>


{{-- ════════ PÁGINA 2+ — EXPEDIENTE TÉCNICO ════════ --}}
<div class="pb"></div>

<div class="doc-header">
    <h1>EXPEDIENTE TÉCNICO — {{ $expediente->file_number }}</h1>
    <h2>{{ $expediente->type }} | Inventario: {{ $expediente->inventory_number }}</h2>
</div>

<table class="kv">
    <tr>
        <td class="lbl">Entidad</td>
        <td class="lbl">Departamento</td>
        <td class="lbl">Responsable</td>
        <td class="lbl">Estado</td>
    </tr>
    <tr>
        <td class="val">{{ $expediente->entity?->name ?? 'N/A' }}</td>
        <td class="val">{{ $expediente->department?->name ?? 'N/A' }}</td>
        <td class="val">{{ $expediente->responsible }}</td>
        <td class="val status-{{ strtolower($expediente->status ?? '') }}">{{ $expediente->status }}</td>
    </tr>
    <tr>
        <td class="lbl">Reparable</td>
        <td class="lbl">Sello actual</td>
        <td class="lbl">Creado</td>
        <td class="lbl">Dirección IP</td>
    </tr>
    <tr>
        <td class="val">{{ $expediente->repairable ?: 'N/A' }}</td>
        <td class="val">{{ $expediente->seal_code ?: 'N/A' }}</td>
        <td class="val">{{ $expediente->created_at?->format('d/m/Y') ?? 'N/A' }}</td>
        <td class="val">{{ $expediente->ip_address ?: 'N/A' }}</td>
    </tr>
    @if($expediente->station_name || $expediente->operating_system)
        <tr>
            <td class="lbl" colspan="2">Nombre de estación</td>
            <td class="lbl" colspan="2">Sistema Operativo</td>
        </tr>
        <tr>
            <td class="val" colspan="2">{{ $expediente->station_name ?: 'N/A' }}</td>
            <td class="val" colspan="2">{{ $expediente->operating_system ?: 'N/A' }}</td>
        </tr>
    @endif
    @if($expediente->chassis)
        <tr><td class="lbl" colspan="4">Chasis / Notas</td></tr>
        <tr><td class="full" colspan="4">{{ $expediente->chassis }}</td></tr>
    @endif
</table>

@if($caracteristicas->count())
    <div class="section-title">Características del Equipo</div>
    <table class="data">
        <thead>
            <tr>
                <th width="24%">Componente</th>
                <th width="18%">Marca</th>
                <th width="30%">Modelo</th>
                <th width="18%">No. Serie</th>
                <th width="10%">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($caracteristicas as $c)
                <tr>
                    <td>{{ $c->label }}</td>
                    <td>{{ $c->brand ?: '-' }}</td>
                    <td>{{ $c->model ?: '-' }}</td>
                    <td>{{ $c->serial_number ?: '-' }}</td>
                    <td class="status-{{ strtolower($c->status ?? '') }}">{{ $c->status ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if($perifericos->count())
    <div class="section-title">Periféricos</div>
    <table class="data">
        <thead>
            <tr>
                <th width="22%">Componente</th>
                <th width="16%">Marca</th>
                <th width="22%">Modelo</th>
                <th width="14%">Inventario</th>
                <th width="16%">No. Serie</th>
                <th width="10%">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($perifericos as $p)
                <tr>
                    <td>{{ $p->label }}</td>
                    <td>{{ $p->brand ?: '-' }}</td>
                    <td>{{ $p->model ?: '-' }}</td>
                    <td>{{ $p->inventory_number ?: '-' }}</td>
                    <td>{{ $p->serial_number ?: '-' }}</td>
                    <td class="status-{{ strtolower($p->status ?? '') }}">{{ $p->status ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if($dispositivos->count())
    <div class="section-title">Otros Dispositivos</div>
    <table class="data">
        <thead>
            <tr>
                <th width="22%">Nombre</th>
                <th width="16%">Marca</th>
                <th width="22%">Modelo</th>
                <th width="14%">Inventario</th>
                <th width="16%">No. Serie</th>
                <th width="10%">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dispositivos as $d)
                <tr>
                    <td>{{ $d->label }}</td>
                    <td>{{ $d->brand ?: '-' }}</td>
                    <td>{{ $d->model ?: '-' }}</td>
                    <td>{{ $d->inventory_number ?: '-' }}</td>
                    <td>{{ $d->serial_number ?: '-' }}</td>
                    <td class="status-{{ strtolower($d->status ?? '') }}">{{ $d->status ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if($expediente->seals->count())
    <div class="section-title">Control de Sellos</div>
    <table class="data">
        <thead>
            <tr>
                <th width="22%">Incidencia</th>
                <th width="13%">Retirado</th>
                <th width="13%">Aplicado</th>
                <th width="26%">Motivo</th>
                <th width="13%">Fecha</th>
                <th width="13%">Realizado por</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expediente->seals as $seal)
                <tr>
                    <td>{{ $seal->incidentType?->name ?? 'Sin clasificar' }}</td>
                    <td>{{ $seal->removed_seal ?: '-' }}</td>
                    <td>{{ $seal->applied_seal ?? $seal->code ?? '-' }}</td>
                    <td>{{ $seal->reason }}</td>
                    <td>{{ $seal->date }} {{ $seal->time ? substr($seal->time, 0, 5) : '' }}</td>
                    <td>{{ $seal->performed_by ?: 'Sistema' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if($movimientos->count())
    <div class="section-title">Histórico de Movimientos</div>
    <table class="data">
        <thead>
            <tr>
                <th width="18%">Fecha</th>
                <th width="36%">Origen</th>
                <th width="36%">Destino</th>
                <th width="10%">Inv.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($movimientos as $m)
                <tr>
                    <td>{{ $m->moved_at?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td>{{ $m->fromDepartment?->name ?? '—' }}</td>
                    <td>{{ $m->toDepartment?->name ?? '—' }}</td>
                    <td>{{ $m->inventory_number ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if($inspecciones->count())
    <div class="section-title">Inspecciones Recientes</div>
    <table class="data">
        <thead>
            <tr>
                <th width="14%">Fecha</th>
                <th width="28%">Participantes</th>
                <th width="42%">Situaciones detectadas</th>
                <th width="16%">Hoja Trabajo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($inspecciones as $insp)
                <tr>
                    <td>{{ $insp->inspection_date }}</td>
                    <td>{{ $insp->participants ?: '-' }}</td>
                    <td>{{ $insp->situations_detected ?: '-' }}</td>
                    <td>{{ $insp->worksheet_reference ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if($controlSoporte->count())
    <div class="section-title">Soporte Técnico Reciente</div>
    <table class="data">
        <thead>
            <tr>
                <th width="14%">Fecha</th>
                <th width="18%">Área</th>
                <th width="14%">No. Soporte</th>
                <th width="54%">Resumen</th>
            </tr>
        </thead>
        <tbody>
            @foreach($controlSoporte as $sc)
                <tr>
                    <td>{{ $sc->record_date }}</td>
                    <td>{{ $sc->area ?: '-' }}</td>
                    <td>{{ $sc->support_number ?: '-' }}</td>
                    <td>{{ $sc->content_summary ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<p class="generated">Generado el {{ now()->format('d/m/Y H:i') }} | SGI — Sistema de Gestión Informático</p>

@endsection
