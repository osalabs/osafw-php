## What changed

- Merged PR 61 into `master` as `418b8b5` and fast-forwarded the local checkout.
- Updated the Composer lockfile to the newest releases allowed by the framework's compatible version lines. Direct
  versions are Google API Client 2.19.4, PHPMailer 6.12.0, PHPUnit 12.5.33, and firebase/php-jwt 7.1.0.
- Declared `firebase/php-jwt:^7.1` directly because `FwApiController` calls it instead of treating it as an incidental
  Google API Client dependency.
- Updated 14 frontend package pins in `php/libman.json`, including Bootstrap 5.3.8, Bootstrap Icons 1.13.1, Vue 3.5.41,
  Vue DevTools 7.7.10, Pinia 2.3.1, and compatible Markdown/UI utilities.
- Updated Birpc to 2.9.0 as required by Vue DevTools 7.7.10 and preserved Mande's public import-map filenames after its
  package consolidated the development and production modules into `dist/index.mjs`.
- Fixed the Composer test runner's repository-root calculation; `composer test` now finds the root `phpunit.xml`.
- Documented Composer/LibMan installation and the firebase/php-jwt 7 requirement that HS256 secrets be at least 32 bytes.
- Added a regression test for the JWT encode/decode contract used by `FwApiController`.

## Scope reviewed

- `php/composer.json` and `php/composer.lock`.
- `php/libman.json`, `bin/libman.php`, and the LibMan implementation.
- `FwApiController` JWT usage, default API configuration, dependency setup documentation, and the PHPUnit runner.
- Installed LibMan paths, JavaScript syntax, ESM import-map coverage, and public asset loading.

## Commands used / verification

- `gh pr merge 61 --repo osalabs/osafw-php --merge` - merged PR 61 as `418b8b5`.
- `git fetch --prune origin master`, `git switch master`, and `git merge --ff-only origin/master` - synchronized local `master`.
- `composer update --with-all-dependencies --no-interaction` - updated 18 packages and removed three obsolete transitive
  packages; the previous firebase/php-jwt advisory is resolved.
- `composer update firebase/php-jwt --with-dependencies --no-interaction` - refreshed the lockfile after making JWT a
  direct dependency.
- `composer validate --no-check-publish` - valid; only the pre-existing missing-license warning remains.
- `composer install --dry-run --no-interaction` - lockfile is reproducibly installable.
- `composer audit --locked --format=json` - no advisories or abandoned packages.
- `composer prohibits php 8.3.0 --locked` - no locked package prevents the framework's PHP 8.3 target.
- `composer test` - passed with PHPUnit 12.5.33: 15 tests and 32 assertions.
- Non-vendor PHP syntax loop over `php/` and `bin/` - 98 files passed.
- npm registry version-history checks - resolved stable caret-compatible frontend versions and identified intentional
  major-version holds.
- `php bin/libman.php` - final run installed all 31 libraries with no warnings.
- LibMan manifest/path verification - all library destinations and declared files exist.
- Node syntax verification - all 35 installed JavaScript/ESM entry files passed.
- Static ESM import scan - all bare imports used by Vue, Pinia, and DevTools are represented in the framework import map.
- Hidden PHP built-in server smoke - `/` and six representative upgraded assets returned HTTP 200 with no PHP warnings.
- `git diff --check` - passed; Git only reported expected LF-to-CRLF worktree notices.
- Review-fix loop using `docs/agents/code_reviewer.md` - fixed the JWT direct-dependency/config migration gap; final
  review found no remaining issues worth another loop.

## Decisions - why

- Kept PHPMailer on 6.x and PHPUnit on 12.x; their latest releases are new majors requiring separate migration review.
- Kept frontend packages on compatible major lines (or the current minor for 0.x packages). Major migrations such as
  jQuery 4, Chart.js 4, Markdown-It 15, Vue DevTools 8, and Pinia 4 remain separate work.
- Accepted firebase/php-jwt 7 because it resolves the inherited advisory and preserves the framework's encode/decode API.
  Made its new 32-byte HS256 key minimum explicit in configuration and documentation.
- Updated Birpc across a major boundary only because it is an import-map dependency internal to Vue DevTools 7.7.10,
  which requires Birpc `^2.3.0`; no framework code imports Birpc directly.
- Kept `bootstrap-simple-autocomplete` at 1.1.0. Although npm publishes 1.2.0, both 1.2.0 browser builds contain an extra
  closing brace and fail JavaScript parsing, so that release is not compatible in practice.
- Did not commit ignored downloaded browser assets or generated Composer vendor metadata. Deployments install from the
  committed manifests with `composer install` and `php bin/libman.php`.

## Pitfalls - fixes

- Windows command processing stripped the caret from the first npm range probe and made every package appear current.
  Resolved versions from full npm version histories instead and applied caret rules explicitly.
- LibMan defaults to continue-on-error. Mande 2.0.10 removed `dist/mande.mjs` and `dist/mande.prod.mjs`; mapped its new
  `dist/index.mjs` to both stable public filenames and reran the complete install without warnings.
- JavaScript syntax verification caught the malformed upstream `bootstrap-simple-autocomplete` 1.2.0 build; retained
  1.1.0 rather than shipping a broken minor update.
- `composer test` initially looked for `phpunit.xml` in `C:\DOCS_PROJ\github`; corrected the runner's root calculation
  from two parent levels to one.
- Composer selected firebase/php-jwt 7 transitively. Review confirmed the framework uses JWT directly, so it is now a
  direct dependency with its secret-length migration documented and tested.

## Risks / follow-ups

- Existing downstream JWT configurations with secrets shorter than 32 bytes must rotate to a cryptographically random
  32-byte-or-longer secret before deploying this lockfile; rotating the secret invalidates existing JWTs.
- Browser verification covered syntax, imports, static HTTP delivery, and the public home page. Authenticated/admin Vue
  interactions should still receive normal application QA in a downstream app.
- Revisit the intentionally held major versions separately with migration-specific testing.
- Recheck `bootstrap-simple-autocomplete` after an upstream release fixes the malformed 1.2.0 distribution.

## Heuristics

- Exact frontend version pins require both registry/version review and a LibMan install; reinstalling alone does not update the manifest.
- A successful LibMan process exit is insufficient because its default mode logs individual package errors and continues;
  scan warnings and verify every declared output file.

## Testing instructions

- User-facing maintenance upgrade affecting Composer packages, API JWT authentication, browser assets, and the developer
  test command. It also fixes the Composer test runner path.
- From `php/`, run `composer install`, `composer validate --no-check-publish`, `composer audit --locked`, and
  `composer test`.
- From the repository root, run `php bin/libman.php`; confirm there are no `LibMan WARNING` lines.
- Configure `JWT_SECRET` with at least 32 random bytes before testing JWT generation/authentication.
- Manually exercise login/API JWT flows and representative Bootstrap/Vue admin screens in a configured downstream app.
