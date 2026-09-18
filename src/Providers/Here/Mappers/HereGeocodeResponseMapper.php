<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResult;
use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResultCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Support\ValueObjects\Address;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final class HereGeocodeResponseMapper
{
    /**
     * @param float $partialThreshold Abaixo deste queryScore o resultado e considerado parcial.
     *                                Default conservador e nao calibrado — ver a nota da Task 11.
     */
    public function __construct(private readonly float $partialThreshold = 1.0)
    {
    }

    /**
     * Aceita tanto {"items": [...]} (geocode e revgeocode) quanto um objeto unico (lookup).
     */
    public function toCollection(array $resposta): GeocodeResultCollection
    {
        $itens = array_key_exists('items', $resposta)
            ? $resposta['items']
            : (isset($resposta['position']) ? [$resposta] : []);

        return new GeocodeResultCollection(...array_map(
            fn (array $item) => $this->toResult($item),
            $itens,
        ));
    }

    private function toResult(array $item): GeocodeResult
    {
        $endereco = $item['address'] ?? [];
        $cep = $endereco['postalCode'] ?? null;
        $score = isset($item['scoring']['queryScore']) ? (float) $item['scoring']['queryScore'] : null;

        return new GeocodeResult(
            address: new Address(
                street: $endereco['street'] ?? null,
                number: $endereco['houseNumber'] ?? null,
                neighborhood: $endereco['district'] ?? null,
                city: $endereco['city'] ?? null,
                state: $endereco['stateCode'] ?? $endereco['state'] ?? null,
                country: $endereco['countryName'] ?? null,
                postalCode: $cep !== null ? str_replace('-', '', $cep) : null,
                formatted: $endereco['label'] ?? ($item['title'] ?? ''),
            ),
            coordinates: new Coordinates(
                (float) $item['position']['lat'],
                (float) $item['position']['lng'],
            ),
            // O HERE nao tem equivalente ao partial_match: derivamos do queryScore.
            partial: $score !== null && $score < $this->partialThreshold,
            matchScore: $score,
            place: isset($item['id']) ? new PlaceReference(Provider::Here, $item['id']) : null,
        );
    }
}
