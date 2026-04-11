# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
2. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
3. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse   # PHPStan only
composer cs        # CS Fixer only
composer test      # PHPUnit only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

### 3 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^1.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` |
|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 |
| `ez-php/framework` | 3307 | — |
| `ez-php/orm` | 3309 | — |
| `ez-php/cache` | — | 6380 |
| `ez-php/queue` | 3310 | 6381 |
| `ez-php/rate-limiter` | — | 6382 |
| **next free** | **3311** | **6383** |

Only set a port for services the module actually uses. Modules without external services need no port config.

### 4 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/feature-flags

## Source structure

```
src/
├── FlagDriverInterface.php           — contract: enabled(), enabledFor(), all()
├── FlagManager.php                   — delegates to driver; enabled(), enabledFor(), disabled(), disabledFor(), all()
├── Flag.php                          — static facade backed by FlagManager singleton
├── FeatureFlagServiceProvider.php    — binds FlagManager and initialises the Flag facade
└── Driver/
    ├── ArrayDriver.php               — in-memory array (testing + hardcoded flags)
    ├── FileDriver.php                — reads a PHP file returning array<string, bool>
    └── DatabaseDriver.php            — reads feature_flags and feature_flag_contexts tables via PDO

tests/
├── TestCase.php
├── FlagTest.php                      — facade tests
├── FlagManagerTest.php
└── Driver/
    ├── ArrayDriverTest.php
    ├── FileDriverTest.php            — uses sys_get_temp_dir() temp file
    └── DatabaseDriverTest.php        — uses SQLite :memory:
```

---

## Key classes and responsibilities

### FlagDriverInterface (`src/FlagDriverInterface.php`)

Three-method contract:
- `enabled(string $name): bool` — global flag lookup, returns false for unknown flags — never throws
- `enabledFor(string $name, int|string $contextId): bool` — context-specific lookup (e.g. per user); drivers without per-context storage delegate to `enabled()`
- `all(): array<string, bool>` — full global flag map

All driver implementations must honour the no-throw invariant for all three methods.

---

### ArrayDriver (`src/Driver/ArrayDriver.php`)

Constructed with a `array<string, bool>` literal. Intended for tests and for hard-coding flags in application code. Zero I/O — always in-memory.

---

### FileDriver (`src/Driver/FileDriver.php`)

Reads a PHP file via `require` on every call (no caching). The file must return an `array<string, mixed>` — values are cast to `bool`. Returns an empty array (never throws) when the file is missing or returns a non-array. Default path: `config/flags.php`.

---

### DatabaseDriver (`src/Driver/DatabaseDriver.php`)

Queries a `feature_flags` table (`name VARCHAR PRIMARY KEY`, `enabled TINYINT`) for global flag state.

`enabledFor()` additionally checks a `feature_flag_contexts` table (`name`, `context_id`, `enabled`) for per-context overrides. If a matching row exists it takes precedence; otherwise the driver falls back to the global `enabled()` result. The `feature_flag_contexts` table is optional — a missing table is silently treated as no overrides (the `try/catch` in the contexts query falls through to `enabled()`).

All PDO calls are wrapped in `try/catch` — a missing table or connection error results in `false` / empty array, not an exception. This allows the database driver to be used in environments where the migrations have not yet run.

---

### FlagManager (`src/FlagManager.php`)

Thin wrapper around `FlagDriverInterface`. Adds convenience methods: `disabled()` (`!enabled()`), `enabledFor()`, and `disabledFor()` (`!enabledFor()`). Held as the facade's singleton — one manager instance per application lifetime.

---

### Flag (`src/Flag.php`)

Static facade following the same pattern as `Health`, `Mail`, and `Notification`. Holds `private static ?FlagManager $manager`. Initialised by `FeatureFlagServiceProvider::boot()`. Throws `RuntimeException` when called before initialisation (fail-fast). `resetManager()` clears the singleton for test tearDown.

---

### FeatureFlagServiceProvider (`src/FeatureFlagServiceProvider.php`)

`register()` binds `FlagManager` lazily. Driver is selected via `flags.driver` config key:

| Config value | Driver used       | Notes                                                    |
|--------------|-------------------|----------------------------------------------------------|
| `file`       | `FileDriver`      | Path from `flags.file` config key (default: `config/flags.php`) |
| `database`   | `DatabaseDriver`  | Requires `DatabaseInterface` bound in the container      |
| `array`      | `ArrayDriver`     | Empty in-memory driver; useful for CI/test environments  |

`ConfigInterface` is resolved with `try/catch` — defaults apply when Config is not bound. `DatabaseInterface` is resolved directly (throws if missing when `database` driver is requested — fail-fast).

`boot()` calls `Flag::setManager()`. No route registration — this module has no HTTP endpoint.

---

## Design decisions and constraints

- **Depends only on `ez-php/contracts`, not `ez-php/framework`.** Feature flags have no HTTP endpoint and do not need the Router. Depending only on contracts keeps the module usable in any container-based context, not just full framework applications.
- **Unknown flags default to `false`, never throw.** This is the safe default: a missing flag does not crash the application. It is a programmer's responsibility to ensure flags are defined before shipping code that checks them.
- **DatabaseDriver catches all exceptions silently.** A missing `feature_flags` table (e.g., before migrations run) returns false rather than halting the request. This is intentional: feature flags are not critical path — a degraded flag state is preferable to a 500 error. The same applies to `feature_flag_contexts` — the table is optional and a missing one is silently treated as "no overrides".
- **FileDriver re-reads on every call (no caching).** OPcache handles the repeated `require` efficiently in production. Avoiding a cache layer keeps the driver simple and ensures flags are always fresh during development without a cache-clear step.
- **`enabledFor()` on ArrayDriver and FileDriver delegates to `enabled()`.** Neither driver has per-context storage — `enabledFor()` is a global lookup for them. Use `DatabaseDriver` when per-context overrides are needed (e.g. gradual user rollouts).
- **No flag management API (enable/disable via code).** The roadmap describes this module as "simple flag evaluation". Management belongs in a database migration, an admin interface, or a CLI tool — not in the flag module itself. Adding mutation methods would complicate the driver interface and force all drivers (including the read-only FileDriver) to implement writes they cannot support.

---

## Testing approach

No external infrastructure required. All tests run in-process:

- `ArrayDriverTest` — pure unit, no I/O
- `FileDriverTest` — creates a temp file in `sys_get_temp_dir()`, cleans up in `tearDown`
- `DatabaseDriverTest` — uses SQLite `:memory:` via real PDO; tests with and without the `feature_flags` and `feature_flag_contexts` tables; covers context-specific overrides and fallback behaviour
- `FlagManagerTest` — uses `ArrayDriver`, pure unit
- `FlagTest` — tests facade setup, delegation, fail-fast behaviour, and `resetManager()`; `tearDown` always calls `Flag::resetManager()` to prevent state leaking

---

## What does not belong in this module

- **Flag management (enable/disable via API)** — use direct DB access, a migration, or an admin panel
- **Percentage rollouts / user targeting** — use a dedicated feature management service
- **Flag caching layer** — rely on OPcache (FileDriver) or application-level caching
- **HTTP endpoint for flag listing** — expose flags via your own controller if needed
- **Flag validation or type enforcement** — flags are booleans only; typed variants belong in a separate abstraction

