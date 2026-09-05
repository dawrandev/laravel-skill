# Dependencies

Every package added to a project is code that runs with the application's privileges and, at install time, with the developer's. Treat a proposed dependency the way you would treat a pull request from a stranger.

## Contents

- [Ask before adding a dependency](#ask-before-adding-a-dependency)
- [Check Whether the Framework Already Does It](#check-whether-the-framework-already-does-it)
- [Confirm the Package Exists Before Naming It](#confirm-the-package-exists-before-naming-it)
- [Read the Package's Metrics](#read-the-packages-metrics)
- [Decision tiers](#decision-tiers)
- [Packages that are no longer the answer](#packages-that-are-no-longer-the-answer)
- [Packages that are alive despite their reputation](#packages-that-are-alive-despite-their-reputation)
- [Vendor trust is not transitive](#vendor-trust-is-not-transitive)
- [Install with the guard rails on](#install-with-the-guard-rails-on)
- [Never Allow All Composer Plugins](#never-allow-all-composer-plugins)
- [Write Bounded Version Constraints](#write-bounded-version-constraints)
- [Project configuration](#project-configuration)
- [npm](#npm)
- [Rules about your own output](#rules-about-your-own-output)

## Ask before adding a dependency

Laravel's own agent guidelines put it plainly: *"Do not change the application's dependencies without approval."*

Present the capability needed, why the framework cannot supply it, the exact package name, and the metrics from the checks below. Then wait for the answer.

## Check Whether the Framework Already Does It

Most requests for a package are requests for something Laravel ships. Check first.

```bash
composer show --direct
php artisan --version
```

Already built in: CORS handling, trusted proxies, rate limiting, queues, cache, mail, filesystem abstraction, validation, authorization, API tokens, schema inspection, UUIDs and ULIDs, string and array helpers, an HTTP client, events and broadcasting, scheduling, notifications, pagination, soft deletes, encryption, hashing, and column changes in migrations.

With Boost installed, `search-docs` answers this faster than a web search and is scoped to the installed version.

## Confirm the Package Exists Before Naming It

Language models invent package names. A 2025 study across sixteen models found roughly one in five generated package references pointed at a package that did not exist, and that the same invented names recur across runs often enough for attackers to register them in advance. A 2026 measurement across five current frontier models still found around five percent, with dozens of invented names left unregistered and claimable.

Composer is structurally exposed to this, because a plausible name sits under a real, respected vendor: `spatie/laravel-<anything>` looks right whether or not it exists.

One request settles it:

```bash
curl -sfI "https://repo.packagist.org/p2/spatie/laravel-permission.json" >/dev/null \
  && echo EXISTS || echo "DOES NOT EXIST"
```

A 404 means stop. Do not retry with a variation, and do not run `composer require` to find out whether a name is real. Search for the actual package instead:

```bash
curl -s "https://packagist.org/search.json?q=excel+export&per_page=5"
```

## Read the Package's Metrics

```bash
curl -s "https://packagist.org/packages/<vendor>/<name>.json"
```

The fields that matter: `abandoned`, `downloads.monthly`, `dependents`, `type`, `license`, `repository`, and the date and `require` block of the latest stable version.

Two traps:

- **Downloads do not prove a package is alive.** `fzaninotto/faker` stopped in 2020 and still records over 800,000 installs a month. `swiftmailer/swiftmailer` is abandoned and still records two million.
- **Stars are unreliable for split repositories.** `filament/filament` reports a double-digit star count because Packagist reads the read-only split repo, not the real one. Judge by `dependents` and release recency.

## Decision tiers

**Auto-approved.** Already in `composer.json`, or a first-party `laravel/*` package other than `laravel/homestead`. Still confirm it supports the installed Laravel major.

**Green — propose it with one line of justification.** All of:
- `abandoned` is `false`
- a stable release within the last twelve months
- the latest stable's `require` names the installed Laravel major, or the package is framework-agnostic
- at least 50,000 downloads a month and at least 50 dependents
- an OSI license; flag GPL, LGPL, or AGPL to the user before proceeding
- `type` is `library`, not `composer-plugin`

**Amber — present the numbers and ask.** Any of: last release twelve to twenty-four months ago; 5,000 to 50,000 downloads a month; 10 to 50 dependents; `type` is `composer-plugin`; it needs a new `allow-plugins` entry; it drags in more than five new transitive dependencies.

**Red — decline and offer an alternative.** Any of:
- 404 on Packagist
- `abandoned` is set
- no release in over twenty-four months and no support for the installed Laravel major
- under 5,000 downloads a month or under 10 dependents
- first release less than ninety days ago with low adoption
- the name is one character or one word away from a popular package (`spatie/laravel-permissions` against `spatie/laravel-permission`, `guzzle/guzzle` against `guzzlehttp/guzzle`)
- it would require `dev-master`, `dev-main`, `@dev`, `*`, or lowering `minimum-stability`

The thresholds are a starting point for a small team, not a standard. The user can overrule any of them; the abandoned and 404 checks are the two worth arguing about.

## Packages that are no longer the answer

These are what a model trained on older material reaches for. Each is either abandoned or superseded by something in the framework.

| Package | Status | Instead |
|---|---|---|
| `doctrine/dbal` | Alive, but Laravel has not needed it since 11 | Column changes are native; `Schema::getTables()`, `getColumns()`, `getIndexes()` |
| `fideloper/proxy` | Last release 2022, Laravel 9 maximum | `Illuminate\Http\Middleware\TrustProxies`, configured in `bootstrap/app.php` |
| `barryvdh/laravel-cors`, `fruitcake/laravel-cors` | Abandoned | CORS is in the framework: `HandleCors` and `config/cors.php` |
| `laravelcollective/html` | Abandoned, Laravel 10 maximum | `spatie/laravel-html`, or plain Blade |
| `fzaninotto/faker` | Abandoned 2020 | `fakerphp/faker` |
| `nunomaduro/larastan` | Abandoned | `larastan/larastan` |
| `beyondcode/laravel-websockets` | Abandoned, Laravel 10 maximum | `laravel/reverb` |
| `jenssegers/mongodb` | Abandoned | `mongodb/laravel-mongodb` |
| `arcanedev/log-viewer` | No Laravel 12 or 13 support | `opcodesio/log-viewer` |
| `zizaco/entrust` | Last release 2017 | `spatie/laravel-permission` |
| `swiftmailer/swiftmailer` | Abandoned | `symfony/mailer`, already used by the framework |
| `laravel/homestead` | Abandoned | Laravel Sail, or Herd |

The most common single mistake is adding `doctrine/dbal` to change a column. That requirement ended with Laravel 11.

## Packages that are alive despite their reputation

Do not decline these reflexively; verify, then use them if they fit.

`barryvdh/laravel-debugbar`, `maatwebsite/excel` (v4), `darkaonline/l5-swagger`, `nwidart/laravel-modules`, `spatie/laravel-fractal`, `laracasts/flash`, `laravel/ui`.

`tymon/jwt-auth` is maintained again but carries a large open-issue backlog. For a new API, `laravel/sanctum` is the first answer and `laravel/passport` the second.

## Vendor trust is not transitive

`laravel/*`, `spatie/*`, `symfony/*`, `league/*`, `pestphp/*`, `livewire/*`, `filament/*`, `larastan/*`, `guzzlehttp/*`, and `nesbot/carbon` are dependable namespaces. They still contain abandoned packages: `laravel/homestead`, `nunomaduro/larastan`, `barryvdh/laravel-cors`. Check the package, not the vendor.

## Install with the guard rails on

```bash
composer require <vendor>/<name> --dry-run          # see the resolution first, add --dev for tooling
composer require <vendor>/<name> --prefer-stable
composer validate --strict
composer audit
composer why <vendor>/<name>
git diff composer.json composer.lock                # review the whole lock delta
```

Reject the change if the dry run downgrades `laravel/framework` or pulls in an unexpected number of transitive packages.

Development-only tools go in `require-dev`: Pest, Pint, Larastan, Debugbar, Boost, Rector, Telescope when it is local-only.

## Never Allow All Composer Plugins

Composer plugins execute during install with the privileges of whoever runs Composer. The default is to allow none, and that default is correct.

Incorrect:
```json
{ "config": { "allow-plugins": true } }
```

Correct:
```json
{
  "config": {
    "allow-plugins": {
      "pestphp/pest-plugin": true,
      "php-http/discovery": false
    }
  }
}
```

In April 2026 an attacker force-pushed a malicious commit onto an existing release tag of a widely installed PHP package and changed its `type` to `composer-plugin`. Anyone who ran `composer update` during the window executed attacker code at install time, which harvested cloud credentials, `.env` files, and SSH keys. A package that is an ordinary library today can become a plugin tomorrow; an explicit allow-list is what stops that.

Never run Composer as root, and never remove `composer.lock` or run an unconstrained `composer update` to work around an install problem. Update one package at a time:

```bash
composer update vendor/pkg --with-dependencies
```

## Write Bounded Version Constraints

Use `^`, the constraint Composer's own documentation recommends. Never `*`, an unbounded `>=`, or a `dev-` branch.

Let Composer compute the constraint by running `composer require`, rather than hand-writing a version into `composer.json`.

Keep `"minimum-stability": "stable"` and `"prefer-stable": true`. Commit `composer.lock` and `package-lock.json`.

## Project configuration

Composer 2.10 blocks known-malicious and vulnerable versions during resolution, not merely at audit time. Copy `stubs/composer-policy.json` into the project's `composer.json`:

```json
{
  "config": {
    "policy": {
      "malware":    { "block": true,  "audit": "fail", "block-scope": "all" },
      "advisories": { "block": true,  "audit": "fail" },
      "abandoned":  { "block": false, "audit": "fail" }
    }
  }
}
```

Check the installed Composer with `composer --version`; this key needs 2.10 or later. On an older Composer, `composer audit` still works, and `roave/security-advisories` fails resolution on a vulnerable version.

Run `composer audit --locked` as a blocking CI step, and enable Dependabot or Renovate.

## npm

- `npm ci` in CI, never `npm install`.
- `ignore-scripts=true` in `.npmrc`, with an explicit `npm rebuild` where a package genuinely needs it.
- `npm audit --audit-level=high` as a blocking step.
- Set `minimum-release-age` in `.npmrc`; npm supports it but leaves it off.
- No `"latest"` and no `"*"` in `package.json`.

A valid provenance attestation proves who built a package, not that it is safe. A 2026 campaign published malicious versions carrying valid attestations by compromising the build pipeline. Treat provenance as one signal, not a verdict.

## Rules about your own output

1. Never invent a package name. If you do not know one, search Packagist and quote a real result, or say you do not know of one.
2. Never state a version number from memory. Read it from Packagist or `composer.json`.
3. Never recommend a package based on a pre-2025 article without re-checking `abandoned` and the last release date today. That one habit catches most of the table above.
4. Never run `composer require` to test whether a package exists.
5. Never set `"allow-plugins": true`.
6. Never remove a lock file from version control.
