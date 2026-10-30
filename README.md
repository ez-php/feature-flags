# ez-php/feature-flags

Simple feature flag evaluation for the ez-php framework. No external service required — flags are stored in a PHP file, a database table, or a plain array.

---

## Installation

```bash
composer require ez-php/feature-flags
```

Register the provider in `provider/modules.php`:

```php
\EzPhp\FeatureFlags\FeatureFlagServiceProvider::class,
```

---

## Usage

```php
use EzPhp\FeatureFlags\Flag;

if (Flag::enabled('new-checkout')) {
    // show new checkout flow
}

if (Flag::disabled('dark-mode')) {
    // show light theme
}

// Context-aware evaluation (e.g. per-user gradual rollouts)
if (Flag::enabledFor('beta-search', $user->id)) {
    // show beta search to this user
}

if (Flag::disabledFor('new-ui', $user->id)) {
    // show legacy UI for this user
}

$all = Flag::all(); // ['new-checkout' => true, 'dark-mode' => false]
```

---

## Route middleware

`FeatureMiddleware` answers 404 unless every named flag is enabled, so a disabled feature looks like it
does not exist. Register the alias yourself, as with other parameterized middleware:

```php
use EzPhp\FeatureFlags\FeatureMiddleware;

$app->middlewareAlias('feature', FeatureMiddleware::class);

$router->get('/checkout', $handler)->middleware('feature:new-checkout');
$router->get('/beta', $handler)->middleware('feature:beta,new-ui'); // both must be on
```

It reads the global flag state. A per-user rollout (`Flag::enabledFor()`) needs the request's user, so
check that in your own middleware or controller.

---

## Configuration

Add a `config/flags.php` to your application:

```php
return [
    'driver' => getenv('FLAGS_DRIVER') ?: 'file',  // file | database | redis | array
    'file'   => getenv('FLAGS_FILE') ?: 'flags.php',
    'redis'  => [
        'host'     => getenv('FLAGS_REDIS_HOST') ?: '127.0.0.1',
        'port'     => (int) (getenv('FLAGS_REDIS_PORT') ?: 6379),
        'database' => (int) (getenv('FLAGS_REDIS_DB') ?: 0),
    ],
];
```

> `file` must point **outside** `config/`. The framework loads every `config/*.php`
> as a config namespace, so a definitions file at `config/flags.php` would be this
> config file — the driver would then report `driver` and `file` as enabled flags
> and none of your real ones.

### Drivers

| Driver     | Config key value | Description                                      |
|------------|-----------------|--------------------------------------------------|
| `file`     | `file`          | Reads a PHP file returning `array<string, bool>` |
| `database` | `database`      | Reads a `feature_flags` table via PDO            |
| `redis`    | `redis`         | Reads `feature_flags` / `feature_flags:contexts:<name>` hashes via ext-redis |
| `array`    | `array`         | Empty in-memory driver (CI / test environments)  |

### File driver

Create `flags.php` in your application root:

```php
<?php

return [
    'new-checkout' => true,
    'dark-mode'    => false,
    'beta-search'  => false,
];
```

### Database driver

Create the `feature_flags` table (example migration):

```php
$pdo->exec('
    CREATE TABLE feature_flags (
        name    VARCHAR(255) NOT NULL PRIMARY KEY,
        enabled TINYINT(1)   NOT NULL DEFAULT 0
    )
');
```

For per-context overrides (e.g. per-user beta rollouts), also create `feature_flag_contexts`:

```php
$pdo->exec('
    CREATE TABLE feature_flag_contexts (
        name       VARCHAR(255) NOT NULL,
        context_id VARCHAR(255) NOT NULL,
        enabled    TINYINT(1)   NOT NULL DEFAULT 0,
        PRIMARY KEY (name, context_id)
    )
');
```

When `enabledFor('flag', $userId)` is called, the driver checks `feature_flag_contexts` first; if no matching row exists, it falls back to the global `feature_flags` value. A missing `feature_flag_contexts` table is silently treated as "no overrides" — no migration is required for basic use.

Insert flags directly via SQL or through your own admin interface:

```sql
INSERT INTO feature_flags (name, enabled) VALUES ('new-checkout', 1);
INSERT INTO feature_flag_contexts (name, context_id, enabled) VALUES ('beta-search', '42', 1);
```

### Redis driver

Same two-hash shape as the database driver's two tables:

```php
$redis->hSet('feature_flags', 'new-checkout', '1');
$redis->hSet('feature_flags:contexts:beta-search', '42', '1'); // per-context override
```

`enabledFor('beta-search', 42)` checks the context hash first, then falls back to the
global `feature_flags` hash when no override exists — identical precedence to the
database driver.

---

## Percentage rollouts

Release a flag to a stable slice of your users by adding `rollouts` to `config/flags.php`:

```php
return [
    'driver' => 'file',
    'file'   => 'flags.php',
    'rollouts' => [
        'new-checkout' => 25,   // 25 % of contexts
    ],
];
```

```php
Flag::enabledFor('new-checkout', $user->id);   // same user → same answer, every time
```

The bucket is `crc32(flag . '|' . id) % 100`, so a user stays in when you raise the percentage (10 → 25 only adds users) and different flags pick different slices. A rollout **replaces** the stored value for that flag; `Flag::enabled('new-checkout')` (no context) is only `true` at 100. Flags without a rollout behave as before.

---

## Caching flag answers

The database and Redis drivers run one query per check. With `ez-php/cache` installed (and its
provider registered), set `flags.cache_ttl` (`FLAGS_CACHE_TTL`) to cache answers for that many
seconds — one `all()` snapshot serves every `Flag::enabled()` check, and `Flag::enabledFor()`
answers are cached per flag and context:

```php
// config/flags.php
'cache_ttl' => (int) (getenv('FLAGS_CACHE_TTL') ?: 0), // e.g. 60
```

Changes in the store become visible after at most the TTL. To publish one at once, call
`forget()` on a `Driver\CachedDriver` you hold (or wrap the driver yourself:
`new CachedDriver($driver, $cache, ttl: 60)`). Rollouts are still evaluated per context.

## Behaviour

- **Unknown flags default to `false`** — `Flag::enabled('unknown')` never throws.
- **Drivers never throw** — all query/file failures are caught internally and result in `false`.
- The `Flag` facade throws `RuntimeException` when called before `FeatureFlagServiceProvider` has been booted — this is a programmer error, not a runtime failure.

---

## Testing

```bash
docker compose exec app composer test
```

Most tests run without external infrastructure (SQLite `:memory:` for the database driver, temp files for the file driver). `RedisDriverTest` requires a live Redis instance and is skipped automatically when `ext-redis` isn't loaded.

---

## License

MIT
