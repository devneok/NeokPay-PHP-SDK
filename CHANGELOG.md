# Changelog

## 1.0.0-beta.2 — unreleased candidate

- Rename the Composer package to `devneok/neokpay-php`; keep PHP namespace `Neok\Pay\` unchanged.
- Resolve User-Agent versions from the renamed Composer package metadata, without hardcoding release versions.
- First intended Packagist release; beta.1 remains an immutable pre-registry GitHub tag using the unavailable vendor. Do not submit beta.1 to Packagist.
- Current quality gate: 59 tests / 178 assertions.

## 1.0.0-beta.1 — pre-registry GitHub tag

- User-Agent follows Composer-installed metadata, with an honest `development` fallback for missing or unversioned metadata.
- Framework-independent PHP ^8.3 client for API-v1 hosted checkout creation and payment retrieval.
- Explicit idempotency keys, immutable DTOs, decimal strings and pending/paid/cancelled statuses.
- Typed API/validation/conflict/rate-limit/transport exceptions.
- Raw-body timestamped HMAC webhook verification, freshness checks and future-event handling.
- Reject malformed successful payment events; redact credential debugging and sensitive trace parameters; do not chain secret-bearing transport errors.
- 47 tests / 129 assertions certified on PHP 8.3.33 and 8.4.25.

No Laravel or Stellar dependency. Sandbox contract is not implemented by this package. Beta.2 has not been tagged or published.
