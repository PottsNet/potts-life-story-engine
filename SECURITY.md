# Security and error handling

## Supported release

Security fixes are intended for the latest stable release and the current release candidate.

## Reporting

Report suspected vulnerabilities privately to the maintainer before opening a public issue. Do not include private GEDCOM records, credentials or protected media in public reports.

## Design safeguards

- Output is escaped unless it comes from webtrees native rendering helpers.
- External historical-source links use `noopener noreferrer`.
- The module performs no write actions against genealogy data.
- A biography-generation failure displays a generic visitor-safe message.
- Server logging records only the operation, exception class, source filename and line number. It does not intentionally record names, GEDCOM content, notes or media details.
