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
        'retries'         => (int) env('BEE_MAPS_HTTP_RETRIES', 2),
        'retry_delay_ms'  => (int) env('BEE_MAPS_HTTP_RETRY_DELAY', 200),
    ],

    'google' => [
        'key' => env('GOOGLE_MAPS_KEY'),
        'endpoints' => [
            'autocomplete' => 'https://places.googleapis.com/v1/places:autocomplete',
            'geocoding'    => 'https://maps.googleapis.com/maps/api/geocode/json',
        ],
    ],

    'here' => [
        'api_key' => env('HERE_API_KEY'),
        // Abaixo deste queryScore o resultado e marcado como parcial.
        // 1.0 e o valor mais conservador e NAO esta calibrado: ver Task 11.
        'partial_threshold' => (float) env('BEE_MAPS_HERE_PARTIAL_THRESHOLD', 1.0),
        'endpoints' => [
            'autosuggest' => 'https://autosuggest.search.hereapi.com/v1/autosuggest',
            'geocode'     => 'https://geocode.search.hereapi.com/v1/geocode',
            'revgeocode'  => 'https://revgeocode.search.hereapi.com/v1/revgeocode',
            'lookup'      => 'https://lookup.search.hereapi.com/v1/lookup',
        ],
    ],
];
