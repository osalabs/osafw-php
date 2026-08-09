# Agent Workflow

Load this file for substantive tasks that modify the repository. Keep routine fixes lightweight while making risk,
authority, and evidence explicit.

## Establish the task contract

1. Restate the requested outcome, important acceptance criteria, explicit exclusions, and whether the task is read-only
   or authorizes local changes.
2. Inspect current status and the smallest relevant code, schema, templates, tests, public docs, and history. Preserve
   unrelated dirty files and identify overlapping edits before changing them.
3. Determine whether the repository is the framework baseline or a derived application. Do not import assumptions from
   a sibling repository merely because it started from the same framework.
4. Separate implementation suggestions in the request from facts established by the repository. When they conflict,
   explain the evidence and choose the smallest route that still meets the requested outcome.

Search durable memory or prior task summaries only when the subsystem, terminology, or failure mode makes them relevant;
do not load the full archive as routine context.

Read-only verbs do not authorize a task summary, code edits, config initialization, database mutation, commits, pushes,
pull requests, deployments, or external communication. Change verbs authorize scoped local implementation and
verification. Ask before materially expanding the affected system, people, repository, or irreversible state.

## Work by risk

### Routine

Examples: typo fixes, prose clarification, comments, or an isolated sample correction with no public/runtime contract
change.

- Read only nearby context.
- Run a link/text/render check or the narrowest applicable syntax/test command.
- Self-review the diff.
- Do not create a task summary or invoke an independent reviewer merely for process compliance.

### Standard runtime

Examples: bounded controller/model/template changes, a local bug fix, or a new test-covered helper.

- Trace the real call/data flow and adjacent branches.
- Add or adjust focused regression evidence when the existing harness can express the failure.
- Check public/template/schema implications using `compatibility.md`.
- Create a task summary when the change is multi-file, difficult to reconstruct, or has material residual risk.

### High risk

Examples: authentication/authorization, raw public output, core signatures, DB/schema/migrations, transactions, locking,
uploads/paths, dependencies, repeated-query/hot paths, or agent workflow.

- Define protected invariants and negative paths before closing the implementation.
- Create a task summary.
- Run focused mechanical checks before agent review.
- Use `review-routing.md` to select one integrating review and only the necessary overlays.
- Repeat the review-fix loop until no Blocker, High, or Medium finding remains, or report the unresolved blocker.

## Implementation discipline

- Modify the smallest coherent set of files. Keep framework baseline behavior generic and derived-application behavior
  local to the application.
- Use a reproducer or regression-test-first approach when it makes the failure clearer; otherwise implement first and
  verify immediately. Do not impose one sequence on every task.
- Preserve public names and observable behavior by default, but do not retain accidental complexity solely for abstract
  compatibility. Follow `compatibility.md` for deliberate breaks.
- Large changes may use named checkpoints so each stage is reviewable. A checkpoint is a reasoning boundary, not
  permission to create commits. Commit, push, PR, merge, release, and deploy only when separately authorized.
- Put disposable probes, logs, screenshots, and temporary scripts under `docs/agents/artifacts/`. Put reusable helpers
  under `docs/agents/tools/` with short usage notes. Do not leave throwaway files at repository root.

## Task summaries

Create `docs/agents/tasks/summary-<YYYY-MM-DD>-<TASK-ID>.md` when work is high risk, changes agent policy, changes schema or
public contracts, spans substantive implementation surfaces, or leaves decisions future work must understand.

Use:

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

Keep it concise and deterministic. Testing instructions state whether the change is user-facing or internal, whether it
is a feature/fix/refactor/process change, affected flows, exact commands, and setup/caveats. Update the same summary as
the task evolves; do not create a transcript per review pass.

After resolution, promote only:

- stable framework/application facts to `domain.md`;
- canonical terms to `glossary.md`;
- dated lessons likely to help the next few tasks to `heuristics.md`;
- substantial architecture decisions to `adr/`.

Do not create a summary index or scheduled memory rewriting until retrieval failures or volume demonstrate the need.
Use `docs/agents/evals/instruction-benchmark.md` for material instruction comparisons or claims of improved task success,
safety, or efficiency. Keep raw runs and disposable snapshots in ignored artifacts rather than normal task context.

## Delegation and review

The main agent owns scope, integration, verification, and user communication. Delegate only when the live runtime and
user permissions allow it and an independent subtask can proceed without conflicting writes.

Good targets are focused research, disjoint implementation, test execution, or one specialist review. Normally use no
more than one specialist; use two only for genuinely independent high-risk surfaces. Give editing workers exact file
ownership and require changed paths/evidence. Inspect returned work before relying on it.

An overlay is a checklist, not necessarily another agent. When delegation is unavailable or disproportionate, the
integrating reviewer applies the selected overlay sequentially.

## Close the task

- Inspect status and the full in-scope diff, including untracked files.
- Run the proportional checks in `verification.md`.
- Resolve high-signal review findings and update the task summary with actual evidence.
- Report user-visible outcome first, followed by tests not run and residual risk.
- Do not imply that unrun tests, unavailable services, downstream repositories, or deployment behavior were verified.
