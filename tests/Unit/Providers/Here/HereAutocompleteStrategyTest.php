<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Here;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\Exceptions\ConfigurationException;
use BeeDelivery\BeeMaps\Providers\Here\HereAutocompleteStrategy;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class HereAutocompleteStrategyTest extends TestCase
{
    public function test_auto_with_near_picks_autosuggest(): void
    {
        $choice = HereAutocompleteStrategy::Auto->resolve(
            new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6)),
        );

        $this->assertSame(HereAutocompleteStrategy::Autosuggest, $choice);
    }

    public function test_auto_without_near_picks_autocomplete(): void
    {
        $choice = HereAutocompleteStrategy::Auto->resolve(new AutocompleteRequest('Av Paulista'));

        $this->assertSame(HereAutocompleteStrategy::Autocomplete, $choice);
    }

    public function test_a_forced_strategy_ignores_near(): void
    {
        $withNear = new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6));
        $withoutNear = new AutocompleteRequest('Av Paulista');

        $this->assertSame(
            HereAutocompleteStrategy::Autocomplete,
            HereAutocompleteStrategy::Autocomplete->resolve($withNear),
        );
        $this->assertSame(
            HereAutocompleteStrategy::Autosuggest,
            HereAutocompleteStrategy::Autosuggest->resolve($withoutNear),
        );
    }

    public function test_an_empty_config_falls_back_to_auto(): void
    {
        $this->assertSame(HereAutocompleteStrategy::Auto, HereAutocompleteStrategy::fromConfig(null));
        $this->assertSame(HereAutocompleteStrategy::Auto, HereAutocompleteStrategy::fromConfig(''));
    }

    public function test_an_invalid_config_is_a_configuration_error_and_lists_the_values(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/autocomplete_strategy.*discover.*auto, autosuggest, autocomplete/s');

        HereAutocompleteStrategy::fromConfig('discover');
    }
}
