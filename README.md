# KursBuchenZoomLink

[![CI](https://github.com/roadynet/KursBuchenZoomLink/actions/workflows/ci.yml/badge.svg)](https://github.com/roadynet/KursBuchenZoomLink/actions/workflows/ci.yml)

Symfony-App für Kursverwaltung, Buchungen, Zahlungsbestätigung, Zoom-Link-Erzeugung und Tentary/Zapier-Webhook.

## Audit & Evidence

- [Audit Report](docs/audit-report-2026-07-01.md)
- [Quality Report](docs/quality-report.md)
- [Evidence Index](docs/evidence/README.md)
- [Production Evidence](docs/production-evidence.md)
- [Operations Runbook](OPERATIONS.md)

## Tests & Quality Gates

[![PHPUnit + Symfony Quality Gates](https://img.shields.io/github/actions/workflow/status/roadynet/KursBuchenZoomLink/ci.yml?branch=main&label=PHPUnit%20%2B%20Symfony%20Quality%20Gates)](https://github.com/roadynet/KursBuchenZoomLink/actions/workflows/ci.yml)

- **PHPUnit:** Service-Tests prüfen Zoom-Fallback und vorhandene API-Meeting-Daten.
- **Symfony-Gates:** Composer-Validierung, PHP-Syntaxcheck und Container-Lint laufen bei jedem Push.
- **Portfolio-Schutz:** Markdown-Linkcheck, Env-Policy und Secret-Pattern-Scan sind Teil der CI.
- **PHPStan:** als nächster Quality Gate dokumentiert, nachdem die wichtigsten Service-Tests sichtbar sind.

## Auf einen Blick

- **Was ist es?** Eine kleine Backend-Anwendung für Online-Kurse: Kurs anlegen, Buchung erfassen, Zahlung bestätigen, Zoom-Link erzeugen.
- **Tech-Stack:** PHP 8.4, Symfony 8.1, PDO/MySQL, Twig, kleine JSON-API.
- **Warum interessant?** Das Projekt zeigt einen praxisnahen Workflow zwischen Kursverwaltung, Zahlungsereignis, E-Mail-Bestätigung und Meeting-Automation.
- **Portfolio-Fokus:** klare Backend-Flows, Secret-Trennung, Webhook-Schutz und schlanke API-Endpunkte.

## Senior-Level Review-Pfad

| Frage | Antwort |
| --- | --- |
| Wo liegt die Fachlogik? | Controller delegieren an Repository- und Service-Klassen. |
| Wie kommen Buchungen ins System? | Manuell über UI/API oder automatisch über Tentary/Zapier-Webhook. |
| Welche Praxis ist belegbar? | [Production Evidence](docs/production-evidence.md) |
| Wie wird Betrieb dokumentiert? | [OPERATIONS.md](OPERATIONS.md) |
| Welche Audits gibt es? | [Audit Report](docs/audit-report-2026-07-01.md) · [Quality Report](docs/quality-report.md) |
| Wie wird Live-Konfiguration geschützt? | Echte Werte gehören in `.env.local` oder Servervariablen, nicht ins Repository. |
| Was ist bewusst klein gehalten? | Kein vollständiges CRM, kein Payment-System, keine Mandantenverwaltung. |

## Kleine Codebeispiele

Kurs-Workflow:

```text
Kurs erstellen -> Buchung erfassen -> Zahlung bestätigen -> Zoom-Link erzeugen
```

Kleine API-Fläche:

```text
GET  /api/courses
POST /api/courses/{id}/bookings
POST /api/bookings/{id}/confirm-payment
POST /api/courses/{id}/zoom
```

Webhook-Schutz:

```text
POST /api/webhooks/tentary/paid-order
Header: X-Federleicht-Webhook-Secret
```

## Architektur

| Bereich | Rolle |
| --- | --- |
| `CourseController` | HTTP-Endpunkte, Validierung, Response-Aufbau |
| `CourseRepository` | Datenzugriff auf Kurse und Buchungen |
| `ConfirmationMailer` | Zahlungs-/Buchungsbestätigung |
| `ZoomMeetingProvider` | Meeting-Link-Erzeugung und Fallbacks |
| Tentary/Zapier-Webhook | externer Zahlungseingang als Buchungsereignis |

## Security und Betrieb

- `.env.example` enthält nur Platzhalter.
- `.env.local` und echte Serverwerte werden nicht committet.
- Webhooks benötigen ein Secret im Header.
- Zoom/API-Zugangsdaten bleiben serverseitig.
- Bestätigungs-E-Mails sind per Flag aktivierbar.

## Lokal starten

```powershell
composer install
php -d max_execution_time=300 -S 127.0.0.1:8092 -t public
```

Dann im Browser öffnen:

```text
http://127.0.0.1:8092
```

## Grenzen

Dieses Repository ist als schlanke Kursverwaltungs- und Integrationsdemo gedacht. Es ersetzt kein vollständiges Buchhaltungssystem, kein CRM und keine produktive Zahlungsabwicklung.

Weitere Praxis-Evidence: [Production Evidence](docs/production-evidence.md)

Operations-Runbook: [OPERATIONS.md](OPERATIONS.md)

Audit-/Quality-Doku: [Audit Report](docs/audit-report-2026-07-01.md) · [Evidence Index](docs/evidence/README.md)
