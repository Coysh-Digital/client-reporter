# Installation

This section covers installing Client Reporter and getting to the point where you can log in and start adding clients.

Client Reporter is a standard Laravel 13 application. The quickest path is to clone the repository, install dependencies, build the front-end assets, point your web server at the `public/` directory and open the site in a browser to run the install wizard. No Docker is required, and the default SQLite database means there is nothing extra to provision to get started.

The short version:

```bash
git clone https://github.com/coysh-digital/client-reporter.git
cd client-reporter
composer install
npm install && npm run build
```

Then point your web root at `public/` and open the site in your browser to run the install wizard.

Topics this section will cover:

- Requirements (PHP 8.3+, Composer, Node.js, a supported database) — see also [Configuration](../configuration/README.md)
- Cloning the repository and installing dependencies
- Pointing your web server's document root at `public/`
- Running the browser-based install wizard — coming soon
- Choosing and configuring your database (SQLite, MySQL/MariaDB, PostgreSQL)
- Setting up the scheduler cron entry — see [Shared hosting](../shared-hosting/README.md)
- Creating the first Administrator account — coming soon
- Post-install checklist — coming soon
