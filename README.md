<div align="center">

# 🧱 laravel-backend

**A Claude Code skill that makes an AI agent write Laravel like a senior backend engineer.**

Consistent layering · A repeatable feature workflow · A strict dependency policy
— written once, so you never re-explain it at the start of a project.

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![Pest](https://img.shields.io/badge/tested%20with-Pest-F472B6?style=flat-square)
![Claude Code](https://img.shields.io/badge/Claude%20Code-plugin-D97757?style=flat-square&logo=anthropic&logoColor=white)
![Version](https://img.shields.io/badge/version-1.0.0-blue?style=flat-square)

[Quick start](#-quick-start) · [What it covers](#-what-it-covers) · [Layout](#-layout) · [Design principles](#-design-principles) · [Maintaining](#-maintaining-it)

</div>

---

## 💡 Why

Starting a new project used to mean teaching the agent *how* to write code before it could write any.
This skill is that explanation, written once and loaded automatically whenever the agent touches Laravel backend code.

## ⚡ Quick start

Inside Claude Code:

```text
/plugin marketplace add dawrandev/laravel-skill
/plugin install laravel-backend@dawrandev
```

That's it. Pull later updates with:

```text
/plugin marketplace update dawrandev
```

> [!TIP]
> Install [`laravel/boost`](https://github.com/laravel/boost) in your project as well. This skill is designed to sit on top of it.

## 🎯 What it covers

Laravel ships its own agent guidelines in **Boost**: nineteen rule files covering Eloquent, security, validation, queues, caching, migrations and more, plus an MCP server with version-scoped documentation search.

Boost is deliberately neutral about architecture. Its guiding rule is *"the best choice is the one the codebase already uses."* That is the right stance for a tool shipped to every Laravel user, and exactly the gap on an empty project, where there is no convention to follow yet.

**This skill fills that gap:**

| 🟥 Laravel Boost covers | 🟦 This skill covers |
|---|---|
| Eloquent, caching, queues, migrations, validation, mail | Which layer a piece of code belongs in |
| Security and testing rule files | Project structure and naming |
| Version-specific framework APIs | The end-to-end feature workflow |
| Documentation search | Dependency vetting, house style, API shape |

### Precedence

The skill never overrides decisions a project has already made. When rules conflict, the agent applies them in this order:

1. The user's explicit instruction
2. The project's `CLAUDE.md`, `AGENTS.md` and decision log
3. **Conventions already in the codebase**
4. This skill
5. Laravel Boost guidelines
6. General Laravel practice

## 🗂 Layout

This repository is a Claude Code plugin **and** its own single-plugin marketplace.

```text
.claude-plugin/
├── marketplace.json        makes this repo installable by URL
└── plugin.json             plugin metadata
skills/laravel-backend/
├── SKILL.md                entry point: grounding, precedence, hard rules, workflow
├── references/
│   ├── architecture.md     layers, actions vs services, query builders, repositories
│   ├── laravel-13.md       version gates, model attributes, removed patterns, footguns
│   ├── house-style.md      naming, i18n, Blade, Alpine + AJAX, commits
│   ├── new-project.md      day-zero setup checklist
│   ├── api-design.md       envelope, Problem Details, versioning, pagination, OpenAPI
│   ├── security.md         OWASP API Top 10 mapped to Laravel
│   ├── testing.md          Pest discipline, endpoint coverage, isolation, arch tests
│   └── dependencies.md     package trust policy
└── stubs/                  ArchTest, CI, PHPStan, Pint and Composer policy to copy in
evals/                      scenarios that check the skill still changes behaviour
```

## 🛠 Manual install

Use this if plugin commands are unavailable, or on the machine where you edit the skill (edits go live instantly, with no reinstall).

**Windows (PowerShell)**

```powershell
git clone https://github.com/dawrandev/laravel-skill.git D:\path\to\repo
New-Item -ItemType Directory -Force "$env:USERPROFILE\.claude\skills"
cmd /c mklink /J "$env:USERPROFILE\.claude\skills\laravel-backend" "D:\path\to\repo\skills\laravel-backend"
```

**macOS / Linux**

```bash
git clone https://github.com/dawrandev/laravel-skill.git ~/laravel-skill
ln -s ~/laravel-skill/skills/laravel-backend ~/.claude/skills/laravel-backend
```

To scope it to a single project, link into `<project>/.claude/skills/laravel-backend` instead.

> [!WARNING]
> Use either the plugin **or** the manual link on a given machine, never both. Two copies of the same skill name are ambiguous.

## 📐 Design principles

The format follows what Anthropic, Cursor and Boost independently converged on:

- **Small entry point.** `SKILL.md` stays under 500 lines. References sit one level deep, because Claude reads deeper files only partially.
- **Decidable rules.** Two engineers reading a rule produce the same code, and each rule states the limits of the mechanism it describes.
- **Positive phrasing.** Negative instructions are followed less reliably, so a bare "don't" appears only for genuine tripwires, always paired with the replacement.
- **Checkable by tools.** Rules point at Pint, PHPStan, `composer audit` or a Pest `arch()` test. `stubs/ArchTest.php` turns the architecture rules into a failing test rather than advice.

## 🔧 Maintaining it

- When the agent gets something wrong, make the relevant rule **more specific**, not longer. Length is what makes a ruleset get ignored.
- When a new Laravel major ships, re-check the two parts that age: the tables in `references/laravel-13.md` and the abandoned-package list in `references/dependencies.md`.
- Verify changes with the scenarios in [`evals/`](evals/README.md): run each with and without the skill in a fresh session.

---

<div align="center">

Made by [**@dawrandev**](https://github.com/dawrandev) for [Claude Code](https://claude.com/claude-code)

</div>
