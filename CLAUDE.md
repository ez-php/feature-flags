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
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
4. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
5. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
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
- Concrete classes are `final` — extend behavior through composition, not inheritance. Exception-hierarchy base classes (e.g. `EzPhpException`, `HttpException`, `CacheException`) are one carve-out, since they exist specifically to be extended. A documented template-method-style base class (e.g. `Mailable`, meant to be configured via constructor-time subclassing) is the other — the owning module's `CLAUDE.md` must record it under Design Decisions.

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

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` (each `-`-separated word upper-cased) unless `--namespace=`
overrides it. Existing exceptions the guess gets wrong: `bignum` → `BigNum`,
`dataloader` → `DataLoader`, `dotenv` → `Env`, `graphql` → `GraphQL`, `oauth` → `OAuth`,
`opcache` → `OPCache`, `swagger-ui` → `SwaggerUI`, `webauthn` → `WebAuthn` and
`websocket` → `WebSocket`; `websocket-client` → `WebsocketClient`, `websocket-tls` → `WebsocketTls`,
`webauthn-metadata` → `WebauthnMetadata` and `metrics-statsd` → `MetricsStatsd` are
intentional lower-case-word namespaces, and `testing-application` shares `EzPhp\Testing\`
with `testing`).

To bring in a module whose code already lives in its own repository instead of
generating a fresh skeleton, pass `--repo=` with a git URL:

```
php make_module.php <name> --repo=<git-url> [--namespace=Foo]
```

This runs `git submodule add <url> modules/<name>` instead of writing package
files, then applies the same monorepo wiring below. It is mutually exclusive
with `--services` and `--description` — a submodule brings its own Docker
scaffold (if any) and its own `composer.json` description. A minimal `CLAUDE.md`
stub is written only if the submodule doesn't already ship one, so
`composer guidelines:sync` has a `# Package:` heading to anchor part 1 against.

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4` **and** the shared
`autoload-dev` `Tests\` directory list), `phpstan.neon`, `phpunit.xml` (test suite
**and** coverage source), and `packages.sh` (alphabetical position) — in both
generated and `--repo` mode.

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — claim the "next free" row by
  editing the table in `CODING_GUIDELINES.md` (never in a `CLAUDE.md` copy) and run
  `composer guidelines:sync` in the same change. Editing it drifts every `CLAUDE.md`
  until the sync runs, which is why the generator only reminds you instead of doing
  it. Skipping the edit leaves "next free" stale, so the next module collides.

### 4 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^2.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
```

Pass `--extensions` to merge PHP extension install blocks (apt packages plus `docker-php-ext-install`/`pecl` lines) directly into `docker/app/Dockerfile`, instead of hand-editing it afterward — supported extensions: `bcmath`, `gmp`, `gd`, `imagick`:

```
vendor/bin/docker-init --extensions=gmp,bcmath
vendor/bin/docker-init --extensions=gd,imagick
```

When run from a module directory inside this monorepo, any requested extension not already present is also merged into the shared root `docker/app/Dockerfile` — the container `composer full` at the root actually runs against, distinct from the module's own standalone image.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | Redis host port | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 (`REDIS_PORT`) | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/` (application template) | 3308 | 6383 (`REDIS_PORT`) | — |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 (`REDIS_HOST_PORT`) | — |
| `ez-php/queue` | 3310 | 6381 (`REDIS_HOST_PORT`) | — |
| `ez-php/rate-limiter` | — | 6382 (`REDIS_HOST_PORT`) | — |
| `ez-php/search` | — | — | 7701 |
| `ez-php/event-store` | 3311 | — | — |
| **next free** | **3312** | **6384** | **7702** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

