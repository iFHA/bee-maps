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
    public function fromItem(array $endereco, string $formatadoPadrao): Address
    {
        $cep = $endereco['postalCode'] ?? null;

        return new Address(
            street: $endereco['street'] ?? null,
            number: $endereco['houseNumber'] ?? null,
            neighborhood: $endereco['district'] ?? null,
            city: $endereco['city'] ?? null,
            state: $endereco['stateCode'] ?? $endereco['state'] ?? null,
            country: $endereco['countryName'] ?? null,
            postalCode: $cep !== null ? str_replace('-', '', $cep) : null,
            formatted: $endereco['label'] ?? $formatadoPadrao,
        );
    }
}
