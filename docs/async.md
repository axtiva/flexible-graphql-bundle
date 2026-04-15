## Async execution

### Amphp v3

To enable async execution, you can set `executor` in `config/packages/flexible_graphql.yaml` as `amphp_v3`.

```yaml

flexible_graphql:
  # ...
  executor: amphp_v3
```
This allows you to return a promise that will be resolved asynchronously.

And in your GraphQL controller add `AmpFutureAdapter` to server config:

```php
use Axtiva\FlexibleGraphql\Executor\AmpFutureAdapter;


$config = ServerConfig::create()
    # ...
    ->setPromiseAdapter(new AmpFutureAdapter())
;

$server = new StandardServer($config);
$promise = $server->executePsrRequest($psrRequest);
$response = null;
if ($promise->adoptedPromise instanceof Future) {
    $response = $promise->adoptedPromise->await()->toArray();
}

return new JsonResponse($response);
```