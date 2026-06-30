# Federleicht Kursverwaltung

Symfony-App fuer Kurse, Buchungen, Zahlungsbestaetigungen, Zoom-Links und einen Tentary/Zapier-Webhook.

## Setup

```powershell
cd P:\projects\federleicht
Copy-Item .env.example .env.local
composer install
php -d max_execution_time=300 -S 127.0.0.1:8092 -t public
```

```text
http://127.0.0.1:8092
```

## Konfiguration

```dotenv
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=change_me_to_a_long_random_value

DB_HOST=localhost
DB_PORT=3306
DB_NAME=federleicht
DB_USER=federleicht_user
DB_PASSWORD=change_me

CONFIRMATION_EMAIL_ENABLED=0
CONFIRMATION_FROM_EMAIL=noreply@example.com
CONFIRMATION_SENDER_NAME=Federleicht
CONFIRMATION_REPLY_TO=
CONFIRMATION_COPY_EMAIL=

TENTARY_WEBHOOK_SECRET=change_me_to_a_long_random_value

ZOOM_API_KEY=
ZOOM_HOST_USER_ID=me
ZOOM_DEFAULT_DURATION_MINUTES=60
ZOOM_TIMEZONE=Europe/Berlin
```

## API-Beispiele

```powershell
Invoke-RestMethod http://127.0.0.1:8092/api/courses
```

```powershell
Invoke-RestMethod http://127.0.0.1:8092/api/courses `
  -Method Post `
  -ContentType 'application/json' `
  -Body '{
    "title": "Kreatives Schreiben",
    "teacher": "Anna Weber",
    "date": "2026-07-02",
    "time": "17:30",
    "capacity": 12,
    "booked": 0,
    "status": "offen",
    "zoomLink": ""
  }'
```

```powershell
Invoke-RestMethod http://127.0.0.1:8092/api/courses/COURSE_ID/bookings `
  -Method Post `
  -ContentType 'application/json' `
  -Body '{
    "name": "Max Mustermann",
    "email": "max@example.com",
    "note": "Kommt online dazu."
  }'
```

```powershell
Invoke-RestMethod http://127.0.0.1:8092/api/bookings/BOOKING_ID/confirm-payment `
  -Method Post
```

```powershell
Invoke-RestMethod http://127.0.0.1:8092/api/courses/COURSE_ID/zoom `
  -Method Post
```

```powershell
Invoke-RestMethod http://127.0.0.1:8092/api/courses/COURSE_ID `
  -Method Delete
```

```powershell
Invoke-RestMethod http://127.0.0.1:8092/api/courses/reset `
  -Method Post
```

## Tentary/Zapier-Webhook

```powershell
Invoke-RestMethod http://127.0.0.1:8092/api/webhooks/tentary/paid-order `
  -Method Post `
  -Headers @{ 'X-Federleicht-Webhook-Secret' = 'change_me_to_a_long_random_value' } `
  -ContentType 'application/json' `
  -Body '{
    "orderId": "TENTARY-12345",
    "productId": "kurs-kreatives-schreiben",
    "courseTitle": "Kreatives Schreiben",
    "customerName": "Max Mustermann",
    "customerEmail": "max@example.com",
    "paidAt": "2026-07-02T10:00:00+02:00"
  }'
```

## Struktur

```text
src/Controller/CourseController.php
src/Repository/DatabaseConnection.php
src/Repository/CourseRepository.php
src/Service/ConfirmationMailer.php
src/Service/ZoomMeetingProvider.php
templates/course/index.html
public/assets/app.js
public/assets/styles.css
```
