# Static Analysis Audit - 2026-07-01

## Scope

This audit documents the current static-analysis position for KursBuchenZoomLink.

This smaller public course-booking repository now claims repeatable PHPStan
coverage through a committed level 3 setup.

## Current Gates

```text
composer validate --strict
vendor/bin/phpstan analyse --memory-limit=1G
php -l across bin/config/public/src
php bin/console lint:container --env=test
Markdown link check
secret-token pattern check
GitHub Actions CI
```

## Current Result

```text
CI: success
PHPStan: no errors
```

## PHPStan Position

The current position is:

- the app has a working CI quality gate
- syntax and Symfony service wiring are checked
- secrets are kept outside the repository
- webhook boundaries are documented
- PHPUnit and PHPStan run in CI

## Implemented Setup

Committed setup:

```text
phpstan.neon
phpstan/phpstan
phpstan/phpstan-symfony
tests/phpstan-console-application.php
CI step: vendor/bin/phpstan analyse --memory-limit=1G
```

## Audit Position

For a compact integration demo, the current CI now includes both service-level
tests and static analysis. Future work can expand domain tests around booking
creation, payment confirmation and webhook validation.
