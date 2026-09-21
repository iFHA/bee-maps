<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Here;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereFindSequenceMapper;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class HereFindSequenceMapperTest extends TestCase
{
    private function requisicao(): RouteRequest
    {
        return new RouteRequest(
            new Coordinates(50.10228, 8.69821),
            new Coordinates(50.09878, 8.68752),
            [new Coordinates(50.1001, 8.69), new Coordinates(50.10063, 8.6915)],
            TravelMode::Drive,
            optimizeIntermediates: true,
        );
    }

    public function test_query_nomeia_os_waypoints_como_a_api_espera(): void
    {
        $query = (new HereFindSequenceMapper())->toQuery($this->requisicao(), 'chave');

        // O formato e `WaypointId;lat,lng`: a API ecoa o id na resposta, e e por
        // ele que a ordem devolvida e reconciliada com o array de intermediarios.
        $this->assertSame('start;50.1022800,8.6982100', $query['start']);
        $this->assertSame('end;50.0987800,8.6875200', $query['end']);
        $this->assertSame('destination1;50.1001000,8.6900000', $query['destination1']);
        $this->assertSame('destination2;50.1006300,8.6915000', $query['destination2']);
        $this->assertSame('fastest;car', $query['mode']);
        $this->assertSame('chave', $query['apiKey']);
    }

    public function test_os_ids_enviados_casam_com_os_ids_que_a_resposta_ecoa(): void
    {
        $mapper = new HereFindSequenceMapper();

        $query = $mapper->toQuery($this->requisicao(), 'chave');

        // Amarra os dois lados do mapper com os ids que ele proprio gerou, em vez
        // de com os ids escritos a mao na fixture. Sem isto, tirar o id do valor
        // em toQuery() deixa toOrder() sem casar nada: devolve [], a rota sai na
        // ordem original e NENHUM teste reclama — falha silenciosa, o pior caso.
        $id = fn (string $parametro): string => explode(';', $query[$parametro])[0];

        $respostaSimulada = ['results' => [['waypoints' => [
            ['id' => $id('start'), 'sequence' => 0],
            ['id' => $id('destination2'), 'sequence' => 1],
            ['id' => $id('destination1'), 'sequence' => 2],
            ['id' => $id('end'), 'sequence' => 3],
        ]]]];

        $this->assertSame([1, 0], $mapper->toOrder($respostaSimulada));
    }

    public function test_ordem_devolvida_e_traduzida_para_indices_do_array_original(): void
    {
        $resposta = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/here/findsequence.json'), true);

        // destination2 veio antes de destination1: em indices 0-based do array
        // de intermediarios, isso e [1, 0].
        $this->assertSame([1, 0], (new HereFindSequenceMapper())->toOrder($resposta));
    }

    public function test_resposta_sem_resultado_vira_excecao_tipada(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new HereFindSequenceMapper())->toOrder(['results' => []]);
    }
}
