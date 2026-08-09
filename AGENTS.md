<!-- AGENTS.md - osafw-php framework agent guide (v1.0, 2026-04-18) -->

# Agent Workflow

1. For any non-trivial task that changes files or requires investigation, create or update
   `docs/agents/tasks/summary-<YYYY-MM-DD>-<TASK-ID>.md`. Use a short human-readable task id when none is provided.

   ```md
   ## What changed

   ## Scope reviewed

   ## Commands used / verification

   ## Decisions - why

   ## Pitfalls - fixes

   ## Risks / follow-ups

   ## Heuristics

   ## Testing instructions
   ```

2. Always check `docs/agents/local/instructions.md` when it exists. Treat it as machine-local guidance and do not commit
   its contents.

3. Read the smallest useful set of framework context before editing. Start with `README.md`, this file, and the nearby
   code/templates/schema touched by the task.

4. Implement required code changes before unit-test or verification work. Keep edits scoped to the framework behavior,
   sample app surface, docs, or schema directly involved in the task.

5. After implementation, run risk-appropriate verification. At minimum, syntax-check changed PHP files. Broaden to
   Composer validation, local database/manual checks, or browser checks when the change affects those surfaces.

6. For runtime-affecting code, schema, templates, scripts, tests, or config changes, run a review-fix loop before closing:
   use `docs/agents/code_reviewer.md`, fix high-signal findings, and repeat until no issues worth another loop remain.
   Documentation-only tasks do not need this loop unless they change operational or runtime guidance.

7. Keep testing instructions current in the task summary. State whether the change is user-facing or internal, whether it
   is a feature/fix/refactor, which flows are affected, exact commands run, and any setup or caveats for manual QA.

8. Post-process after the task is resolved:
   - Add stable framework facts to `docs/agents/domain.md`, `docs/agents/glossary.md`, or this file.
   - Add short-lived working lessons to `docs/agents/heuristics.md` with a date and revise stale entries.
   - Keep one-off details in the task summary only.
   - Add ADRs under `docs/agents/adr/` for substantial framework architecture decisions.
   - Do not store secrets, DB dumps, or large generated logs.

Whenever `AGENTS.md` changes, copy the same guidance to `.github/copilot-instructions.md`.

## Sub-Agent Delegation

- The main agent owns the outcome, user communication, final integration, and final verification.
- Use sub-agents only when the runtime and user permissions allow it, and only for concrete subtasks that can proceed
  independently without blocking the next local step.
- Good delegation targets: targeted framework research, docs/source comparison, isolated implementation in disjoint files,
  focused test planning/execution, and review using `docs/agents/code_reviewer.md`.
- Keep prompts narrow. For code-editing workers, name their file/module ownership, tell them not to revert other people's
  changes, and require a changed-path list in their final response.
- When a sub-agent returns, inspect its evidence and changes before relying on them. Record material findings and commands
  in the task summary.

## Project Overview

`osafw-php` is a lightweight PHP framework for business applications. It provides an MVC-like structure, REST-style
controller dispatch, MySQL data access through the bundled DB wrapper, ParsePage templates, Bootstrap-based admin screens,
auth/access-level helpers, dynamic CRUD screens, uploads, activity logs, reports, and optional API controllers.

The current framework code uses modern PHP features such as typed properties, union types, and typed class constants.
Treat PHP 8.3+ as the target for framework development unless a specific downstream application states otherwise.

This repository is the framework and sample application baseline, not a product backend. Keep reusable framework behavior
generic and avoid importing downstream application business logic, domains, secrets, observability settings, endpoints, or
data.

## Folder Structure

