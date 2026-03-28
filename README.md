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

$all = Flag::all(); // ['new-checkout' => true, 'dark-mode' => false]
```

---

## Configuration

Add a `config/flags.php` to your application:

```php
return [
    'flags' => [
        'driver' => env('FLAGS_DRIVER', 'file'),  // file | database | array
        'file'   => base_path('config/flags.php'),
    ],
];
```

### Drivers

| Driver     | Config key value | Description                                      |
|------------|-----------------|--------------------------------------------------|
| `file`     | `file`          | Reads a PHP file returning `array<string, bool>` |
| `database` | `database`      | Reads a `feature_flags` table via PDO            |
| `array`    | `array`         | Empty in-memory driver (CI / test environments)  |

### File driver

Create `config/flags.php` in your application:

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

Insert flags directly via SQL or through your own admin interface:

```sql
INSERT INTO feature_flags (name, enabled) VALUES ('new-checkout', 1);
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
