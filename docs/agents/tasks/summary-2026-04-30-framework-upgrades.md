## What changed

- Added framework-focused database, CRUD, template, and dynamic-controller docs.
- Added upgrade notes for the generic import set and skipped downstream-only areas.
- Imported generic core runtime, dispatcher, DB wrapper, exception, helper, and ParsePage improvements from the downstream framework copy.
- Imported generic admin/dynamic CRUD, attachment, S3, locks, activity-log, login-cookie, DevManage, and report-controller improvements.
- Added optional config defaults for offline logging, model subfolder autoloading, route ID regex override, and environment-specific remember-me cookies.
- Updated framework schema for activity log payload size, user name lengths, and attachment raw-data comment wording.
- Added a matching dated update SQL script.
- Added common ParsePage list hooks and plaintext/json/url show renderers.
- Hardened URL show renderers so unsafe schemes render as text instead of clickable links.
- Added Vue list-header `header_links` support with generic config plumbing.
- Added a generic PHPUnit harness, baseline tests, Composer dev scripts, and Windows runner.
- Moved local untracked public-root artifacts (`www/ppm.php`, `www/web.config.bak`) out of the public web root and added `.gitignore` coverage for those filenames plus machine-local `php/configs/*.lo.php`.
- Merged current `origin/master` into `upgrades202604`; resolved the deleted-old-path Composer conflict by carrying the master dependency upgrades into `php/composer.lock`.
- Preserved legacy `ilist()` and `doLogin()` subclass overrides while keeping definition-aware lookups and remember-me behavior through separate call boundaries.
- Corrected S3 presigning/cache-control order, recursive attachment-folder deletion, attachment write rollback, and readable-original guards before table/S3 storage transitions.
- Made lock expiry use the database clock, retried acquisition once after expired-row cleanup, and added DB-backed regression coverage.
- Corrected exact elapsed-minute calculation, configured cookie suffix use, autocomplete separator parsing, model-subfolder discovery, download-finalization error reporting, log caller paths, and oversized activity payload JSON.

## Scope reviewed

- `README.md`, `AGENTS.md`, and local instructions check.
- Existing framework schema in `db/fwdatabase.sql`.
- Existing common list/form/Vue templates under `template/common/`.
- Source docs/templates/test harness from the read-only downstream repository, adapted to framework paths and generic concepts.
- Existing Composer metadata in `php/composer.json`.
- Matching downstream framework files.
- PR #61 metadata, reviews/checks, merge state, and every content-changing path after excluding pure file moves.
- Public method signature changes across moved framework/model files, with focused downstream-subclass compatibility checks.

## Commands used / verification

