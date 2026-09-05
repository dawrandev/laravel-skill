# laravel-backend

A Claude Code skill that makes an AI agent write Laravel like a senior backend engineer on this team: consistent layering, a repeatable feature workflow, and a dependency policy — without re-explaining any of it at the start of every project.

## What it is for

Starting a new project used to mean teaching the agent how to write code before it could write any. This skill is that explanation, written once.

## What it does not do

It does not restate the framework. Laravel ships its own agent guidelines in [`laravel/boost`](https://github.com/laravel/boost): nineteen rule files covering Eloquent, security, validation, queues, caching, migrations, and more, plus an MCP server with documentation search scoped to the installed version.

Boost is deliberately neutral about architecture. Its guiding rule is *"the best choice is the one the codebase already uses"* and, on actions, *"an action class has no special meaning to Laravel; follow the project's naming and invocation conventions."* That is the right stance for a tool shipped to every Laravel user, and it is exactly the gap on an empty project, where there is no existing convention to follow.

So this skill covers the decisions Boost leaves open:

| Boost covers | This skill covers |
|---|---|
| Eloquent, caching, queues, migrations, validation, mail | Which layer a piece of code belongs in |
| Security and testing rule files | Project structure and naming |
| Version-specific framework APIs | The end-to-end feature workflow |
| Documentation search | Dependency vetting, house style, API shape |

Install both. The skill defers to Boost on framework-level questions and to the existing codebase on everything the codebase has already decided.

## Layout

This repository is a Claude Code plugin and also its own single-plugin marketplace.

```
.claude-plugin/
  marketplace.json        makes this repo installable by URL
  plugin.json             plugin metadata
skills/laravel-backend/
  SKILL.md                entry point: grounding, precedence, hard rules, workflow, index
  references/
    architecture.md       layers, actions vs services, query builders, repository policy
    laravel-13.md         version gates, model attributes, removed patterns, footguns
    house-style.md        naming, i18n, Blade and Alpine, comments, commits
    new-project.md        day-zero setup checklist
    api-design.md         envelope, Problem Details, versioning, pagination, OpenAPI
    security.md           OWASP API Top 10 mapped to Laravel
    testing.md            Pest discipline, endpoint coverage, isolation, arch tests
    dependencies.md       package trust policy
  stubs/                  files to copy into a project
evals/                    scenarios for checking the skill still works
```

## Install

### On any machine (recommended)

```
/plugin marketplace add dawrandev/laravel-skill
/plugin install laravel-backend@dawrandev
```

Nothing depends on where the repository lives, so the path the repository sits at on any given machine does not matter.

If the repository is later made private, the machine also needs git credentials for the `dawrandev` account — a personal access token or Git Credential Manager, the same setup any private clone needs.

Pull later changes with:

```
/plugin marketplace update dawrandev
```

### Fallback: link it by hand

Works everywhere, including where the plugin commands are unavailable.

```powershell
git clone https://github.com/dawrandev/laravel-skill.git D:\path\to\repo
New-Item -ItemType Directory -Force "$env:USERPROFILE\.claude\skills"
cmd /c mklink /J "$env:USERPROFILE\.claude\skills\laravel-backend" "D:\path\to\repo\skills\laravel-backend"
```

On macOS or Linux, `ln -s <repo>/skills/laravel-backend ~/.claude/skills/laravel-backend`.

Per project rather than globally: link into `<project>/.claude/skills/laravel-backend`.

### On the machine where you edit the skill

Use the junction, not the plugin. Edits are then live with no reinstall step. Do not do both on one machine: two copies of the same skill name is ambiguous.

## Design constraints

The format follows what Anthropic, Cursor, and Boost independently converged on:

- `SKILL.md` stays under 500 lines. Reference files sit one level deep, because Claude reads deeper files only partially.
- Every rule is decidable — two engineers reading it produce the same code — and states the limit of the mechanism it describes.
- Rules are phrased positively. Negative instructions are followed less reliably, so a bare "don't" appears only for genuine tripwires and always alongside the positive replacement.
- Rules point at something checkable: Pint, PHPStan, `composer audit`, or a Pest `arch()` test. `stubs/ArchTest.php` turns the architecture rules into a failing test rather than advice.

## Maintaining it

When the agent gets something wrong, make the relevant rule **more specific**, not longer. Length is what makes a ruleset get ignored.

Re-check the version-sensitive parts when a new Laravel major ships: the tables in `references/laravel-13.md` and the abandoned-package list in `references/dependencies.md` are the two places that age.
