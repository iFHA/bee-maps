<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Here;

use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereFindSequenceMapper;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class HereFindSequenceMapperTest extends TestCase
{
    public function test_query_nomeia_os_waypoints_como_a_api_espera(): void
    {
        $query = (new HereFindSequenceMapper())->toQuery(
            new Coordinates(50.10228, 8.69821),
            new Coordinates(50.09878, 8.68752),
            [new Coordinates(50.1001, 8.69), new Coordinates(50.10063, 8.6915)],
            TravelMode::Drive,
            'chave',
        );

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

        $query = $mapper->toQuery(
            new Coordinates(50.10228, 8.69821),
            new Coordinates(50.09878, 8.68752),
            [new Coordinates(50.1001, 8.69), new Coordinates(50.10063, 8.6915)],
            TravelMode::Drive,
            'chave',
        );

        // Amarra os dois lados do mapper com os ids que ele proprio gerou, em vez
        // de com os ids escritos a mao na fixture. Sem isto, tirar o id do valor
        // em toQuery() deixa toOrder() sem casar nada: devolve [], a rota sai na
        // ordem original e NENHUM teste reclama — falha silenciosa, o pior caso.
        $id = fn (string $parameter): string => explode(';', $query[$parameter])[0];

        $fakeResponse = ['results' => [['waypoints' => [
            ['id' => $id('start'), 'sequence' => 0],
            ['id' => $id('destination2'), 'sequence' => 1],
            ['id' => $id('destination1'), 'sequence' => 2],
            ['id' => $id('end'), 'sequence' => 3],
        ]]]];

        $this->assertSame([1, 0], $mapper->toOrder($fakeResponse, 2));
    }

    public function test_ordem_incompleta_lanca_excecao_em_vez_de_devolver_lista_parcial(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/sequencia|ordem/i');

        // Ids que o HERE nao ecoou como destinationN: sem validacao isto voltava
        // [] e os waypoints desapareciam silenciosamente da rota.
        (new HereFindSequenceMapper())->toOrder(['results' => [['waypoints' => [
            ['id' => 'Waypoint0', 'sequence' => 0],
            ['id' => 'Waypoint1', 'sequence' => 1],
        ]]]], 2);
    }

    public function test_indice_fora_de_faixa_lanca_excecao_tipada(): void
    {
        $this->expectException(InvalidRequestException::class);

        // Antes: devolvia [4, 0] e o request mapper estourava com
        // "Call to a member function toString() on null", fora do contrato.
        (new HereFindSequenceMapper())->toOrder(['results' => [['waypoints' => [
            ['id' => 'destination5', 'sequence' => 1],
            ['id' => 'destination1', 'sequence' => 2],
        ]]]], 2);
    }

    public function test_indice_repetido_lanca_excecao_tipada(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new HereFindSequenceMapper())->toOrder(['results' => [['waypoints' => [
            ['id' => 'destination1', 'sequence' => 1],
            ['id' => 'destination1', 'sequence' => 2],
        ]]]], 2);
    }

    public function test_ordem_devolvida_e_traduzida_para_indices_do_array_original(): void
    {
        $response = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/here/findsequence.json'), true);

        // destination2 veio antes de destination1: em indices 0-based do array
        // de intermediarios, isso e [1, 0].
        $this->assertSame([1, 0], (new HereFindSequenceMapper())->toOrder($response, 2));
    }

    public function test_resposta_sem_resultado_vira_excecao_tipada(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new HereFindSequenceMapper())->toOrder(['results' => []], 2);
    }

    public function test_objetivo_de_distancia_vira_improveFor(): void
    {
        // Medido ao vivo em 22/09: improveFor aceita 'time' e 'distance', e o
        // default da API e 'time'. Valor invalido responde 400.
        $query = (new HereFindSequenceMapper())->toQuery(
            new Coordinates(-23.5615, -46.6562),
            new Coordinates(-23.598, -46.686),
            [new Coordinates(-23.5505, -46.6333)],
            TravelMode::Drive,
            'chave',
            OptimizationObjective::MinDistance,
        );

        $this->assertSame('distance', $query['improveFor']);
        // mode continua `fastest`: `shortest` e alavanca separada, muda como cada
        // perna e roteada e nao tem equivalente no Google. Ver D20 do spec.
        $this->assertSame('fastest;car', $query['mode']);
    }

    public function test_objetivo_de_tempo_vira_improveFor(): void
    {
        $query = (new HereFindSequenceMapper())->toQuery(
            new Coordinates(-23.5615, -46.6562),
            new Coordinates(-23.598, -46.686),
            [new Coordinates(-23.5505, -46.6333)],
            TravelMode::Drive,
            'chave',
            OptimizationObjective::MinTravelTime,
        );

        $this->assertSame('time', $query['improveFor']);
    }

    public function test_tour_aberto_omite_end(): void
    {
        // Medido ao vivo: o findsequence2 aceita requisicao sem `end` e responde
        // 200. Tour aberto e nativo no HERE — nada de emular ciclo e descontar.
        $query = (new HereFindSequenceMapper())->toQuery(
            new Coordinates(-23.5615, -46.6562),
            null,
            [new Coordinates(-23.5505, -46.6333)],
            TravelMode::Drive,
            'chave',
        );

        $this->assertArrayNotHasKey('end', $query);
        $this->assertSame('start;-23.5615000,-46.6562000', $query['start']);
    }

    public function test_sem_objetivo_nao_manda_improveFor(): void
    {
        // O contrato Routing nao tem objetivo. Mandar improveFor ali mudaria o
        // comportamento do HereRouting, que este refactor nao pode tocar.
        $query = (new HereFindSequenceMapper())->toQuery(
            new Coordinates(-23.5615, -46.6562),
            new Coordinates(-23.598, -46.686),
            [new Coordinates(-23.5505, -46.6333)],
            TravelMode::Drive,
            'chave',
        );

        $this->assertArrayNotHasKey('improveFor', $query);
    }

    public function test_totais_saem_da_resposta(): void
    {
        $response = json_decode(
            file_get_contents(__DIR__ . '/../../../Fixtures/here/findsequence-otimizacao.json'),
            true,
        );

        $totals = (new HereFindSequenceMapper())->toTotals($response);

        $this->assertSame(23787, $totals['distance']);
        $this->assertSame(3058, $totals['duration']);
    }

    public function test_totais_ausentes_sao_erro(): void
    {
        // O HERE manda distance e time explicitos — ausencia aqui e falha de
        // verdade, nao omissao de valor zero como no proto3 do Google.
        $this->expectException(InvalidRequestException::class);

        (new HereFindSequenceMapper())->toTotals(['results' => [['waypoints' => []]]]);
    }
}
