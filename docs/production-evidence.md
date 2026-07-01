# Production Evidence - KursBuchenZoomLink

Dieses Repository ist bewusst kleiner als die Commerce-Plattformen. Es belegt
einen praxisnahen Backend-Workflow: Kursdaten verwalten, Buchungen erfassen,
Zahlungseingang über Webhook verarbeiten, Bestätigung auslösen und Zoom-Link
bereitstellen.

## Belegbare Praxis

| Bereich | Evidence | Was es zeigt |
| --- | --- | --- |
| Symfony-Minimalbetrieb | kleine Symfony-App mit Controller, Services und Repository | pragmatisches Backend ohne Overengineering |
| Datenzugriff | PDO/MySQL-Repository | direkte Datenbankarbeit und einfache Persistenz |
| Webhooks | Tentary/Zapier-Zahlungsereignis mit Secret-Header | externe Ereignisse sicher annehmen |
| E-Mail/Automation | Bestätigungslogik nach Zahlungsstatus | Prozessautomatisierung |
| Zoom-Integration | Meeting-Link-Service mit Fallbacks | externe API sauber kapseln |
| Secrets | `.env.example` mit Platzhaltern, `.env.local` ignoriert | keine Zugangsdaten im Repository |

## Betriebsfälle

### 1. Zahlungsereignis als Webhook

**Ziel:** Externe Zahlungssysteme sollen nicht manuell im Admin nachgetragen
werden müssen.

```text
Tentary/Zapier paid order -> webhook secret prüfen -> booking paid -> confirmation -> Zoom link
```

**Praxis-Signal:** Backend reagiert auf externe Events, validiert sie und
übersetzt sie in einen internen Fachprozess.

### 2. Secrets und lokale Produktivwerte getrennt halten

**Problem:** API-Keys, DB-Passwörter und Webhook-Secrets dürfen nicht in ein
öffentliches Portfolio-Repository.

**Lösung:**

- `.env.example` enthält nur Platzhalter
- echte Werte gehören in `.env.local` oder Servervariablen
- `.gitignore` verhindert das Committen lokaler Secret-Dateien

**Praxis-Signal:** Auch kleine Projekte werden nicht mit Klartext-Secrets
veröffentlicht.

### 3. Kleine API statt großer Monolith

**API-Fläche:**

```text
GET  /api/courses
POST /api/courses/{id}/bookings
POST /api/bookings/{id}/confirm-payment
POST /api/courses/{id}/zoom
POST /api/webhooks/tentary/paid-order
```

**Praxis-Signal:** Fachliche Aktionen sind als klare Endpunkte modelliert und
lassen sich später in ein größeres System integrieren.

## Interview-Demo in 3 Minuten

1. Kursliste öffnen oder API-Endpunkt zeigen
2. Buchung anlegen
3. Zahlung bestätigen oder Webhook erklären
4. Zoom-Link-Erzeugung zeigen
5. Secret-Grenze über `.env.example` erklären

## Bewusst nicht veröffentlicht

- echte Zoom-Zugangsdaten
- echte Zahlungsanbieter-Secrets
- produktive Datenbankwerte
- reale Teilnehmerdaten
