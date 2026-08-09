## What changed

- Moved tracked `www/php` to `php` with `git mv`.
- Moved tracked `www/template` to `template` with `git mv`.
- Moved untracked/ignored `www/upload` contents to `upload` and added `upload/.gitkeep` so fresh checkouts keep the non-public storage root.
- Updated web bootstrap and CLI/test scripts for the new `php/` location.
- Changed default config roots so `SITE_ROOT` is the public `www/`, `SITE_ROOT_OFFLINE` is the repository root, `PHP_ROOT` is `php/`, `SITE_TEMPLATES` is `template/`, and `PUBLIC_UPLOAD_DIR` is `upload/`.
- Changed local attachment direct URLs to route through `/Att/<id>` instead of exposing files from `upload/`.
- Removed public rewrite exceptions for `upload/` and stale template-deny rules because those directories are no longer under `www/`.
- Moved the DB admin standalone tool from `www/` to `php/tools/` and exposed it only through the existing site-admin `/Admin/DB` route.
- Removed the DB admin fallback password from the tool config.
- Redirected DB admin server-side dumps to ignored non-public `upload/dbdumps/` instead of writing beside tool source.
- Updated README, framework docs, agent guidance, review guidance, and upgrade notes for the new layout.

## Scope reviewed

- `README.md`, `AGENTS.md`, `.github/copilot-instructions.md`, and `docs/agents/local/instructions.md` check.
- Public web root files under `www/`.
- Config/bootstrap files under `php/configs/`, `www/index.php`, `bin/`, and `phpunit.xml`.
- Attachment URL flow in `php/models/Att.php` and upload helper defaults.
- Admin DB tool routing through `php/controllers/AdminDB.php`.
- DevManage/report code paths that referenced PHP code under the old public root.
- Existing docs that referenced `www/php`, `www/template`, or `www/upload`.

## Commands used / verification

- `git status --short` to inspect the dirty worktree before and after moves.
- `Get-ChildItem -Force www` to verify public-root contents.
- `git mv www/php php` and `git mv www/template template`; both required elevated filesystem access after Git index-lock permission errors.
- `Move-Item www/upload upload` for ignored/untracked upload contents because `git mv` had no tracked source files to move.
- `rg` scans for stale `www/php`, `www/template`, `www/upload`, machine-local Markdown paths, and downstream project terms.
- `Compare-Object (Get-Content AGENTS.md) (Get-Content .github/copilot-instructions.md)` confirmed the instruction mirrors match.
- `php -r "require 'php/fw/fw.php'; ..."` confirmed config roots resolve to public `www/`, `php/`, `template/`, and `upload/`.
- `Get-ChildItem -Path php,www,bin -Recurse -Filter *.php | Where-Object { $_.FullName -notmatch '\\vendor\\' } | ForEach-Object { php -l $_.FullName }` - passed for 98 non-vendor PHP files.
- `php -l php/tools/phpminiconfig.php; php -l php/controllers/AdminDB.php; php -l php/tools/phpminiadmin.php` - passed.
- `composer validate --no-check-publish` from `php` - valid; Composer reports existing dubious-ownership messages and a no-license warning.
- `php php/tests/run-local-phpunit.php --testsuite Unit` - blocked because the current installed/tracked Composer autoload metadata does not include PHPUnit dev classes; run `composer install` from `php` before rerunning.
- `git diff --check` - passed; Git reported only CRLF normalization warnings.
- Review loop with `docs/agents/code_reviewer.md` found the public DB admin tool and DB dump-path issues; both were fixed.
- Final follow-up review reported no issues worth another loop.

## Decisions - why

- Kept `www/` as only the web server document root. Code, templates, Composer vendor files, configs, and upload storage now live outside it.
- Kept `SITE_ROOT` meaning as public web root and added/used `PHP_ROOT` for framework code paths instead of overloading `SITE_ROOT`.
- Kept `PUBLIC_UPLOAD_URL` as a compatibility setting for projects that intentionally expose a mapped upload base, but default attachment links now use `/Att/<id>`.
- Moved the DB admin tool instead of only blocking it in rewrite config so it cannot be directly served by Apache, IIS, or another web server that honors existing-file bypasses.

## Pitfalls - fixes

- `www/upload` was not tracked, so `git mv` could not move it. Moved the directory contents directly and added `upload/.gitkeep`.
- DevManage and report helpers still constructed paths through `SITE_ROOT`; updated those code paths to `PHP_ROOT`.
- PHPUnit bootstrap paths originally walked one directory too far after the move; adjusted them to the repository root.
- Rewrite rules still treated `upload/` as public; removed that exception from Apache and IIS config.
- Review caught that DB admin tooling still lived in `www/`; moved it to `php/tools/`, updated `/Admin/DB`, fixed the config include path, and removed the fallback password.
- Follow-up review caught that the DB admin dump base moved next to tracked source; pointed it at `upload/dbdumps/`.
- Manual code-path review found the moved DB admin config loaded framework DB settings but did not mark the standalone tool session as logged in; set its local `is_logged` flag after the framework site-admin gate.

## Risks / follow-ups

- `/Admin/DB` still exposes a powerful DB admin tool to site administrators. Consider replacing it with narrower framework-native DB maintenance screens if broad SQL access is not desired.
- DB admin server-side dumps are non-public and ignored, but can contain sensitive data; clean `upload/dbdumps/` after use.
- `PUBLIC_UPLOAD_URL` remains available for downstream code that uses `FwModel::getUploadUrl()` directly. Default attachment flows no longer depend on it.
- Browser/admin and attachment download checks still require local web server and DB configuration.

## Heuristics

- Public-root minimization should preserve `www/assets/`, `www/index.php`, server rewrite config, and simple public metadata, while moving executable framework internals out of `www/`.
- When a config key name describes the public root, add a separate explicit key for non-public code paths rather than changing all call sites to infer repository structure.

## Testing instructions

- User-facing/internal: internal framework layout and public-surface hardening.
- Type: refactor/security hardening.
- Affected flows: bootstrap, routing, template loading, dynamic controller config loading, DevManage helpers, Composer/PHPUnit execution, attachment file URLs, and web-server rewrites.
- Run non-vendor PHP syntax checks under `php/`, `www/`, and `bin/`.
- Run `Push-Location php; composer validate --no-check-publish; Pop-Location`.
- Run `php php/tests/run-local-phpunit.php --testsuite Unit` when dev dependencies are installed.
- Manually verify a local web request, a template-rendered page, `/Dev/Manage`, and `/Att/<id>` attachment delivery when local DB/config are available.
