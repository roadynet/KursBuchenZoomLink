# OPERATIONS.md - KursBuchenZoomLink

Dieses Runbook beschreibt den Betrieb der Kursbuchungs- und Zoom-Link-
Automation. Das Projekt ist kleiner als CTC, zeigt aber einen realistischen
Backend-Prozess mit Datenbank, Webhook, E-Mail und externer Meeting-Integration.

## Umgebung und Serverpfade

| Bereich | Konzept |
| --- | --- |
| Anwendung | Symfony-App für Kursbuchungen und Zahlungsbestätigung |
| Datenbank | MySQL/PDO |
| lokale Entwicklung | `php -S 127.0.0.1:8092 -t public` |
| produktive Pfade | nicht öffentlich dokumentiert |
| private Konfiguration | `.env.local` oder Servervariablen, nicht im Repository |

Dieses öffentliche Repository enthält keine produktiven Teilnehmerdaten und
keine echten API-Zugangsdaten.

## Deployment-Ablauf

1. PHP-Syntax prüfen:

   ```bash
   php -l public/index.php
   ```

2. Composer validieren:

   ```bash
   composer validate --strict
   ```

3. Dateien deployen.
4. produktive Env-Werte setzen:

   ```text
   APP_SECRET
   DB_HOST
   DB_NAME
   DB_USER
   DB_PASSWORD
   TENTARY_WEBHOOK_SECRET
   ZOOM_API_KEY
   ```

5. Smoke-Check:

   ```text
   Kursliste
   Buchung anlegen
   Zahlung bestätigen
   Zoom-Link erzeugen
   Tentary/Zapier-Webhook mit Testpayload
   ```

## Env- und Secrets-Konzept

- `.env.example` enthält nur Platzhalter.
- echte Werte liegen in `.env.local` oder Servervariablen.
- `.env.local` wird nicht committet.
- Webhooks benötigen ein Secret im Header.
- Zoom/API-Zugangsdaten bleiben serverseitig.

Webhook-Schutz:

```text
POST /api/webhooks/tentary/paid-order
Header: X-Federleicht-Webhook-Secret
```

## Datenbankmigrationen

Dieses Projekt nutzt eine schlanke PDO/MySQL-Struktur statt eines großen
Doctrine-Migrations-Setups. Betriebsregel:

- Schemaänderungen vor Deployment manuell dokumentieren
- Backup/Export vor produktiven Schemaänderungen
- nach Deployment Kursliste und Buchungsfluss testen

Nächste sinnvolle Ausbaustufe:

- Doctrine Migrations oder ein kleines SQL-Migrationsverzeichnis
- automatisierter Schema-Check
- GitHub Action für Syntax und Composer

## Rollback-Idee

1. vorherigen Code-Stand deployen
2. `.env.local`/Servervariablen unverändert lassen
3. Datenbank nicht blind zurückrollen
4. Webhook bei Fehlverhalten vorübergehend deaktivieren oder Secret rotieren
5. fehlgeschlagene Buchungen manuell prüfen

## Typische Fehlerfälle

| Fehlerbild | Ursache | Prüfung / Fix |
| --- | --- | --- |
| Webhook wird abgelehnt | falsches oder fehlendes Secret | Header und Env-Wert prüfen |
| Buchung wird nicht gespeichert | DB-Verbindung oder SQL-Fehler | DB-Env und Logs prüfen |
| keine Bestätigungsmail | Mailer/Flag deaktiviert | `CONFIRMATION_EMAIL_ENABLED` prüfen |
| Zoom-Link fehlt | API-Key/Host-ID/Fallback | `ZoomMeetingProvider` und Env prüfen |
| Kursliste leer | DB-Daten oder falsche DB | DB-Verbindung und Tabelle prüfen |

## Logs und Debugging

Pragmatische Debug-Einstiege:

```text
PHP error log
Webserver error log
Webhook response body
Datenbankverbindung
API-Response des Zoom-Providers
```

Lokaler Smoke-Test:

```bash
php -S 127.0.0.1:8092 -t public
```

## Monitoring-Ansatz

Aktuell:

- manueller Smoke-Test der wichtigsten API-Endpunkte
- Webhook-Testpayload nach Änderungen
- Kontrolle, dass keine Secrets committet wurden
- Composer-/Syntax-Prüfung lokal

Nächste Ausbaustufe:

- GitHub Action
- einfacher Health-Endpunkt
- Webhook-Fehlerzähler
- E-Mail bei fehlgeschlagener Zahlungsbestätigung

## Praxisnachweis

- [Production Evidence](docs/production-evidence.md)
- [README](README.md)
