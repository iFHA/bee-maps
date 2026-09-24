<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Google;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleRouteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleRouteResponseMapper;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class GoogleRouteMapperTest extends TestCase
{
    private function simpleRequest(): RouteRequest
    {
        return new RouteRequest(new Coordinates(-23.5, -46.6), new Coordinates(-23.6, -46.7));
    }

    public function test_the_minimal_payload_has_origin_destination_and_mode(): void
    {
        $payload = (new GoogleRouteRequestMapper())->toPayload($this->simpleRequest(), 'pt-BR');

        $this->assertSame(-23.5, $payload['origin']['location']['latLng']['latitude']);
        $this->assertSame(-46.7, $payload['destination']['location']['latLng']['longitude']);
        $this->assertSame('DRIVE', $payload['travelMode']);
        $this->assertSame('pt-BR', $payload['languageCode']);
        $this->assertArrayNotHasKey('intermediates', $payload);
        $this->assertArrayNotHasKey('optimizeWaypointOrder', $payload);
    }

    public function test_each_travel_mode_becomes_the_google_enum(): void
    {
        $mapper = new GoogleRouteRequestMapper();

        $modes = [
            TravelMode::Drive->value => 'DRIVE',
            TravelMode::TwoWheeler->value => 'TWO_WHEELER',
            TravelMode::Bicycle->value => 'BICYCLE',
            TravelMode::Walk->value => 'WALK',
        ];

        foreach (TravelMode::cases() as $mode) {
            $payload = $mapper->toPayload(
                new RouteRequest(new Coordinates(0, 0), new Coordinates(1, 1), [], $mode),
                'pt-BR',
            );

            $this->assertSame($modes[$mode->value], $payload['travelMode']);
        }
    }

    public function test_intermediates_and_optimization_enter_the_payload(): void
    {
        $payload = (new GoogleRouteRequestMapper())->toPayload(
            new RouteRequest(
                new Coordinates(-23.5, -46.6),
                new Coordinates(-23.6, -46.7),
                [new Coordinates(-23.55, -46.65)],
                TravelMode::Drive,
                optimizeIntermediates: true,
            ),
            'pt-BR',
        );

        $this->assertCount(1, $payload['intermediates']);
        $this->assertSame(-23.55, $payload['intermediates'][0]['location']['latLng']['latitude']);
        $this->assertTrue($payload['optimizeWaypointOrder']);
    }

    public function test_the_field_mask_asks_only_for_what_the_request_needs(): void
    {
        $mapper = new GoogleRouteRequestMapper();

        $minimum = $mapper->fieldMask($this->simpleRequest());

        $this->assertStringContainsString('routes.distanceMeters', $minimum);
        $this->assertStringContainsString('routes.duration', $minimum);
        $this->assertStringNotContainsString('routes.polyline', $minimum);
        $this->assertStringNotContainsString('routes.legs', $minimum);
        $this->assertStringNotContainsString('optimizedIntermediateWaypointIndex', $minimum);

        $full = $mapper->fieldMask(new RouteRequest(
            new Coordinates(-23.5, -46.6),
            new Coordinates(-23.6, -46.7),
            [new Coordinates(-23.55, -46.65)],
            TravelMode::Drive,
            optimizeIntermediates: true,
            includePolyline: true,
            includeLegs: true,
        ));

        $this->assertStringContainsString('routes.polyline.encodedPolyline', $full);
        $this->assertStringContainsString('routes.legs.distanceMeters', $full);
        $this->assertStringContainsString('routes.legs.polyline.encodedPolyline', $full);
        $this->assertStringContainsString('routes.optimizedIntermediateWaypointIndex', $full);
    }

    public function test_the_response_becomes_a_typed_route_with_duration_in_seconds(): void
    {
        $response = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/google/route.json'), true);

        $route = (new GoogleRouteResponseMapper())->toRoute($response, true, true);

        $this->assertSame(12400, $route->distance->meters);
        // O Google devolve duracao como string "1830s"; o contrato e int.
        $this->assertSame(1830, $route->duration->seconds);
        $this->assertSame('_p~iF~ps|U_ulLnnqC_mqNvxq`@', $route->polyline->raw());
        $this->assertCount(3, $route->polyline->coordinates());
        $this->assertSame([1, 0], $route->optimizedOrder);

        $this->assertCount(2, $route->legs);
        $this->assertSame(5200, $route->legs[0]->distance->meters);
        $this->assertSame(780, $route->legs[0]->duration->seconds);
        $this->assertEqualsWithDelta(38.5, $route->legs[0]->origin->latitude, 0.00001);
        $this->assertEqualsWithDelta(43.252, $route->legs[1]->destination->latitude, 0.00001);
        $this->assertSame('_flwFn`faV_mqNvxq`@', $route->legs[1]->polyline->raw());
        // Decodificar, nao so comparar a string: a polyline de uma perna tem que
        // ser autonoma. A primeira versao desta fixture usava um fragmento de
        // delta da polyline da rota, que sozinho decodificava para uma
        // coordenada no Golfo da Guine — e comparar raw() nao pegava isso.
        $this->assertCount(2, $route->legs[1]->polyline->coordinates());
        $this->assertEqualsWithDelta(40.7, $route->legs[1]->polyline->coordinates()[0]->latitude, 0.00001);
    }

    public function test_legs_only_show_up_when_asked_for(): void
    {
        $response = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/google/route.json'), true);

        // Simetrico com o HERE: o contrato diz que pernas sao opt-in, entao o
        // mapper nao repassa o que a resposta trouxer sem ninguem ter pedido.
        $route = (new GoogleRouteResponseMapper())->toRoute($response, false);

        $this->assertSame([], $route->legs);
        $this->assertSame(12400, $route->distance->meters);
    }

    public function test_a_response_without_a_route_becomes_a_typed_exception(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new GoogleRouteResponseMapper())->toRoute(['routes' => []], true);
    }
}
