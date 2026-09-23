<?php

namespace BeeDelivery\BeeMaps\DTOs\Responses;

use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;
use BeeDelivery\BeeMaps\Support\ValueObjects\Polyline;

final readonly class Route
{
    /**
     * @param Polyline|null  $polyline       Geometria da rota inteira. Null quando nao
     *                                       foi pedida e tambem quando o provider so
     *                                       fornece geometria por perna — ver D17.
     * @param list<RouteLeg> $legs
     * @param list<int>      $optimizedOrder Indices dos intermediarios na ordem que o
     *                                       provider escolheu. Vazio sem otimizacao.
     * @param list<Route>    $alternatives   Outras rotas para o mesmo trajeto, quando
     *                                       `RouteRequest::$alternatives` pediu. Vazio
     *                                       por padrao. Esta rota e a preferida do
     *                                       provider; as alternativas vem sem
     *                                       alternativas proprias, para nao aninhar.
     *                                       Escolher entre elas e do chamador.
     */
    public function __construct(
        public Distance $distance,
        public Duration $duration,
        public ?Polyline $polyline,
        public array $legs = [],
        public array $optimizedOrder = [],
        public array $alternatives = [],
    ) {
    }
}
