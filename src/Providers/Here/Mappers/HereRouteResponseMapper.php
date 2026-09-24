<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\Route;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteLeg;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\Polyline\HereFlexiblePolylineDecoder;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;
use BeeDelivery\BeeMaps\Support\ValueObjects\Polyline;

final class HereRouteResponseMapper
{
    public function __construct(
        private readonly HereFlexiblePolylineDecoder $decoder = new HereFlexiblePolylineDecoder(),
    ) {
    }

    /**
     * @param list<int> $optimizedOrder
     * @param bool      $askedForAlternatives Mesma razao de `incluirPernas`: sem pedido,
     *                                     NENHUM provider devolve alternativas, ainda que
     *                                     a resposta traga — simetria antes de generosidade.
     */
    public function toRoute(
        array $response,
        bool $includeLegs,
        array $optimizedOrder = [],
        bool $askedForAlternatives = false,
    ): Route {
        $routes = $response['routes'] ?? [];
        $sections = $routes[0]['sections'] ?? null;

        if ($sections === null || $sections === []) {
            throw new InvalidRequestException('O HERE nao devolveu rota para os pontos informados.');
        }

        return $this->route($sections, $includeLegs, $optimizedOrder, $askedForAlternatives
            ? array_values(array_filter(array_map(
                fn (array $other) => $this->alternativeRoute($other, $includeLegs),
                array_slice($routes, 1),
            )))
            : []);
    }

    /**
     * Alternativa sem secoes e resposta incompleta do provider, nao alternativa
     * vazia: descartamos em vez de fabricar uma rota de zero metros.
     */
    private function alternativeRoute(array $route, bool $includeLegs): ?Route
    {
        $sections = $route['sections'] ?? null;

        if ($sections === null || $sections === []) {
            return null;
        }

        return $this->route($sections, $includeLegs, [], []);
    }

    /**
     * @param list<int>   $optimizedOrder
     * @param list<Route> $alternativas
     */
    private function route(array $sections, bool $includeLegs, array $optimizedOrder, array $alternativas): Route
    {
        $meters = 0;
        $seconds = 0;

        foreach ($sections as $section) {
            $meters += (int) ($section['summary']['length'] ?? 0);
            $seconds += (int) ($section['summary']['duration'] ?? 0);
        }

        return new Route(
            distance: new Distance($meters),
            duration: new Duration($seconds),
            // D17: com mais de uma secao nao existe polyline unica da rota — e a
            // geometria fica nas pernas. Concatenar as strings produziria lixo.
            polyline: count($sections) === 1 ? $this->polyline($sections[0]['polyline'] ?? null) : null,
            legs: $includeLegs ? array_map($this->leg(...), $sections) : [],
            optimizedOrder: $optimizedOrder,
            alternatives: $alternativas,
        );
    }

    private function leg(array $section): RouteLeg
    {
        return new RouteLeg(
            origin: $this->point($section['departure']['place']['location'] ?? []),
            destination: $this->point($section['arrival']['place']['location'] ?? []),
            distance: new Distance((int) ($section['summary']['length'] ?? 0)),
            duration: new Duration((int) ($section['summary']['duration'] ?? 0)),
            polyline: $this->polyline($section['polyline'] ?? null),
        );
    }

    private function point(array $location): Coordinates
    {
        return new Coordinates((float) ($location['lat'] ?? 0), (float) ($location['lng'] ?? 0));
    }

    private function polyline(?string $encoded): ?Polyline
    {
        return $encoded !== null ? new Polyline($encoded, $this->decoder) : null;
    }
}
