# Verification and Resource Safety

Choose checks that can falsify the changed behavior. Verification depth follows risk; commands are examples, not
permission to mutate external state or an unrelated local environment.

## Verification matrix

| Changed surface | Minimum useful evidence |
|---|---|
| Documentation/agent prose | Read final files, check local links/paths, search for stale or duplicate owners, inspect diff |
| Changed PHP | `php -l` on every changed PHP file |
| Framework-wide PHP | Syntax-check non-vendor PHP; run focused PHPUnit suite(s) |
| Composer metadata | `composer validate --no-check-publish`; run affected tests |
| Controller/model behavior | Focused test or reproducible route/model check, including negative/empty cases |
| Template/admin/dynamic config | Check controller/config/template alignment; exercise route when safe setup exists |
| API/auth/public boundary | Positive and negative auth, OPTIONS/CORS/header, error, and JSON-shape checks |
| Schema/migration/DB wrapper | Fresh-install plus forward-update convergence on a disposable DB; focused DB behavior tests |
| Performance/repeated path | Query/call count plus output identity/order at representative cardinality |
| Agent workflow | Link/routing/authority scenarios and agent-workflow review overlay; run `docs/agents/evals/instruction-benchmark.md` before claiming broader task-success or efficiency improvement |

Canonical commands include:

```powershell
php -l php/fw/FwModel.php
php php/tests/run-local-phpunit.php --testsuite Unit
php php/tests/run-local-phpunit.php --testsuite Models
Push-Location php; composer validate --no-check-publish; Pop-Location
```

For broad PHP syntax risk:

```powershell
Get-ChildItem -Path php -Recurse -Filter *.php |
    Where-Object { $_.FullName -notmatch '\\vendor\\' } |
    ForEach-Object { php -l $_.FullName }
```

Use focused checks before broad suites. Do not run a database, browser, network, or dependency command just because it
exists; first establish that the task needs it and the selected resource is safe.

## Database contract

- Unit tests should avoid the database where practical.
- DB-backed model/controller tests use a database dedicated to testing, never a real application or production database.
  Select the intended host/config explicitly when auto-detection could choose another local application.
- Treat the local test host, credentials, and database name as machine-local values. Tracked documentation may describe
  required keys and safety markers but must not contain their values.
- Existing DB tests use transaction/rollback for DML isolation. Run them serially when workers share a database.
- MySQL DDL and some external effects do not reliably roll back. Schema/migration checks require a separate disposable
  database and an explicit command; normal PHPUnit runs must not create, drop, or migrate schemas implicitly.
- Before any destructive database operation, resolve the exact server and database, prove that the database is dedicated
  to the task, and limit cleanup to objects created by that run.
- When parallel DB work becomes necessary, use a distinct test database/config per worktree or worker. Do not introduce a
  cloning service or lock registry until measured collisions justify it.

A future mechanical DB-test marker such as `IS_TEST_DB` is preferable to relying on a database name, but adding that
guard is a runtime/test-harness change and must be implemented and migrated as its own reviewed task.

## Worktrees and generated state

- Worktrees isolate tracked files, not Git metadata, service state, processes, ports, databases, caches, or external
  providers. They commonly omit ignored host configs and downloaded assets.
- Inspect status before dependency or generation commands. Check whether `php/vendor/` is tracked; it is tracked in the
  framework baseline, so a Composer operation can create unrelated diffs. Do not normalize or revert them without scope.
- Frontend libraries belong under `www/assets/lib/`. LibMan may continue after package errors, so inspect warnings and
  verify expected files rather than trusting exit code alone.
- Allocate task-owned ports/processes, record enough identity to stop only what the task started, and clean them up when
  verification finishes. Do not kill broad process groups.
- Use task-owned temporary upload/filesystem directories. A config path may point outside the worktree; do not write to it
  without explicit scope and identity checks.

## Caches and external services

- Memcached/session keys can collide across workers. Use a unique test prefix, disable the cache, or serialize when
  isolation is unresolved.
- Email, S3, Google APIs, logging/Sentry, webhooks, and other providers can change external state or expose secrets. Mock
  them or stay offline unless the request explicitly authorizes the exact external operation.
- Never print credentials or include host-local config, production payloads, DB dumps, or large generated logs in task
  artifacts or summaries.
- Downstream/sibling repositories are outside scope unless explicitly named. Inspect them read-only by default and never
  use their availability as a public contributor requirement.

## Evidence reporting

Classify evidence precisely:

- **Passed:** a command or behavior check ran successfully.
- **Resolved by inspection:** a deterministic source/schema/doc fact was checked directly without executing the flow.
- **Not run:** the check was unnecessary, unavailable, unsafe, or out of scope; state why when material.
- **Blocked:** a required check could not run and leaves completion uncertain; identify the missing resource or decision.

Exit code alone is not enough when tools can skip tests, continue after errors, or depend on unresolved configuration.
Record skips, warnings, selected suite/config, and relevant output.
