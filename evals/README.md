# Evaluations

Four scenarios that check whether the skill changes what the agent does. Run each in a fresh Claude Code session so nothing carries over from this conversation.

Method: run the prompt once with the skill unavailable to establish a baseline, then once with it installed. The skill earns its place only where the two differ.

Record results in `results.md` with a date and the model used.

---

## 1. New project

**Setup:** an empty directory.

**Prompt:** "Buyurtmalar uchun API kerak. Foydalanuvchi buyurtma yaratadi, admin uni tasdiqlaydi."

**Pass when the agent:**

- [ ] Asks about the delivery surface, ownership model, roles, and locales before generating code — and not much more than that
- [ ] Creates a Laravel 13 project and reports the PHP version
- [ ] Proposes `laravel/boost` and waits for approval
- [ ] Adds `Model::preventLazyLoading()` and `preventSilentlyDiscardingAttributes()` behind `! isProduction()`
- [ ] Routes under `/api/v1`
- [ ] Puts the approval rule in an action with a `handle()` method, not in the controller
- [ ] Creates a backed `OrderStatus` enum with `label()` returning `__()`
- [ ] Returns an `OrderResource`, not the model
- [ ] Copies `ArchTest.php` and leaves it green
- [ ] Writes Pest feature tests covering unauthenticated, wrong-owner, validation failure, and success
- [ ] Runs Pint

**Fail signals:** `app/Http/Kernel.php`; a `RouteServiceProvider`; `$request->all()`; a repository interface over `Order`; `$guarded = []`; hard-coded user-facing strings.

---

## 2. Existing project — the precedence test

This is the most important scenario. The skill must not rewrite a codebase that has already decided.

**Setup:** `D:\laragon\www\SamVet-library`, which routes every read through `app/Repositories/` and mandates that in its own `ARCHITECTURE.md`.

**Prompt:** "Yangi modul qo'sh: tadbirlar (events) uchun CRUD."

**Pass when the agent:**

- [ ] Reads `CLAUDE.md` and `ARCHITECTURE.md` before writing
- [ ] Follows the existing repository + service structure
- [ ] Registers the binding in `RepositoryServiceProvider` as the project does
- [ ] Mentions at most once that a new project would be structured differently, then continues
- [ ] Writes tests in the project's existing style
- [ ] Uses `auth('web')` rather than a bare `auth()`

**Fail signals:** introducing `app/Actions/` alongside the existing repositories; refactoring existing files; arguing about the architecture instead of doing the work.

---

## 3. Dependency vetting

**Setup:** any Laravel project.

**Prompt A:** "Excel eksport qo'shish kerak."

**Pass when the agent:**

- [ ] Checks whether the project already has an export mechanism
- [ ] Confirms `maatwebsite/excel` exists on Packagist rather than asserting it from memory
- [ ] Reports the version, the release date, and the supported Laravel range
- [ ] Asks for approval before running `composer require`

**Prompt B (the control):** "PDF uchun `spatie/laravel-pdf-generator` paketini o'rnat."

That package does not exist.

**Pass when the agent:**

- [ ] Checks Packagist, finds a 404, and stops
- [ ] Says plainly that the package does not exist rather than inventing a description of it
- [ ] Searches for and offers a real alternative
- [ ] Does not run `composer require` to find out

**Fail signals:** running `composer require` on the invented name; substituting a similar-looking invented name; describing the non-existent package's features.

**Prompt C:** "Migratsiyada ustun turini o'zgartirish kerak."

**Pass when the agent** changes the column natively and does **not** add `doctrine/dbal`.

---

## 4. Security

**Setup:** a project with `User`, `Team`, and `Order`, where an order belongs to a team.

**Prompt:** "Foydalanuvchi o'z jamoasining buyurtmalarini ko'ra olsin, ro'yxat va bitta buyurtma."

**Pass when the agent:**

- [ ] Constrains the query by team rather than filtering after fetching
- [ ] Calls `Gate::authorize()` or implements a policy, not `auth:sanctum` alone
- [ ] Caps `per_page`
- [ ] Orders with a unique tie-breaker before paginating
- [ ] Returns a resource
- [ ] Writes a cross-team test asserting **404**, not 403
- [ ] Allow-lists any sort or filter parameter it exposes

**Fail signals:** `Order::all()` then filtering in PHP; `orderBy($request->sort)`; returning the model; a cross-team test asserting 403.

---

## Interpreting failures

A missed rule usually means the rule is buried, not that it is absent. Fixes, in order of preference:

1. Move it into `SKILL.md`'s hard rules, if it is genuinely always-on.
2. Make it more specific — name the exact API, add an `Incorrect`/`Correct` pair.
3. Add an `arch()` rule so the failure is mechanical rather than a matter of attention.

Adding words is the last resort. Length is what makes a ruleset get skimmed.
