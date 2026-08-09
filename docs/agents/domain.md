# Framework Knowledge

Durable framework facts discovered during agent work belong here. Keep entries concise and update this file only when the
fact is broadly useful for future osafw-php development.

## Baseline

- `osafw-php` is the reusable PHP framework and sample app baseline, not a product-specific backend.
- New applications commonly start by copying the full repository and then evolve independently; they are not expected to
  consume or upgrade from `osafw-php` as a versioned package.
- Compatibility is preferred when inexpensive, but practical deliberate breaks are allowed when in-repository consumers,
  tests/examples, and a useful migration note are updated. DB and ParsePage merit extra care because applications may
  selectively backport those components.
- The framework target is PHP 8.3+ because the current core uses typed class constants.
- Public web files live under `www/`; PHP framework code lives under `php/`; core framework classes live under
  `php/fw/`.
- ParsePage templates live under `template/`; dynamic controller configuration lives in route-level `config.json`
  files under that template tree.
- Default attachment file storage lives under `upload/` outside the public web root and is served through framework routes.
- Standalone developer/admin tools that should not be directly served live under `php/tools/` and must be exposed only
  through authenticated framework routes when needed.
- Database bootstrap and migration SQL lives under `db/`.
- `FwModel::ilist(?array $statuses = null)` is a downstream override contract; dynamic lookup definitions flow through
  `ilistByDef()` so existing model subclasses remain loadable.
- Configured model subfolders are resolved by `fw::getAutoloadModelDirs()` for both runtime autoloading and DevManage.
- Lock expiry is evaluated by MySQL using `NOW()` because framework lock timestamps are timezone-naive `DATETIME` values.
- `FwApiController` uses the direct `firebase/php-jwt:^7.1` dependency for HS256 tokens; `JWT_SECRET` must contain at
  least 32 bytes.
