<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResult;
use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResultCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Support\ValueObjects\Address;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final class GoogleGeocodeResponseMapper
{
    private const COMPONENTES = [
        'route' => 'street',
        'street_number' => 'number',
        'sublocality_level_1' => 'neighborhood',
        'administrative_area_level_2' => 'city',
        'administrative_area_level_1' => 'state',
        'country' => 'country',
        'postal_code' => 'postalCode',
    ];

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
        $partes = [];

        foreach ($resultado['address_components'] ?? [] as $componente) {
            foreach ($componente['types'] ?? [] as $tipo) {
                if (! isset(self::COMPONENTES[$tipo])) {
                    continue;
                }

                // O estado usa a sigla (SP), os demais usam o nome por extenso.
                $partes[self::COMPONENTES[$tipo]] = $tipo === 'administrative_area_level_1'
                    ? $componente['short_name']
                    : $componente['long_name'];
            }
        }

        $formatado = $resultado['formatted_address'] ?? '';
        $rua = $partes['street'] ?? null;

        return new GeocodeResult(
            address: new Address(
                street: ($rua === null && ! isset($partes['number'])) ? $formatado : $rua,
                number: $partes['number'] ?? null,
                neighborhood: $partes['neighborhood'] ?? null,
                city: $partes['city'] ?? null,
                state: $partes['state'] ?? null,
                country: $partes['country'] ?? null,
                postalCode: isset($partes['postalCode']) ? str_replace('-', '', $partes['postalCode']) : null,
                formatted: $formatado,
            ),
            coordinates: new Coordinates(
                (float) $resultado['geometry']['location']['lat'],
                (float) $resultado['geometry']['location']['lng'],
            ),
            partial: isset($resultado['partial_match']),
            // O Google nao expoe grau de casamento, so o booleano acima.
            matchScore: null,
            place: isset($resultado['place_id'])
                ? new PlaceReference(Provider::Google, $resultado['place_id'])
                : null,
        );
    }
}