- `README.md` - framework overview, routing map, request flow, config, dynamic-controller docs, and recommendations.
- `db/` - framework schema, lookup seed data, role tables, views, demo tables, and incremental SQL updates.
- `db/updates/` - dated update scripts. Pair schema changes with a matching update script.
- `www/` - public web root.
- `www/index.php` - web entrypoint.
- `php/` - PHP code, config, Composer metadata, framework core, controllers, models, hooks, and vendor files.
- `php/fw/` - framework core (`fw`, dispatcher, controllers, model base, DB wrapper, ParsePage, utilities).
- `php/controllers/` - sample/admin/app controllers built on the framework.
- `php/controllers/v1/` - sample versioned API controllers.
- `php/models/` - sample/admin models and shared framework models.
- `php/tools/` - non-public standalone developer/admin tools invoked through framework routes.
- `php/configs/` - default and host-local config files. Never commit secrets.
- `template/` - ParsePage templates and dynamic-controller `config.json` files.
- `upload/` - default non-public attachment storage served through framework routes.
- `www/assets/` - CSS, JavaScript, images, and optional downloaded frontend libraries.
- `docs/agents/` - agent workflow notes, task summaries, reusable review instructions, and scratch-space conventions.
- `.github/copilot-instructions.md` - mirror of this file for tools that read GitHub Copilot instructions.

## Coding Style

- PHP uses global classes without namespaces. Follow the surrounding class naming: `FwModel`, `FwController`,
  `AdminDemosController`, `v1DemosApiController`.
- Use 4-space indentation, same-line opening braces for classes and methods, short arrays for new code, and typed
  properties/returns where the existing API contract is clear.
- Keep comments useful and concise. Existing files use `#`, `//`, and docblocks; do not add comments that only restate
  the line of code.
- Prefer framework helpers over ad hoc code: `fw::i()`, `fw::model()`, `Model::i()`, `reqs()`, `reqi()`, `reqh()`,
  `Utils`, `FormUtils`, `DateUtils`, `UploadUtils`, `FwModel`, and DB helper methods.
- Controllers should return parse data arrays. API controllers should return `['_json' => $payload]`; do not echo JSON
  directly from controllers.
- Put validation in controller `Validate()` methods or focused model helpers. Use framework exceptions such as
  `ApplicationException`, `ValidationException`, `AuthException`, and `NotFoundException` for error signaling.
- Keep business/domain rules in controllers/models. ParsePage templates should stay mostly presentational.
- Do not add shallow wrappers that only pass arguments through. Add a method only when it names a real framework concept,
  validates a boundary, reduces meaningful duplication, or preserves a public extension point.
- Before adding defensive casts or `??` defaults, trace the data source. For guaranteed DB fields from `one()`/`list*()`
  and non-null schema fields, prefer direct access so framework examples stay readable.
- Preserve public method signatures and template variable names unless the task is explicitly a breaking framework change.

## Framework Development Rules

- Core changes under `php/fw/` must be generic. Do not assume application-specific models, templates, routes, or
  config keys beyond the framework baseline.
- Maintain backward compatibility for downstream apps when practical. If a change alters routing, controller contracts,
  model CRUD behavior, template variables, config names, DB wrapper semantics, or JSON shape, document the break and
  provide the smallest migration path.
- For route behavior, verify `dispatcher.php`, `fw.php`, controller `route_default_action`, and route prefix config
  together. Standard REST mappings are documented in `README.md`.
- For API work, preserve `FwApiController` auth flow, CORS/header behavior, OPTIONS handling, `_json` responses, and
  standard metadata keys (`metadata`, `items`, `count`, `limit`, `offset`, `sortField`, `sortOrder`, `item`).
- For dynamic admin screens, keep `config.json` fields, `show_fields`, `showform_fields`, list sorting/search fields,
  and templates aligned. Check both controller defaults and template fragments.
- For schema changes, update the create-from-scratch SQL (`db/fwdatabase.sql` or the relevant `db/*.sql`) and add a dated
  update under `db/updates/`.
- Do not commit secrets or machine-only host config. Use local files under `php/configs/` for developer machines and
  keep credentials out of docs and logs.
- Composer dependency changes belong in `php/composer.json` and `php/composer.lock`. Do not manually edit generated
  vendor metadata unless the repository intentionally tracks that generated output for the task.
- Keep downloaded frontend libraries under `www/assets/lib/`; this path is ignored except for `.gitkeep`.

## Helpful Docs

