# Contract and Compatibility Review Overlay

Use with `../code_reviewer.md` when a change affects routes/actions, public or overridable methods, API/admin payloads,
identifiers, serialization, templates/ParsePage, dynamic config, DB helper/schema behavior, configuration, fixtures,
examples, or a component likely to be copied/backported.

Identify the canonical producer and all materially affected in-repository consumers. Check presence/null/empty/omitted
semantics, types and identifier stability, authorization-dependent shapes, ordering, default behavior, error/exception
contracts, template/config agreement, and documentation/example parity. Give extra attention to DB and ParsePage surfaces
that derived applications may selectively backport.

Compatibility is preferred when inexpensive, not absolute. If the change intentionally breaks behavior, verify that the
benefit/rationale is explicit, all in-repository consumers and tests/examples changed together, and the migration note is
small but sufficient. Do not infer behavior from a similarly named sibling/downstream implementation.

Return only Confirmed, Needs validation, or Observation items using the common severity/location/impact/fix format.
