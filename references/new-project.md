# New project

Day-zero setup for a Laravel application that starts from an empty directory. Work through it in order; each step is cheap now and expensive to retrofit.

## Contents

- [Before the first command](#before-the-first-command)
- [1. Create the application](#1-create-the-application)
- [2. Install Boost](#2-install-boost)
- [3. Harden the model layer](#3-harden-the-model-layer)
- [4. Formatting and static analysis](#4-formatting-and-static-analysis)
- [5. Testing](#5-testing)
- [6. Dependency policy](#6-dependency-policy)
- [7. API scaffolding](#7-api-scaffolding)
- [8. Observability](#8-observability)
- [9. Continuous integration](#9-continuous-integration)
- [10. Project documentation](#10-project-documentation)
- [Verification](#verification)

## Before the first command

Ask the questions whose answers are expensive to reverse. Four are usually enough:

1. **Delivery surface.** JSON API, server-rendered Blade, or both? This decides routing, auth, and response shape.
2. **Identity and ownership.** Who owns a record — a user, a team, an organisation? Is there more than one auth guard? Tenancy and the guard structure shape every table.
3. **Roles and permissions.** A single admin, a fixed role list, or per-record permissions? This decides whether policies exist from day one.
4. **Languages.** Which locales, and is the admin panel translated too? Retrofitting translation means finding every string again.

Anything else can start on a sensible default and be changed later.

## 1. Create the application

```bash
composer create-project laravel/laravel <name>
cd <name>
php artisan --version
php -v
```

Laravel 13 requires PHP 8.3 or later. Pest 5 requires PHP 8.4; on 8.3 the project stays on Pest 4, which is what the skeleton pins anyway.

Set `APP_TIMEZONE=UTC` and add a separate display timezone to `config/app.php`:

```php
'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Tashkent'),
```

## 2. Install Boost

```bash
composer require laravel/boost --dev
php artisan boost:install
```

This writes Laravel's own guidelines into `CLAUDE.md` or `AGENTS.md` and registers an MCP server providing documentation search, schema inspection, log reading, and error lookup. Commit `.ai/rules/`; the generated guidelines file is regenerated and does not need committing.

## 3. Harden the model layer

In `AppServiceProvider::boot()`:

```php
Model::preventLazyLoading(! $this->app->isProduction());
Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
```

Lazy loading then throws in development and CI, where it is a bug worth fixing, and stays permissive in production, where it is a performance issue rather than a reason to fail a request. The second call turns a dropped non-fillable attribute into an exception instead of silence.

Define rate limiters in the same method:

```php
RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
    ->by($request->user()?->id ?: $request->ip()));
```

## 4. Formatting and static analysis

```bash
composer require --dev larastan/larastan
```

Copy `stubs/pint.json` and `stubs/phpstan.neon` into the project root. Start PHPStan at level 5, generate a baseline if the project is not empty, and raise the level as the code settles.

```bash
./vendor/bin/pint
./vendor/bin/phpstan analyse
```

Always pass Pint an explicit path when running it by hand. Without one, a shell whose working directory has drifted formats every sibling project on the disk.

## 5. Testing

```bash
composer require --dev pestphp/pest --with-all-dependencies
php artisan pest:install
```

Copy `stubs/ArchTest.php` to `tests/Feature/ArchTest.php` and delete the rules the project does not want. Configure `tests/Pest.php`:

```php
pest()->use(LazilyRefreshDatabase::class)->in('Feature');

pest()->beforeEach(function () {
    Http::preventStrayRequests();
});
```

In `phpunit.xml`, set `BCRYPT_ROUNDS=4` and disable Telescope and Pulse for the test environment.

```bash
php artisan test --compact
```

The architecture tests should pass on an empty project. If they do not, the rule is wrong for this project — fix the rule now rather than living with a red suite.

## 6. Dependency policy

Merge `stubs/composer-policy.json` into `composer.json`, and copy `stubs/.npmrc` to the project root.

```bash
composer --version      # policy config needs 2.10 or later
composer validate --strict
composer audit
```

## 7. API scaffolding

Only for a project with a JSON API.

```bash
php artisan install:api          # creates routes/api.php and installs Sanctum
```

- Route the API under `/api/v1` in `bootstrap/app.php`.
- Register the Problem Details responder in `withExceptions()`; see `references/api-design.md`.
- Add `dedoc/scramble` for OpenAPI generation, and wire the regenerate-and-diff step into CI.
- For a first-party SPA, enable `$middleware->statefulApi()` and use cookie authentication rather than tokens.

## 8. Observability

Add one middleware that establishes a request identifier and puts it in the context:

```php
Context::add('trace_id', $request->header('X-Request-Id') ?? (string) Str::uuid7());
```

Visible context is merged into log context and is carried into queued jobs and restored there, so one identifier links a request to the asynchronous work it started. Echo it as a response header and include it in every Problem Details body. Use `Context::addHidden()` for anything that must propagate without appearing in logs.

Use structured JSON logging in production. Telescope is a local tool; if it is installed, keep it out of production explicitly.

## 9. Continuous integration

Copy `stubs/ci.yml` to `.github/workflows/ci.yml`. The order runs cheap checks first so a failure is reported in seconds rather than minutes:

```
composer validate --strict
pint --test
phpstan
composer audit --locked
pest --parallel
openapi regenerate + git diff --exit-code
npm ci && npm run build
```

## 10. Project documentation

Propose these to the user rather than creating them unprompted:

- **`CLAUDE.md`** — the stack, the domain rules that are easy to get wrong, the commands, and any deviation from this skill. Short. It is read every session, so every line competes for attention.
- **`docs/decisions.md`** — one entry per decision: what was decided, why, what was rejected, and the date. When the code and this file disagree, this file wins and the code is the bug.

```markdown
## D-001: Orders are cancelled, never deleted
**Decision:** `orders.status` gains a `cancelled` value; no hard deletes.
**Why:** Financial reporting reads historical orders.
**Rejected:** Soft deletes alone — a cancelled order still needs a reason.
**Date:** 2026-09-06
```

## Verification

The project is ready when all of these pass on a clean checkout:

```bash
composer validate --strict
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
composer audit
php artisan test --compact
php artisan route:list
```
