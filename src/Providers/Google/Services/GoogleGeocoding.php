<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Services;

use BeeDelivery\BeeMaps\Contracts\Services\Geocoding;
use BeeDelivery\BeeMaps\DTOs\Requests\GeocodeFilters;
use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResult;
use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResultCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleGeocodeResponseMapper;
use BeeDelivery\BeeMaps\Support\CountryCode;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final class GoogleGeocoding implements Geocoding
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly GoogleGeocodeResponseMapper $mapper,
        private readonly string $url,
        private readonly string $apiKey,
        private readonly string $language,
    ) {
    }

    public function geocode(string $query, ?GeocodeFilters $filters = null): GeocodeResultCollection
    {
        $parametros = ['address' => $query];

        if ($componentes = $this->componentes($filters)) {
            $parametros['components'] = $componentes;
        }

        return $this->consultar($parametros);
    }

    public function reverse(Coordinates $coordinates): GeocodeResultCollection
    {
        return $this->consultar(['latlng' => $coordinates->toString()]);
    }

    public function lookup(PlaceReference $place): ?GeocodeResult
    {
        $place->assertBelongsTo(Provider::Google);

        return $this->consultar(['place_id' => $place->id])->first();
    }

    private function consultar(array $parametros): GeocodeResultCollection
    {
        $resposta = $this->http->get(
            Provider::Google,
            Service::Geocoding,
            $this->url,
            $parametros + ['language' => $this->language, 'key' => $this->apiKey],
        );

        return $this->mapper->toCollection($resposta);
    }

    private function componentes(?GeocodeFilters $filters): ?string
    {
        if ($filters === null) {
            return null;
        }

        $partes = array_filter([
            $filters->city !== null ? 'administrative_area:' . $filters->city : null,
            $filters->postalCode !== null ? 'postal_code:' . $filters->postalCode : null,
            $filters->country !== null ? 'country:' . CountryCode::toAlpha2($filters->country) : null,
        ]);

        return $partes === [] ? null : implode('|', $partes);
    }
}
