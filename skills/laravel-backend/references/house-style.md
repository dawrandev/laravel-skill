# House style

Conventions this developer's projects follow. Where a project's own `CLAUDE.md` says something different, that wins.

## Contents

- [Stack](#stack)
- [Write Code Comments in English](#write-code-comments-in-english)
- [Wrap User-Facing Text in a Translation Call From the Start](#wrap-user-facing-text-in-a-translation-call-from-the-start)
- [Naming](#naming)
- [Routing and controllers](#routing-and-controllers)
- [Blade structure](#blade-structure)
- [CSS and JavaScript](#css-and-javascript)
- [Name the Guard in Background Code](#name-the-guard-in-background-code)
- [Prefer Status Columns to Hard Deletes](#prefer-status-columns-to-hard-deletes)
- [Store Time in UTC and Convert on Display](#store-time-in-utc-and-convert-on-display)
- [PHP style](#php-style)
- [Commits](#commits)
- [Patterns proven in these projects](#patterns-proven-in-these-projects)

## Stack

Plain Laravel with hand-written Blade. Admin panel builders are not used: they are slower to work in than writing the screen, and they take over patterns the rest of the application already has.

- Blade + Tailwind CSS 4 + Alpine.js, bundled through Vite. Not a CDN.
- No Filament, no Nova, no Livewire, no Inertia, no React or Vue. Interactivity is Alpine plus small JSON endpoints.
- Tailwind 4 is CSS-first: new colours and sizes go in the `@theme` block in `resources/css/app.css`. No `tailwind.config.js`.
- MySQL.

Ask before introducing any of the above; they change how every later screen is built.

## Write Code Comments in English

Every comment is English: PHPDoc, `//`, `/* */`, Blade `{{-- --}}`, JavaScript, CSS, migration notes, and test names. This holds even when the conversation and the documentation are in another language.

Documentation files, decision logs, and commit messages stay in the project's own language. Code stays English.

Comments explain why. What the code does is already visible.

## Wrap User-Facing Text in a Translation Call From the Start

Every string a user sees goes through `__()` the first time it is written. Retrofitting translation later means finding every string again, and some are always missed.

Incorrect:
```php
return back()->with('success', 'Order created.');
```

Correct:
```php
return back()->with('success', __('orders.created'));
```

Enum labels centralise user-visible names for a value:

```php
public function label(): string
{
    return match ($this) {
        self::Draft => __('orders.status.draft'),
        self::Placed => __('orders.status.placed'),
    };
}
```

Locales vary per project (`uz`, `ru`, `kk`, sometimes `en`). Read `config/app.php` and the `lang/` directory to find the default, and follow whichever key style the project already uses: translation-key files (`__('orders.created')`) or the default-language string as the key.

Use `__()`, not `@lang`, because the same call then works in PHP and Blade.

## Naming

| Element | Convention | Example |
|---|---|---|
| Table | plural snake_case | `order_status_changes` |
| Pivot table | singular model names, alphabetical | `order_product` |
| Column | snake_case | `placed_at` |
| Foreign key | singular model + `_id` | `order_id` |
| Model | singular StudlyCase | `OrderStatusChange` |
| Controller | singular resource + `Controller` | `OrderController` |
| Enum | singular StudlyCase, no suffix | `OrderStatus` |
| Data object | `*Data` | `CreateOrderData` |
| Action | verb phrase, no suffix | `CreateOrder`, `ApproveInvoice` |
| Job | verb phrase, no suffix | `SendInvoiceReminder` |
| Event | tense marks before or after | `ApprovingLoan`, `LoanApproved` |
| Command | `*Command` | `PublishScheduledPostsCommand` |
| Mailable | `*Mail` | `AccountActivatedMail` |
| Service method | verb | `assign()`, `changeStatus()` |
| Route name | dotted, snake_case inside a segment | `admin.orders.show_active` |
| Policy ability | camelCase, `view` rather than `show` | `viewOrder`, `updateOrder` |
| Blade view | kebab-case | `show-filtered.blade.php` |
| Config file | snake_case | `google_calendar.php` |
| Variable | camelCase; plural for collections | `$activeOrders`, `$activeOrder` |

## Routing and controllers

- URL segments are kebab-case: `/order-lines`.
- HTTP verb first: `Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');`
- Tuple notation for the action, never the `Controller@method` string.
- Stick to the seven resource actions. A custom verb usually means a second resource: `POST /published-orders/{order}` handled by `PublishedOrderController@store`, rather than `OrderController@publish`.
- Single-action controllers are named for the action: `PerformCleanupController`.

## Blade structure

```
resources/views/
  layouts/     admin.blade.php, guest.blade.php, site.blade.php
  partials/    admin/, site/          repeated fragments: sidebar, header
  components/  admin/, site/          reusable elements: <x-admin.form.input>
  pages/       admin/, site/, auth/   actual screens, each @extends a layout
  errors/
```

- Controllers reference the full path: `view('pages.admin.orders.index')`.
- One `partials/form.blade.php` serves both create and edit, branching on `$order ?? null`.
- When the same UI appears on two screens, extract a component. Two copies drift, and a fix applied to one leaves the bug in the other.

## CSS and JavaScript

- Tailwind utility classes stay in the markup. Moving them into a stylesheet loses the benefit.
- A repeated visual pattern becomes a Blade component, not a CSS class.
- Small Alpine attributes (`x-data`, `@click`, `x-show`) belong inline in Blade; that is the idiom.
- Larger JavaScript goes to a module under `resources/js/`. No inline `<script>` blocks.
- Load heavy libraries only on the pages that need them.
- Tailwind 4 reads class names out of Blade files at build time. A new or edited `class="..."` does not exist in the compiled CSS until `npm run build` runs, so the page looks right locally with the dev server and breaks on the server.

## Name the Guard in Background Code

In an application with more than one guard, always name it. Authenticating with a second guard switches the default guard for the whole request, so a bare `auth()` in an observer, job, or command reports the wrong user, and a foreign key against the wrong table fails.

Incorrect:
```php
$log->admin_id = auth()->id();
```

Correct:
```php
$log->admin_id = auth('web')->id();
```

## Prefer Status Columns to Hard Deletes

Records are deactivated, cancelled, or soft-deleted. `SoftDeletes` plus a status column keeps history and analytics intact. Ask before writing a hard delete.

## Store Time in UTC and Convert on Display

The database and `config/app.php` are UTC. Display converts to the local zone, from a config value rather than a literal. Cast every date column so no view parses a string.

Incorrect:
```blade
{{ $order->created_at }}
```

Correct:
```blade
{{ $order->created_at->timezone(config('app.display_timezone'))->format('d.m.Y H:i') }}
```

## PHP style

Pint with the `laravel` preset formats the code, so style beyond what Pint fixes is limited to:

- `declare(strict_types=1)` in every PHP file.
- Explicit return types and parameter type hints on every method.
- Constructor property promotion with `private readonly` for injected dependencies.
- Type properties directly. A `/** @var string */` docblock is not a substitute for a type.
- Docblocks only where a type cannot be expressed in the signature (`@return Collection<int, Order>`, `@param array<string, mixed>`) or where a description adds something. Import class names in docblocks.
- String interpolation over concatenation: `"Order {$order->id} placed."`
- Guard clauses first, happy path last. Avoid `else` by returning early. Separate `if` statements read better than one compound condition.
- Validation rules as arrays: `['required', 'string', 'max:255']`, never a pipe string.

Run Pint with an explicit path so it cannot walk out of the project:

```bash
./vendor/bin/pint --dirty "D:\laragon\www\<project>\app" "D:\laragon\www\<project>\tests"
```

## Commits

- Message in the project's documentation language, prefixed `feat:`, `fix:`, `refactor:`, `docs:`, or `test:`.
- Stage explicit paths. `git add -A` sweeps in whatever another session left half-finished in the same working directory.
- Before committing: Pint, the test suite, and `npm run build` when anything under `resources/` changed.
- Some projects forbid tool attribution trailers in commit messages. Check the project's `CLAUDE.md` before adding one.

## Patterns proven in these projects

Reuse these rather than reinventing them; each came out of a production problem.

**Audit trail.** An observer plus one writer service records who changed what. Mask sensitive values (`password`, `remember_token`) as `[hidden]` so the field name is visible without copying the secret. Skip writing a record when only `updated_at` changed.

**Chunked upload.** Large files upload as sequential small POSTs, which sidesteps server upload limits on hosting without root access and survives a dropped connection. Validate the reassembled file by real content, never by the client-declared MIME type. Claim the upload with a one-time token. Clean up abandoned sessions lazily on the next upload, so no cron is required.

**Base classes for repeated lookups.** With many identically-shaped reference-data resources, a base class plus a short subclass each is worth it. Extract the base only once three or four identical modules exist.

**Blade fragments for list refreshes.** Return `->fragmentIf($request->hasHeader('X-Partial'), 'list')` so the full page and the incremental update render from one template.

**Search isolated behind one layer.** Keep search logic in a query builder or a single service, so swapping the engine later touches one file.
