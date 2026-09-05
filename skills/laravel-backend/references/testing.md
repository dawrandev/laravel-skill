# Testing

Pest is the test runner. Every change ships with a test.

## Contents

- [Detect the Pest version](#detect-the-pest-version)
- [Write a Feature Test First](#write-a-feature-test-first)
- [Cover Six Cases for Every Endpoint](#cover-six-cases-for-every-endpoint)
- [Return 404 When Existence Is Itself Private](#return-404-when-existence-is-itself-private)
- [Name the Test After the Observable Outcome](#name-the-test-after-the-observable-outcome)
- [Choose Isolation by Ladder](#choose-isolation-by-ladder)
- [Create Records Before Faking Events](#create-records-before-faking-events)
- [Name the Classes You Fake](#name-the-classes-you-fake)
- [Assert With Framework Assertions](#assert-with-framework-assertions)
- [Build Test Data From Named Factory States](#build-test-data-from-named-factory-states)
- [Choose the Database Trait](#choose-the-database-trait)
- [Control Time and Randomness Explicitly](#control-time-and-randomness-explicitly)
- [Guard Query Counts](#guard-query-counts)
- [Enforce Architecture With arch() Tests](#enforce-architecture-with-arch-tests)
- [Keep the Suite Fast](#keep-the-suite-fast)
- [Running tests](#running-tests)

## Detect the Pest version

```bash
composer show pestphp/pest | head -3
```

Pest 5 requires PHP 8.4 and PHPUnit 13. Pest 4 is what a fresh Laravel 13 skeleton pins. Write the syntax the installed version supports; the rules below hold for both unless marked.

Create tests with `php artisan make:test --pest OrderStoreTest`. Do not put the suite directory in the name.

## Write a Feature Test First

A feature test exercises the real route, middleware, validation, policy, and database. That is where behaviour lives, so that is the default.

Write a unit test only for logic that does not use the framework: calculations, value objects, enums, data objects, state machines, custom validation rules.

Testing that your cast, scope, relationship, or rule is configured correctly is testing this project. Testing that Eloquent casts work at all is testing the framework; leave that alone.

## Cover Six Cases for Every Endpoint

For each endpoint, assert:

1. Unauthenticated request is refused.
2. Request from a different tenant, team, or owner is refused.
3. Authenticated user without the required role or permission is refused.
4. A route or scope constraint that is not satisfied is refused.
5. Validation failure returns the expected status and message.
6. A valid request succeeds, asserting **both** the response and the stored state.

Assert the behaviour this application actually has. An API returns 401 for a missing token; a browser route redirects to the sign-in page. `assertStatus(401)` on a route that redirects is a test that never told you anything.

For authorization, assert the full permission matrix against the policy directly, and keep one HTTP test proving the endpoint applies it. An HTTP 403 cannot tell you whether middleware, a policy, or an `abort()` produced it.

## Return 404 When Existence Is Itself Private

When one tenant must not learn that another tenant's record exists, respond 404. A 403 confirms the record is there.

Correct:
```php
it('returns 404 when the order belongs to another team', function () {
    $order = Order::factory()->for(Team::factory())->create();

    $this->actingAs(User::factory()->create())
        ->getJson("/api/v1/orders/{$order->id}")
        ->assertNotFound();
});
```

## Name the Test After the Observable Outcome

The name states what happens and when. Use result verbs: returns, renders, creates, dispatches, rejects, forbids, falls back. Include the status code in an API error test.

Incorrect:
```php
it('works correctly', function () { ... });
test('test_store', function () { ... });
it('given a draft order when publishing then it publishes', function () { ... });
```

Correct:
```php
it('publishes a draft order and records the transition', function () { ... });
it('returns 422 when the quantity exceeds available stock', function () { ... });
```

Mirror the path of the class under test: `app/Actions/Order/CreateOrder.php` becomes `tests/Unit/Actions/Order/CreateOrderTest.php`.

Use `it()` for behaviour and `test()` for declarative facts, one style per file. Reach for `describe()` only when a file genuinely spans distinct lifecycle actions, not as a substitute for a dataset.

## Choose Isolation by Ladder

Take the first option that applies:

1. A framework fake for facades: events, queues, mail, notifications, storage, HTTP client, time, sleep.
2. A fake implementation the application already provides.
3. A mock of a container-resolved contract, only when the real implementation leaves the process or is nondeterministic.
4. The real implementation for everything else, including the database.

Never mock the query builder. A test built on a query-builder mock asserts the mock.

Create fakes inside the test that needs them, not in `beforeEach()`. Setup holds configuration; the test holds its own subject.

Use `Exceptions::fake()` to assert that an exception was reported. `withoutExceptionHandling()` changes the response you are testing.

## Create Records Before Faking Events

Factories rely on model events, so a fake installed first can suppress a `creating` hook that generates a UUID and leave you with an invalid model.

Incorrect:
```php
Event::fake();
$order = Order::factory()->create();
```

Correct:
```php
$order = Order::factory()->create();

Event::fake([OrderPlaced::class]);
```

## Name the Classes You Fake

A bare fake swallows every dispatch, including one you did not intend.

Incorrect:
```php
Queue::fake();
Http::fake();
```

Correct:
```php
Queue::fake([SendInvoiceReminder::class]);
Http::preventStrayRequests();
Http::fake(['api.stripe.com/*' => Http::response(['id' => 'ch_1'], 200)]);
```

Put `Http::preventStrayRequests()` in `Pest.php` or the base `TestCase` so an unfaked outbound call fails loudly instead of hitting the network.

## Assert With Framework Assertions

A framework assertion names the wrong table, value, or status. A hand-built check reports `false is not true`.

Incorrect:
```php
expect($response->status())->toBe(404);
$this->assertTrue(Order::where('id', $order->id)->exists());
$this->assertDatabaseHas('orders', ['id' => $order->id]);
```

Correct:
```php
$response->assertNotFound();
$this->assertModelExists($order);
```

Assert the complete result of a write: the response, the database, and any job, event, notification, or mail it should produce. On a failure path, assert that none of those happened.

Never compute the expected value with the implementation's own logic. A test that recalculates the total the same way the code does passes when both are wrong.

Assert one fact once. A `assertOk()` before an `assertSee()` adds nothing.

## Build Test Data From Named Factory States

A named state carries meaning; a raw attribute carries only a value.

Incorrect:
```php
User::factory()->create(['email_verified_at' => null]);
```

Correct:
```php
User::factory()->unverified()->create();
```

Use `for()` to declare ownership, `recycle()` to share one parent across records, and `sequence()` to vary attributes. Every record you create must either arrange the behaviour or support an assertion.

Use a dataset only when the setup, body, and assertions are identical across cases, and name each case so a failure identifies it. One test function with a branch in its body is two tests.

## Choose the Database Trait

| Trait | When |
|---|---|
| `LazilyRefreshDatabase` | Default. Migrations run only for tests that touch the database. |
| `RefreshDatabase` | Where the project already uses it; matching the existing suite matters more. |
| `DatabaseTruncation` | Only when the code under test commits: browser tests, multiple connections, MySQL `FULLTEXT` assertions. Significantly slower, so scope it to one file. |

`RefreshDatabase` wraps each test in a transaction it rolls back. Code that commits, or an index that does not see uncommitted rows, needs truncation instead.

Pest 5 syntax:
```php
pest()->use(LazilyRefreshDatabase::class)->in('Feature');
pest()->use(DatabaseTruncation::class)->in('Feature/CatalogSearchTest.php');
```

## Control Time and Randomness Explicitly

Freeze or travel in every test whose behaviour depends on a date, a period, or a timestamp.

Incorrect:
```php
Carbon::setTestNow('2026-01-01');
```

Correct:
```php
freezeTime();
travelTo(now()->addDays(31));
travelBack();
```

Use `Sleep::fake()` for waits and `Str::createRandomStringsUsing()` when asserting a generated identifier.

## Guard Query Counts

`expectsDatabaseQueryCount()` catches an N+1 regression the moment it appears.

```php
it('lists orders without an N+1 query', function () {
    Order::factory()->count(5)->hasLines(3)->create();

    $this->expectsDatabaseQueryCount(3);

    $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk();
});
```

## Enforce Architecture With arch() Tests

Architecture tests convert the rules in this skill into failures. Copy `stubs/ArchTest.php` into `tests/Feature/ArchTest.php` on every project and adjust it to what the project actually does.

```php
arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel();

arch()->expect('App')->toUseStrictTypes()
    ->not->toUse(['dd', 'dump', 'die', 'var_dump', 'ray']);
```

An architecture test states a convention for a directory rather than a behaviour, so it is exempt from the rules above about observable outcomes.

## Keep the Suite Fast

- `BCRYPT_ROUNDS=4` in `phpunit.xml`. The default of 12 dominates the runtime of every test that signs a user in.
- Disable Telescope, Pulse, and Nightwatch in the test environment.
- `withoutVite()` in the base `TestCase` for tests that render views.
- Run with `--parallel`.
- Pest 5 adds `--tia` to run only tests affected by the change, and time-balanced sharding through `--update-shards` and `--shard=1/4`.

## Running tests

```bash
php artisan test --compact --filter=publishesADraftOrder
php artisan test --compact tests/Feature/Admin/OrderCrudTest.php
./vendor/bin/pest --parallel
```

Run the narrowest set that covers the change, rerun after each edit to it, then ask the user to run the full suite. Do not delete or rewrite an existing test without approval; report the problem instead.
