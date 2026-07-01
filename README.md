# KursBuchenZoomLink

Symfony-App für Kursverwaltung, Buchungen, Zahlungsbestätigung, Zoom-Link-Erzeugung und Tentary/Zapier-Webhook.

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
