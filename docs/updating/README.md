# Updating

This section covers keeping an existing Client Reporter installation up to date.

Because Client Reporter is distributed as source you clone, updating means pulling the latest code and running the usual Laravel update steps — installing any new dependencies, running database migrations and rebuilding the front-end assets. The exact recommended sequence, including guidance for shared hosting where you may not have shell access, will be documented here.

Topics this section will cover:

- Pulling the latest code
- Updating Composer and npm dependencies
- Running database migrations
- Rebuilding front-end assets (`npm run build`)
- Clearing and rebuilding caches
- Updating on shared hosting without shell access — coming soon
- Release notes and following the [changelog](../../CHANGELOG.md)
- Backing up before you update — coming soon
