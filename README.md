# bee-maps

SDK Laravel multi-provider de geolocalização. Google Maps e HERE atrás dos mesmos contratos, com respostas tipadas — trocar de provedor não muda o código que consome.

> **Status:** `0.x`. Autocomplete, geocoding, busca de lugares e rotas estão implementados nos dois provedores. Matriz de rotas e otimização de frota ainda não existem (ver [Ainda não implementado](#ainda-não-implementado)). A API pública pode mudar antes do `1.0.0`.

## Requisitos

- PHP >= 8.2
- Laravel 10, 11 ou 12

## Instalação

```bash
composer require beedelivery/bee-maps
php artisan vendor:publish --tag=bee-maps-config
```

```dotenv
GOOGLE_MAPS_KEY=
HERE_API_KEY=
```

## Uso

O provedor é **sempre explícito**. O pacote nunca decide por você, não cacheia e não guarda estado entre chamadas.

```php
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;

final class BuscaDeEndereco
{
    public function __construct(private MapServiceFactory $maps) {}

    public function buscar(string $texto): void
    {
        $sugestoes = $this->maps->autocomplete(Provider::Here)
            ->suggest(new AutocompleteRequest($texto, countries: ['BR']));

        foreach ($sugestoes as $sugestao) {
            echo $sugestao->mainText;
        }
    }
}
```

A `MapServiceFactory` é injetável. Há também uma facade, para uso pontual:

```php
use BeeDelivery\BeeMaps\Facades\BeeMaps;

$sugestoes = BeeMaps::autocomplete(Provider::Google)->suggest($request);
```

### Escolhendo o provedor em tempo de execução

O pacote não conhece a sua regra de negócio. Se a escolha depende de algum dado seu — cliente, região, teste A/B —, resolva no seu código e passe o `Provider` já escolhido:

```php
$provider = $this->flags->providerPara($contaId, Service::Autocomplete);

$this->maps->autocomplete($provider)->suggest($request);
```

## Autocomplete

```php
new AutocompleteRequest(
    query:        'Av Paulista 1000',
    near:         new Coordinates(-23.5615, -46.6562),  // opcional
    radiusMeters: 3000,                                  // default 50000
    countries:    ['BR'],                                // default: região do config
    language:     'pt-BR',                               // default: idioma do config
);
```

Cada `Suggestion` traz:

| Campo | Tipo | |
|---|---|---|
| `place` | `?PlaceReference` | **pode ser nulo** — ver abaixo |
| `description` | `string` | endereço completo |
| `mainText` | `string` | linha principal |
| `secondaryText` | `string` | complemento |
| `isEstablishment` | `bool` | é estabelecimento, não endereço |

### `place` pode ser nulo — verifique antes do `lookup()`

O Autosuggest do HERE devolve também itens `chainQuery` e `categoryQuery` (ex.: "Postos Shell"), que são **refinamentos de busca, não lugares**, e não podem ser resolvidos. No Google todo resultado tem identificador, então lá o campo nunca é nulo.

```php
$sugestao = $sugestoes->first();

if ($sugestao->place !== null) {
    $endereco = $this->maps->geocoding(Provider::Here)->lookup($sugestao->place);
}
```

## Geocoding

```php
$geocoding = $this->maps->geocoding(Provider::Google);

$geocoding->geocode('Av Paulista 1000', new GeocodeFilters(city: 'São Paulo'));
$geocoding->reverse(new Coordinates(-23.5615, -46.6562));
$geocoding->lookup($placeReference);   // devolve ?GeocodeResult
```

Cada `GeocodeResult` traz `address` (`Address`), `coordinates`, `partial`, `matchScore` e `place`.

### Identificadores de lugar não atravessam provedores

Um `place_id` do Google não significa nada para o HERE. `PlaceReference` registra quem o emitiu, e `lookup()` recusa referências de outro provedor em vez de responder "não encontrado":

```php
$doGoogle = new PlaceReference(Provider::Google, 'ChIJ...');

$maps->geocoding(Provider::Here)->lookup($doGoogle);
// PlaceReferenceProviderMismatchException
```

Se você grava identificadores no banco, **grave também o provedor** — ou re-geocodifique por texto ao trocar.

## Busca de lugares

```php
use BeeDelivery\BeeMaps\DTOs\Requests\PlaceSearchRequest;

$lugares = BeeMaps::placeSearch(Provider::Google)->search(
    new PlaceSearchRequest('farmacia', new Coordinates(-23.5615, -46.6562)),
);

foreach ($lugares as $lugar) {
    echo $lugar->name, ' — ', $lugar->address->formatted, PHP_EOL;
}
```

Cada `Place` traz `place` (`?PlaceReference`), `name`, `address` (`Address` estruturado nos dois provedores) e `coordinates`.

**O HERE exige contexto geográfico.** O `discover` não aceita busca sem `at` ou `in`: passe `near`, ou `region`, ou deixe `bee-maps.defaults.region` preenchido. Sem nenhum dos três, o pacote lança `InvalidRequestException` **antes** de sair para a rede, em vez de deixar o HERE responder 400. No Google os três são opcionais.

## Rota

```php
use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\Enums\TravelMode;

$rota = BeeMaps::routing(Provider::Here)->route(new RouteRequest(
    origin: new Coordinates(-23.5615, -46.6562),
    destination: new Coordinates(-23.5505, -46.6425),
    intermediates: [new Coordinates(-23.5580, -46.6500)],
    mode: TravelMode::TwoWheeler,
    optimizeIntermediates: true,
    includePolyline: true,
    includeLegs: true,
));

echo $rota->distance->kilometers(), ' km em ', $rota->duration->minutes(), ' min', PHP_EOL;

// raw() para repassar ao front; coordinates() decodifica sob demanda.
$pontos = $rota->polyline?->coordinates() ?? $rota->legs[0]->polyline->coordinates();
```

`polyline`, `legs` e `optimizedOrder` são **opt-in**: sem `includePolyline`, `includeLegs` e `optimizeIntermediates`, os dois provedores devolvem `null`, `[]` e `[]`. Pedir geometria encarece o field mask do Google e infla a resposta do HERE, então nada disso vem sem você pedir.

`TravelMode` tem quatro casos, traduzidos para o vocabulário de cada API:

| `TravelMode` | Google | HERE |
|---|---|---|
| `Drive` | `DRIVE` | `car` |
| `TwoWheeler` | `TWO_WHEELER` | `scooter` |
| `Bicycle` | `BICYCLE` | `bicycle` |
| `Walk` | `WALK` | `pedestrian` |

## Códigos de país

O pacote aceita ISO 3166-1 **alpha-2** (`BR`) ou **alpha-3** (`BRA`) e converte para o formato que cada API exige — o Google quer alpha-2, o HERE quer alpha-3. Um código inválido lança `InvalidRequestException` em vez de produzir um filtro que a API ignora em silêncio.

```php
new AutocompleteRequest('Av Paulista', countries: ['BR']);   // ok
new AutocompleteRequest('Av Paulista', countries: ['BRA']);  // ok
new AutocompleteRequest('Av Paulista', countries: ['XX']);   // InvalidRequestException
```

## `partial` e `matchScore`

Os dois provedores respondem perguntas diferentes sobre a qualidade do resultado:

- **Google** devolve `partial_match`, um booleano: "não casei tudo que você pediu".
- **HERE** devolve `scoring.queryScore`, um número de 0 a 1: "casei *assim* de bem".

`GeocodeResult` carrega os dois. `matchScore` é o número cru (`null` no Google) e `partial` é derivado dele pelo limiar `bee-maps.here.partial_threshold`.

> **O limiar default `1.0` não está calibrado.** É o valor mais conservador possível: marca como parcial até um resultado com score 0.97, que na prática é um endereço ótimo. Consequência: o HERE reporta `partial = true` com muito mais frequência que o Google.
>
> Se o seu código ramifica nesse campo, **calibre o limiar contra endereços reais antes de trocar de provedor** — rode o mesmo conjunto nos dois e compare a distribuição do `queryScore` com o `partial_match`. Quando precisar de precisão, leia o `matchScore` diretamente.

```dotenv
BEE_MAPS_HERE_PARTIAL_THRESHOLD=1.0
```

## Erros

| Exceção | Quando |
|---|---|
| `ProviderNotSupportedException` | provedor fora de `bee-maps.providers` |
| `ServiceNotSupportedByProviderException` | provedor não implementa aquele serviço |
| `MissingCredentialsException` | chave de API ausente no config |
| `InvalidRequestException` | entrada inválida (coordenada fora de faixa, código de país desconhecido) |
| `PlaceReferenceProviderMismatchException` | referência de lugar de outro provedor |
| `ProviderAuthenticationException` | 401 / 403 |
| `ProviderRateLimitException` | 429 |
| `ProviderUnavailableException` | 5xx ou timeout |

Todas descendem de `BeeMapsException`, e as três últimas expõem `provider()`, `service()`, `httpStatus()` e `providerCode()`.

**Nenhum resultado não é erro.** Buscas devolvem coleção vazia; `lookup()` devolve `null`. Quem decide se isso é um problema é o seu caso de uso.

**Credenciais não vazam para o log.** Mensagens de erro de conexão do cURL incluem a URL completa, e o HERE envia a chave na query string. O pacote redige esses valores (`apiKey=[REDACTED]`) antes de construir a exceção, e não encadeia a exceção original — que carregaria a mensagem crua para o seu rastreador de erros.

## Observabilidade

Cada chamada dispara `MapRequestCompleted`:

```php
use BeeDelivery\BeeMaps\Support\Events\MapRequestCompleted;

Event::listen(MapRequestCompleted::class, function (MapRequestCompleted $e) {
    Log::info('maps', [
        'provider' => $e->provider->value,
        'service'  => $e->service->value,
        'status'   => $e->httpStatus,      // 0 quando houve falha de conexão
        'ms'       => $e->durationMs,
        'chamadas' => $e->upstreamCalls,
    ]);
});
```

O evento dispara **também em falha e em timeout** — sem isso, um provedor que estoura tempo em 30% das chamadas seria indistinguível de um que nunca foi chamado.

**Cache e métricas ficam por sua conta.** O pacote não cacheia nada. Como os contratos são interfaces, um decorator no seu projeto resolve — e lembre-se de incluir o provedor na chave, senão uma conta que mudar de provedor continuará recebendo a resposta cacheada da anterior:

```php
final class AutocompleteComCache implements \BeeDelivery\BeeMaps\Contracts\Services\Autocomplete
{
    public function __construct(private Autocomplete $inner, private string $provider) {}

    public function suggest(AutocompleteRequest $request): SuggestionCollection
    {
        return Cache::remember(
            "{$this->provider}:autocomplete:" . sha1(serialize($request)),
            now()->addDay(),
            fn () => $this->inner->suggest($request),
        );
    }
}
```

## Configuração

```php
'defaults' => [
    'language' => env('BEE_MAPS_LANGUAGE', 'pt-BR'),
    'region'   => env('BEE_MAPS_REGION', 'BR'),   // usada quando countries vem vazio
],

'http' => [
    'timeout'         => 10,
    'connect_timeout' => 3,
    'attempts'        => 2,    // TOTAL de tentativas, incluindo a primeira
    'retry_delay_ms'  => 200,
],
```

Só 429 e 5xx são repetidos. Falhas de autenticação (401/403) **não** são — repeti-las dobraria a carga exatamente quando o provedor está bloqueando você.

Todo endpoint é sobrescrevível por quem publica o config, o que permite apontar para um proxy ou corrigir uma URL sem esperar release do pacote:

```php
'google' => ['endpoints' => [
    'autocomplete' => 'https://places.googleapis.com/v1/places:autocomplete',
    'geocoding'    => 'https://maps.googleapis.com/maps/api/geocode/json',
    'place_search' => 'https://places.googleapis.com/v1/places:searchText',
    'routing'      => 'https://routes.googleapis.com/directions/v2:computeRoutes',
]],

'here' => ['endpoints' => [
    'autosuggest'  => 'https://autosuggest.search.hereapi.com/v1/autosuggest',
    'geocode'      => 'https://geocode.search.hereapi.com/v1/geocode',
    'revgeocode'   => 'https://revgeocode.search.hereapi.com/v1/revgeocode',
    'lookup'       => 'https://lookup.search.hereapi.com/v1/lookup',
    'discover'     => 'https://discover.search.hereapi.com/v1/discover',
    'routing'      => 'https://router.hereapi.com/v8/routes',
    'findsequence' => 'https://wps.hereapi.com/v8/findsequence2',
]],
```

## Serviços disponíveis

| Serviço | Google | HERE |
|---|---|---|
| Autocomplete | `places:autocomplete` | `/v1/autosuggest` |
| Geocoding | Geocoding API | `/v1/geocode`, `/v1/revgeocode`, `/v1/lookup` |
| PlaceSearch | `places:searchText` | `/v1/discover` |
| Routing | `directions/v2:computeRoutes` | `/v8/routes` (+ `/v8/findsequence2` quando otimiza) |

### Ainda não implementado

Matriz de rotas e otimização de frota **não existem neste pacote**. Não há contrato, não há classe, e chamar não é possível.

## Limitações conhecidas

**A normalização de endereços é ajustada ao Brasil.** Revise antes de usar fora do país:

- O hífen do CEP é removido (`01310-100` → `01310100`). No Brasil ele é cosmético, mas em Portugal, Polônia e Japão faz parte do código.
- No Google, `administrative_area_level_2` é mapeado para `city`. No Brasil esse nível é o município; nos Estados Unidos é o *county*, e o HERE devolveria a cidade — os dois provedores discordariam.

**Uma resposta malformada do HERE devolve coleção vazia** em vez de erro de mapeamento, pela mesma regra de "nenhum resultado não é erro".

**`Route::polyline` é nulo no HERE quando a rota tem waypoints intermediários.** O HERE devolve uma polyline por trecho e não existe forma válida de concatenar duas *flexible polylines* como string. Nesse caso a geometria vive em `RouteLeg::polyline`. O Google sempre devolve a polyline da rota inteira.

**`PlaceSearch` no Google usa o SKU Enterprise do Text Search.** O field mask pede `places.addressComponents` para que `Place::address` venha estruturado como no HERE. Quem preferir o SKU Basic remove o campo do `GooglePlaceSearchRequestMapper::fieldMask()` e passa a receber `Address` apenas com `formatted`.

**Rota otimizada no HERE custa duas chamadas upstream.** O `/v8/routes` não reordena waypoints; a ordem vem da Waypoints Sequence API (`/v8/findsequence2`). As duas chamadas são agrupadas em um único `MapRequestCompleted` com `upstreamCalls: 2`, para que a comparação de latência contra o Google não atribua ao HERE uma lentidão sem causa visível. O endpoint fica em `bee-maps.here.endpoints.findsequence` porque a documentação do HERE é ambígua entre `findsequence2` e `findsequence.json`.

**A suíte não roda contra Laravel 10.** O `orchestra/testbench ^8.0` só casa com versões pontuais do Laravel 10 bloqueadas por advisories de segurança. Isso afeta só o desenvolvimento do pacote — **consumidores em Laravel 10 instalam normalmente** (verificado por resolução do Composer com plataforma forçada).

## Migrando do `beedelivery/google-maps`

Três mudanças observáveis, além dos tipos:

1. **Retornos são DTOs, não arrays.** `$resposta['addresses'][0]['lat']` vira `$resultado->coordinates->latitude`.
2. **Erro é exceção, não chave no array.** Some o `isset($response['code'])`; some também a exceção para "nenhum resultado", que agora é coleção vazia.
3. **O autocomplete do Google passa a ser restrito à região configurada** quando `countries` não é informado. O pacote antigo buscava no mundo todo. Foi o preço de `countries: []` significar a mesma coisa nos dois provedores — "irrestrito nos dois" é impossível, porque o Autosuggest do HERE exige `at` ou `in`. Passe `countries` explicitamente se quiser outro comportamento.

## Testes

```bash
composer install
vendor/bin/phpunit
```

Nenhum teste toca a rede — `Http::preventStrayRequests()` está ativo, então uma requisição não simulada falha em vez de sair.

## Licença

MIT. Ver [LICENSE](LICENSE).