- `README.md` - primary framework documentation.
- `db/README.md` - local database bootstrap notes and schema file roles.
- `docs/agents/code_reviewer.md` - independent review checklist and report format.
- `docs/agents/domain.md` - durable framework facts learned during work.
- `docs/agents/glossary.md` - framework terms and naming.
- `docs/agents/heuristics.md` - dated working heuristics for future agents.
- `docs/agents/tasks/` - per-task summaries and verification notes.
- `docs/agents/tools/` - reusable tracked helper scripts for agent/debug workflows.
- `docs/agents/artifacts/` - ignored scratch output, probes, screenshots, temp scripts, and generated logs.
- `docs/agents/local/` - ignored machine-local instructions.

## Agent Workspace

- Put disposable probes, temporary scripts, generated logs, and screenshots under `docs/agents/artifacts/`.
- Keep reusable helper scripts under `docs/agents/tools/` with short README notes.
- Keep machine-specific guidance under `docs/agents/local/instructions.md`; do not commit local instructions.
- Do not leave `tmp_*.php`, throwaway SQL, logs, or downloaded diagnostics at repo root.
- Do not store secrets, DB dumps, production payloads, or large generated files in agent folders.

## Common Tasks

- Add or adjust controllers under `php/controllers/` or `php/controllers/v1/`, then update matching templates or
  API response handling.
- Extend models under `php/models/` or generic model behavior in `php/fw/FwModel.php`.
- Update dynamic CRUD by changing the controller defaults and `template/<route>/config.json` together.
- Update ParsePage layouts/fragments under `template/`; keep template logic minimal.
- Update database install/update scripts under `db/` and `db/updates/`.
- Update Composer dependencies from `php/`.
- Use `logger()` and the configured log destination for debugging instead of dumping output in production paths.

## Verification

- Syntax-check changed PHP files:
  - `php -l php/fw/FwModel.php`
  - `php -l php/controllers/AdminDemos.php`
- Syntax-check all project PHP when a framework-wide change has broad risk:
  - `Get-ChildItem -Path php -Recurse -Filter *.php | Where-Object { $_.FullName -notmatch '\\vendor\\' } | ForEach-Object { php -l $_.FullName }`
- Validate Composer metadata after dependency changes:
  - `Push-Location php; composer validate --no-check-publish; Pop-Location`
- Run the PHPUnit harness when the change affects test-covered framework behavior:
  - `php php/tests/run-local-phpunit.php --testsuite Unit`
  - `Push-Location php; composer test; Pop-Location`
- For DB changes, bootstrap a local database using `db/README.md`, apply base SQL plus the new update, and manually check
  the affected controller/model flow.
- For web/admin/template changes, exercise the relevant route in a browser when local config and DB are available.
- For API changes, check auth behavior and JSON shape, including error and not-found cases.

## Command Notes

- The repository may be checked out under Windows with ownership that triggers Git's dubious-ownership protection. Run
  Git from the repository root and prefer a local command override for inspection, or ask before changing global Git config.
- Do not revert unrelated dirty files. This repo may contain local config, generated vendor metadata, or developer changes
  unrelated to the current task.
- Prefer `rg` / `rg --files` for searches. Use focused file reads before broad sweeps.

## PhpStorm MCP

- When PhpStorm MCP is connected, prefer indexed/project-aware calls for discovery: `get_file_text_by_path`, `read_file`,
  `search_symbol`, `search_text`, `list_directory_tree`, `get_php_project_config`, and `get_repositories`.
- Use IDE inspections, symbol lookup, refactors, formatting, and database browsing when the server is responsive.
- Treat IDE results as machine-local context. If the MCP bridge times out, narrow once, then fall back to shell commands
  and `apply_patch`.

## Documentation Sync

- Update `README.md` when framework routing, request flow, configuration, dynamic-controller behavior, debugging, or public
  recommendations change.
- Update `db/README.md` when database bootstrap or schema file roles change.
- Update `docs/agents/code_reviewer.md` when review priorities need new framework-specific checks.
- Update this file when durable workflow, command, structure, or framework development guidance changes, then mirror it to
  `.github/copilot-instructions.md`.