- `Get-Content README.md`, local instructions check, and targeted source-app reads for docs/templates/tests.
- `Get-ChildItem -Path php/tests -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }` - passed for all test harness PHP files.
- `Get-ChildItem -Path php -Recurse -Filter *.php | Where-Object { $_.FullName -notmatch '\\vendor\\' } | ForEach-Object { php -l $_.FullName }` - passed for all non-vendor project PHP after nullable signature cleanup in sample models.
- `php -l php/tests/run-local-phpunit.php` - passed after runner error handling was added.
- `php -l bin/libman.php` - passed; no PHP changes under `bin/` except the batch runner.
- `Push-Location php; composer validate --no-check-publish; Pop-Location` - composer.json valid, with expected warnings for dubious Git ownership, missing license, and out-of-date lock/dev dependency not present in lock.
- `Push-Location php; composer validate --no-check-publish --no-check-lock; Pop-Location` - composer.json valid, same environment/lock warnings from this Composer version.
- `composer update phpunit/phpunit --with-dependencies` from `php` with approved network access - updated `composer.lock`; Composer reported existing advisories in dependencies.
- `composer install --no-interaction` from `php` with approved access - installed dev dependencies for verification. A sandboxed rerun failed on Composer cache/vendor permissions, then succeeded with elevated filesystem/network access.
- `php php/tests/run-local-phpunit.php --testsuite Unit` - passed with 3 tests / 8 assertions after the review URL-safety test was added.
- `php php/tests/run-local-phpunit.php` - passed with 5 tests / 12 assertions while dev dependencies were installed.
- `git restore -- php/vendor` - reverted Composer-generated tracked vendor metadata after the test run; rerun `composer install` before rerunning PHPUnit in a fresh checkout.
- Generic-product-term scan on the new framework docs and test harness - no matches.
- `Get-Content -Raw php/composer.json | ConvertFrom-Json` - JSON OK.
- `[xml](Get-Content -Raw phpunit.xml)` - XML OK.
- `git diff --check -- <tracked changed files>` - passed; Git reported only CRLF normalization warnings.
- Core/runtime worker syntax and smoke checks passed for DB, dispatcher, `fw.php`, exceptions, FormUtils, DateUtils, Utils, and ParsePage.
- CRUD/storage worker syntax checks passed for owned controller/model/framework PHP files.
- Independent review using `docs/agents/code_reviewer.md` found report parse-data, unsafe URL renderer, locks schema/runtime drift, Sentry log-level gating, and local `www/ppm.php` risks; runtime/schema/template fixes were applied.
- Follow-up review found the ignored local DB-admin script was still executable under `www/`; it was moved to `docs/agents/artifacts/ppm.php`.
- Final review found another local backup file under `www/` plus an untracked machine-local host config; the backup was moved to `docs/agents/artifacts/web.config.bak`, and `php/configs/*.lo.php` is ignored.
- Final follow-up review reported no issues worth another fix loop remain.
- Follow-up cleanup removed downstream project names from repo-visible files and replaced Markdown machine paths with repo-relative wording.
- Follow-up cleanup simplified `Locks` to the fixed framework table shape and changed the update script to drop/recreate `locks`.
- Focused review of the simplified lock change reported no issues worth another fix loop.
- `git fetch --prune origin master upgrades202604` and `git merge --no-ff origin/master` - fetched four dependency commits and produced merge commit `a9d76b8`; only `www/php/composer.lock` conflicted because that old path was removed by the branch.
- Composer updates moved master versions of Guzzle 7.15.2, PSR-7 2.13.0, promises 2.5.1, and phpseclib 3.0.55 into `php/composer.lock`.
- `composer validate --no-check-publish` and `composer install --dry-run --no-interaction` - lock metadata is valid/installable; only the existing missing-license warning remains.
- `composer audit --locked --format=json` - reports one low-severity advisory in transitive `firebase/php-jwt` v6.11.0, also present on `master` through Google API Client.
- Installed locked dev dependencies under ignored `docs/agents/artifacts/pr61-vendor` so tracked `php/vendor` files stayed untouched.
- Focused PHPUnit runs covered unit compatibility/helpers and stale-lock reacquisition; the complete suite passed after the fixes.
- Parsed all template `config.json` files and syntax-checked the only content-modified JavaScript file (`template/common/vue/store.js`).
- Inspected the local `locks` schema and ran the lock test inside the transaction-backed DB harness; the test exposed and then verified the MySQL/PHP timezone fix.
- Final non-vendor syntax pass checked 97 PHP files successfully.
- Final PHPUnit run passed 14 tests / 30 assertions on PHP 8.5.3, including unit, DB-backed model, and controller suites.
- A hidden PHP built-in server against `www/` returned HTTP 200 for `GET /` with the local host config; its logs contained no warnings/errors.

## Decisions - why

