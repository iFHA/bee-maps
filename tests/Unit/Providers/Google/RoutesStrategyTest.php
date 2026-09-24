<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Google;

use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleRouteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Optimization\RoutesStrategy;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class RoutesStrategyTest extends TestCase
{
    private const URL = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    private function strategy(): RoutesStrategy
    {
        return new RoutesStrategy(
            $this->app->make(MapsHttpClient::class),
            new GoogleRouteRequestMapper(),
            self::URL,
            'chave-google-de-teste',
            'pt-BR',
        );
    }

    private function point(float $offset): Coordinates
    {
        return new Coordinates(-23.5 - $offset, -46.6 - $offset);
    }

    /** @return list<Coordinates> */
    private function stops(): array
    {
        return [$this->point(0.1), $this->point(0.2), $this->point(0.3)];
    }

    private function fakeFromFixture(): void
    {
        Http::fake(['routes.googleapis.com/directions/*' => Http::response(
            json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/google/route-optimized.json'), true),
            200,
        )]);
    }

    public function test_a_fixed_end_sums_every_leg(): void
    {
        $this->fakeFromFixture();

        $result = $this->strategy()->optimize(new OptimizeWaypointsRequest(
            origin: $this->point(0),
            destination: $this->point(0.9),
            intermediates: $this->stops(),
        ));

        $this->assertSame([2, 0, 1], $result->order);
        $this->assertSame(10000, $result->distance->meters);
        $this->assertSame(1000, $result->duration->seconds);
        $this->assertSame('google.routes', $result->strategy);
        $this->assertSame(OptimizationObjective::MinTravelTime, $result->objective);
    }

    public function test_an_open_tour_drops_the_last_leg(): void
    {
        // Sem destino, o computeRoutes — que EXIGE destination — recebe a origem
        // como destino. A ultima perna e a volta para casa, que ninguem pediu.
        $this->fakeFromFixture();

        $result = $this->strategy()->optimize(new OptimizeWaypointsRequest(
            origin: $this->point(0),
            intermediates: $this->stops(),
        ));

        $this->assertSame([2, 0, 1], $result->order, 'a ordem nao muda, so os totais');
        $this->assertSame(6000, $result->distance->meters, '10000 menos a perna de 4000');
        $this->assertSame(600, $result->duration->seconds);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            $this->assertSame($payload['origin'], $payload['destination'], 'tour aberto fecha na origem');
            $this->assertTrue($payload['optimizeWaypointOrder']);

            return true;
        });
    }

    public function test_the_field_mask_asks_only_for_what_the_contract_uses(): void
    {
        $this->fakeFromFixture();

        $this->strategy()->optimize(new OptimizeWaypointsRequest(
            origin: $this->point(0),
            destination: $this->point(0.9),
            intermediates: $this->stops(),
        ));

        Http::assertSent(function ($request): bool {
            // Geometria e localizacao de perna sao cobradas por campo e nao sao
            // usadas aqui: este contrato devolve ordem e totais, nao tracado.
            $this->assertSame(
                'routes.optimizedIntermediateWaypointIndex,routes.legs.distanceMeters,routes.legs.duration',
                $request->header('X-Goog-FieldMask')[0],
            );

            return true;
        });
    }

    public function test_a_leg_count_different_from_the_expected_is_an_error(): void
    {
        // 3 intermediarios => 4 pernas. Com 3, somar o que veio produziria um
        // total silenciosamente menor.
        Http::fake(['routes.googleapis.com/directions/*' => Http::response([
            'routes' => [[
                'optimizedIntermediateWaypointIndex' => [0, 1, 2],
                'legs' => [
                    ['distanceMeters' => 1000, 'duration' => '100s'],
                    ['distanceMeters' => 2000, 'duration' => '200s'],
                    ['distanceMeters' => 3000, 'duration' => '300s'],
                ],
            ]],
        ], 200)]);

        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/3 pernas.*4|4.*3 pernas/s');

        $this->strategy()->optimize(new OptimizeWaypointsRequest(
            origin: $this->point(0),
            destination: $this->point(0.9),
            intermediates: $this->stops(),
        ));
    }

    public function test_an_incomplete_order_is_an_error(): void
    {
        Http::fake(['routes.googleapis.com/directions/*' => Http::response([
            'routes' => [[
                'optimizedIntermediateWaypointIndex' => [0, 1],
                'legs' => [
                    ['distanceMeters' => 1000, 'duration' => '100s'],
                    ['distanceMeters' => 2000, 'duration' => '200s'],
                    ['distanceMeters' => 3000, 'duration' => '300s'],
                    ['distanceMeters' => 4000, 'duration' => '400s'],
                ],
            ]],
        ], 200)]);

        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/2 de 3/');

        $this->strategy()->optimize(new OptimizeWaypointsRequest(
            origin: $this->point(0),
            destination: $this->point(0.9),
            intermediates: $this->stops(),
        ));
    }

    public function test_a_response_without_a_route_is_an_error(): void
    {
        Http::fake(['routes.googleapis.com/directions/*' => Http::response(['routes' => []], 200)]);

        $this->expectException(ProviderRequestException::class);

        $this->strategy()->optimize(new OptimizeWaypointsRequest(
            origin: $this->point(0),
            destination: $this->point(0.9),
            intermediates: $this->stops(),
        ));
    }

    public function test_a_leg_without_distanceMeters_counts_as_zero_because_proto3_omits_zero(): void
    {
        // Duas paradas na mesma coordenada produzem perna de 0 metros, e o proto3
        // omite o campo. Recusar aqui rejeitaria resposta valida.
        Http::fake(['routes.googleapis.com/directions/*' => Http::response([
            'routes' => [[
                'optimizedIntermediateWaypointIndex' => [0, 1, 2],
                'legs' => [
                    ['distanceMeters' => 1000, 'duration' => '100s'],
                    ['duration' => '0s'],
                    ['distanceMeters' => 3000, 'duration' => '300s'],
                    ['distanceMeters' => 4000, 'duration' => '400s'],
                ],
            ]],
        ], 200)]);

        $result = $this->strategy()->optimize(new OptimizeWaypointsRequest(
            origin: $this->point(0),
            destination: $this->point(0.9),
            intermediates: $this->stops(),
        ));

        $this->assertSame(8000, $result->distance->meters);
    }
}
