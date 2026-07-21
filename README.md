# Lexora Legal — Case Management System

PHP/MySQL legal case management system for WAMP with three role-based portals:

- **Admin Portal** — firm-wide control (users, cases, finance, reports, settings, AI)
- **Lawyer Portal** — assigned clients, cases, court, documents, AI
- **Client Portal** — own cases only, documents, appointments, payments, scoped AI

## Requirements

- WAMP (Apache + MySQL/MariaDB + PHP 8+)
- Project path: `C:\wamp64\www\legal-system`

## Install

1. Start **WAMP** (Apache + MySQL green).
2. Open [http://localhost/legal-system/install.php](http://localhost/legal-system/install.php)
3. Click **Create database & seed demo data**
4. Sign in at [http://localhost/legal-system/](http://localhost/legal-system/)

Default DB settings in `config/database.php`: host `127.0.0.1`, user `root`, empty password, database `legal_system`.

## Demo accounts

Login with **username or email** + password:

| Role   | Username / Email              | Password  |
|--------|-------------------------------|-----------|
| Admin  | `admin` or `admin@admin.mu`   | `admin123` |
| Lawyer | `lawyer01`                    | `lawyer01` |
| Client | `yeshna`                      | `yeshna`   |

## AI Assistant

The assistant is **fully built in** — no OpenAI or external API key is required.

It can answer **Mauritius law questions** with Act names and section citations (e.g. Employment Rights Act 2008, s. 45–49; Civil Code, Art. 1134; Constitution, s. 3–19) from an integrated legal corpus, plus firm data, calculations, and document review.

Optional: **Admin → Settings → AI Assistant** lets you connect an external OpenAI-compatible API for broader coverage if you want it — but it is not needed for normal use.

Client AI only receives that client’s own cases/invoices and will refuse requests about other clients.

## Structure

```
admin/          Admin portal modules
lawyer/         Lawyer portal modules
client/         Client portal modules
api/            AI chat endpoint
config/         App + database config
database/       schema.sql + seed.sql
includes/       Auth, helpers, layout
uploads/        Document storage
```

## Modules covered

**Admin:** dashboard, clients, lawyers, cases, appointments, court, finance, staff, reports, notifications, AI, settings, users  

**Lawyer:** dashboard, cases, clients, appointments, court, documents, notifications, AI, profile  

**Client:** dashboard, cases, documents, appointments, payments, contact lawyer, notifications, AI  
