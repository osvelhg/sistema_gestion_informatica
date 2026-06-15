<?php

namespace App\Services;

/**
 * Cálculo de subred IPv4 (notación CIDR) para herramientas de diagnóstico.
 */
class Ipv4SubnetCalculator
{
    /**
     * Analiza una notación tipo 10.0.0.5/24 o 172.16.17.172/30.
     *
     * @return array{
     *   cidr_input: string,
     *   network_int: int,
     *   broadcast_int: int,
     *   prefix: int,
     *   network_address: string,
     *   broadcast_address: string,
     *   netmask: string,
     *   wildcard: string,
     *   first_host: ?string,
     *   last_host: ?string,
     *   usable_hosts: int
     * }|null
     */
    public function analyze(string $cidrInput): ?array
    {
        $cidrInput = trim($cidrInput);
        if ($cidrInput === '') {
            return null;
        }

        if (! preg_match('#^(\d{1,3}(?:\.\d{1,3}){3})/(\d{1,2})$#', $cidrInput, $m)) {
            return null;
        }

        $ip = $m[1];
        $prefix = (int) $m[2];

        if ($prefix < 0 || $prefix > 32) {
            return null;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        $ipInt = $this->ipv4ToInt($ip);
        if ($ipInt === null) {
            return null;
        }

        $mask = $this->maskFromPrefix($prefix);
        $networkInt = $ipInt & $mask;
        $broadcastInt = $networkInt | (~$mask & 0xFFFFFFFF);

        $usable = $this->usableHostRange($prefix, $networkInt, $broadcastInt);

        return [
            'cidr_input'         => $cidrInput,
            'network_int'        => $networkInt,
            'broadcast_int'      => $broadcastInt,
            'prefix'             => $prefix,
            'network_address'    => $this->intToIpv4($networkInt),
            'broadcast_address'  => $this->intToIpv4($broadcastInt),
            'netmask'            => $this->intToIpv4($mask),
            'wildcard'           => $this->intToIpv4(~$mask & 0xFFFFFFFF),
            'first_host'         => $usable['first'],
            'last_host'          => $usable['last'],
            'usable_hosts'       => $usable['count'],
        ];
    }

    public function ipv4InAnyCidr(string $ip, array $cidrs): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $ipInt = $this->ipv4ToInt($ip);
        if ($ipInt === null) {
            return false;
        }

        foreach ($cidrs as $cidr) {
            $cidr = trim((string) $cidr);
            if ($cidr === '') {
                continue;
            }
            $info = $this->analyze($cidr);
            if ($info === null) {
                continue;
            }
            if ($ipInt >= $info['network_int'] && $ipInt <= $info['broadcast_int']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{first: ?string, last: ?string, count: int}
     */
    private function usableHostRange(int $prefix, int $networkInt, int $broadcastInt): array
    {
        if ($prefix >= 31) {
            // /31 punto a punto, /32 host único: sin rango "usable" clásico
            if ($prefix === 32) {
                return ['first' => $this->intToIpv4($networkInt), 'last' => $this->intToIpv4($networkInt), 'count' => 1];
            }

            return [
                'first' => $this->intToIpv4($networkInt),
                'last'  => $this->intToIpv4($broadcastInt),
                'count' => 2,
            ];
        }

        $first = $networkInt + 1;
        $last = $broadcastInt - 1;

        if ($first > $last) {
            return ['first' => null, 'last' => null, 'count' => 0];
        }

        return [
            'first' => $this->intToIpv4($first),
            'last'  => $this->intToIpv4($last),
            'count' => $last - $first + 1,
        ];
    }

    private function maskFromPrefix(int $prefix): int
    {
        if ($prefix <= 0) {
            return 0;
        }
        if ($prefix >= 32) {
            return 0xFFFFFFFF;
        }

        return (0xFFFFFFFF ^ ((1 << (32 - $prefix)) - 1)) & 0xFFFFFFFF;
    }

    private function ipv4ToInt(string $ipv4): ?int
    {
        $packed = @inet_pton($ipv4);
        if ($packed === false || strlen($packed) !== 4) {
            return null;
        }
        $u = unpack('N', $packed);

        return $u[1] & 0xFFFFFFFF;
    }

    private function intToIpv4(int $n): string
    {
        $n &= 0xFFFFFFFF;

        return inet_ntop(pack('N', $n));
    }
}
