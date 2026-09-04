# Security

This section describes Client Reporter's security model and points to how to report vulnerabilities.

Client Reporter stores credentials for third-party services and generates shareable client reports, so a few areas warrant particular care: the authentication of companion connectors, the storage of integration credentials, and the tokens that grant access to shared reports. To report a vulnerability, please follow the process in [SECURITY.md](../../SECURITY.md) — do not open a public issue.

## Companion-connector authentication

The WordPress and Craft integrations talk to a companion plugin installed on the client's site. The security model is deliberately narrow: **Client Reporter always pulls, and the plugin only ever responds with read-only data.** There is no inbound channel from the site into Client Reporter, and the plugin performs no updates, installs nothing, and exposes no secrets, files or database access.

**Shared secret (the connection code).** When you add a connector-based integration, Client Reporter generates a random 48-character secret — the *connection code* — and stores it as an encrypted credential. You paste the same code into the plugin's settings (WordPress: **Settings → Client Reporter**, stored in the `client_reporter_secret` option; Craft: the plugin's **Connection code** setting, which can also be an environment variable). Both ends now hold the same secret.

**Signed requests.** Every request Client Reporter makes to a connector is a `GET` signed with HMAC-SHA256. The signature covers a newline-joined payload of:

1. the HTTP method (`GET`),
2. the request path,
3. a Unix timestamp,
4. a random 16-byte nonce, and
5. a SHA-256 hash of the request body (always empty for these read-only calls).

The signature and its inputs travel in the `X-CR-Signature`, `X-CR-Timestamp` and `X-CR-Nonce` headers. The plugin recomputes the signature with its copy of the shared secret and compares using a constant-time comparison (`hash_equals`).

**Timestamp tolerance and replay protection.** The plugin rejects any request whose timestamp differs from its own clock by more than ~300 seconds (configurable on the Craft side; fixed at 300s on WordPress), which bounds how long a captured request could be reused. Within that window, each nonce is cached and a repeated nonce is rejected, so a captured request cannot be replayed even once. Requests with a missing signature, timestamp or nonce are rejected outright, and if no connection code has been saved the connector returns `403` and exposes nothing.

Because both ends share the secret, you can **rotate** it at any time: generate a new code in Client Reporter and paste it into the plugin. The old code stops working immediately.

See the per-platform guides for setup details: [WordPress](../wordpress/README.md) and [Craft](../craft/README.md).

## Encrypted credential storage at rest

Integration credentials — connector connection codes, API keys, OAuth refresh tokens and the like — are stored encrypted in the database. Both `SiteIntegration` (a connection for one site) and `WorkspaceIntegration` (a shared, account-level connection) cast their `credentials` attribute with Laravel's `encrypted:array` cast, so the values are encrypted with the application's `APP_KEY` (AES-256) before they are written and decrypted only when used. The `credentials` attribute is also hidden from array/JSON serialization, so it is not accidentally exposed through model output.

The practical consequences:

- A database dump alone does not reveal any usable credential; an attacker would also need `APP_KEY`.
- Keeping `APP_KEY` secret (and out of version control) is essential — see hardening below.
- Rotating `APP_KEY` invalidates all stored credentials, which would then need to be re-entered.

## Public report share tokens

Reports can be shared with clients through public links. These are designed so that a database read cannot reveal a working link, and so that access can be time-boxed, password-protected and revoked.

- **Unguessable tokens.** A share token is a long random string (default 64 characters). The URL is `/r/{token}`.
- **Stored hashed.** Only the SHA-256 hash of the token is stored (`token_hash`); the plaintext is shown to the agency once at creation and never persisted. Resolving a link hashes the incoming token and looks up the hash, so the stored record cannot be turned back into a working link.
- **Optional expiry.** A share can be given an expiry date; once past, it no longer resolves.
- **Optional password.** A share can require a password, stored as a bcrypt hash (`password_hash`) and checked when the visitor unlocks the report.
- **Revocation.** A share can be revoked (`revoked_at`); a revoked or expired share is treated as inactive and will not resolve.
- **Rate limiting.** Public report routes are throttled (60 requests/minute), and the password-unlock endpoint is throttled more tightly (10 requests/minute) to resist brute-force guessing of passwords.

## Staff roles and the client portal

Client Reporter has four roles (see `App\Enums\UserRole`). Three are internal agency staff roles; the fourth is the restricted client role. Authorisation is expressed through Laravel gates (defined in `AppServiceProvider`) rather than hard-coded role checks, so the role hierarchy is Administrator > Manager > Viewer, and Administrators pass every gate.

| Role | Access |
| --- | --- |
| **Administrator** | Full access. Everything a Manager can do, plus managing users, branding and application settings. Passes every authorisation gate. |
| **Manager** | Manages the agency's working data: clients, sites and integrations, and creating, editing and sending reports. |
| **Viewer** | Read-only staff access: can view clients, sites, data and reports, but cannot edit. |
| **Client** | Not staff. Restricted **portal** access to only their own sites and reports, through a separate portal interface. Never satisfies a staff-role gate. |

Two top-level gates separate the interfaces: `access-admin` (any staff member) guards the agency admin area, and `access-portal` (a client user linked to a client record) guards the client portal. Capability gates such as `manage-clients`, `manage-sites`, `manage-integrations` and `manage-reports` require Manager or above, while `manage-users`, `manage-branding` and `manage-settings` require Administrator.

## Reporting a vulnerability

Please do **not** report security vulnerabilities through public GitHub issues. Instead, use GitHub's private vulnerability reporting from the repository's **Security** tab. The full process, and what to include, is documented in [SECURITY.md](../../SECURITY.md).

## Hardening recommendations for self-hosted installs

Client Reporter is self-hosted, so the security of an installation depends partly on how it is deployed. Recommended baseline:

- **Serve everything over HTTPS.** Connection codes, share links and session cookies all travel over the network; TLS protects them in transit. Set `APP_URL` to the `https://` origin and enforce HTTPS at the web server or load balancer.
- **Protect `APP_KEY`.** It encrypts all stored credentials. Generate a strong key (`php artisan key:generate`), keep it out of version control, and back it up securely — losing it means re-entering every integration credential; leaking it undermines credential encryption.
- **Lock down file permissions.** The web server needs write access only to `storage/` and `bootstrap/cache/`; the rest of the application (especially `.env`) should not be world-readable, and `.env` must never be web-accessible.
- **Keep everything updated.** Apply Client Reporter, PHP, and dependency updates promptly, and keep the companion plugins on client sites up to date too. Security fixes are applied to the default branch ahead of tagged releases (see [SECURITY.md](../../SECURITY.md)).
- **Restrict admin access.** Limit who holds Administrator and Manager accounts, use strong unique passwords, and consider restricting the admin interface by network (VPN/IP allow-list) where practical. Remember that the client portal is a separate, restricted surface — client users can only ever reach their own sites and reports.
- **Rotate secrets when needed.** If a connection code or credential may have been exposed, rotate it: generate a new connection code and re-paste it into the plugin, or re-enter the affected credential.
