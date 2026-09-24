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

    public function test_the_minimal_query_has_origin_destination_mode_and_return(): void
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

    public function test_each_travel_mode_becomes_the_here_transport_mode(): void
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

    public function test_the_polyline_enters_the_return_when_asked_for(): void
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

    public function test_intermediates_become_repeated_via_in_the_original_order(): void
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

    public function test_the_optimized_order_reorders_the_via(): void
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

    public function test_an_order_of_a_different_size_throws_instead_of_erasing_the_via(): void
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

    public function test_a_single_section_becomes_a_route_with_its_own_polyline(): void
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

    public function test_two_sections_sum_the_totals_and_leave_the_route_polyline_null(): void
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

    public function test_legs_only_show_up_when_asked_for(): void
    {
        $route = (new HereRouteResponseMapper())->toRoute($this->fixture('route.json'), false);

        $this->assertSame([], $route->legs);
        $this->assertSame(12400, $route->distance->meters);
    }

    public function test_the_optimized_order_is_passed_through_to_the_dto(): void
    {
        $route = (new HereRouteResponseMapper())->toRoute($this->fixture('route.json'), false, [1, 0]);

        $this->assertSame([1, 0], $route->optimizedOrder);
    }

    public function test_a_response_without_a_route_becomes_a_typed_exception(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new HereRouteResponseMapper())->toRoute(['routes' => []], false);
    }
}
