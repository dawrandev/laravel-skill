# API design

Rules for JSON APIs. Existing APIs keep their current shape; these are the defaults for a new one or a new version.

## Contents

- [Version in the URI](#version-in-the-uri)
- [Name Resources With Plural Nouns](#name-resources-with-plural-nouns)
- [Keep Laravel's Response Envelope](#keep-laravels-response-envelope)
- [Return Errors as Problem Details](#return-errors-as-problem-details)
- [Status codes](#status-codes)
- [Choose the Pagination Style by Table Size](#choose-the-pagination-style-by-table-size)
- [Order Deterministically Before Paginating](#order-deterministically-before-paginating)
- [Cap Page Size on the Server](#cap-page-size-on-the-server)
- [Allow-List Every Filter, Sort, and Include](#allow-list-every-filter-sort-and-include)
- [Load Relationships Through whenLoaded](#load-relationships-through-whenloaded)
- [Rate limit](#rate-limit)
- [Accept an Idempotency Key on Repeatable Writes](#accept-an-idempotency-key-on-repeatable-writes)
- [Generate OpenAPI From the Code](#generate-openapi-from-the-code)
- [Skip HATEOAS](#skip-hateoas)

## Version in the URI

`/api/v1/orders`. The version is visible in logs, gateways, CDN cache keys, and a pasted URL, and every tool assumes it.

```php
// bootstrap/app.php
->withRouting(
    api: __DIR__.'/../routes/api_v1.php',
    apiPrefix: 'api/v1',
)
```

Inside a released version, only add. A field removal, a type change, or a status-code change is a new version. Announce retirement with `Deprecation` and `Sunset` headers and keep the old version running through the window.

## Name Resources With Plural Nouns

`/api/v1/orders`, `/api/v1/orders/{order}/lines`. Path segments are kebab-case; JSON keys follow whatever the project already emits, usually snake_case.

Stick to the seven resource actions. A custom verb is usually a second resource:

Incorrect:
```php
Route::post('/orders/{order}/publish', [OrderController::class, 'publish']);
```

Correct:
```php
Route::post('/published-orders/{order}', [PublishedOrderController::class, 'store']);
Route::delete('/published-orders/{order}', [PublishedOrderController::class, 'destroy']);
```

Treat a custom verb as a signal to look at the model, not as proof another controller is required.

Scope nested bindings so a child cannot be read through the wrong parent. This constrains resolution; it does not replace authorization.

```php
Route::apiResource('orders.lines', OrderLineController::class)->scopeBindings();
```

`apiResource()` drops `create` and `edit`. It does not add the `/api` prefix; that comes from `withRouting`.

## Keep Laravel's Response Envelope

`JsonResource` and `ResourceCollection` produce `{data}` for a single record and `{data, links, meta}` for a paginated collection. Use them.

A `success: true` flag duplicates the HTTP status, teaches clients to ignore the status line, and does not reach the responses the framework generates itself: 422 from validation, 429 from throttling, 404 from a missing binding. You would have to rewrite all of those to keep the shape consistent. Stripe and GitHub ship no such flag.

Incorrect:
```php
return response()->json([
    'success' => true,
    'data' => OrderResource::collection($orders),
    'meta' => ['total' => $orders->total()],
]);
```

Correct:
```php
return OrderResource::collection($orders);
```

Leave wrapping enabled. `JsonResource::withoutWrapping()` does not unwrap paginated collections, so calling it makes single and list responses structurally different.

Laravel 13 ships JSON:API resources (`make:resource OrderResource --json-api`) which handle sparse fieldsets, includes, and `Content-Type: application/vnd.api+json`. Choose them for a public or third-party-consumed API. Do not mix them with plain `JsonResource` inside one version.

## Return Errors as Problem Details

RFC 9457 `application/problem+json` gives every error one shape: `type`, `title`, `status`, `detail`, `instance`, plus any extension members you add.

Register it in `bootstrap/app.php`. `respond()` sees framework-generated responses too — 404, 405, 419, 429 — which a per-exception `render()` callback does not.

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson()
    );

    $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
        if (! $request->is('api/*')) {
            return $response;
        }

        $payload = [
            'type' => 'about:blank',
            'title' => Response::$statusTexts[$response->getStatusCode()] ?? 'Error',
            'status' => $response->getStatusCode(),
            'instance' => $request->path(),
            'trace_id' => Context::get('trace_id'),
        ];

        if ($e instanceof ValidationException) {
            $payload['detail'] = $e->getMessage();
            $payload['errors'] = $e->errors();
        }

        return response()->json($payload, $response->getStatusCode(), [
            'Content-Type' => 'application/problem+json',
        ] + $response->headers->all());
    });
})
```

Keep Laravel's field-keyed `errors` object as an extension member on 422 so existing clients keep working. Preserve headers the framework set, in particular `Retry-After` on 429.

An API already emitting Laravel's `{message, errors}` shape keeps it. Introduce Problem Details at the next version rather than changing responses under live clients.

## Status codes

| Situation | Code |
|---|---|
| Body is unparseable or structurally wrong | 400 |
| Body parsed, failed validation rules | 422 |
| Credentials missing, invalid, or expired | 401 |
| Authenticated, not permitted, existence not secret | 403 |
| Authenticated, not permitted, **existence is secret** | 404 |
| Conflicting state: duplicate, already published, version mismatch | 409 |
| `If-Match` precondition failed | 412 |
| Rate limited | 429 with `Retry-After` |
| Resource created | 201 with `Location` |
| Accepted for async processing | 202 |
| Success with no body | 204 |

Laravel's `ValidationException` returns 422. Use 422 for rule failures and document it; fighting the framework here costs more than it returns.

## Choose the Pagination Style by Table Size

`paginate()` gives page numbers and a total, at the cost of a SQL `OFFSET` that grows slower as the offset grows. `cursorPaginate()` compares against the ordered columns instead, stays fast at any depth, and offers only next and previous.

Use `paginate()` for admin screens and anything that shows a page count. Use `cursorPaginate()` for large tables, high write volume, and infinite scroll, with an index on the ordering columns.

## Order Deterministically Before Paginating

Without an explicit `ORDER BY`, row order is undefined, so records repeat or vanish across pages. Add a unique tie-breaker.

Incorrect:
```php
Order::query()->latest()->paginate();
```

Correct:
```php
Order::query()->orderByDesc('created_at')->orderByDesc('id')->paginate();
```

## Cap Page Size on the Server

An uncapped `per_page` is an availability hole, not a convenience.

Correct:
```php
$perPage = min($request->integer('per_page', 15), 100);
```

## Allow-List Every Filter, Sort, and Include

Query bindings protect values, not identifiers. A column name or sort direction taken from the request goes straight into SQL.

Incorrect:
```php
Order::query()->orderBy($request->input('sort'))->get();
```

Correct:
```php
QueryBuilder::for(Order::class)
    ->allowedFilters(['status', 'buyer_id'])
    ->allowedSorts(['created_at', 'total'])
    ->allowedIncludes(['lines', 'buyer'])
    ->paginate();
```

`spatie/laravel-query-builder` throws on anything not listed, which is the point. Every allowed include is both a query cost and a data-exposure decision: authorize it and cap include depth.

## Load Relationships Through whenLoaded

Passing the relationship itself triggers a query per record inside the resource.

Incorrect:
```php
'lines' => OrderLineResource::collection($this->lines),
```

Correct:
```php
'lines' => OrderLineResource::collection($this->whenLoaded('lines')),
```

## Rate limit

Define limiters in `AppServiceProvider::boot()`.

```php
RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
    ->by($request->user()?->id ?: $request->ip()));

RateLimiter::for('login', fn (Request $request) => [
    Limit::perMinute(500),
    Limit::perMinute(5)->by(Str::lower($request->string('email')).'|'.$request->ip()),
]);
```

An IP alone groups everyone behind one NAT together. An account identifier alone lets an attacker lock out a known account. Combine them for authentication routes.

When one subject has several windows, prefix the keys so the buckets stay separate:

```php
Limit::perMinute(10)->by('minute:'.$userId);
Limit::perDay(1000)->by('day:'.$userId);
```

Laravel 13 can count only selected responses, which blunts object-ID enumeration cheaply:

```php
Limit::perMinute(20)->by($request->ip())->after(fn (Response $r) => $r->status() === 404);
```

## Accept an Idempotency Key on Repeatable Writes

Payments, order creation, and outbound messaging need to survive a client retry.

Store the key with a fingerprint of the request body and the response, scoped to the authenticated user or tenant, with a TTL. On a replay with the same key and the same fingerprint, return the stored response and add `Idempotency-Replayed: true`. On the same key with a different body, return 409. Serialise concurrent duplicates with a lock.

An idempotency key at the HTTP edge does not make a queued job idempotent. Both are needed.

## Generate OpenAPI From the Code

`dedoc/scramble` reads the controllers and resources and needs no annotations, so the specification cannot drift from the code. It infers response shapes from `JsonResource::toArray()`, recognises paginators, and derives error responses from `validate()` (422), `authorize()` (403), implicit binding (404), and `abort()`.

Override individual cases with `@response`, `@status`, and `@body` docblock tags or the `#[Response]` attribute.

Treat the specification as build output: regenerate it in CI and fail when the committed file differs.

```yaml
- run: php artisan scramble:export --path=docs/openapi.json
- run: git diff --exit-code docs/openapi.json
```

Hand-written `#[OA\...]` attributes are the alternative when the specification is a contract governed outside the codebase. They are several times the size of the method they document and go stale silently, so choose them deliberately. An existing `l5-swagger` setup is still supported and works on Laravel 13; migrating it is an improvement, not a requirement, and needs the user's agreement.

## Skip HATEOAS

Full hypermedia inflates payloads, breaks paste-a-URL debugging, and almost no client uses the links. Keep the parts that come free: `links.self` on a JSON:API resource, and the pagination `links` block. Where a client genuinely needs to know which transitions are currently legal, put an explicit list in `meta`.
