<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Conectividad</title>
    <style>
        @page { margin: 22px 22px 24px 22px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 9px; }
        .header { border-bottom: 2px solid #1e3a8a; padding-bottom: 6px; margin-bottom: 8px; }
        .header .l1 { font-size: 12px; font-weight: 700; }
        .header .l2 { font-size: 10px; color: #334155; margin-top: 2px; }
        .header .l3 { font-size: 8px; color: #1d4ed8; margin-top: 3px; text-transform: uppercase; }
        .title { font-size: 11px; font-weight: 700; color: #1e3a8a; margin: 8px 0 6px; }
        .mini-grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .mini-grid td { border: 1px solid #cbd5e1; padding: 4px 5px; font-size: 8.5px; vertical-align: top; }
        .mini-grid .k { background: #f1f5f9; font-weight: 700; width: 20%; }
        .small { font-size: 8px; color: #64748b; margin: 0 0 6px; }
        table.data { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.data thead { display: table-header-group; }
        table.data tfoot { display: table-row-group; }
        table.data tr { page-break-inside: avoid; }
        table.data th { background: #1e3a8a; color: #fff; padding: 4px 5px; text-align: left; font-size: 8px; }
        table.data td { padding: 3px 4px; border-bottom: 1px solid #e5e7eb; font-size: 7.5px; vertical-align: top; word-wrap: break-word; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <div class="l1">{{ $branding['organization_name'] ?? 'Centro Laboral' }}</div>
        <div class="l2">{{ $branding['system_name'] ?? 'SGI - Sistema de Gestion Informatica' }}</div>
        <div class="l3">{{ $branding['header_title'] ?? 'Gestion documental institucional' }}</div>
    </div>

    <div class="title">Conectividad - Resumen ejecutivo</div>
    <table class="mini-grid">
        <tr>
            <td class="k">Total registros</td>
            <td>{{ $total }}</td>
            <td class="k">Sin conexion</td>
            <td>{{ $sinConexion }}</td>
        </tr>
    </table>
    <table class="mini-grid">
        <tr>
            <td class="k">Por tipo de enlace</td>
            <td>
                @foreach($tipoCounts as $k => $v)
                    <div>{{ $k }}: <strong>{{ $v }}</strong></div>
                @endforeach
            </td>
            <td class="k">Por ancho de banda</td>
            <td>
                @foreach($speedCounts as $k => $v)
                    <div>{{ $k }}: <strong>{{ $v }}</strong></div>
                @endforeach
            </td>
        </tr>
        <tr>
            <td class="k">Por cuota</td>
            <td colspan="3">
                @foreach($quotaCounts as $k => $v)
                    <span style="display:inline-block; margin-right:10px;">{{ $k }}: <strong>{{ $v }}</strong></span>
                @endforeach
            </td>
        </tr>
    </table>

    <div class="title">Detalle de conectividad</div>
    <p class="small">Formato formal y ajustado. Campos compactados para evitar una fila por pagina.</p>
    <table class="data">
        <thead>
            <tr>
                <th style="width:16%">Piso de venta</th>
                <th style="width:12%">Entidad</th>
                <th style="width:17%">Direccion</th>
                <th style="width:7%">Enlace</th>
                <th style="width:13%">ED / INA / Fact.</th>
                <th style="width:8%">Velocidad</th>
                <th style="width:7%">Cuota</th>
                <th style="width:10%">WAN</th>
                <th style="width:10%">LAN</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row->salesFloor?->name ?? $row->unit_name }}</td>
                    <td>
                        {{ $row->salesFloor?->entity?->code ?? '-' }}
                        @if($row->salesFloor?->entity?->name)
                            <div class="muted">{{ $row->salesFloor?->entity?->name }}</div>
                        @endif
                    </td>
                    <td>{{ $row->salesFloor?->address ?? $row->address ?? '-' }}</td>
                    <td>{{ $row->tipo_enlace ?? '-' }}</td>
                    <td>
                        ED: {{ $row->ed ?? '-' }}<br>
                        INA: {{ $row->ina ?? '-' }}<br>
                        Fact: {{ $row->id_facturacion ?? '-' }}
                    </td>
                    <td>{{ $row->contracted_speed ?? $row->velocidad_etecsa ?? '-' }}</td>
                    <td>{{ $row->cuota ?? '-' }}</td>
                    <td>{{ $row->wan_cidr ?? $row->ip_wan ?? '-' }}</td>
                    <td>{{ $row->lan_cidr ?? $row->ip_lan ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
