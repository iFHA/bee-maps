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
     * @param list<int> $ordemOtimizada
     * @param bool      $pediuAlternativas Mesma razao de `incluirPernas`: sem pedido,
     *                                     NENHUM provider devolve alternativas, ainda que
     *                                     a resposta traga — simetria antes de generosidade.
     */
    public function toRoute(
        array $resposta,
        bool $incluirPernas,
        array $ordemOtimizada = [],
        bool $pediuAlternativas = false,
    ): Route {
        $rotas = $resposta['routes'] ?? [];
        $secoes = $rotas[0]['sections'] ?? null;

        if ($secoes === null || $secoes === []) {
            throw new InvalidRequestException('O HERE nao devolveu rota para os pontos informados.');
        }

        return $this->rota($secoes, $incluirPernas, $ordemOtimizada, $pediuAlternativas
            ? array_values(array_filter(array_map(
                fn (array $outra) => $this->rotaAlternativa($outra, $incluirPernas),
                array_slice($rotas, 1),
            )))
            : []);
    }

    /**
     * Alternativa sem secoes e resposta incompleta do provider, nao alternativa
     * vazia: descartamos em vez de fabricar uma rota de zero metros.
     */
    private function rotaAlternativa(array $rota, bool $incluirPernas): ?Route
    {
        $secoes = $rota['sections'] ?? null;

        if ($secoes === null || $secoes === []) {
            return null;
        }

        return $this->rota($secoes, $incluirPernas, [], []);
    }

    /**
     * @param list<int>   $ordemOtimizada
     * @param list<Route> $alternativas
     */
    private function rota(array $secoes, bool $incluirPernas, array $ordemOtimizada, array $alternativas): Route
    {
        $metros = 0;
        $segundos = 0;

        foreach ($secoes as $secao) {
            $metros += (int) ($secao['summary']['length'] ?? 0);
            $segundos += (int) ($secao['summary']['duration'] ?? 0);
        }

        return new Route(
            distance: new Distance($metros),
            duration: new Duration($segundos),
            // D17: com mais de uma secao nao existe polyline unica da rota — e a
            // geometria fica nas pernas. Concatenar as strings produziria lixo.
            polyline: count($secoes) === 1 ? $this->polyline($secoes[0]['polyline'] ?? null) : null,
            legs: $incluirPernas ? array_map($this->perna(...), $secoes) : [],
            optimizedOrder: $ordemOtimizada,
            alternatives: $alternativas,
        );
    }

    private function perna(array $secao): RouteLeg
    {
        return new RouteLeg(
            origin: $this->ponto($secao['departure']['place']['location'] ?? []),
            destination: $this->ponto($secao['arrival']['place']['location'] ?? []),
            distance: new Distance((int) ($secao['summary']['length'] ?? 0)),
            duration: new Duration((int) ($secao['summary']['duration'] ?? 0)),
            polyline: $this->polyline($secao['polyline'] ?? null),
        );
    }

    private function ponto(array $localizacao): Coordinates
    {
        return new Coordinates((float) ($localizacao['lat'] ?? 0), (float) ($localizacao['lng'] ?? 0));
    }

    private function polyline(?string $codificada): ?Polyline
    {
        return $codificada !== null ? new Polyline($codificada, $this->decoder) : null;
    }
}
