# Laravel version rules

What changes between Laravel majors, and the outdated patterns a model trained on older Laravel will reach for.

## Contents

- [Detect the version before writing code](#detect-the-version-before-writing-code)
- [Supported versions](#supported-versions)
- [Detect the application structure before applying structure rules](#detect-the-application-structure-before-applying-structure-rules)
- [Configure models with attributes on Laravel 13](#configure-models-with-attributes-on-laravel-13)
- [Patterns removed in Laravel 11 and later](#patterns-removed-in-laravel-11-and-later)
- [Laravel 13 breaking changes](#laravel-13-breaking-changes)
- [Framework footguns](#framework-footguns)

## Detect the version before writing code

Read the installed versions, do not assume them.

```bash
composer show --direct
composer show laravel/framework | head -3
php -v
```

With Boost installed, `search-docs` is scoped to the installed version. Use it before relying on any framework API you have not confirmed.

Every rule below that names a version applies only when that version is installed.

## Supported versions

As of September 2026:

| Version | PHP | Released | Bug fixes until | Security fixes until |
|---|---|---|---|---|
| 11 | 8.2 – 8.4 | 2024-03-12 | ended | 2026-03-12 (end of life) |
| 12 | 8.2 – 8.5 | 2025-02-24 | 2026-08-13 (ended) | 2027-02-24 |
| 13 | 8.3 – 8.5 | 2026-03-17 | 2027 Q3 | 2028-03-17 |

Start new projects on Laravel 13. An application on Laravel 11 receives no further fixes and should be scheduled for an upgrade; say so once when you notice it, then continue with the task.

Laravel 13 is a small upgrade from 12. Boost ships an `/upgrade-laravel-v13` slash command that requires `laravel/boost ^2.0`.

## Detect the application structure before applying structure rules

```bash
test -f app/Http/Kernel.php && echo "Laravel 10 structure" || echo "streamlined structure"
```

When `app/Http/Kernel.php` exists, the application was upgraded from Laravel 10 and kept the older layout. Laravel supports that layout, so follow it: register middleware in `app/Http/Kernel.php`, handle exceptions in `app/Exceptions/Handler.php`, schedule in `app/Console/Kernel.php`. Convert to the streamlined structure only when the user asks for it.

The rest of this file describes the streamlined structure introduced in Laravel 11.

## Configure models with attributes on Laravel 13

Laravel 13 documents model configuration through PHP attributes. The equivalent properties still work, so match the surrounding code first; use attributes in a new Laravel 13 application with no established convention.

```php
use Illuminate\Database\Eloquent\Attributes\{Fillable, ObservedBy, Scope, Table, UsePolicy};

#[Fillable(['buyer_id', 'status', 'placed_at'])]
#[ObservedBy([OrderObserver::class])]
#[UsePolicy(OrderPolicy::class)]
final class Order extends Model
{
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'placed_at' => 'immutable_datetime',
        ];
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereIn('status', [OrderStatus::Draft, OrderStatus::Placed]);
    }
}
```

| Concern | Laravel 13 | Older equivalent |
|---|---|---|
| Mass assignment | `#[Fillable([...])]`, `#[Guarded]` | `$fillable`, `$guarded` |
| Table and keys | `#[Table(...)]`, `#[Connection(...)]`, `#[WithoutTimestamps]` | `$table`, `$connection`, `$timestamps` |
| Local scope | `#[Scope]` on `protected function open()` | `public function scopeOpen()` |
| Observer | `#[ObservedBy([...])]` | `static::observe()` in `booted()` |
| Global scope | `#[ScopedBy([...])]` | `static::addGlobalScope()` in `booted()` |
| Policy | `#[UsePolicy(...)]` | `$policies` in `AuthServiceProvider` |
| Collection | `#[CollectedBy(...)]` | `newCollection()` |

Controllers accept `#[Middleware('auth')]` and `#[Authorize('update', 'post')]`. Form requests accept `#[StopOnFirstFailure]`, `#[FailOnUnknownFields]`, `#[RedirectToRoute(...)]`. Jobs accept `#[Tries(5)]`, `#[Timeout(120)]`, `#[Backoff([1, 5, 10])]`.

Models are guarded against mass assignment by default. A model needs `#[Fillable]` or `#[Guarded]` before `create()` or `fill()` will set anything.

## Patterns removed in Laravel 11 and later

These files do not exist in the streamlined structure. Reaching for them is the most common way a model produces code that cannot run.

| Gone | Use instead |
|---|---|
| `app/Http/Kernel.php` | `bootstrap/app.php` -> `->withMiddleware(...)` |
| `app/Console/Kernel.php` | `routes/console.php` and `->withCommands(...)` |
| `app/Exceptions/Handler.php` | `bootstrap/app.php` -> `->withExceptions(...)` |
| `RouteServiceProvider` | `->withRouting(web:, api:, commands:, health:)` |
| `EventServiceProvider` | listener discovery in `app/Listeners` |
| `AuthServiceProvider::$policies` | policy discovery, `#[UsePolicy]`, or `Gate::policy()` |
| `config/app.php` providers key | `bootstrap/providers.php` |

Code-level replacements:

```php
// Rate limiters live in AppServiceProvider::boot(), not RouteServiceProvider
RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
    ->by($request->user()?->id ?: $request->ip()));

// Scheduling lives in routes/console.php
use Illuminate\Support\Facades\Schedule;
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Controller middleware: the constructor call was removed in Laravel 11
final class OrdersController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['auth', new Middleware('verified', only: ['store'])];
    }
}

// The base controller no longer has AuthorizesRequests
Gate::authorize('update', $order);   // not $this->authorize(...)

// Jobs use one trait
use Illuminate\Foundation\Queue\Queueable;
```

`app/` ships with only `Http/`, `Models/`, and `Providers/`. `Jobs/`, `Events/`, `Listeners/`, `Policies/`, `Rules/`, and the rest appear when the matching `make:` command creates them. Most `config/*.php` files are not published; run `php artisan config:publish {name}` when you need to edit one. `routes/api.php` arrives with `php artisan install:api`.

Per-second rate limiting exists (`Limit::perSecond(...)`), added in Laravel 11.

## Laravel 13 breaking changes

- `VerifyCsrfToken` became `PreventRequestForgery`, which verifies request origin using `Sec-Fetch-Site`. Configure with `->preventRequestForgery(...)`; disable in a test with `->withoutMiddleware([PreventRequestForgery::class])`.
- `cache.serializable_classes` now defaults to `false`. Allow-list any class stored in the cache as an object.
- `upsert()` on MySQL and MariaDB throws when `uniqueBy` is empty.
- Session serialization changed from `php` to `json`. Changing this setting invalidates every existing session.
- Domain routes now take precedence over non-domain routes.
- `array_first()` and `array_last()` are defined by the PHP 8.5 polyfill and collide with `laravel/helpers`. Use `Arr::first()` and `Arr::last()`.

## Framework footguns

Each of these is valid PHP that behaves differently from how it reads.

- `$guarded = []` disables mass-assignment protection entirely. Declare fillable attributes instead.
- `env()` returns `null` once `config:cache` has run. Read env in `config/*.php`, then `config()` everywhere else.
- `JsonResource::withoutWrapping()` does not unwrap paginated collections, so single and list responses end up structurally different. Leave wrapping on.
- `tokenCan()` returns `true` for every first-party SPA session request. Pair every ability check with an ownership check.
- Sanctum tokens never expire by default. Set `'expiration'` in `config/sanctum.php` and schedule `sanctum:prune-expired`.
- Query bindings protect values, not identifiers. `orderBy($request->sort)` is injectable; map user input through an allow-list.
- `DB::table()` bypasses global scopes, including tenant scopes.
- Nested `Route::apiResource()` without `->scopeBindings()` resolves a child that belongs to a different parent.
- A 419 response means the CSRF token is missing or stale. Fix the token; leave the middleware in place.
- `strlen()` and `strtolower()` operate on bytes. Use `mb_*` or `Str::` helpers for user text.
- `Model::preventLazyLoading()` belongs behind `! app()->isProduction()`. Lazy loading is a performance defect, not a reason to fail a production request.
