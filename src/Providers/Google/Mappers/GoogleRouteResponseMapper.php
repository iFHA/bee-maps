<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\Route;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteLeg;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\Polyline\GoogleEncodedPolylineDecoder;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;
use BeeDelivery\BeeMaps\Support\ValueObjects\Polyline;

final class GoogleRouteResponseMapper
{
    public function __construct(
        private readonly GoogleEncodedPolylineDecoder $decoder = new GoogleEncodedPolylineDecoder(),
    ) {
    }

    /**
     * @param bool $incluirPernas           Pernas sao opt-in no contrato do pacote. O field
     *                                      mask ja omite `routes.legs` quando ninguem pediu,
     *                                      mas honrar o flag aqui tambem e o que garante a
     *                                      simetria com o HERE: sem includeLegs, NENHUM
     *                                      provider devolve pernas, independente do que a
     *                                      resposta trouxer.
     * @param bool $otimizouIntermediarios  Mesma razao para optimizedOrder, que o DTO Route
     *                                      documenta como "vazio sem otimizacao".
     */
    /**
     * @param bool $pediuAlternativas Mesma razao de `incluirPernas`: sem pedido, NENHUM
     *                                provider devolve alternativas, ainda que a resposta
     *                                traga — simetria antes de generosidade.
     */
    public function toRoute(
        array $resposta,
        bool $incluirPernas,
        bool $otimizouIntermediarios = false,
        bool $pediuAlternativas = false,
    ): Route {
        $rotas = $resposta['routes'] ?? [];
        $rota = $rotas[0] ?? null;

        if ($rota === null) {
            throw new InvalidRequestException('O Google nao devolveu rota para os pontos informados.');
        }

        return new Route(
            distance: new Distance((int) ($rota['distanceMeters'] ?? 0)),
            duration: new Duration($this->segundos($rota['duration'] ?? null)),
            polyline: $this->polyline($rota['polyline']['encodedPolyline'] ?? null),
            legs: $incluirPernas ? array_map($this->perna(...), $rota['legs'] ?? []) : [],
            optimizedOrder: $otimizouIntermediarios
                ? array_map('intval', $rota['optimizedIntermediateWaypointIndex'] ?? [])
                : [],
            alternatives: $pediuAlternativas
                ? array_map(
                    fn (array $outra) => $this->rotaSimples($outra, $incluirPernas),
                    array_values(array_slice($rotas, 1)),
                )
                : [],
        );
    }

    /**
     * Alternativa nao carrega alternativas proprias nem ordem otimizada: a ordem
     * dos intermediarios e uma so para a requisicao inteira.
     */
    private function rotaSimples(array $rota, bool $incluirPernas): Route
    {
        return new Route(
            distance: new Distance((int) ($rota['distanceMeters'] ?? 0)),
            duration: new Duration($this->segundos($rota['duration'] ?? null)),
            polyline: $this->polyline($rota['polyline']['encodedPolyline'] ?? null),
            legs: $incluirPernas ? array_map($this->perna(...), $rota['legs'] ?? []) : [],
        );
    }

    private function perna(array $perna): RouteLeg
    {
        return new RouteLeg(
            origin: $this->ponto($perna['startLocation']['latLng'] ?? []),
            destination: $this->ponto($perna['endLocation']['latLng'] ?? []),
            distance: new Distance((int) ($perna['distanceMeters'] ?? 0)),
            duration: new Duration($this->segundos($perna['duration'] ?? null)),
            polyline: $this->polyline($perna['polyline']['encodedPolyline'] ?? null),
        );
    }

    private function ponto(array $latLng): Coordinates
    {
        return new Coordinates((float) ($latLng['latitude'] ?? 0), (float) ($latLng['longitude'] ?? 0));
    }

    private function polyline(?string $codificada): ?Polyline
    {
        return $codificada !== null ? new Polyline($codificada, $this->decoder) : null;
    }

    /**
     * O Google serializa duracao como string de protobuf ("1830s"); o HERE manda
     * inteiro. A normalizacao acontece aqui, na fronteira do mapper.
     */
    private function segundos(string|int|null $valor): int
    {
        return match (true) {
            $valor === null => 0,
            is_int($valor) => $valor,
            default => (int) rtrim($valor, 's'),
        };
    }
}
