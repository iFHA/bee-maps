<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResult;
use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResultCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final class GoogleGeocodeResponseMapper
{
    public function __construct(private readonly GoogleAddressMapper $enderecos = new GoogleAddressMapper())
    {
    }

    public function toCollection(array $resposta): GeocodeResultCollection
    {
        $resultados = [];

        foreach ($resposta['results'] ?? [] as $resultado) {
            $resultados[] = $this->toResult($resultado);
        }

        return new GeocodeResultCollection(...$resultados);
    }

    private function toResult(array $resultado): GeocodeResult
    {
        return new GeocodeResult(
            address: $this->enderecos->fromComponents(
                $resultado['address_components'] ?? [],
                $resultado['formatted_address'] ?? '',
                'long_name',
                'short_name',
            ),
            coordinates: new Coordinates(
                (float) $resultado['geometry']['location']['lat'],
                (float) $resultado['geometry']['location']['lng'],
            ),
            partial: ($resultado['partial_match'] ?? false) === true,
            // O Google nao expoe grau de casamento, so o booleano acima.
            matchScore: null,
            place: isset($resultado['place_id'])
                ? new PlaceReference(Provider::Google, $resultado['place_id'])
                : null,
        );
    }
}
