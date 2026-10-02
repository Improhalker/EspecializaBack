<?php

return [
    'enabled' => (bool) env('ANALYTICS_ENABLED', true),
    'excluded_ips' => array_values(array_filter(array_map('trim', explode(',', env('ANALYTICS_EXCLUDED_IPS', '179.98.61.162,127.0.0.1,::1'))))),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', env('ANALYTICS_TRUSTED_PROXIES', ''))))),
    'timezone' => env('ANALYTICS_TIMEZONE', 'America/Sao_Paulo'),
    'allowed_origins' => ['https://especializacondutor.com.br', 'https://www.especializacondutor.com.br'],
];
