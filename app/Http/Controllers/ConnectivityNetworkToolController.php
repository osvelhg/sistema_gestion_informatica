<?php

namespace App\Http\Controllers;

use App\Models\ConnectivityRecord;
use App\Services\Ipv4SubnetCalculator;
use App\Services\NetworkPingService;
use App\Support\UserEntityAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectivityNetworkToolController extends Controller
{
    public function analyze(Request $request, Ipv4SubnetCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'cidr' => 'required|string|max:64',
        ]);

        $info = $calculator->analyze(trim($data['cidr']));
        if ($info === null) {
            return response()->json([
                'success' => false,
                'message' => 'CIDR no válido. Use IPv4/prefijo, por ejemplo 10.146.74.0/24 o 172.16.17.172/30.',
            ], 422);
        }

        unset($info['network_int'], $info['broadcast_int']);

        return response()->json([
            'success' => true,
            'data'    => $info,
        ]);
    }

    public function ping(Request $request, NetworkPingService $pingService): JsonResponse
    {
        $data = $request->validate([
            'conectividade_id'   => 'required|exists:registros_conectividad,id',
            'target_ip'          => 'required|ipv4',
            'additional_cidr'    => 'nullable|string|max:64',
        ]);

        $rec = ConnectivityRecord::query()
            ->with(['salesFloor:id,entity_id'])
            ->findOrFail((int) $data['conectividade_id']);

        $this->assertConnectivityEntityAccess($request, $rec);

        $calculator = app(Ipv4SubnetCalculator::class);
        $allowedCidrs = array_values(array_filter([
            trim((string) $rec->wan_cidr),
            trim((string) $rec->lan_cidr),
        ], fn ($s) => $s !== ''));

        $allowedCidrs = array_merge($allowedCidrs, $this->inferredCidrsFromAnchorIps($rec, $calculator));

        $extra = isset($data['additional_cidr']) ? trim((string) $data['additional_cidr']) : '';
        if ($extra !== '') {
            $parsed = $calculator->analyze($extra);
            if ($parsed !== null) {
                $allowedCidrs[] = $parsed['network_address'].'/'.$parsed['prefix'];
            }
        }

        $allowedCidrs = array_values(array_unique(array_filter($allowedCidrs)));

        $result = $pingService->pingIfAllowed(
            $data['target_ip'],
            $allowedCidrs,
            array_values(array_filter(array_map('trim', [
                $rec->ip_wan,
                $rec->ip_lan,
            ])), fn ($s) => $s !== '')
        );

        return response()->json($result);
    }

    /**
     * Si no hay CIDR guardado pero sí IP WAN/LAN, infiere /24 con la red que contiene esa IP
     * (caso habitual en Excel ETECSA: IP de red sin máscara en el mismo registro).
     */
    private function inferredCidrsFromAnchorIps(ConnectivityRecord $rec, Ipv4SubnetCalculator $calculator): array
    {
        $out = [];
        foreach (['wan_cidr' => 'ip_wan', 'lan_cidr' => 'ip_lan'] as $cidrField => $ipField) {
            if (! empty($rec->{$cidrField})) {
                continue;
            }
            $ip = trim((string) ($rec->{$ipField} ?? ''));
            if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                continue;
            }
            $info = $calculator->analyze($ip.'/24');
            if ($info !== null) {
                $out[] = $info['network_address'].'/24';
            }
        }

        return $out;
    }

    private function assertConnectivityEntityAccess(Request $request, ConnectivityRecord $rec): void
    {
        $floorId = $rec->sales_floor_id;
        if (! $floorId) {
            abort(403, 'Registro sin piso de venta asociado.');
        }

        $allowed = UserEntityAccess::allowedEntityIds($request->user());
        if ($allowed === null) {
            return;
        }

        $entityId = $rec->salesFloor?->entity_id;
        if (! $entityId || $allowed === [] || ! in_array((int) $entityId, array_map('intval', $allowed), true)) {
            abort(403, 'No tiene acceso a este registro de conectividad.');
        }
    }
}
