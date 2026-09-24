<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Here;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereRouteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereRouteResponseMapper;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class HereRouteMapperTest extends TestCase
{
    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/here/' . $name), true);
    }

    public function test_query_minima_tem_origem_destino_modo_e_return(): void
    {
        $query = (new HereRouteRequestMapper())->toQuery(
            new RouteRequest(new Coordinates(-23.5, -46.6), new Coordinates(-23.6, -46.7)),
            'pt-BR',
        );

        $this->assertSame('-23.5000000,-46.6000000', $query['origin']);
        $this->assertSame('-23.6000000,-46.7000000', $query['destination']);
        $this->assertSame('car', $query['transportMode']);
        $this->assertSame('summary', $query['return']);
        $this->assertArrayNotHasKey('via', $query);
    }

    public function test_cada_modo_de_viagem_vira_o_transport_mode_do_here(): void
    {
        $mapper = new HereRouteRequestMapper();

        $modes = [
            TravelMode::Drive->value => 'car',
            TravelMode::TwoWheeler->value => 'scooter',
            TravelMode::Bicycle->value => 'bicycle',
            TravelMode::Walk->value => 'pedestrian',
        ];

        foreach (TravelMode::cases() as $mode) {
            $query = $mapper->toQuery(
                new RouteRequest(new Coordinates(0, 0), new Coordinates(1, 1), [], $mode),
                'pt-BR',
            );

            $this->assertSame($modes[$mode->value], $query['transportMode']);
        }
    }

    public function test_polyline_entra_no_return_quando_pedida(): void
    {
        $query = (new HereRouteRequestMapper())->toQuery(
            new RouteRequest(
                new Coordinates(-23.5, -46.6),
                new Coordinates(-23.6, -46.7),
                includePolyline: true,
            ),
            'pt-BR',
        );

        $this->assertSame('summary,polyline', $query['return']);
    }

    public function test_intermediarios_viram_via_repetido_na_ordem_original(): void
    {
        $query = (new HereRouteRequestMapper())->toQuery(
            new RouteRequest(
                new Coordinates(-23.5, -46.6),
                new Coordinates(-23.6, -46.7),
                [new Coordinates(-23.55, -46.65), new Coordinates(-23.58, -46.68)],
            ),
            'pt-BR',
        );

        $this->assertSame(
            ['-23.5500000,-46.6500000', '-23.5800000,-46.6800000'],
            $query['via'],
        );
    }

    public function test_ordem_otimizada_reordena_os_via(): void
    {
        $query = (new HereRouteRequestMapper())->toQuery(
            new RouteRequest(
                new Coordinates(-23.5, -46.6),
                new Coordinates(-23.6, -46.7),
                [new Coordinates(-23.55, -46.65), new Coordinates(-23.58, -46.68)],
            ),
            'pt-BR',
            [1, 0],
        );

        $this->assertSame(
            ['-23.5800000,-46.6800000', '-23.5500000,-46.6500000'],
            $query['via'],
        );
    }

    public function test_ordem_de_tamanho_divergente_lanca_excecao_em_vez_de_apagar_os_via(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/ordem|intermediari/i');

        // Antes: ordem [] fazia o guard `!== []` pular o `via` inteiro, e a rota
        // voltava como viagem direta — plausivel e errada.
        (new HereRouteRequestMapper())->toQuery(
            new RouteRequest(
                new Coordinates(-23.5, -46.6),
                new Coordinates(-23.6, -46.7),
                [new Coordinates(-23.55, -46.65), new Coordinates(-23.58, -46.68)],
            ),
            'pt-BR',
            [],
        );
    }

    public function test_uma_secao_vira_rota_com_polyline_propria(): void
    {
        $route = (new HereRouteResponseMapper())->toRoute($this->fixture('route-uma-secao.json'), true);

        $this->assertSame(5200, $route->distance->meters);
        $this->assertSame(780, $route->duration->seconds);
        $this->assertSame('BFoz5xJ67i1B1B7PzIhaxL7Y', $route->polyline->raw());
        $this->assertCount(4, $route->polyline->coordinates());

        $this->assertCount(1, $route->legs);
        $this->assertEqualsWithDelta(50.10228, $route->legs[0]->origin->latitude, 0.00001);
        $this->assertEqualsWithDelta(50.09878, $route->legs[0]->destination->latitude, 0.00001);
    }

    public function test_duas_secoes_somam_os_totais_e_deixam_a_polyline_da_rota_nula(): void
    {
        $route = (new HereRouteResponseMapper())->toRoute($this->fixture('route.json'), true);

        $this->assertSame(12400, $route->distance->meters);
        $this->assertSame(1830, $route->duration->seconds);

        // D17: concatenar duas flexible polylines como string produz lixo, entao
        // a geometria vive nas pernas quando ha mais de uma secao.
        $this->assertNull($route->polyline);
        $this->assertCount(2, $route->legs);
        $this->assertNotNull($route->legs[0]->polyline);
        $this->assertSame(7200, $route->legs[1]->distance->meters);
    }

    public function test_pernas_so_aparecem_quando_pedidas(): void
    {
        $route = (new HereRouteResponseMapper())->toRoute($this->fixture('route.json'), false);

        $this->assertSame([], $route->legs);
        $this->assertSame(12400, $route->distance->meters);
    }

    public function test_ordem_otimizada_e_repassada_para_o_dto(): void
    {
        $route = (new HereRouteResponseMapper())->toRoute($this->fixture('route.json'), false, [1, 0]);

        $this->assertSame([1, 0], $route->optimizedOrder);
    }

    public function test_resposta_sem_rota_vira_excecao_tipada(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new HereRouteResponseMapper())->toRoute(['routes' => []], false);
    }
}
