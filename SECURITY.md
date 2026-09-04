# Security Policy

We take the security of Client Reporter seriously. Because Client Reporter stores credentials for third-party services and generates shareable client reports, we especially value reports that concern authentication, credential storage and report sharing.

## Supported versions

Security fixes are provided for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| Latest release / `main` | :white_check_mark: |
| Older releases          | :x:                |

Client Reporter is in active development ahead of its first tagged release. Until then, security fixes are applied to the default branch. Once versioned releases exist, this table will be updated to reflect the supported release line.

## Reporting a vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, disclose them privately using GitHub's [private vulnerability reporting](https://docs.github.com/en/code-security/security-advisories/guidance-on-reporting-and-writing-information-about-vulnerabilities/privately-reporting-a-security-vulnerability): go to the repository's **Security** tab and choose **Report a vulnerability**. This opens a private advisory visible only to the maintainers.

Please include:

- A description of the vulnerability and its impact.
- Steps to reproduce, or a proof of concept.
- The affected version, commit or configuration.
- Any suggested remediation, if you have one.

We will acknowledge your report, work with you to understand and validate the issue, and keep you informed as we prepare a fix. Please give us a reasonable opportunity to address the problem before any public disclosure.

## In-scope areas

The following areas are of particular interest:

- **Companion-connector authentication** — the HMAC-signed request scheme used between Client Reporter and the WordPress and Craft companion plugins.
- **Encrypted credential storage** — how third-party service credentials are stored and protected at rest.
- **Public report share tokens** — the tokens that grant access to shared, client-facing reports.

## Responsible disclosure

We ask that you:

- Give us a reasonable time to investigate and fix an issue before disclosing it publicly.
- Avoid accessing, modifying or destroying data that is not yours, and avoid degrading the service for others.
- Act in good faith to avoid privacy violations and disruption.

## No bug bounty

Client Reporter is a free, open-source project. We do **not** operate a paid bug bounty program. We are grateful for responsible disclosures and are happy to credit reporters in release notes where appropriate.
