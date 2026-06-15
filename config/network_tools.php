<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ping ICMP desde el servidor (Debian / iputils-ping)
    |--------------------------------------------------------------------------
    |
    | Desactivar en entornos donde no se desee ejecutar ping (false).
    |
    */
    'ping_enabled' => (bool) env('NETWORK_TOOLS_PING_ENABLED', true),

    /*
    | Número de paquetes ICMP (-c) y tiempo máximo de espera por respuesta (-W, segundos).
    */
    'ping_count' => max(1, min(4, (int) env('NETWORK_TOOLS_PING_COUNT', 1))),
    'ping_wait_seconds' => max(1, min(10, (int) env('NETWORK_TOOLS_PING_WAIT', 2))),

];
