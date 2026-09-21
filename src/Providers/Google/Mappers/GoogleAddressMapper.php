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
    private const COMPONENTES = [
        'route' => 'street',
        'street_number' => 'number',
        'sublocality_level_1' => 'neighborhood',
        'administrative_area_level_2' => 'city',
        'administrative_area_level_1' => 'state',
        'country' => 'country',
        'postal_code' => 'postalCode',
    ];

    /**
     * @param array<int, array<string, mixed>> $componentes
     */
    public function fromComponents(
        array $componentes,
        string $formatado,
        string $chaveTexto,
        string $chaveSigla,
    ): Address {
        $partes = [];

        foreach ($componentes as $componente) {
            foreach ($componente['types'] ?? [] as $tipo) {
                if (! isset(self::COMPONENTES[$tipo])) {
                    continue;
                }

                // O estado usa a sigla (SP), os demais usam o nome por extenso.
                $partes[self::COMPONENTES[$tipo]] = $tipo === 'administrative_area_level_1'
                    ? $componente[$chaveSigla]
                    : $componente[$chaveTexto];
            }
        }

        $rua = $partes['street'] ?? null;

        return new Address(
            street: ($rua === null && ! isset($partes['number'])) ? $formatado : $rua,
            number: $partes['number'] ?? null,
            neighborhood: $partes['neighborhood'] ?? null,
            city: $partes['city'] ?? null,
            state: $partes['state'] ?? null,
            country: $partes['country'] ?? null,
            postalCode: isset($partes['postalCode']) ? str_replace('-', '', $partes['postalCode']) : null,
            formatted: $formatado,
        );
    }
}
