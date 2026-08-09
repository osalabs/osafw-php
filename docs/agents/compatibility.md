# Framework and Application Compatibility

This repository is commonly copied into a new application rather than consumed as a versioned package. Compatibility is
therefore a design preference and migration aid, not a promise that independent applications can upgrade wholesale.

## Determine the role

### Framework baseline

- Keep `php/fw/` generic and keep sample controllers/models/templates representative.
- Treat README behavior, examples, schema, routes, template variables, API shapes, and documented configuration as the
  current public framework contract.
- Do not introduce product-specific domains, providers, endpoints, hosts, observability, secrets, or data.

### Derived application

- Tracked application requirements, domain rules, schema, and deployment behavior are authoritative.
- Framework components may be specialized for the application; generic upstream suitability is optional unless the task
  explicitly requests a reusable backport.
- Do not assume an upstream remote, upgrade path, branch name, or obligation to contribute changes back.

## Contract inventory

Trace the affected producer and in-repository consumers for:

- controller routes/actions, default actions, request names, redirects, and response status;
- API authentication order, CORS/OPTIONS behavior, errors, `_json` payloads, metadata keys, identifiers, types,
  presence/null/empty semantics, ordering, limits, and pagination;
- public/overridable model and framework method signatures, DB helper semantics, cache behavior, and exceptions;
- ParsePage syntax, template variables/fragments, raw output, and layout expectations;
- dynamic controller `config.json`, controller defaults, list/search/sort fields, and matching templates;
- schema tables/columns/defaults/nullability/indexes/foreign keys, fresh-install SQL, and incremental updates;
- configuration key names/defaults and local override behavior;
- sample code, README snippets, upgrade notes, fixtures, and tests that teach consumers how the framework works.

DB wrapper/schema behavior and ParsePage receive extra scrutiny because applications may selectively backport those
components even when they do not upgrade the rest of the framework.

## Compatibility decision

1. Establish the requested outcome and the current behavior from code/tests/docs; do not assume the request's proposed
   implementation describes the existing contract correctly.
2. Search in-repository callers, overrides, templates, schema readers, examples, and tests. Inspect an independent
   application only when explicitly scoped, and treat it as evidence rather than an owner of this repository's policy.
3. Preserve observable behavior when the compatibility path is small and understandable. Prefer a new extension method,
   tolerant reader, additive field, or staged migration over a fatal signature/shape/schema break when it does not retain
   harmful complexity.
4. A breaking change is acceptable when it materially simplifies the framework/application or makes the intended design
   more practical. Make the decision explicit rather than hiding it behind a cast, default, alias, or silent behavior
   change.
5. For a deliberate break, update all in-repository consumers/tests/examples and add the smallest migration note that
   states old behavior, new behavior, affected adopters, and replacement. No fixed deprecation period, SemVer release, or
   changelog is required unless the repository later adopts those contracts.

Never preserve an insecure boundary, data-corrupting behavior, or clearly invalid contract solely for compatibility.
Where temporary compatibility would create such risk, fail safely and document the migration.

## Schema and persistence

- Update the create-from-scratch SQL and a dated `db/updates/` script together.
- Consider existing rows, defaults/nulls, indexes/FKs, ordering/timezone, retry/idempotency, and rollback or recovery after
  partial work.
- Prove fresh-install and forward-update paths converge on required behavior. Use the disposable database rules in
  `verification.md`; do not test migrations against a configured application database.
- When code must tolerate a staged schema rollout, identify which version reads/writes each representation and when the
  bridge can be removed.

## Documentation and migration notes

Public documentation and examples are supported behavior for the framework baseline. Update the nearest owner rather
than duplicating the rule in multiple documents:

- `README.md` for routing, request flow, configuration, dynamic controllers, and general recommendations;
- `db/README.md` for database bootstrap and schema file roles;
- a dated upgrade document for material behavior or migration changes;
- task summaries for one-off implementation evidence, not enduring public contracts.

Do not infer a release, protected branch, deployment, or downstream rollout. Commit, push, PR, merge, release, and deploy
remain separate actions requiring current authority.
