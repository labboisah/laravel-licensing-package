# Changelog

## 1.2.1 - 2026-10-09

- Add database-backed deployment fingerprints and fail-closed runtime mismatch validation.
- Add explicit CLI re-provisioning for intentional deployment configuration changes.
- Validate required deployment settings before persisting a re-provisioned profile.
- Keep profile replacement CLI-only by default, with confirmation defaulting to no; do not expose it through the public license UI.
- Add license status revision and effective-time metadata and expand licensing regression coverage.
- Document GitHub installation, deployment setup, configuration drift, and operational recovery.

The deployment fingerprint detects configuration drift. It is not hardware attestation or a security boundary against an administrator who controls the product application's files and database.
