## What changed

- Replaced the monolithic root agent guide with a concise dual-role router for the framework baseline and copied derived
  applications.
- Added focused workflow, verification/resource, compatibility, review-routing, and specialist-review guidance.
- Added a durable real-work benchmark specification plus a bounded structural routing check for instruction-only changes
  that make no broad performance claim.
- Removed the unused GitHub Copilot instruction mirror.

## Scope reviewed

- Root and GitHub Copilot instruction topology.
- Existing reviewer, durable memory, task summaries, framework README/docs, tests, database/config surfaces, and Git
  worktree/branch state.
- Reusable agent-improvement method and reviewer overlays from an independent derived application, generalized for this
  public framework.
- Current official OpenAI guidance for `AGENTS.md` discovery and runtime authority boundaries.

## Commands used / verification

- `git status --short`, `git branch --show-current`, `git diff --stat`, targeted/full diff reads - established a clean
  detached baseline, inspected all tracked/untracked changes, and confirmed only the intended guidance files changed.
- `git diff --check` - passed after implementation and after review fixes; Git reported only the checkout's expected
  LF-to-CRLF conversion warnings.
- PowerShell required-path and route checks - all concrete root/focused/reviewer routes resolve; the Copilot instruction
  file is absent.
- PowerShell portability scan - no active Copilot/local-instruction dependency, private path, source-application name,
  PhpStorm-specific tool rule, model/reasoning selection, or sandbox-mode directive was found.
- Baseline/candidate structural routing evaluation - normalized root instructions decreased from 13,442 to 7,812 bytes
  (41.9%); routine, security, state, contract, performance, documentation, agent-workflow, derived-application,
  authority-boundary, direct benchmark routing, verification benchmark routing, and benchmark-contract assertions all
  passed.
- Independent integrating review with `docs/agents/reviewers/agent-workflow.md` - first pass found two Medium process
  issues and one Low observation. The verification contract, benchmark specification, startup README routing, and this
  summary were corrected. The second pass found one Medium progressive-disclosure gap: the benchmark file was not an
  exact root route. Root, workflow, and verification guidance now link it directly. The final independent pass reported
  `No blocking issues. Review loop can stop.` with no findings or observations.
- PHP, PHPUnit, Composer, database, browser, and external-service checks were not run because no runtime code,
  configuration, schema, template, or dependency behavior changed.

## Decisions - why

- Treat compatibility as a preference rather than package-versioning policy because applications copy the repository and
  evolve independently.
- Preserve stronger scrutiny for DB and ParsePage because those components are sometimes selectively backported.
- Use conditional review overlays rather than standing panels so routine work remains lightweight.
- Remove Copilot-specific instructions because there is no intended Copilot consumer; Codex uses `AGENTS.md`.
- Require a full frozen real-work comparison before broad task-success/efficiency claims, while allowing deterministic
  routing/authority checks for instruction maintenance that makes no such claim.

## Pitfalls - fixes

- Avoided product-specific rules, private paths, credentials, fixed branch names, and claims that repository prose can
  grant runtime permissions.
- The first structural evaluator mixed normalized Git blob bytes with checkout bytes and used a single-line authority
  regex across a wrapped sentence. Normalizing line endings and matching the stable phrase produced a comparable passing
  run.
- Independent review found that the first verification table accidentally required the full real-work benchmark for
  every agent-policy edit. The rule now distinguishes structural maintenance from claims of end-to-end improvement.
- The second review found that merely creating the benchmark under `docs/agents/evals/` did not make it reliably
  discoverable. Material comparisons and broad outcome claims now route to the exact benchmark file.

## Risks / follow-ups

- The database test harness does not yet mechanically require an explicit disposable-test marker; that remains a
  separate runtime/test-harness improvement if the maintainer wants enforcement.
- The full real-work baseline/candidate task benchmark is specified but not executed. No claim is made that first-pass
  coding success improved; the measured result is limited to route coverage, portability, and a 41.9% smaller auto-loaded
  root instruction file.

## Heuristics

- A small conditional reviewer overlay can provide specialist depth without requiring another agent or a standing panel.
- Instruction verification should separate deterministic routing/authority regressions from expensive end-to-end task
  claims so small policy corrections do not inherit unnecessary ceremony.

## Testing instructions

- Internal process/documentation change affecting framework and derived-application agent workflows.
- Verify all local guidance links, absence of stale Copilot/local-path rules, focused review routing, and the final Git
  diff. Run `docs/agents/evals/instruction-benchmark.md` before claiming broader task-success or efficiency improvement.
  No PHP runtime, database, browser, or external-service behavior is changed.
