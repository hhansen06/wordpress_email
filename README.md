# WordPress Email Connector

WordPress-Plugin fuer konfigurierbaren Mailversand mit SMTP-Unterstuetzung und Versand-Logging.

## Features

- Umschaltbarer Mailserver:
  - Standard WordPress PHPMailer
  - Externer SMTP-Server (Host, Port, Verschluesselung, Benutzername, Passwort)
- Logging fuer ausgehende Mails mit Status:
  - gesendet / fehlgeschlagen
  - Empfaenger, Betreff, Fehlertext, Mailer, Zeitstempel
- Admin-UI fuer Konfiguration und Log-Ansicht
- Dashboard-Widget fuer Administratoren:
  - Gesamt, gesendet, fehlgeschlagen, Fehler 24h
  - Filter: alle Logs oder fehlgeschlagene Logs der letzten 24 Stunden

## Installation

1. Plugin in den Ordner `wp-content/plugins/wordpress_email` legen.
2. Im WordPress-Backend unter Plugins aktivieren.
3. Unter **WP Email** die Versandart und optional SMTP-Daten konfigurieren.

## Einstellungen im Backend

Zu finden unter **WP Admin -> WP Email**.

- Mailserver: `phpmailer` oder `external`
- Absender: `From E-Mail`, `From Name`
- SMTP: Host, Port, SSL/TLS/keine Verschluesselung, Auth, Benutzername, Passwort
- Logging aktivieren/deaktivieren

## Logging

- Vollstaendige Log-Tabelle unter **WP Email -> Mail Logs**
- Dashboard-Schnellansicht direkt im WordPress Dashboard fuer Administratoren

## Automatischer Release-Workflow (GitHub Actions)

Die Datei `.github/workflows/release.yml` baut bei jedem Commit auf `master` automatisch ein Plugin-ZIP und erstellt ein neues Tag + Release.

- Trigger: Push auf `master`
- Tag-Schema: `v<plugin-version>-<run-number>`
- Release Asset: `wordpress_email-v<plugin-version>-<run-number>.zip`

## Entwicklung

PHP-Syntax lokal pruefen:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Deinstallation

Beim Deinstallieren werden entfernt:

- Plugin-Option `wp_email_connector_settings`
- Log-Tabelle `wp_email_connector_logs`
