# Configuration

This section covers configuring Client Reporter after installation.

Most configuration follows standard Laravel conventions through your `.env` file. The choices most relevant to Client Reporter are your database, the cache/session/queue drivers, and the PDF renderer. The defaults are chosen to work on shared hosting with no extra services: SQLite for the database, the database drivers for cache/session/queue, and dompdf for PDF rendering.

Topics this section will cover:

- Choosing a database: SQLite (default), MySQL/MariaDB, or PostgreSQL
- Cache, session and queue drivers (database by default; Redis optional on a VPS)
- Queue processing: scheduler-driven on shared hosting vs. a persistent worker on a VPS
- PDF rendering: dompdf (default, shared-host-safe) vs. Browsershot (optional, VPS)
- Mail configuration for sending reports and notifications — coming soon
- Application and environment settings — coming soon
- Storing and encrypting integration credentials — see [Security](../security/README.md)
