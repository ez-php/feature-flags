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

## Configuration

Add a `config/flags.php` to your application:

```php
return [
    'driver' => getenv('FLAGS_DRIVER') ?: 'file',  // file | database | array
    'file'   => getenv('FLAGS_FILE') ?: 'flags.php',
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

---

## Behaviour

- **Unknown flags default to `false`** — `Flag::enabled('unknown')` never throws.
- **Drivers never throw** — all query/file failures are caught internally and result in `false`.
- The `Flag` facade throws `RuntimeException` when called before `FeatureFlagServiceProvider` has been booted — this is a programmer error, not a runtime failure.

---

## Testing

```bash
docker compose exec app composer test
```

All tests run without external infrastructure (SQLite `:memory:` for the database driver, temp files for the file driver).

---

## License

MIT
