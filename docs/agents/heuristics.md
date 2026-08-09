# Heuristics

Use this file for dated lessons that are helpful but may become stale. Revisit entries older than 90 days.

- 2026-04-18: For framework docs, prefer generic osafw-php guidance over application-specific rules from downstream
  projects, even when those downstream projects are useful examples.
- 2026-04-18: When Git reports dubious ownership for this checkout, run Git from the repository root and prefer a local
  command override for read-only inspection instead of changing global Git config automatically.
- 2026-08-09: Before adding optional parameters to an overridable framework method, check downstream subclass
  compatibility; prefer a separate extension method when widening the parent signature would make legacy overrides fatal.
- 2026-08-09: Compare timezone-naive database timestamps inside the database rather than parsing them with PHP's process
  timezone.
