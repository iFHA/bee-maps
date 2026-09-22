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
        // Limite imposto pela propria API: "The product of the number of origins
        // and destinations must be <= 625" (HTTP 400). Verificado em 2026-09-22.
        'matrix_max_elements' => (int) env('BEE_MAPS_GOOGLE_MATRIX_MAX_ELEMENTS', 625),
        'endpoints' => [
            'autocomplete'  => 'https://places.googleapis.com/v1/places:autocomplete',
            'geocoding'     => 'https://maps.googleapis.com/maps/api/geocode/json',
            'place_search'  => 'https://places.googleapis.com/v1/places:searchText',
            'routing'       => 'https://routes.googleapis.com/directions/v2:computeRoutes',
            'route_matrix'  => 'https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix',
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
        // Foco espacial usado pelo Autosuggest quando a requisicao nao traz
        // coordenada. O endpoint exige um de `at`/`in=bbox`/`in=circle`/`in=ring`
        // e responde 400 sem nenhum deles — `in=countryCode` NAO conta como foco.
        // Sem esta chave e sem AutocompleteRequest::$near, o pacote lanca
        // InvalidRequestException em vez de deixar o 400 vazar do provider.
        // Formato: "latitude,longitude" (ex.: "-23.5615,-46.6562").
        'autosuggest_center' => env('BEE_MAPS_HERE_AUTOSUGGEST_CENTER'),
        'endpoints' => [
            'autosuggest' => 'https://autosuggest.search.hereapi.com/v1/autosuggest',
            'geocode'     => 'https://geocode.search.hereapi.com/v1/geocode',
            'revgeocode'  => 'https://revgeocode.search.hereapi.com/v1/revgeocode',
            'lookup'      => 'https://lookup.search.hereapi.com/v1/lookup',
            'discover'    => 'https://discover.search.hereapi.com/v1/discover',
            'routing'     => 'https://router.hereapi.com/v8/routes',
            // Waypoints Sequence API: resolve a ordem de visita, nao a rota.
            // O host diverge do que a secao 8 do spec registra (router.hereapi.com,
            // que responde 404) — ver D18. Fica em config porque a doc do HERE
            // ainda e ambigua entre `findsequence2` e `findsequence.json`.
            'findsequence' => 'https://wps.hereapi.com/v8/findsequence2',
        ],
    ],
];
