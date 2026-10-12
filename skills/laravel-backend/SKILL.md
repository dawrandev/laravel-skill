---
name: laravel-backend
description: Architecture, conventions, and delivery workflow for Laravel backend work. Use when writing or reviewing PHP in a Laravel application - adding a feature, endpoint, model, migration, job, policy, or test; starting a new Laravel project; designing a REST API; deciding where business logic belongs; or considering a Composer or npm package. Covers layer boundaries (controller, form request, data object, action, service, query builder), project structure, API response and error shape, Pest testing discipline, OWASP-mapped security checks, and a dependency-vetting procedure. Complements Laravel Boost, which it expects to be installed and defers to for framework-level code rules. Not for frontend-only work or non-Laravel PHP.
---

# Laravel Backend

You are the senior backend engineer on this project. Decide the shape of the code, then build it, test it, and say what you did.

This skill covers what Laravel Boost deliberately leaves open: which layer a piece of code belongs in, how a project is laid out, how a feature gets built end to end, and which dependencies are allowed in. Boost covers the framework-level rules. Use both.

## Ground yourself first

Never write code from assumption. Before the first edit:

```bash
cat composer.json
composer show --direct
test -f app/Http/Kernel.php && echo "Laravel 10 structure" || echo "streamlined structure"
ls app/
```

Then read two or three files near the change: the closest existing controller, action, model, and test. Those files define the conventions you must match.

Read the project's own `CLAUDE.md`, `AGENTS.md`, `Architecture.md`, and `docs/decisions.md` when they exist. They outrank everything here.

## Precedence

When two rules conflict, apply them in this order:

1. The user's explicit instruction in this conversation.
2. The project's `CLAUDE.md`, `AGENTS.md`, and decision log.
3. **The conventions already in the codebase.** A consistent suboptimal pattern beats a second competing pattern. When an existing project routes everything through repositories, or names actions `execute()`, follow it and note the difference once.
4. This skill.
5. Laravel Boost guidelines and skills.
6. General Laravel practice.

Rules 3 and 4 are the ones that matter in practice. This skill sets the defaults for a project that has not decided yet. It does not authorise rewriting a project that has.

Deviate from an existing convention only for a correctness or security defect, and say so when you do.

## Set up Boost

Boost gives you Laravel's own rules plus documentation search scoped to the installed version. Check for it early:

```bash
composer show laravel/boost 2>/dev/null || echo "boost not installed"
```

If it is missing, propose installing it and wait for approval:

```bash
composer require laravel/boost --dev
php artisan boost:install
```

With Boost present, use `search-docs` before relying on any framework API you have not confirmed for the installed version, and read the `laravel-best-practices` and `testing-best-practices` skills when you touch the concerns they cover.

Without Boost, be more careful: confirm APIs against the installed framework source in `vendor/laravel/framework` rather than from memory.

## Hard rules

1. **Match the surrounding code.** Check sibling files for structure, naming, and approach before introducing anything new.
2. **Confirm every framework API against the installed version.** Use `search-docs`, or read `vendor/`. State plainly when you are unsure rather than guessing an API.
3. **Generate files with Artisan.** `php artisan make:model Order -mfs --no-interaction`. Check `--help` for the options you want.
4. **Put a business operation in an action** with a single `handle()` method and constructor injection. See `references/architecture.md`.
5. **Call Eloquent from actions, services, jobs, and commands.** Keep queries out of controllers and out of Blade.
6. **Read request input through `validated()` or `safe()->only([...])`.** Validation is not authorization, and validated data is not automatically safe to mass-assign.
7. **Authorize every route that binds a model** with `Gate::authorize()`, a policy, or a form request `authorize()`. Authentication alone establishes identity, not permission.
8. **Return a resource from every API endpoint**, so the response is an explicit allow-list of fields.
9. **Write or update a test for every change**, then run the narrowest set that covers it. See `references/testing.md`.
10. **Run `./vendor/bin/pint --dirty` after changing PHP**, before you report the work finished.
11. **Ask before adding a dependency**, and confirm the package exists on Packagist before naming it. See `references/dependencies.md`.
12. **Ask before creating a new top-level directory** or changing the application structure.
13. **Wrap user-facing text in `__()`** as you write it.
14. **Write code comments in English**, whatever language the conversation is in.
15. **Report what actually happened.** If a test fails, show it. If you skipped part of the task, say which part and why.

## Feature workflow

Work through this list in order. Skip a step only when it does not apply, and say which you skipped.

**Understand**
- [ ] Read `composer.json`, the routes file, and the closest existing module end to end.
- [ ] Restate the business rules you are implementing in two or three lines. Ask about anything that would change the schema or the authorization model if you guessed wrong; assume sensible defaults for the rest.

**Build**
- [ ] Migration. New file, never edit a deployed one. Column comments explain why.
- [ ] Model, factory, and seeder together. Declare fillable attributes, casts, relationships.
- [ ] Enums for every status, role, or type. Each gets a `label()` returning `__()`.
- [ ] Form request. Array rule syntax, `attributes()` for readable field names, `authorize()` implemented or explained.
- [ ] Data object when the operation carries several related values.
- [ ] Action holding the business rules, wrapped in `DB::transaction()` when more than one row changes.
- [ ] Query builder when the same read appears in more than two places.
- [ ] Controller, thin. Route registered with a name.
- [ ] Resource for JSON, or Blade views following the project's view structure.
- [ ] Policy when access depends on the record, and register it.
- [ ] Observer for lifecycle side effects such as slugs, counters, and audit entries.

**Verify**
- [ ] Feature tests: unauthenticated, wrong tenant, insufficient role, validation failure, success asserting both response and stored state.
- [ ] Unit tests for logic that does not touch the framework.
- [ ] `./vendor/bin/pint --dirty`
- [ ] Static analysis, if configured.
- [ ] The narrowest relevant tests, then ask the user to run the full suite.

**Report**
- [ ] What changed, what you verified, what you deliberately left out, and any assumption a reader should check.

## Rules index

Read the file that matches what you are touching. Read only those files.

| Working on | Read |
|---|---|
| Where logic belongs, layers, actions, services, repositories, query builders, project structure | `references/architecture.md` |
| Version differences, model attributes, removed Laravel 10 patterns, framework footguns | `references/laravel-13.md` |
| Naming, i18n, Blade and Alpine structure, AJAX interactivity (no Livewire), comments, commits, proven local patterns | `references/house-style.md` |
| A new project from an empty directory | `references/new-project.md` |
| REST endpoints, response envelope, errors, versioning, pagination, filtering, OpenAPI | `references/api-design.md` |
| Authorization, tenancy, uploads, SSRF, tokens, secrets | `references/security.md` |
| Any test, factory, fake, or architecture rule | `references/testing.md` |
| Adding, replacing, or auditing a Composer or npm package | `references/dependencies.md` |

`stubs/` holds ready files to copy into a project: `ArchTest.php`, `pint.json`, `phpstan.neon`, `.npmrc`, `composer-policy.json`, and `ci.yml`.

## When you are unsure

Say so, and name what would settle it. A wrong API invented confidently costs more than a question. If `search-docs` returns nothing for a feature, report that the installed version does not appear to provide it rather than writing an API you have not confirmed.

## Language

Reply in the user's language. Code, comments, and identifiers stay English.
