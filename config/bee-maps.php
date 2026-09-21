<?php

return [
    'providers' => [
        BeeDelivery\BeeMaps\Providers\Google\GoogleProvider::class,
        BeeDelivery\BeeMaps\Providers\Here\HereProvider::class,
    ],

    'defaults' => [
        'language' => env('BEE_MAPS_LANGUAGE', 'pt-BR'),
        'region'   => env('BEE_MAPS_REGION', 'BR'),
    ],

    'http' => [
        'timeout'         => (int) env('BEE_MAPS_HTTP_TIMEOUT', 10),
        'connect_timeout' => (int) env('BEE_MAPS_HTTP_CONNECT_TIMEOUT', 3),
        // Número TOTAL de tentativas, incluindo a primeira (e não o número de
        // repetições): attempts => 2 significa a chamada original + 1 retry.
        'attempts'        => (int) env('BEE_MAPS_HTTP_ATTEMPTS', 2),
        'retry_delay_ms'  => (int) env('BEE_MAPS_HTTP_RETRY_DELAY', 200),
    ],

    'google' => [
        'key' => env('GOOGLE_MAPS_KEY'),
        'endpoints' => [
            'autocomplete'  => 'https://places.googleapis.com/v1/places:autocomplete',
            'geocoding'     => 'https://maps.googleapis.com/maps/api/geocode/json',
            'place_search'  => 'https://places.googleapis.com/v1/places:searchText',
            'routing'       => 'https://routes.googleapis.com/directions/v2:computeRoutes',
        ],
    ],

    'here' => [
        'api_key' => env('HERE_API_KEY'),
        // Converte o scoring.queryScore do HERE (0 a 1) no booleano `partial`:
        // abaixo deste limiar, o resultado é marcado como parcial. O default de
        // 1.0 é deliberadamente conservador e não foi calibrado contra dados
        // reais. Se sua lógica de negócio ramifica em cima de `partial`,
        // calibre este valor contra o seu próprio corpus de endereços antes
        // de trocar de provider.
        'partial_threshold' => (float) env('BEE_MAPS_HERE_PARTIAL_THRESHOLD', 1.0),
        'endpoints' => [
            'autosuggest' => 'https://autosuggest.search.hereapi.com/v1/autosuggest',
            'geocode'     => 'https://geocode.search.hereapi.com/v1/geocode',
            'revgeocode'  => 'https://revgeocode.search.hereapi.com/v1/revgeocode',
            'lookup'      => 'https://lookup.search.hereapi.com/v1/lookup',
            'discover'    => 'https://discover.search.hereapi.com/v1/discover',
            'routing'     => 'https://router.hereapi.com/v8/routes',
        ],
    ],
];
