# Security

The OWASP API Security Top 10 (2023) mapped to the Laravel mechanism that addresses it.

## Contents

- [Authorize Every Model-Bound Route](#authorize-every-model-bound-route)
- [Scope Tenant Data at the Query, Not the Response](#scope-tenant-data-at-the-query-not-the-response)
- [Declare Mass-Assignable Attributes](#declare-mass-assignable-attributes)
- [Read Input Through validated or safe](#read-input-through-validated-or-safe)
- [Enforce Invariants With Database Constraints](#enforce-invariants-with-database-constraints)
- [Give Tokens Abilities and an Expiry](#give-tokens-abilities-and-an-expiry)
- [Use Cookie Authentication for a First-Party SPA](#use-cookie-authentication-for-a-first-party-spa)
- [Bind Values and Allow-List Identifiers](#bind-values-and-allow-list-identifiers)
- [Validate Uploads by Content](#validate-uploads-by-content)
- [Validate Outbound URLs Against the Resolved Address](#validate-outbound-urls-against-the-resolved-address)
- [Bound Every Response](#bound-every-response)
- [Keep Configuration and Secrets Out of Code](#keep-configuration-and-secrets-out-of-code)
- [Hide and Encrypt Sensitive Attributes](#hide-and-encrypt-sensitive-attributes)
- [Keep an Inventory](#keep-an-inventory)
- [Treat Third-Party Responses as Untrusted Input](#treat-third-party-responses-as-untrusted-input)
- [Security checklist](#security-checklist)

## Authorize Every Model-Bound Route

Broken object level authorization is the most exploited API flaw. `auth:sanctum` proves who is calling; it says nothing about whether they may touch this record.

Incorrect:
```php
Route::middleware('auth:sanctum')->put('/orders/{order}', function (Order $order, Request $request) {
    $order->update($request->validated());
});
```

Correct:
```php
public function update(UpdateOrderRequest $request, Order $order): OrderResource
{
    Gate::authorize('update', $order);

    $order->update($request->validated());

    return OrderResource::make($order);
}
```

Or in the form request:
```php
public function authorize(): bool
{
    return $this->user()?->can('update', $this->route('order')) ?? false;
}
```

`->scopeBindings()` on nested routes constrains which record resolves. It does not replace authorization.

Test the whole matrix against the policy, and keep one HTTP test proving the endpoint applies it.

## Scope Tenant Data at the Query, Not the Response

Filtering after fetching leaks through counts, pagination totals, and any code path that forgets the filter. Constrain the query.

Correct:
```php
Order::query()->whereBelongsTo($request->user()->team)->paginate();
```

A global scope centralises this, but `DB::table()` bypasses global scopes entirely. When tenancy matters, keep raw query-builder calls out of request paths.

Respond 404, not 403, when one tenant must not learn another tenant's record exists.

## Declare Mass-Assignable Attributes

Models are guarded by default. `$guarded = []` turns that protection off for every attribute, including `is_admin`, `team_id`, and `status`.

Incorrect:
```php
protected $guarded = [];
```

Correct:
```php
#[Fillable(['title', 'quantity', 'notes'])]   // Laravel 13
protected $fillable = ['title', 'quantity', 'notes'];   // Laravel 12 and earlier
```

Add `Model::preventSilentlyDiscardingAttributes(! app()->isProduction())` in `AppServiceProvider::boot()` so an attempt to set a non-fillable attribute throws in development instead of being dropped without a word.

## Read Input Through validated or safe

`$request->all()` passes through whatever the client sent, including keys you never validated.

Incorrect:
```php
Order::create($request->all());
```

Correct:
```php
Order::create($request->safe()->only(['title', 'quantity']));
```

Validated data is not automatically safe to mass-assign. Both layers are needed, and neither is authorization. Never add an attribute to the validation rules purely to make mass assignment convenient.

## Enforce Invariants With Database Constraints

A `unique` validation rule reads the table, then the insert happens later. Two concurrent requests both pass. Only a constraint, an atomic update, or a transaction actually prevents the duplicate.

Correct:
```php
$table->unique(['team_id', 'slug']);
```

The same holds for stock levels and balances: decrement atomically or lock the row inside a transaction, rather than checking and then writing.

## Give Tokens Abilities and an Expiry

Sanctum tokens are hashed at rest and shown once. By default they never expire.

```php
// config/sanctum.php
'expiration' => 525600,
```

```php
// routes/console.php
Schedule::command('sanctum:prune-expired --hours=24')->daily();
```

Issue narrow abilities and check them:
```php
$token = $user->createToken('mobile', ['orders:read', 'orders:write']);
```

`tokenCan()` returns `true` for every request authenticated through the first-party SPA session guard. An ability check alone is therefore never sufficient:

Incorrect:
```php
if ($request->user()->tokenCan('orders:write')) { ... }
```

Correct:
```php
if ($request->user()->tokenCan('orders:write') && $order->buyer_id === $request->user()->id) { ... }
```

Rate-limit login, password reset, and verification routes with a key combining the account identifier and the IP.

## Use Cookie Authentication for a First-Party SPA

Sanctum's SPA mode uses the session guard and CSRF protection, and keeps credentials out of JavaScript-readable storage. API tokens are for third-party clients and mobile apps.

Set `$middleware->statefulApi()` in `bootstrap/app.php`, request `GET /sanctum/csrf-cookie` before the first write, set `supports_credentials => true` in `config/cors.php`, and share a top-level session domain.

Choose Passport only when the application must actually speak OAuth2.

## Bind Values and Allow-List Identifiers

Bindings protect values. Column names, table names, and sort directions are identifiers and are not bindable.

Incorrect:
```php
DB::select("SELECT * FROM orders WHERE status = '{$request->status}'");
Order::query()->orderBy($request->input('sort'), $request->input('dir'))->get();
```

Correct:
```php
Order::query()->where('status', $request->string('status'))->get();

$sort = in_array($request->input('sort'), ['created_at', 'total'], true)
    ? $request->input('sort')
    : 'created_at';
$dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';
```

In Blade, `{{ }}` escapes. Use `{!! !!}` only for content sanitised for that exact context; escaping rules differ between HTML, attributes, URLs, JavaScript, and CSS.

Keep `@csrf` on state-changing web forms. A 419 means the token is missing or stale; fix the token rather than removing the middleware. A webhook route excluded from CSRF needs its own authenticity check, normally an HMAC signature.

## Validate Uploads by Content

`mimes:` and `mimetypes:` both read the file's contents; they differ only in whether you list extensions or MIME strings. `extensions:` checks the client-supplied filename and is not a content check, so it is never used alone.

Correct:
```php
'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
```

- Let Laravel name the stored file: `$request->file('avatar')->store('avatars')`. A client-supplied filename is a path-traversal vector.
- Store private files outside the public root and serve them through a controller or a short-lived signed URL, after the policy check.
- SVG passes the `image` rule and can carry script. Exclude it unless the application needs it and sanitises it.
- Re-encode user images to strip embedded payloads and EXIF.
- Cap dimensions so a small file cannot decompress into gigabytes.

When a file is assembled server-side from chunks, validate the assembled file, not the parts.

## Validate Outbound URLs Against the Resolved Address

Anywhere a user-supplied URL becomes an outbound request — avatar import, webhook target, link preview — the server can be pointed at internal services.

Centralise the check in one validation rule and apply it everywhere:

- Allow-list destination hosts when the use case permits it; that is the strongest control.
- Allow only `http` and `https`.
- Resolve DNS, then test the **resolved address** against `127.0.0.0/8`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `169.254.0.0/16` (cloud metadata), and `::1/128`.
- Disable redirect following, or re-run the check on every hop. A 302 otherwise bypasses the whole check.
- Connect to the address you validated, so DNS cannot change between check and request.

## Bound Every Response

- Rate limit every API route group, with tighter per-endpoint limits on expensive work.
- Cap `per_page` server-side.
- Cap the depth and count of allowed includes.
- Limit upload size in validation and in PHP configuration.
- Iterate large tables with `chunkById()`, `cursor()`, or `lazyById()` rather than `get()`.
- Give jobs a `$timeout` and `$tries`, and a `RateLimited` middleware when they share a third-party quota.

## Keep Configuration and Secrets Out of Code

- `APP_DEBUG=false` in production, always. Debug mode exposes environment values, database credentials, the application key, and file paths to anyone who can trigger an error.
- Call `env()` only inside `config/*.php`. After `config:cache`, `env()` returns `null` everywhere else.
- Check the environment with `app()->isProduction()`, never `env('APP_ENV')`.
- Never commit a populated `.env`. Use `php artisan env:encrypt` or a platform secret store.
- `allowed_origins` is never `['*']` on an authenticated API, and a wildcard origin is incompatible with `supports_credentials`.
- Send `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, and HSTS over HTTPS from one middleware. Content-Security-Policy needs its own audit in an Alpine application because of inline attributes; add it deliberately rather than half-configured.
- Keep Telescope, Debugbar, and Ignition out of production, and gate `/horizon` and `/telescope`.

## Hide and Encrypt Sensitive Attributes

```php
protected $hidden = ['api_secret', 'remember_token'];

protected function casts(): array
{
    return ['api_secret' => 'encrypted'];
}
```

Hidden attributes are still readable in PHP, and encryption is not access control. An encrypted column cannot be searched and needs `TEXT` or larger.

Do not put secrets in `Context::add()`; visible context is merged into log context. `Context::addHidden()` propagates to queued jobs without reaching the logs.

Redact secrets and personal data in the log formatter rather than at each call site.

## Keep an Inventory

Version the API, regenerate the OpenAPI specification in CI, and retire old versions with `Sunset` headers. Undocumented endpoints and forgotten staging hosts are the exposure this covers. Run `composer audit` in CI as a blocking step.

## Treat Third-Party Responses as Untrusted Input

The HTTP client returns 4xx and 5xx responses instead of throwing, and its default response timeout is 30 seconds.

Correct:
```php
$response = Http::connectTimeout(3)->timeout(5)
    ->retry(2, 200)
    ->withHeader('Idempotency-Key', $key)
    ->post($url, $payload)
    ->throw();
```

Retry only idempotent operations, or send a stable idempotency key on every attempt. Validate the response body before mapping it onto a model.

## Security checklist

Run through this before calling an endpoint finished:

- [ ] Authorization on the record, not just authentication
- [ ] Tenant constraint in the query; 404 rather than 403 where existence is private
- [ ] Fillable attributes declared; input read through `validated()` or `safe()`
- [ ] Uniqueness and balance invariants backed by a constraint or an atomic update
- [ ] Sort, filter, and include parameters allow-listed
- [ ] Uploads validated by content, stored outside the public root, served through a policy check
- [ ] Outbound user-supplied URLs validated against the resolved address
- [ ] Rate limit applied; `per_page` capped
- [ ] Response built from a resource, with no unintended fields
- [ ] Tests covering unauthenticated, cross-tenant, and insufficient-role access
