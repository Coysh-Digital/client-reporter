# Security

This section describes Client Reporter's security model and points to how to report vulnerabilities.

Client Reporter stores credentials for third-party services and generates shareable client reports, so a few areas warrant particular care: the authentication of companion connectors, the storage of integration credentials, and the tokens that grant access to shared reports. To report a vulnerability, please follow the process in [SECURITY.md](../../SECURITY.md) — do not open a public issue.

Topics this section will cover:

- Companion-connector authentication: the HMAC-signed, read-only request model
- Encrypted storage of integration credentials at rest
- Public report share tokens and how access is controlled
- Staff roles (Administrator, Manager, Viewer) and the restricted Client portal role
- Reporting a vulnerability — see [SECURITY.md](../../SECURITY.md)
- Hardening recommendations for self-hosted installations — coming soon
