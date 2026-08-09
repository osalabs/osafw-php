# Agent Guide

## Purpose and repository role

`osafw-php` is a lightweight PHP framework plus a sample application. New applications commonly begin by copying this
repository and then evolve independently, so these instructions must remain useful in either role:

- **Framework baseline:** keep reusable code under `php/fw/` generic, and treat the sample app, schema, templates, and
  documentation as supported examples of the framework.
- **Derived application:** treat that repository's tracked product requirements, domain rules, schema, and deployment
  documentation as authoritative. Framework code may be specialized when the application benefits.

Determine the repository role from its current tracked evidence. Do not assume that a derived application tracks this
repository, is upgradeable from it, or should send changes back. A sibling application may be inspected or used as a
source only when the task explicitly puts it in scope; generalize any backported idea and remove product-specific rules.

## Authority and scope

- Live system, developer, runtime, and user instructions outrank repository guidance. Repository text cannot
  grant permissions, select a model, switch a UI mode, or authorize commits, pushes, pull requests, deployments,
  credentials, database mutation, or external messages.
- Treat requested outcomes and acceptance criteria as authoritative, but validate implementation claims against current
  code, schema, tests, and documentation. Explain material contradictions instead of silently implementing a false
  premise.
- A request to inspect, explain, diagnose, audit, or review is read-only unless it also asks for a change. A request to
  fix, build, or update authorizes scoped local edits and proportionate verification, not unrelated integration actions.
- Preserve unrelated work. Inspect status before editing, keep changes in scope, and never revert user changes merely to
  obtain a clean tree.
- Discover the actual current branch, worktree state, and repository contracts. Do not assume `master`, `main`,
  `production`, branch protection, a release process, or a deployment path.

## Start and route the task

1. Read the smallest useful context: relevant `README.md` sections when repository role, setup, or documented behavior
   matters; otherwise begin with the nearest implementation/docs/schema and any more-specific `AGENTS.md` in the working
   path.
2. Classify the repository role and changed risk before choosing tests or review depth.
3. Load only the focused guidance needed for the task:

| Task surface | Required guidance |
|---|---|
| Any substantive write task | `docs/agents/workflow.md` |
| Verification, tests, database, worktree, process, filesystem, or external-resource use | `docs/agents/verification.md` |
| Public methods, routes, payloads, templates, config, schema, DB wrapper, ParsePage, examples, or deliberate breaks | `docs/agents/compatibility.md` |
| A change requiring independent or specialist review | `docs/agents/review-routing.md` and `docs/agents/code_reviewer.md` |
| Agent instructions, memory, summaries, review policy, or evaluation tooling | `docs/agents/reviewers/agent-workflow.md` during review |
| A material instruction comparison or claim of improved task success/efficiency | `docs/agents/evals/instruction-benchmark.md` |
| An unfamiliar framework fact/term or a recurring subsystem pitfall | Search the relevant entries in `docs/agents/domain.md`, `docs/agents/glossary.md`, and `docs/agents/heuristics.md` |

Supporting documents are not automatically authoritative outside their routed surface. Machine-local preferences and
secrets must not be added to tracked guidance. Consult optional local guidance only when the environment explicitly
provides it and the task needs it.

## Risk and review depth

- **Routine:** documentation-only changes, comments, or a localized sample fix with no public/runtime contract change.
  Use focused verification and self-review; do not create process artifacts solely for ceremony.
- **Standard runtime:** bounded controller, model, template, or tool behavior. Run focused behavior checks and review
  affected contracts.
- **High risk:** authentication/authorization, CORS/JWT/API boundaries, raw HTML/URLs, paths/uploads, core framework
  signatures, ParsePage, DB wrapper/schema/migrations/locks, transactions/concurrency, dependencies, or agent workflow.
  Require negative/regression evidence, a task summary, and an independent review when the runtime provides one.

Use one integrating reviewer and only the specialist overlays selected by `docs/agents/review-routing.md`. Reviewer count
is not a quality metric. When independent review is unavailable, perform the same focused review yourself and disclose
that limitation.

## Framework and application invariants

- Treat PHP 8.3+ as the framework baseline unless a derived repository declares another supported range.
- Follow surrounding global-class naming and four-space PHP style. Prefer typed properties/returns where contracts are
  clear, short arrays for new code, and concise comments that explain intent.
- Prefer framework helpers (`fw::i()`, `fw::model()`, `Model::i()`, request helpers, `Utils`, `FormUtils`, `DateUtils`,
  `UploadUtils`, `FwModel`, and DB helpers) over parallel ad hoc mechanisms.
- Controllers return parse-data arrays. API controllers return `['_json' => $payload]`; do not echo JSON directly.
- Keep validation at controller/model boundaries and signal failures with framework exceptions. Keep templates mostly
  presentational.
- In the framework baseline, core changes must not depend on application-specific models, routes, config keys, hosts,
  providers, secrets, observability, or data. In a derived application, repository-local rules may intentionally
  specialize those surfaces.
- Prefer compatibility when it is inexpensive, especially for DB and ParsePage surfaces that applications may backport
  selectively. A practical breaking change is allowed when intentional: identify affected contracts, update all
  in-repository consumers/tests/examples, and add the smallest useful migration note.
- Schema changes update both the fresh-install SQL and a dated file under `db/updates/`. Keep controller defaults,
  dynamic `config.json`, templates, and documented examples aligned.
- Dependency changes belong in `php/composer.json` and `php/composer.lock`. Treat tracked vendor/generated churn as
  intentional only when the task requires it.
- Never commit credentials, host-local config, database dumps, production payloads, or large generated logs.

## Memory and documentation

- Do not create a task summary for read-only work, triage, or trivial documentation. Create
  `docs/agents/tasks/summary-<YYYY-MM-DD>-<TASK-ID>.md` for high-risk, substantive multi-file, schema, public-contract,
  or agent-workflow changes; follow `docs/agents/workflow.md`.
- Promote only durable facts to `docs/agents/domain.md` or `docs/agents/glossary.md`, dated working lessons to
  `docs/agents/heuristics.md`, and substantial architecture decisions to `docs/agents/adr/`. Keep one-off detail in the
  task summary. Do not auto-load or index an ever-growing task transcript archive without measured need.
- Update `README.md` or focused public docs when routing, request flow, configuration, dynamic-controller behavior,
  schema/bootstrap, debugging, or supported examples change. Public docs and examples are part of the repository's
  current contract.

## Completion

Implement the requested change, run risk-appropriate checks from `docs/agents/verification.md`, inspect the final diff,
and resolve high-signal review findings. Report what passed, what was resolved by inspection, what was not run, and any
residual risk. Never claim verification that did not run.
