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

    private function estrategia(): RoutesStrategy
    {
        return new RoutesStrategy(
            $this->app->make(MapsHttpClient::class),
            new GoogleRouteRequestMapper(),
            self::URL,
            'chave-google-de-teste',
            'pt-BR',
        );
    }

    private function ponto(float $offset): Coordinates
    {
        return new Coordinates(-23.5 - $offset, -46.6 - $offset);
    }

    /** @return list<Coordinates> */
    private function paradas(): array
    {
        return [$this->ponto(0.1), $this->ponto(0.2), $this->ponto(0.3)];
    }

    private function fakeDaFixture(): void
    {
        Http::fake(['routes.googleapis.com/directions/*' => Http::response(
            json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/google/route-optimized.json'), true),
            200,
        )]);
    }

    public function test_fim_fixo_soma_todas_as_pernas(): void
    {
        $this->fakeDaFixture();

        $resultado = $this->estrategia()->optimize(new OptimizeWaypointsRequest(
            origin: $this->ponto(0),
            destination: $this->ponto(0.9),
            intermediates: $this->paradas(),
        ));

        $this->assertSame([2, 0, 1], $resultado->order);
        $this->assertSame(10000, $resultado->distance->meters);
        $this->assertSame(1000, $resultado->duration->seconds);
        $this->assertSame('google.routes', $resultado->strategy);
        $this->assertSame(OptimizationObjective::MinTravelTime, $resultado->objective);
    }

    public function test_tour_aberto_descarta_a_ultima_perna(): void
    {
        // Sem destino, o computeRoutes — que EXIGE destination — recebe a origem
        // como destino. A ultima perna e a volta para casa, que ninguem pediu.
        $this->fakeDaFixture();

        $resultado = $this->estrategia()->optimize(new OptimizeWaypointsRequest(
            origin: $this->ponto(0),
            intermediates: $this->paradas(),
        ));

        $this->assertSame([2, 0, 1], $resultado->order, 'a ordem nao muda, so os totais');
        $this->assertSame(6000, $resultado->distance->meters, '10000 menos a perna de 4000');
        $this->assertSame(600, $resultado->duration->seconds);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            $this->assertSame($payload['origin'], $payload['destination'], 'tour aberto fecha na origem');
            $this->assertTrue($payload['optimizeWaypointOrder']);

            return true;
        });
    }

    public function test_field_mask_pede_so_o_que_o_contrato_usa(): void
    {
        $this->fakeDaFixture();

        $this->estrategia()->optimize(new OptimizeWaypointsRequest(
            origin: $this->ponto(0),
            destination: $this->ponto(0.9),
            intermediates: $this->paradas(),
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

    public function test_numero_de_pernas_diferente_do_esperado_e_erro(): void
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

        $this->estrategia()->optimize(new OptimizeWaypointsRequest(
            origin: $this->ponto(0),
            destination: $this->ponto(0.9),
            intermediates: $this->paradas(),
        ));
    }

    public function test_ordem_incompleta_e_erro(): void
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

        $this->estrategia()->optimize(new OptimizeWaypointsRequest(
            origin: $this->ponto(0),
            destination: $this->ponto(0.9),
            intermediates: $this->paradas(),
        ));
    }

    public function test_resposta_sem_rota_e_erro(): void
    {
        Http::fake(['routes.googleapis.com/directions/*' => Http::response(['routes' => []], 200)]);

        $this->expectException(ProviderRequestException::class);

        $this->estrategia()->optimize(new OptimizeWaypointsRequest(
            origin: $this->ponto(0),
            destination: $this->ponto(0.9),
            intermediates: $this->paradas(),
        ));
    }

    public function test_perna_sem_distanceMeters_vale_zero_porque_proto3_omite_zero(): void
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

        $resultado = $this->estrategia()->optimize(new OptimizeWaypointsRequest(
            origin: $this->ponto(0),
            destination: $this->ponto(0.9),
            intermediates: $this->paradas(),
        ));

        $this->assertSame(8000, $resultado->distance->meters);
    }
}
