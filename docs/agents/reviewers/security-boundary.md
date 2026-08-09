# Security and Public-Boundary Review Overlay

Use with `../code_reviewer.md` only when a change touches authentication, authorization, user-selected identifiers,
public errors, raw HTML/Markdown, URLs/redirects, attachments/uploads/paths, API/webhook boundaries, logs, credentials,
or developer/admin capabilities.

Trace the real actor and target object through every changed path. Check access-level/role distinctions, direct-child
authorization, session/API-key/JWT alternatives, CORS/OPTIONS and method/token gates, signed or bearer links, output
escaping, redirect/download/content safety, path containment, environment/allowlist gates, sensitive-data exposure, and
retry/replay behavior. Inspect malformed, expired, denied, replayed, missing, and unauthorized inputs as well as the happy
path. Preserve the intended public exception/status/JSON boundary instead of leaking internal errors.

For environment-dependent claims, identify the missing config/runtime evidence and the smallest safe check. Do not
broaden into generic style review or speculative threats without a reachable changed path.

Return only Confirmed, Needs validation, or Observation items using the common severity/location/impact/fix format.
