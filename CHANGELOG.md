# Changelog

All notable changes to `ez-php/feature-flags` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.1.0] — 2026-03-28

### Added
- `FlagDriverInterface` — contract for flag drivers: `enabled(name)` and `all()`
- `FileDriver` — reads flags from a PHP file returning `array<string, bool>`; unknown flags default to `false`; file read failures are caught and return `false`
- `DatabaseDriver` — reads flags from a `feature_flags` table via PDO; supports MySQL and SQLite; unknown flags and query failures default to `false`
- `ArrayDriver` — in-memory driver for testing and CI; starts empty, all flags `false`
- `FlagEvaluator` — wraps a `FlagDriverInterface`; `enabled()`, `disabled()`, `all()`
- `Flag` — static façade: `Flag::enabled()`, `Flag::disabled()`, `Flag::all()`; throws `RuntimeException` if called before `FeatureFlagServiceProvider` has booted
- `FeatureFlagServiceProvider` — registers `FlagEvaluator` and `Flag` façade based on `config/flags.php`; supports `file`, `database`, and `array` drivers
