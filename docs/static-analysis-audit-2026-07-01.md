# Static Analysis Audit - 2026-07-01

## Scope

This audit documents the current static-analysis position for KursBuchenZoomLink.

SkillBuilder has a documented PHPStan cleanup in its private codebase. This
smaller public course-booking repository does not yet claim PHPStan coverage.

## Current Gates

```text
composer validate --strict
php -l across bin/config/public/src
php bin/console lint:container --env=test
Markdown link check
secret-token pattern check
GitHub Actions CI
```

## Current Result

```text
CI: success
Composer validation: OK
PHP syntax: OK
Symfony container: OK
```

## Why PHPStan Is Not Claimed Yet

No PHPStan configuration is committed for this repository at this stage.
Claiming PHPStan without a repeatable committed setup would not be useful
portfolio evidence.

The current honest position is:

- the app has a working CI quality gate
- syntax and Symfony service wiring are checked
- secrets are kept outside the repository
- webhook boundaries are documented
- PHPUnit and PHPStan are next quality steps

## Proposed Next Step

Add tests first, then PHPStan:

```text
composer require --dev phpunit/phpunit phpstan/phpstan
```

Recommended first test targets:

- booking creation
- payment confirmation
- Tentary/Zapier webhook secret validation
- Zoom link fallback behavior

Then add:

```text
phpstan.neon
vendor/bin/phpstan analyse src
```

## Audit Position

For a compact integration demo, the current CI is appropriate. The next senior
step is to add service-level tests and then enforce PHPStan in CI.
