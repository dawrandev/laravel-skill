# Architecture

Layer boundaries, where each kind of code lives, and which abstractions to skip.

## Contents

- [Request flow](#request-flow)
- [Directory layout](#directory-layout)
- [Keep Controllers Thin](#keep-controllers-thin)
- [Put One Business Operation in an Action](#put-one-business-operation-in-an-action)
- [Use a Service for Stateless Helpers and External Wrappers](#use-a-service-for-stateless-helpers-and-external-wrappers)
- [Call Eloquent from Actions, Services, Jobs, and Commands](#call-eloquent-from-actions-services-jobs-and-commands)
- [Extract Repeated Queries to a Dedicated Query Builder](#extract-repeated-queries-to-a-dedicated-query-builder)
- [Reserve Repositories for Non-Eloquent Data Sources](#reserve-repositories-for-non-eloquent-data-sources)
- [Pass a Data Object When an Operation Takes Several Related Values](#pass-a-data-object-when-an-operation-takes-several-related-values)
- [Keep Models Declarative](#keep-models-declarative)
- [Move Model Lifecycle Logic to an Observer](#move-model-lifecycle-logic-to-an-observer)
- [Return a Resource From Every API Endpoint](#return-a-resource-from-every-api-endpoint)
- [Extract a Domain Folder Only After Repeated Growth](#extract-a-domain-folder-only-after-repeated-growth)
- [Enforcement](#enforcement)

## Request flow

```
Route
  -> Controller            thin: resolve input, call one collaborator, return a response
  -> FormRequest           validation and authorization of the incoming request
  -> Data object           typed carrier, when the operation takes several related values
  -> Action / Service      business rules, transactions, file handling, events
  -> Model / QueryBuilder  persistence and reads
  -> Resource / View       response shaping
```

Observers run on model events. Jobs run the same actions asynchronously.

## Directory layout

Default Laravel structure. Create a directory when its first real member appears, not in advance, and use `php artisan make:*` so the generator owns the path.

```
app/
  Actions/Order/CreateOrder.php         one business operation per class
  Services/PricingService.php           stateless helpers, third-party wrappers
  Data/CreateOrderData.php              data objects (make:data default namespace)
  Enums/OrderStatus.php                 backed enums with label()
  Models/Order.php
  Models/Builders/OrderBuilder.php      custom Eloquent query builders
  Observers/OrderObserver.php
  Policies/OrderPolicy.php
  Support/Money.php                     value objects
  Http/
    Controllers/Api/V1/OrdersController.php
    Requests/Order/StoreOrderRequest.php
    Resources/OrderResource.php
  Jobs/  Events/  Listeners/  Notifications/  Mail/  Console/Commands/
```

## Keep Controllers Thin

A controller resolves input, calls one collaborator, and returns a response. Business branching and queries belong elsewhere. A controller that reads and writes one model with no extra rules needs no action.

Incorrect:
```php
public function store(Request $request): RedirectResponse
{
    $data = $request->validate(['title' => 'required']);

    if ($request->user()->orders()->where('status', 'draft')->count() >= 5) {
        return back()->withErrors(['title' => __('orders.too_many_drafts')]);
    }

    $order = Order::create($data + ['user_id' => $request->user()->id]);
    Mail::to($request->user())->send(new OrderCreated($order));

    return redirect()->route('orders.show', $order);
}
```

Correct:
```php
public function store(StoreOrderRequest $request, CreateOrder $createOrder): RedirectResponse
{
    $order = $createOrder->handle(
        CreateOrderData::fromRequest($request),
        $request->user(),
    );

    return redirect()->route('orders.show', $order);
}
```

## Put One Business Operation in an Action

An action is a plain class with one public method, constructor injection, and no framework base class. Name it after the operation a person would describe: `CreateOrder`, `ApproveInvoice`, `CancelSubscription`. Actions compose by injecting other actions. An action class has no special meaning to Laravel, so the convention only pays off while it stays consistent across the project.

Use `handle()` as the public method everywhere. It matches jobs, listeners, and console commands, so the same class runs from a queue without a second convention.

Correct:
```php
final class CreateOrder
{
    public function __construct(
        private readonly ReserveStock $reserveStock,
    ) {}

    public function handle(CreateOrderData $data, User $buyer): Order
    {
        return DB::transaction(function () use ($data, $buyer): Order {
            $order = $buyer->orders()->create($data->toAttributes());

            $this->reserveStock->handle($order);

            return $order;
        });
    }
}
```

An action receives values or a data object. Reading `request()` inside an action ties it to HTTP and stops it working from a job or a command.

Incorrect:
```php
public function handle(): Order
{
    return Order::create(['title' => request('title')]);
}
```

Correct:
```php
public function handle(CreateOrderData $data): Order
{
    return Order::create($data->toAttributes());
}
```

Add an action when it removes duplication, creates a reusable operation, or makes behaviour independently testable. A line count in a controller is not by itself a reason.

## Use a Service for Stateless Helpers and External Wrappers

A service holds several cohesive methods around one capability that is not a single business operation: a third-party client wrapper (`StripeService`, `SmsService`), a calculator, a formatter. It is also the right home for code that does not yet belong anywhere else.

A service that grows one method per model is a sign those operations should have been actions.

## Call Eloquent from Actions, Services, Jobs, and Commands

Eloquent is the data-access layer. A second layer that wraps it and returns Eloquent models adds indirection without adding a boundary, because the returned model still exposes `save()`, `delete()`, and `newQuery()` to the caller.

Keep queries out of Blade templates and out of controllers, where they hide cost and cause N+1 problems.

Incorrect:
```blade
@foreach (App\Models\Order::where('status', 'open')->get() as $order)
```

Correct:
```php
// Controller
return view('pages.orders.index', [
    'orders' => Order::query()->open()->with('buyer')->paginate(),
]);
```

## Extract Repeated Queries to a Dedicated Query Builder

When the same filters, joins, or orderings appear in more than two places, give the model a custom builder. A builder is a real class, so an IDE navigates to it and PHPStan checks it; neither holds for magic `scopeX()` methods. Use a `#[Scope]` attribute for one or two trivial filters and a builder beyond that.

Correct:
```php
// app/Models/Builders/OrderBuilder.php
/** @extends Builder<Order> */
final class OrderBuilder extends Builder
{
    public function open(): self
    {
        return $this->whereIn('status', [OrderStatus::Draft, OrderStatus::Placed]);
    }

    public function forBuyer(User $buyer): self
    {
        return $this->whereBelongsTo($buyer, 'buyer');
    }
}
```

```php
// app/Models/Order.php
/** @method static OrderBuilder query() */
final class Order extends Model
{
    public function newEloquentBuilder($query): OrderBuilder
    {
        return new OrderBuilder($query);
    }
}
```

A builder groups multi-condition clauses itself; model scopes get that grouping automatically, so move the grouping into the builder method when you convert one.

## Reserve Repositories for Non-Eloquent Data Sources

Write a repository interface only when one of these is true today, not hypothetically:

1. The data does not live in this database. An external REST or GraphQL API, a payment provider, an LDAP directory, a search index. There is no Eloquent model to leak, so the abstraction is real.
2. Two implementations exist right now and both ship.
3. The real implementation is nondeterministic or leaves the process, and tests need a fake.

Name it for the capability and give it domain-shaped methods.

Incorrect:
```php
interface OrderRepositoryInterface
{
    public function all(): Collection;
    public function find(int $id): ?Order;
    public function create(array $data): Order;
}
```

Correct:
```php
interface ExchangeRateProvider
{
    public function rateFor(Currency $from, Currency $to, CarbonImmutable $on): Money;
}
```

If the interface will only ever have one implementation for the life of the project, skip the interface and inject the class.

An existing project overrides this. When a codebase already routes reads through repositories, extend that structure and say so in your summary, rather than introducing a second pattern beside it.

## Pass a Data Object When an Operation Takes Several Related Values

A data object carries validated input from the boundary into an action. It earns its place when several related values travel together. A single model or scalar does not need one.

Incorrect:
```php
final class PublishPostData
{
    public function __construct(public int $postId) {}
}
```

Correct:
```php
public function handle(Post $post): Post
```

Hand-written data classes with promoted constructor properties are the default. Reach for `spatie/laravel-data` when the project has a TypeScript front end, or many API endpoints that would otherwise repeat the same fields across a request, a DTO, and a resource. Its own documentation notes that a FormRequest stays easier for complex conditional rules, and it has no equivalent of `authorize()`.

When a data object mirrors a table, adding a column means changing the constructor, the factory method, `toAttributes()`, and the validation rules together. Cover each data object with one test that creates a record and asserts the new field persisted, so a missed spot fails loudly instead of dropping data silently.

## Keep Models Declarative

A model declares mass-assignable attributes, casts, relationships, and small derived accessors. Query logic goes to a builder, lifecycle logic to an observer, business rules to an action.

## Move Model Lifecycle Logic to an Observer

Slug generation, counters, and audit entries belong in an observer registered with `#[ObservedBy]`. Observers resolve through the container, so they accept constructor injection.

In an application with more than one auth guard, name the guard in observer, job, and command code: `auth('web')->id()`. A bare `auth()` follows whichever guard the current request authenticated with, so background code attributes the record to the wrong user.

## Return a Resource From Every API Endpoint

Returning a model serialises whatever columns exist today, so a future column becomes public API without review. A resource is an explicit allow-list.

Incorrect:
```php
return $order;
```

Correct:
```php
return OrderResource::make($order->load('lines'));
```

## Extract a Domain Folder Only After Repeated Growth

Stay in flat `app/` until one business area has clearly outgrown it. Then move that one area to `app/Domain/<Context>/` and leave everything else alone. Adopt a module package only after that has happened three or more times, or when separate teams own separate areas.

A non-default structure competes with every generator, every tutorial, and every agent that reads sibling files to infer conventions. Ask before creating a new top-level namespace.

## Enforcement

`stubs/ArchTest.php` turns the rules above into failing tests: controller suffixes and public methods, models unused inside controllers, actions final, requests extending `FormRequest`, jobs implementing `ShouldQueue`, strict types, and no debug helpers left behind.
