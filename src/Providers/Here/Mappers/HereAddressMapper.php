<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\Support\ValueObjects\Address;

/**
 * O objeto `address` e identico em geocode, revgeocode, lookup e discover —
 * uma unica traducao para todos, inclusive as regras ajustadas ao Brasil
 * (hifen do CEP removido, district como bairro).
 */
final class HereAddressMapper
{
    public function fromItem(array $address, string $defaultFormatted): Address
    {
        $postalCode = $address['postalCode'] ?? null;

        return new Address(
            street: $address['street'] ?? null,
            number: $address['houseNumber'] ?? null,
            neighborhood: $address['district'] ?? null,
            city: $address['city'] ?? null,
            state: $address['stateCode'] ?? $address['state'] ?? null,
            country: $address['countryName'] ?? null,
            postalCode: $postalCode !== null ? str_replace('-', '', $postalCode) : null,
            formatted: $address['label'] ?? $defaultFormatted,
        );
    }
}
