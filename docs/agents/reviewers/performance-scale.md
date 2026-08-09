# Performance and Scale Review Overlay

Use with `../code_reviewer.md` only for an explicit performance task or a changed hot/repeated path, bulk operation,
fan-out, large-result query, remote/file call loop, cache, report, or persistent worker resource.

Estimate or measure relevant input cardinality and query/call/resource count. Check repeated DB/remote/file/config/template
work, missing projection/pagination/limits, unbounded memory, blocking calls in loops, cache keys/staleness/isolation,
lock duration, and behavior at empty and maximum practical input. When point lookups become a batch map, prove duplicate
or normalized-key selection is deterministic and tests assert query count plus selected identities/order.

Prefer a focused measurement or query plan over stylistic micro-optimization. Do not report theoretical complexity with
no reachable scale or propose caching/batching that changes authorization, ordering, freshness, or failure semantics.

Return only Confirmed, Needs validation, or Observation items using the common severity/location/impact/fix format.
