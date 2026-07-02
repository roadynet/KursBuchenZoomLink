# KursBuchenZoomLink

[![CI](https://github.com/roadynet/KursBuchenZoomLink/actions/workflows/ci.yml/badge.svg)](https://github.com/roadynet/KursBuchenZoomLink/actions/workflows/ci.yml)

Symfony-App für Kursverwaltung, Buchungen, Zahlungsbestätigung, Zoom-Link-Erzeugung und Tentary/Zapier-Webhook.

## Best place to start

1. **Produktbild:** Buchung erfassen, Zahlung bestaetigen, Zoom-Link erzeugen und Teilnehmer informieren.
2. **Bester Codepfad:** `src/Controller`, `src/Repository`, `src/Service` und Webhook-Verarbeitung.
3. **Architektur:** siehe [Senior-Level Review-Pfad](#senior-level-review-pfad) und [OPERATIONS.md](OPERATIONS.md)
4. **Qualitaet:** [Quality Report](docs/quality-report.md) und gruene CI mit PHPUnit, Container-Lint und PHPStan Level 3
5. **Grenze:** bewusst kleines Integrationsprodukt, kein CRM und keine produktive Zahlungsabwicklung.

Portfolio-Kontext: [Roadynet PHP/Symfony Portfolio](https://github.com/roadynet/skillbuilder-showcase/blob/main/PORTFOLIO.md)

## Produktpositionierung

Dieses Projekt zeigt eine schmale, reale Backend-Automation: ein Kurs wird gebucht, ein Zahlungsereignis kommt per UI/API/Webhook an, danach entsteht der Zoom-Workflow. Der Wert liegt nicht in einer grossen Oberflaeche, sondern in klarer Fachlogik, geschuetzten Webhooks und sauber dokumentierten Betriebsgrenzen.

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
- **PHPStan:** Level 3 läuft mit Symfony-Unterstützung als verpflichtender CI-Quality-Gate.

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
