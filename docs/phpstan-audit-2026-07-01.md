# PHPStan Audit - 2026-07-01

## Scope

This audit records the PHPStan/static-analysis status for KursBuchenZoomLink.

The repository now commits a repeatable PHPStan setup and runs it in GitHub
Actions.

## Current State

Current repeatable gates:

```text
composer validate --strict
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G
php -l across bin/config/public/src
php bin/console lint:container --env=test
Markdown link check
environment file policy check
secret-token pattern check
GitHub Actions CI
```

Recorded result:

```text
Composer validation: OK
PHPUnit: OK
PHP syntax: OK
Symfony container: OK
PHPStan: no errors
CI: success
```

## Baseline Position

No PHPStan baseline is committed. The current level 3 setup runs cleanly against
`src` and `tests`.

## Implemented Setup

Committed setup:

```text
phpstan.neon
phpstan/phpstan
phpstan/phpstan-symfony
tests/phpstan-console-application.php
GitHub Actions step: vendor/bin/phpstan analyse --memory-limit=1G
```

## Audit Position

PHPStan is now an enforced public quality gate alongside Composer validation,
PHP syntax checks, Symfony container linting, PHPUnit and secret checks.
