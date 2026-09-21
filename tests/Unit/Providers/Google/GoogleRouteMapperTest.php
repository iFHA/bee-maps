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
    private function requisicaoSimples(): RouteRequest
    {
        return new RouteRequest(new Coordinates(-23.5, -46.6), new Coordinates(-23.6, -46.7));
    }

    public function test_payload_minimo_tem_origem_destino_e_modo(): void
    {
        $payload = (new GoogleRouteRequestMapper())->toPayload($this->requisicaoSimples(), 'pt-BR');

        $this->assertSame(-23.5, $payload['origin']['location']['latLng']['latitude']);
        $this->assertSame(-46.7, $payload['destination']['location']['latLng']['longitude']);
        $this->assertSame('DRIVE', $payload['travelMode']);
        $this->assertSame('pt-BR', $payload['languageCode']);
        $this->assertArrayNotHasKey('intermediates', $payload);
        $this->assertArrayNotHasKey('optimizeWaypointOrder', $payload);
    }

    public function test_cada_modo_de_viagem_vira_o_enum_do_google(): void
    {
        $mapper = new GoogleRouteRequestMapper();

        $modos = [
            TravelMode::Drive->value => 'DRIVE',
            TravelMode::TwoWheeler->value => 'TWO_WHEELER',
            TravelMode::Bicycle->value => 'BICYCLE',
            TravelMode::Walk->value => 'WALK',
        ];

        foreach (TravelMode::cases() as $modo) {
            $payload = $mapper->toPayload(
                new RouteRequest(new Coordinates(0, 0), new Coordinates(1, 1), [], $modo),
                'pt-BR',
            );

            $this->assertSame($modos[$modo->value], $payload['travelMode']);
        }
    }

    public function test_intermediarios_e_otimizacao_entram_no_payload(): void
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

    public function test_field_mask_so_pede_o_que_a_requisicao_precisa(): void
    {
        $mapper = new GoogleRouteRequestMapper();

        $minimo = $mapper->fieldMask($this->requisicaoSimples());

        $this->assertStringContainsString('routes.distanceMeters', $minimo);
        $this->assertStringContainsString('routes.duration', $minimo);
        $this->assertStringNotContainsString('routes.polyline', $minimo);
        $this->assertStringNotContainsString('routes.legs', $minimo);
        $this->assertStringNotContainsString('optimizedIntermediateWaypointIndex', $minimo);

        $completo = $mapper->fieldMask(new RouteRequest(
            new Coordinates(-23.5, -46.6),
            new Coordinates(-23.6, -46.7),
            [new Coordinates(-23.55, -46.65)],
            TravelMode::Drive,
            optimizeIntermediates: true,
            includePolyline: true,
            includeLegs: true,
        ));

        $this->assertStringContainsString('routes.polyline.encodedPolyline', $completo);
        $this->assertStringContainsString('routes.legs.distanceMeters', $completo);
        $this->assertStringContainsString('routes.legs.polyline.encodedPolyline', $completo);
        $this->assertStringContainsString('routes.optimizedIntermediateWaypointIndex', $completo);
    }

    public function test_resposta_vira_rota_tipada_com_duracao_em_segundos(): void
    {
        $resposta = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/google/route.json'), true);

        $rota = (new GoogleRouteResponseMapper())->toRoute($resposta);

        $this->assertSame(12400, $rota->distance->meters);
        // O Google devolve duracao como string "1830s"; o contrato e int.
        $this->assertSame(1830, $rota->duration->seconds);
        $this->assertSame('_p~iF~ps|U_ulLnnqC_mqNvxq`@', $rota->polyline->raw());
        $this->assertCount(3, $rota->polyline->coordinates());
        $this->assertSame([1, 0], $rota->optimizedOrder);

        $this->assertCount(2, $rota->legs);
        $this->assertSame(5200, $rota->legs[0]->distance->meters);
        $this->assertSame(780, $rota->legs[0]->duration->seconds);
        $this->assertEqualsWithDelta(38.5, $rota->legs[0]->origin->latitude, 0.00001);
        $this->assertEqualsWithDelta(43.252, $rota->legs[1]->destination->latitude, 0.00001);
        $this->assertSame('_mqNvxq`@', $rota->legs[1]->polyline->raw());
    }

    public function test_resposta_sem_rota_vira_excecao_tipada(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new GoogleRouteResponseMapper())->toRoute(['routes' => []]);
    }
}
