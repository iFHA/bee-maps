<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\Support\ValueObjects\Address;

/**
 * O Google devolve componentes de endereco em dois formatos: long_name/short_name
 * no Geocoding API e longText/shortText no Places v1. As regras de negocio sao as
 * mesmas nos dois — e duplica-las e garantir que divirjam.
 */
final class GoogleAddressMapper
{
    private const COMPONENTS = [
        'route' => 'street',
        'street_number' => 'number',
        'sublocality_level_1' => 'neighborhood',
        'administrative_area_level_2' => 'city',
        'administrative_area_level_1' => 'state',
        'country' => 'country',
        'postal_code' => 'postalCode',
    ];

    /**
     * @param array<int, array<string, mixed>> $components
     */
    public function fromComponents(
        array $components,
        string $formatted,
        string $textKey,
        string $codeKey,
    ): Address {
        $parts = [];

        foreach ($components as $component) {
            foreach ($component['types'] ?? [] as $type) {
                if (! isset(self::COMPONENTS[$type])) {
                    continue;
                }

                // O estado usa a sigla (SP), os demais usam o nome por extenso.
                $parts[self::COMPONENTS[$type]] = $type === 'administrative_area_level_1'
                    ? $component[$codeKey]
                    : $component[$textKey];
            }
        }

        $street = $parts['street'] ?? null;

        return new Address(
            street: ($street === null && ! isset($parts['number'])) ? $formatted : $street,
            number: $parts['number'] ?? null,
            neighborhood: $parts['neighborhood'] ?? null,
            city: $parts['city'] ?? null,
            state: $parts['state'] ?? null,
            country: $parts['country'] ?? null,
            postalCode: isset($parts['postalCode']) ? str_replace('-', '', $parts['postalCode']) : null,
            formatted: $formatted,
        );
    }
}