- Kept the import generic and avoided downstream fixtures, domains, brands, routes, and service integrations.
- Kept REST string IDs backward-compatible by making the wider downstream matcher opt-in through `ROUTE_ID_REGEX`.
- Kept attachment file storage, remember-me cookie naming, and cache backend defaults unchanged unless explicitly configured.
- Kept existing report parse-data contract (`f` and `rep`) to avoid breaking report templates and CSV rendering.
- Kept `Locks` simple against the fixed framework `locks` table shape; the update script drops/recreates `locks` instead of guessing legacy columns.
- Kept public subclass contracts stable by adding `ilistByDef()` rather than widening `ilist()`, and by managing remember-me cookies outside `Users::doLogin()`.
- Kept attachment records/local files unchanged when storage migration cannot prove the original is readable.
- Kept S3 response overrides inside the command before SigV4 signing; query mutation after signing invalidates a presigned URL.
- Used root `phpunit.xml` with `php/tests/bootstrap.php` so tests can be run from repo root or via Composer scripts in `php`.
- Made DB-backed tests skip when the host config or database is unavailable so a fresh checkout can still run unit tests after dev dependencies are installed.
- Added `header_links` only to common Vue list header and virtual common index plumbing.

## Pitfalls - fixes

- Source test base classes contained many downstream helper methods; only generic setup and user fixture helpers were retained.
- The framework CLI bootstrap does not load controller base classes, so the PHPUnit bootstrap requires them explicitly.
- Parallel implementation produced duplicate task summaries; consolidated the useful notes here and removed the duplicate files created during this task.
- Review caught that the imported URL renderer would make `javascript:` values clickable; added PHP/Vue safe URL normalization and unit coverage.
- Review caught that Sentry still received skipped log levels; gated Sentry with the same `LOG_LEVEL` decision used for file logging.
- The first lock import tolerated legacy column names, but the framework owns a fixed `locks` schema; removed field guessing and made the migration recreate the table.
- The first dynamic lookup implementation widened `ilist()`, which would make downstream one-argument overrides fail class loading; introduced `ilistByDef()` and added a legacy-override regression test.
- Remember-me support widened `doLogin()`, which had the same downstream override risk; restored its original signature and kept cookie actions in `LoginController`.
- S3 cache parameters were appended after signing, invalidating SigV4 URLs, and attachment deletion passed an object key instead of a folder key; both were corrected.
- Attachment string/storage paths could report success or delete local data after failed writes/reads; writes now roll back the new row, and moves require a readable original.
- PHP-side lock expiry interpreted MySQL `DATETIME` in PHP's timezone; the DB-backed test reproduced the mismatch and expiry is now evaluated entirely by MySQL.

## Risks / follow-ups

- `php/composer.lock` was updated for the new PHPUnit dev dependency, but tracked vendor metadata was not kept in the diff.
- The dated migration itself was not applied in this pass; the configured local DB already had the target `locks` schema, which was inspected and exercised transactionally.
- PHPUnit tests require dev dependencies; this review installed them only in an ignored artifact directory so tracked vendor files remain unchanged.
- Runtime DB reconnect, cURL batch loading, live S3 operations, and deeper authenticated browser/admin flows still need environment-backed manual QA.
- Composer audit retains one low-severity transitive `firebase/php-jwt` advisory inherited from `master`; remediation depends on the Google API Client dependency range.
- GitHub currently has no CI checks for this PR, so merge confidence comes from the local verification recorded here.
- The pre-existing local public DB admin script and `web.config` backup were moved to ignored `docs/agents/artifacts/`; do not move them back under `www/` before serving this checkout.

## Heuristics

- For downstream imports, copy the capability shape, not product fixtures or routes.
- For common templates, prefer optional ParsePage hooks that render empty by default over screen-specific branches.
- When database timestamps are stored without timezone information, compare expiry with the database clock instead of parsing them in PHP.

## Testing instructions

- User-facing/internal: internal framework import with schema, template, docs, and test harness changes.
- Type: feature/refactor baseline.
- Affected flows: DB access, routing, request bootstrap/logging, exceptions, dynamic/admin CRUD, attachments/S3, locks, login cookies, DevManage, reports, list/show templates, Vue list headers, and local PHPUnit test execution.
- Run `php -l` on changed PHP files.
- Run `composer validate --no-check-publish` from `php`.
- Run `composer audit --locked` and review the inherited JWT advisory when Google API Client can accept a fixed major version.
- After dev dependencies are installed, run `bin\phpunit-local.bat <local-host>` or set `OSAFW_TEST_HOST` and run `composer test` from `php`.
