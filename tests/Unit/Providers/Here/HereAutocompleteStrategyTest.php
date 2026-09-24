<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Here;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\Exceptions\ConfigurationException;
use BeeDelivery\BeeMaps\Providers\Here\HereAutocompleteStrategy;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class HereAutocompleteStrategyTest extends TestCase
{
    public function test_auto_com_near_escolhe_autosuggest(): void
    {
        $escolha = HereAutocompleteStrategy::Auto->resolver(
            new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6)),
        );

        $this->assertSame(HereAutocompleteStrategy::Autosuggest, $escolha);
    }

    public function test_auto_sem_near_escolhe_autocomplete(): void
    {
        $escolha = HereAutocompleteStrategy::Auto->resolver(new AutocompleteRequest('Av Paulista'));

        $this->assertSame(HereAutocompleteStrategy::Autocomplete, $escolha);
    }

    public function test_estrategia_forcada_ignora_o_near(): void
    {
        $comNear = new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6));
        $semNear = new AutocompleteRequest('Av Paulista');

        $this->assertSame(
            HereAutocompleteStrategy::Autocomplete,
            HereAutocompleteStrategy::Autocomplete->resolver($comNear),
        );
        $this->assertSame(
            HereAutocompleteStrategy::Autosuggest,
            HereAutocompleteStrategy::Autosuggest->resolver($semNear),
        );
    }

    public function test_config_vazio_cai_em_auto(): void
    {
        $this->assertSame(HereAutocompleteStrategy::Auto, HereAutocompleteStrategy::fromConfig(null));
        $this->assertSame(HereAutocompleteStrategy::Auto, HereAutocompleteStrategy::fromConfig(''));
    }

    public function test_config_invalido_e_erro_de_configuracao_e_lista_os_valores(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/autocomplete_strategy.*discover.*auto, autosuggest, autocomplete/s');

        HereAutocompleteStrategy::fromConfig('discover');
    }
}
