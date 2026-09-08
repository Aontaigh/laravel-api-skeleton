# Security Policy

How to report a security issue in this repository.

Machine-readable contact details follow
[RFC 9116](https://www.rfc-editor.org/rfc/rfc9116):
[/.well-known/security.txt](public/.well-known/security.txt), served at
`/.well-known/security.txt` on every environment.

## Reporting a Vulnerability

Use the contact in `security.txt` (currently the repository's
[private vulnerability reporting](https://github.com/Aontaigh/laravel-api-skeleton/security/advisories/new)).
Put `SECURITY` in the subject line where e-mail is used.

Do not open a public GitHub issue or pull request for an unfixed vulnerability.

## What to Include

Enough for us to reproduce and assess impact:

- The affected URL, endpoint, or component
- Steps to reproduce, including a signed-in role if the issue is not anonymous
- What you observed versus what you expected
- Impact (data exposure, privilege change, account takeover, and so on)
- Your contact details and, if you have one, a preferred name for acknowledgement

Do not include real personal data, live credentials, or a proof that modifies
hosted data. A local reproduction against this repository is preferred.

## Response Expectations

We aim to acknowledge reports within two business days and to agree a fix
timeline based on severity. Low-severity hardening notes may be batched into
the regular release cycle; anything remotely exploitable is treated as urgent.

## Scope

Everything in this repository: the API, its authentication and session flows,
the interactive docs, and the CI and release tooling. Third-party packages are
out of scope here - report those upstream - but tell us if you believe our use
of one is at fault and we will coordinate.

## Forks

If you build on this starter, replace the `Contact` in
`public/.well-known/security.txt` with your own disclosure address and keep
`Expires` within a year - the test suite fails the build when it goes stale.
