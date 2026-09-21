# bee-maps

SDK Laravel multi-provider de geolocalização. Google Maps e HERE atrás dos mesmos contratos, com respostas tipadas — trocar de provedor não muda o código que consome.

> **Status:** `0.x`. Autocomplete e geocoding estão implementados nos dois provedores. Busca de lugares, rotas, matriz de rotas e otimização de waypoints ainda não existem (ver [Ainda não implementado](#ainda-não-implementado)). A API pública pode mudar antes do `1.0.0`.

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

## Serviços disponíveis

| Serviço | Google | HERE |
|---|---|---|
| Autocomplete | `places:autocomplete` | `/v1/autosuggest` |
| Geocoding | Geocoding API | `/v1/geocode`, `/v1/revgeocode`, `/v1/lookup` |

### Ainda não implementado

Busca de lugares, rotas, matriz de rotas e otimização de waypoints **não existem neste pacote**. Não há contrato, não há classe, e chamar não é possível.

## Limitações conhecidas

**A normalização de endereços é ajustada ao Brasil.** Revise antes de usar fora do país:

- O hífen do CEP é removido (`01310-100` → `01310100`). No Brasil ele é cosmético, mas em Portugal, Polônia e Japão faz parte do código.
- No Google, `administrative_area_level_2` é mapeado para `city`. No Brasil esse nível é o município; nos Estados Unidos é o *county*, e o HERE devolveria a cidade — os dois provedores discordariam.

**Uma resposta malformada do HERE devolve coleção vazia** em vez de erro de mapeamento, pela mesma regra de "nenhum resultado não é erro".

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
