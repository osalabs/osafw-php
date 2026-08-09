# Integrating Code Review

Use this file when independent review is required. Review the completed in-scope change as a skeptical maintainer,
adjudicate any specialist overlays selected by `review-routing.md`, and return the sole deduplicated verdict. The reviewer
does not edit unless explicitly asked to fix findings.

When no independent reviewer is available, the implementing agent applies this checklist as self-review and states that
the review was not independent.

## Handoff and setup

The handoff should provide:

- objective, acceptance criteria, important invariants, and repository role;
- exact in-scope paths and deliberate exclusions;
- baseline such as `HEAD`, a base commit, or a described before/after state;
- task-summary path, or an explicit statement that none is required;
- commands/checks already run with results and skips;
- accepted, external, out-of-scope, or intentionally deferred residual risks;
- selected specialist reports, if separate reviewers were used.

Recover an unambiguous missing item from task context and state the assumption. Do not infer scope from the entire dirty
worktree. Read in-scope untracked files directly because a normal Git diff omits them.

Read `AGENTS.md`, the active task summary, the exact diff, and only the nearby implementation, tests, templates, schema,
config, public docs, or history needed to verify the contract. Search `docs/agents/heuristics.md` for the changed
subsystem rather than loading unrelated memory. Broaden for shared framework, persistence, security, or agent-workflow
changes.

## Review priorities

Check in order and scale depth to risk:

1. **Correctness:** requested behavior, real control/data flow, sibling branches, state transitions, ordering/time,
   idempotency, transactions, retries, and failure paths.
2. **Contracts:** routes/actions, public/overridable methods, API/admin payloads, template variables, ParsePage, dynamic
   config, DB helpers/schema, configuration, examples, and intentional compatibility decisions.
3. **Data integrity:** writes, schema/update convergence, existing rows, defaults/nulls/FKs/indexes, JSON, cache,
   filesystem effects, rollback, retry, and concurrency.
4. **Security/privacy:** authentication, actor/object authorization, method/token gates, raw output, URLs/redirects,
   uploads/paths, secrets, logs, public errors, and external-service boundaries.
5. **Performance/scale:** repeated DB/remote/file/template work, large materialization, missing limits/projection,
   cache isolation/staleness, and blocking calls on reachable repeated paths.
6. **Project fit/simplicity:** correct framework-versus-derived-application role, controller/model/template boundaries,
   existing helpers, avoidable wrappers/casts/defaults, duplication, and generated churn.
7. **Verification:** behavior-level regression/negative evidence proportional to risk, including skipped or unavailable
   checks. Do not demand production seams solely to test private implementation detail.
8. **Documentation/memory:** public owner docs, migration notes, task-summary evidence, and promotion without duplicate or
   stale instruction owners.

For docs/process-only work, focus on authority, concise routing, concrete failure modes, duplicate/stale rules, link/path
accuracy, capability assumptions, portable public policy, and evidence that representative work improves. Reject
wording-only churn without a specific failure mode or measured cost.

Treat specialist reports as hypotheses. Confirm source location, reachability, impact, and counter-evidence; distinguish
confirmed defects from environment-dependent risks. Prefer the smallest falsifying check over speculative breadth.

## Framework/application checks

- In the framework baseline, `php/fw/` remains generic and sample code/docs/schema stay coherent. In a derived
  application, do not reject an intentional product specialization merely because it is not reusable upstream.
- PHP remains compatible with the repository's declared target (PHP 8.3+ in this baseline).
- Controllers return arrays; API controllers use `_json`; auth, OPTIONS/CORS, standard metadata, and public error
  boundaries remain correct where affected.
- Dynamic controller defaults, `config.json`, list/search/sort fields, and templates agree.
- ParsePage templates stay mostly presentational; raw output has an explicit safety boundary.
- Schema changes pair fresh-install SQL with `db/updates/` and use disposable/serialized DB verification.
- Composer changes align manifest/lock; tracked vendor/generated changes are intentional.
- Credentials, host-local config, product payloads, DB dumps, and large logs are absent.
- A practical breaking change may be correct, but its affected in-repository consumers, examples/tests, and migration
  note must be complete.

## Severity and loop decision

- **Blocker:** likely security exposure, data corruption, destructive resource mistake, irreversible migration risk, or
  unusable core flow.
- **High:** likely important runtime bug, broken public/internal contract, missed authorization, or missing required
  migration.
- **Medium:** material edge-case bug, meaningful coverage gap, misleading operational/public documentation, or a
  maintenance trap likely to cause wrong future changes.
- **Low observation:** cleanup, naming, redundancy, minor documentation/test clarity, or optional hardening.

Blocker, High, and Medium findings continue the review-fix loop. Low observations do not continue it by default. An open
product/maintainer question blocks only when correctness depends on the answer.

## Report format

Start with exactly one verdict:

- `Issues found. Review loop should continue.`
- `No blocking issues. Review loop can stop.`

Then use:

```md
## Findings

None.

## Observations

None.

## Verification Reviewed

- Scope and baseline: ...
- Requirements/invariants: ...
- Commands/evidence reviewed: ...

## Tests Not Run

- ...

## Residual Risk

- ...
```

Each finding includes severity, tight location, problem, impact, and smallest fix direction. Prefer a few evidence-backed
findings over checklist recital; do not report pure style preferences or repeat resolved findings.