> The "Redis host port" column is likewise the **host**-published port. `ez-php/cache`, `ez-php/queue`, and `ez-php/rate-limiter` map it through a separate `REDIS_HOST_PORT` env var in `docker-compose.yml`, keeping `REDIS_PORT` fixed at `6379` for in-container connections (the app container always reaches Redis at `redis:6379` over the Compose network, regardless of the host mapping) — the root project and the `ez-php/` application template are the two exceptions, since both have no host/container split and use `REDIS_PORT` for both (the template's other in-container Redis settings — `CACHE_REDIS_PORT`, `QUEUE_REDIS_PORT`, `RATE_LIMITER_REDIS_PORT` — stay fixed at `6379` regardless, same as every other module).

> This table tracks only MySQL, Redis, and Meilisearch ports — the three services shared across multiple modules where a collision is otherwise easy to introduce. Mailpit is the one other service with published host ports: SMTP `1025` and web UI `8025`. `ez-php/mail` maps them through `MAILPIT_SMTP_HOST_PORT`/`MAILPIT_API_HOST_PORT` in `modules/mail/docker-compose.yml` (mirroring the `*_HOST_PORT` pattern above, documented in `modules/mail/.env.example`); the root project and the `ez-php/` template each run their own Mailpit on the same defaults (`MAIL_PORT`/`MAIL_WEB_PORT`), so **these three stacks cannot run at the same time** without overriding those variables. It isn't a table column because no module beyond those three runs Mailpit — but a new module adding its own single-use service's ports should likewise parameterize them and document the defaults in its own `.env.example` rather than adding a column here.

### 5 — Monorepo scripts

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
    ├── DatabaseDriver.php            — reads feature_flags and feature_flag_contexts tables via PDO
    ├── RedisDriver.php               — reads feature_flags / feature_flags:contexts:<name> hashes via ext-redis
    └── RolloutDriver.php             — decorator: percentage rollouts per flag (flags.rollouts), deterministic crc32 bucket

tests/
├── TestCase.php
├── FlagTest.php                      — facade tests
├── FlagManagerTest.php
└── Driver/
    ├── ArrayDriverTest.php
    ├── FileDriverTest.php            — uses sys_get_temp_dir() temp file
    ├── RolloutDriverTest.php         — determinism, 0/100 bounds, share ≈ percentage, monotonic growth, clamping, delegation
    ├── DatabaseDriverTest.php        — uses SQLite :memory:
    └── RedisDriverTest.php           — requires a live Redis instance; skipped when ext-redis is unavailable; uses Redis database 3
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

### RolloutDriver (`src/Driver/RolloutDriver.php`)

Decorator over any `FlagDriverInterface` taking `array<string, int>` rollouts (flag → 0–100, clamped). `enabledFor($name, $id)` is `crc32($name.'|'.$id) % 100 < percent` for flags with a rollout and delegated otherwise; `enabled()` is true only at 100 %. `FeatureFlagServiceProvider` wraps the selected driver when `flags.rollouts` is non-empty (non-int values are ignored).

### RedisDriver (`src/Driver/RedisDriver.php`)

Same schema shape as `DatabaseDriver`, mapped onto Redis hashes: `HSET feature_flags <name> 1|0` for global state, `HSET feature_flags:contexts:<name> <contextId> 1|0` for the optional per-context override. `enabledFor()` checks the per-flag context hash first (`HGET feature_flags:contexts:<name> <contextId>`) and falls back to `enabled()` when no override exists — same precedence as `DatabaseDriver`'s `feature_flag_contexts` → `feature_flags` fallback. Requires `ext-redis`; the constructor throws `RuntimeException` if the extension isn't loaded, mirroring `ez-php/rate-limiter`'s `RedisDriver`.

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
| `redis`      | `RedisDriver`     | Connects using `flags.redis.host`/`flags.redis.port`/`flags.redis.database` (defaults: `127.0.0.1`/`6379`/`0`) |
| `array`      | `ArrayDriver`     | Empty in-memory driver; useful for CI/test environments  |

`ConfigInterface` is resolved with `try/catch` — defaults apply when Config is not bound. `DatabaseInterface` is resolved directly (throws if missing when `database` driver is requested — fail-fast).

`boot()` calls `Flag::setManager()`. No route registration — this module has no HTTP endpoint.

---

## Design decisions and constraints

- **Depends only on `ez-php/contracts`, not `ez-php/framework`.** Feature flags have no HTTP endpoint and do not need the Router. Depending only on contracts keeps the module usable in any container-based context, not just full framework applications.
- **Unknown flags default to `false`, never throw.** This is the safe default: a missing flag does not crash the application. It is a programmer's responsibility to ensure flags are defined before shipping code that checks them.
- **DatabaseDriver catches all exceptions silently.** A missing `feature_flags` table (e.g., before migrations run) returns false rather than halting the request. This is intentional: feature flags are not critical path — a degraded flag state is preferable to a 500 error. The same applies to `feature_flag_contexts` — the table is optional and a missing one is silently treated as "no overrides".
- **FileDriver re-reads on every call (no caching).** OPcache handles the repeated `require` efficiently in production. Avoiding a cache layer keeps the driver simple and ensures flags are always fresh during development without a cache-clear step.
- **`enabledFor()` on ArrayDriver and FileDriver delegates to `enabled()`.** Neither driver has per-context storage — `enabledFor()` is a global lookup for them. Use `DatabaseDriver` or `RedisDriver` when per-context overrides are needed (e.g. gradual user rollouts).
- **`RedisDriver` mirrors `DatabaseDriver`'s schema, not `ez-php/cache`'s driver set.** Global flags in one hash, per-flag context overrides in a second hash keyed by flag name — the same two-table shape as `DatabaseDriver`, just on Redis hashes instead of SQL tables. This keeps the two drivers' semantics identical (same fallback order, same "missing storage → false, never throw") rather than inventing a Redis-specific flag model.
- **No flag management API (enable/disable via code).** The roadmap describes this module as "simple flag evaluation". Management belongs in a database migration, an admin interface, or a CLI tool — not in the flag module itself. Adding mutation methods would complicate the driver interface and force all drivers (including the read-only FileDriver) to implement writes they cannot support.

---
- **Percentage rollouts are a decorator, not a new storage format.** `Driver\RolloutDriver` wraps whichever driver is configured; the percentages come from `flags.rollouts` (flag name → 0–100), so the file/database/redis stores stay boolean and unchanged. The bucket is `crc32(name.'|'.contextId) % 100`: deterministic per user, monotonic when the percentage is raised, independent between flags. A configured rollout *replaces* the inner driver's answer for that flag; `enabled()` without a context is true only at 100 %. The per-context entry point already existed (`Flag::enabledFor()`), so no signature changed.

## Testing approach

Most tests need no external infrastructure; `RedisDriverTest` is the one exception:

- `ArrayDriverTest` — pure unit, no I/O
- `FileDriverTest` — creates a temp file in `sys_get_temp_dir()`, cleans up in `tearDown`
- `DatabaseDriverTest` — uses SQLite `:memory:` via real PDO; tests with and without the `feature_flags` and `feature_flag_contexts` tables; covers context-specific overrides and fallback behaviour
- `RedisDriverTest` — requires a live Redis instance (available via Docker); skipped automatically when `ext-redis` is not loaded; uses Redis database `3` to avoid colliding with `ez-php/queue` (database `1`) and `ez-php/rate-limiter` (database `2`); `flushDB()` in `setUp`/`tearDown`
- `FlagManagerTest` — uses `ArrayDriver`, pure unit
- `FlagTest` — tests facade setup, delegation, fail-fast behaviour, and `resetManager()`; `tearDown` always calls `Flag::resetManager()` to prevent state leaking
- `FeatureFlagServiceProviderTest::test_register_binds_redis_driver_when_configured` — also requires live Redis (self-skips); seeds a flag directly via `hSet()` so the assertion can only pass if `RedisDriver` (not the `file` fallback) actually served the read

---

## What does not belong in this module

- **Flag management (enable/disable via API)** — use direct DB access, a migration, or an admin panel
- **Targeting rules beyond a percentage** (segments, attribute rules, schedules, A/B experiments with analytics) — use a dedicated feature management service. Plain percentage rollouts *are* here (`RolloutDriver`)
- **Flag caching layer** — rely on OPcache (FileDriver) or application-level caching
- **HTTP endpoint for flag listing** — expose flags via your own controller if needed
- **Flag validation or type enforcement** — flags are booleans only; typed variants belong in a separate abstraction

