# Framework Knowledge

Durable framework facts discovered during agent work belong here. Keep entries concise and update this file only when the
fact is broadly useful for future osafw-php development.

## Baseline

- `osafw-php` is the reusable PHP framework and sample app baseline, not a product-specific backend.
- The framework target is PHP 8.3+ because the current core uses typed class constants.
- Public web files live under `www/`; PHP framework code lives under `php/`; core framework classes live under
  `php/fw/`.
- ParsePage templates live under `template/`; dynamic controller configuration lives in route-level `config.json`
  files under that template tree.
- Default attachment file storage lives under `upload/` outside the public web root and is served through framework routes.
- Standalone developer/admin tools that should not be directly served live under `php/tools/` and must be exposed only
  through authenticated framework routes when needed.
- Database bootstrap and migration SQL lives under `db/`.
