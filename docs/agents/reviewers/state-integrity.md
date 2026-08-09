# State Integrity Review Overlay

Use with `../code_reviewer.md` for schema, seed, migration, DB wrapper, cache, lock, transaction, JSON, timezone,
filesystem/upload, idempotency, concurrency, retry, or multi-record mutation changes.

Trace fresh-install and forward-update convergence, existing-row behavior, null/default/FK/index semantics, transaction
ownership, rollback after partial effects, concurrent duplicate creation, lock/cache identity and expiry, retry and status
transitions, ordering/timezone behavior, and generated/external side effects. Check both writes and every materially
changed reader. Distinguish DML that transactions can isolate from DDL/files/providers that may persist after rollback.

Require explicit evidence for database assumptions and follow the disposable/serialized DB contract in
`../verification.md`. Do not request broad performance work unless scale is part of the failure.

Return only Confirmed, Needs validation, or Observation items using the common severity/location/impact/fix format.
