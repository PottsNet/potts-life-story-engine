# Privacy review

Potts Biography is a read-only presentation module.

- It reads records through webtrees objects and visibility rules.
- It uses native webtrees media rendering and access checks.
- It does not bypass private-person, private-fact, note or media restrictions.
- It does not create a separate copy of genealogy data.
- It does not send genealogy data to external services.
- It does not use AI, telemetry, tracking pixels or analytics.
- It does not write names, notes, GEDCOM text or media details to its own error messages.

The standard custom-module update check may contact the configured GitHub raw-content URL from an administrator-facing module page. This request contains no genealogy data.

Before release, test signed-out and signed-in views for living people, restricted facts, shared notes and protected media.
