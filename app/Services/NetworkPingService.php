<?php

namespace App\Services;

/**
 * Ping ICMP vía `ping` de iputils (Debian 12). Solo IPv4.
 */
class NetworkPingService
{
    public function __construct(
        private readonly Ipv4SubnetCalculator $calculator,
    ) {}

    /**
     * Ejecuta ping solo si la IP está permitida según política:
     * — dentro de alguno de los CIDR indicados, o
     * — coincide exactamente con alguna de las IPs de anclaje (WAN/LAN del registro).
     *
     * @param  array<int, string|null>  $allowedCidrs  Notaciones x.x.x.x/yy
     * @param  array<int, string|null>  $anchorIps     IPs exactas (sin máscara)
     * @return array{ok: bool, message: string, output?: string, code?: int}
     */
    public function pingIfAllowed(string $targetIp, array $allowedCidrs, array $anchorIps = []): array
    {
        if (! config('network_tools.ping_enabled', true)) {
            return ['ok' => false, 'message' => 'El ping desde el servidor está desactivado en configuración.'];
        }

        $targetIp = trim($targetIp);
        if (! filter_var($targetIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['ok' => false, 'message' => 'Indique una IPv4 válida.'];
        }

        $cidrs = array_values(array_filter(array_map('trim', $allowedCidrs), fn ($s) => $s !== null && $s !== ''));
        $anchors = array_values(array_filter(array_map('trim', $anchorIps), fn ($s) => $s !== null && $s !== ''));

        $inCidr = $cidrs !== [] && $this->calculator->ipv4InAnyCidr($targetIp, $cidrs);
        $isAnchor = $anchors !== [] && in_array($targetIp, $anchors, true);

        if (! $inCidr && ! $isAnchor) {
            return [
                'ok'      => false,
                'message' => 'Solo se permite ping a direcciones dentro de los segmentos WAN/LAN definidos para este registro, o a las IPs WAN/LAN guardadas.',
            ];
        }

        $count = (int) config('network_tools.ping_count', 1);
        $wait = (int) config('network_tools.ping_wait_seconds', 2);

        $cmd = sprintf(
            'ping -c %d -W %d %s 2>&1',
            max(1, min(4, $count)),
            max(1, min(10, $wait)),
            escapeshellarg($targetIp)
        );

        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);

        return [
            'ok'      => $code === 0,
            'message' => $code === 0 ? 'Host respondió al ping.' : 'Sin respuesta al ping o error de red.',
            'output'  => $text,
            'code'    => $code,
        ];
    }
}
