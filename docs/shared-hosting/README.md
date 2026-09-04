# Shared hosting

Client Reporter is designed to run comfortably on shared hosting. This section covers what that involves.

There is no Docker requirement and no need for Redis or a persistent worker process. By default Client Reporter uses the database cache, session and queue drivers, and a single cron entry drives all scheduled work — data collection, report generation and queued jobs — through Laravel's scheduler. PDF rendering defaults to dompdf, which needs no system binaries and is safe on shared hosts.

The one cron entry you need:

```
* * * * * php /path/to/artisan schedule:run
```

Topics this section will cover:

- The single-cron-entry model and how the scheduler drives everything
- Using the database cache, session and queue drivers (no Redis required)
- Pointing the document root at `public/`
- PDF rendering with dompdf (no binaries required) — see [Configuration](../configuration/README.md)
- When to consider a VPS and a persistent queue worker instead
- Common shared-hosting gotchas (PHP version, extensions, file permissions) — coming soon
