# Agent Guide Bootstrap

## What changed

- Added a framework-specific `AGENTS.md` based on the useful workflow patterns from the application project, updated for
  this osafw-php repository.
- Mirrored the same guidance to `.github/copilot-instructions.md`.
- Added `docs/agents/code_reviewer.md` with framework-focused review priorities.
- Added baseline agent knowledge files and scaffold directories under `docs/agents/`.
- Updated `.gitignore` so `docs/agents/artifacts/` and `docs/agents/local/` stay local/scratch-only except `.gitkeep`.

## Scope reviewed

- Source application guide:
  `C:\DOCS_PROJ\_my\planplango.com\backend.planplango.com\AGENTS.md`
- Source reviewer guide:
  `C:\DOCS_PROJ\_my\planplango.com\backend.planplango.com\docs\agents\code_reviewer.md`
- Framework repository docs and code:
  `README.md`, `db/README.md`, `www/php/composer.json`, `www/php/fw/FwModel.php`,
  `www/php/fw/FwController.php`, `www/php/fw/FwAdminController.php`, `www/php/fw/FwApiController.php`,
  `www/php/fw/fw.php`, `www/php/configs/config.php`, `www/php/controllers/v1/v1DemosApi.php`

## Commands used / verification

- `rg --files`
- `git -c safe.directory=C:/DOCS_PROJ/github/osafw-php status --short`
- `Compare-Object (Get-Content 'AGENTS.md') (Get-Content '.github\copilot-instructions.md')`
- `rg -n "backend/php|PlanPlanGo|planplango|PPG|APP-|billing|destination|push" AGENTS.md docs .github`
- `rg -n "code_revewer|code_reviewer" AGENTS.md docs .github`

## Decisions - why

- Kept the task-summary, local-instructions, reviewer-loop, and agent-workspace workflow because those are useful for
  ongoing framework development.
- Made sub-agent guidance conditional on runtime/user permission rather than absolute, so it fits newer agent constraints.
- Removed product-specific observability, billing, push, destination, and PlanPlanGo domain rules from the framework guide.
- Used PHP 8.3+ as the documented framework target because the current core uses typed class constants.
- Used the correct `code_reviewer.md` spelling from the source repository.

## Pitfalls - fixes

- Git status initially failed because this checkout is considered dubious ownership for the current Windows user. Used
  `git -c safe.directory=C:/DOCS_PROJ/github/osafw-php ...` for inspection instead of changing global config.
- The first large patch exceeded the Windows tool runner command length. Split file creation into smaller edits.

## Risks / follow-ups

- The repository currently has unrelated dirty/generated/vendor/config files. They were not touched.
- This repository does not currently have a first-party PHPUnit suite documented; future test harness additions should
  update `AGENTS.md` and `.github/copilot-instructions.md`.

## Heuristics

- Framework agent docs should stay generic and should not import downstream product rules unless they describe reusable
  framework behavior.

## Testing instructions

- This is internal documentation/workflow scaffolding, not a runtime framework change.
- Verify `AGENTS.md` and `.github/copilot-instructions.md` remain identical after edits.
- Verify `docs/agents/code_reviewer.md` exists and is referenced by `AGENTS.md`.
- Verify `.gitignore` keeps `docs/agents/artifacts/` and `docs/agents/local/` scratch-only.
