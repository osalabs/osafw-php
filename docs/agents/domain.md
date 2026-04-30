# Framework Knowledge

Durable framework facts discovered during agent work belong here. Keep entries concise and update this file only when the
fact is broadly useful for future osafw-php development.

## Baseline

- `osafw-php` is the reusable PHP framework and sample app baseline, not a product-specific backend.
- The framework target is PHP 8.3+ because the current core uses typed class constants.
- Public web files live under `www/`; PHP framework code lives under `www/php/`; core framework classes live under
  `www/php/fw/`.
- ParsePage templates live under `www/template/`; dynamic controller configuration lives in route-level `config.json`
  files under that template tree.
- Database bootstrap and migration SQL lives under `db/`.
