<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Here;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereMatrixRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereMatrixResponseMapper;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class HereMatrixMapperTest extends TestCase
{
    private function request(): RouteMatrixRequest
    {
        return new RouteMatrixRequest(
            [new Coordinates(-23.5615, -46.6562), new Coordinates(-23.5505, -46.6425)],
            [new Coordinates(-23.5580, -46.6500), new Coordinates(-23.5540, -46.6470)],
            TravelMode::TwoWheeler,
        );
    }

    public function test_the_payload_uses_auto_circle_and_asks_for_distance_and_time(): void
    {
        $payload = (new HereMatrixRequestMapper())->toPayload($this->request());

        $this->assertSame(['lat' => -23.5615, 'lng' => -46.6562], $payload['origins'][0]);
        $this->assertSame(['lat' => -23.5580, 'lng' => -46.6500], $payload['destinations'][0]);
        // O HERE calcula o circulo sozinho e devolve o que usou; nao e preciso
        // derivar centro e raio dos pontos no cliente.
        $this->assertSame(['type' => 'autoCircle'], $payload['regionDefinition']);
        $this->assertSame(['distances', 'travelTimes'], $payload['matrixAttributes']);
        $this->assertSame('scooter', $payload['transportMode']);
    }

    public function test_flattened_arrays_become_entries_per_pair(): void
    {
        $response = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/here/matrix.json'), true);

        $collection = (new HereMatrixResponseMapper())->toCollection($response, 2, 3);

        $this->assertCount(6, $collection);

        // Row-major: indice = origem * numDestinations + destino. O par (1,0)
        // fica de fora aqui de proposito: a fixture o marca como inalcancavel, e
        // o teste seguinte cuida dele.
        $this->assertSame(1225, $collection->entry(0, 0)->distance->meters);
        $this->assertSame(262, $collection->entry(0, 0)->duration->seconds);
        $this->assertSame(1629, $collection->entry(0, 1)->distance->meters);
        $this->assertSame(300, $collection->entry(0, 1)->duration->seconds);
        $this->assertSame(2594, $collection->entry(0, 2)->distance->meters);
        $this->assertSame(428, $collection->entry(0, 2)->duration->seconds);
        $this->assertSame(26031, $collection->entry(1, 1)->distance->meters);
        $this->assertSame(2290, $collection->entry(1, 1)->duration->seconds);
        $this->assertSame(25021, $collection->entry(1, 2)->distance->meters);
        $this->assertSame(2201, $collection->entry(1, 2)->duration->seconds);
    }

    public function test_a_nonzero_error_code_marks_the_pair_unreachable(): void
    {
        $response = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/here/matrix.json'), true);

        $collection = (new HereMatrixResponseMapper())->toCollection($response, 2, 3);

        $this->assertFalse($collection->entry(1, 0)->reachable);
        // A fixture traz 26089 metros nessa posicao, mas o errorCode 3 diz que
        // nao ha rota: o contrato zera a medida em vez de propagar um numero
        // que nao corresponde a percurso nenhum.
        $this->assertSame(0, $collection->entry(1, 0)->distance->meters);
        $this->assertSame(0, $collection->entry(1, 0)->duration->seconds);
        $this->assertTrue($collection->entry(0, 0)->reachable);
    }

    public function test_missing_error_codes_means_everything_is_reachable(): void
    {
        // Resposta real do HERE quando nao ha par inalcancavel: o campo
        // errorCodes simplesmente nao vem. Tratar ausencia como erro marcaria
        // a matriz inteira como inalcancavel.
        $response = [
            'matrix' => [
                'numOrigins' => 2,
                'numDestinations' => 3,
                'travelTimes' => [262, 300, 428, 2317, 2290, 2201],
                'distances' => [1225, 1629, 2594, 26089, 26031, 25021],
            ],
        ];

        $collection = (new HereMatrixResponseMapper())->toCollection($response, 2, 3);

        $this->assertCount(6, $collection);

        foreach ($collection as $entry) {
            $this->assertTrue($entry->reachable);
        }
    }

    public function test_a_response_without_a_matrix_becomes_an_exception(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/matriz/i');

        // Envelope de job em vez de matriz: acontece se o async=false se perder
        // ou o HERE degradar uma requisicao grande. Devolver colecao vazia aqui
        // contraria o contrato — pares sem rota vem com reachable=false, nunca
        // ausentes — e o chamador nao distingue "sem matriz" de "matriz de nada".
        (new HereMatrixResponseMapper())->toCollection(['matrixId' => 'abc', 'status' => 'pending'], 2, 2);
    }

    public function test_a_matrix_smaller_than_requested_becomes_an_exception(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/1x2|incompleta|dimens/i');

        (new HereMatrixResponseMapper())->toCollection([
            'matrix' => [
                'numOrigins' => 1,
                'numDestinations' => 2,
                'distances' => [100, 200],
                'travelTimes' => [10, 20],
            ],
        ], 2, 2);
    }

    public function test_swapped_dimensions_become_an_exception_even_with_the_right_product(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/3x2|2x3|dimens/i');

        // 3x2 e 2x3 tem o mesmo produto: a guarda por contagem deixava passar,
        // e o resultado tinha origem fantasma (2,0) e par pedido faltando (0,2).
        (new HereMatrixResponseMapper())->toCollection(['matrix' => [
            'numOrigins' => 3,
            'numDestinations' => 2,
            'distances' => [1, 2, 3, 4, 5, 6],
            'travelTimes' => [1, 2, 3, 4, 5, 6],
        ]], 2, 3);
    }

    public function test_missing_distances_become_an_exception_instead_of_a_zero_meter_matrix(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/distances|medida/i');

        // Sem esta guarda o `?? 0` fabricava a matriz inteira como alcancavel
        // com 0 metros — a versao HERE do defeito que a correcao anterior
        // consertou no Google.
        (new HereMatrixResponseMapper())->toCollection(['matrix' => [
            'numOrigins' => 2,
            'numDestinations' => 3,
            'travelTimes' => [1, 2, 3, 4, 5, 6],
        ]], 2, 3);
    }

    public function test_a_measure_array_shorter_than_the_grid_becomes_an_exception(): void
    {
        $this->expectException(ProviderRequestException::class);

        (new HereMatrixResponseMapper())->toCollection(['matrix' => [
            'numOrigins' => 2,
            'numDestinations' => 3,
            'distances' => [1, 2, 3],
            'travelTimes' => [1, 2, 3, 4, 5, 6],
        ]], 2, 3);
    }

    public function test_error_codes_present_but_short_become_an_exception(): void
    {
        $this->expectException(ProviderRequestException::class);

        // errorCodes ausente significa "tudo alcancavel" e continua valendo;
        // errorCodes presente e curto faria o `?? 0` marcar como alcancavel
        // pares sobre os quais o HERE nao disse nada.
        (new HereMatrixResponseMapper())->toCollection(['matrix' => [
            'numOrigins' => 2,
            'numDestinations' => 3,
            'distances' => [1, 2, 3, 4, 5, 6],
            'travelTimes' => [1, 2, 3, 4, 5, 6],
            'errorCodes' => [0, 0],
        ]], 2, 3);
    }

    public function test_a_scalar_measure_field_becomes_a_typed_exception_not_a_type_error(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/lista|distances/i');

        // `??` so absorve null: sem is_array(), array_values() estoura TypeError,
        // que escapa do contrato de excecoes tipadas do pacote.
        (new HereMatrixResponseMapper())->toCollection(['matrix' => [
            'numOrigins' => 1,
            'numDestinations' => 1,
            'distances' => 123,
            'travelTimes' => [1],
        ]], 1, 1);
    }

    public function test_a_non_numeric_measure_becomes_an_exception_instead_of_an_invented_zero(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/numero|numeric/i');

        // Contar nao basta: um null no meio de um array do tamanho certo passava
        // pela guarda e virava (int) null = 0 metros num par marcado como
        // alcancavel — exatamente a medida inventada que a rodada anterior
        // queria eliminar.
        (new HereMatrixResponseMapper())->toCollection(['matrix' => [
            'numOrigins' => 1,
            'numDestinations' => 2,
            'distances' => [100, null],
            'travelTimes' => [10, 20],
        ]], 1, 2);
    }

    public function test_scalar_error_codes_become_a_typed_exception(): void
    {
        $this->expectException(ProviderRequestException::class);

        (new HereMatrixResponseMapper())->toCollection(['matrix' => [
            'numOrigins' => 1,
            'numDestinations' => 1,
            'distances' => [100],
            'travelTimes' => [10],
            'errorCodes' => 'nenhum',
        ]], 1, 1);
    }

    public static function harmlessErrorCodes(): array
    {
        // As tres formas carregam a mesma informacao: nenhum par com erro.
        return ['ausente' => [null], 'lista vazia' => [[]], 'nulo' => ['NULO']];
    }

    #[DataProvider('harmlessErrorCodes')]
    public function test_empty_or_null_error_codes_are_equivalent_to_missing(mixed $value): void
    {
        $matrix = [
            'numOrigins' => 1,
            'numDestinations' => 2,
            'distances' => [10, 20],
            'travelTimes' => [1, 2],
        ];

        if ($value !== null) {
            $matrix['errorCodes'] = $value === 'NULO' ? null : $value;
        }

        $collection = (new HereMatrixResponseMapper())->toCollection(['matrix' => $matrix], 1, 2);

        // Recusar `[]` seria rejeitar uma matriz completa e valida so porque o
        // HERE serializou o caso vazio em vez de omitir o campo.
        $this->assertCount(2, $collection);
        $this->assertTrue($collection->entry(0, 0)->reachable);
        $this->assertTrue($collection->entry(0, 1)->reachable);
    }
}
