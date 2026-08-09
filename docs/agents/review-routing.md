# Adaptive Review Routing

Load this selector only when `AGENTS.md` or `workflow.md` requires review. Mechanical checks run first. The integrating
reviewer in `code_reviewer.md` owns the sole deduplicated verdict; specialist overlays are conditional checklists, not
standing panels and not necessarily separate agents.

## Route by changed risk

| Changed risk | Review route |
|---|---|
| Ordinary localized change that merits review | Integrating reviewer only |
| Authentication, authorization, public output/error, raw HTML/URL, redirect, upload/path, credential/logging boundary | Integrator + `reviewers/security-boundary.md` |
| Schema, migration, DB wrapper, transaction, lock, cache/state, retry, concurrency, or multi-record mutation | Integrator + `reviewers/state-integrity.md` |
| Public/overridable API, route, payload, template, config, fixture/example, or copied-component contract | Integrator + `reviewers/contract-compatibility.md` |
| Explicit performance work or a changed hot/repeated/bulk/fan-out/large-result/cache path | Integrator + `reviewers/performance-scale.md` |
| Agent instructions, worktree/resource policy, verification, review routing, memory, summary, or evaluation tooling | Integrator + `reviewers/agent-workflow.md` |

Run syntax/format, focused tests, schema-plus-update pairing, JSON/link validity, and required companion-file checks before
agent review. Do not spend reviewer judgment on facts a deterministic check can settle.

Normally select one specialist. Select two only when the change contains genuinely independent high-risk surfaces. If the
live runtime and permissions support delegation, specialists may review in parallel without editing. Otherwise the same
reviewer applies each selected overlay sequentially. Never delay a terminal verdict waiting for a sub-agent or tool that
the live runtime does not provide.

Give the reviewer the objective, acceptance criteria, exact in-scope paths, deliberate exclusions, baseline, task-summary
path or explicit absence, verification results, and known residual risks. Treat specialist items as hypotheses until the
integrator confirms the path and impact.

Each specialist item is one of:

- **Confirmed:** source and evidence establish a reachable failure or contract violation.
- **Needs validation:** impact depends on missing runtime/config/data evidence; name the smallest falsifying check and any
  counter-evidence.
- **Observation:** optional hardening, clarity, or maintainability improvement.

Reviewer count and checklist length are process costs, not evidence of quality. A route is useful only when it adds a
confirmed issue or a focused, falsifiable risk that the integrating pass would otherwise miss.
