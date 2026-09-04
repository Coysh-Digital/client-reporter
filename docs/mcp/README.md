# Ask an AI about your data (MCP)

Client Reporter ships a built-in [MCP](https://modelcontextprotocol.io) server, which is a fancy way of saying: you can point an AI assistant (Claude Desktop, Claude Code, or anything else that speaks MCP) at your install and ask it questions about your clients, sites and reports in plain English. "Which sites need attention this month?" "What were Acme's visitor numbers in the last report?" — that sort of thing.

It's **read-only**. Nothing the AI can do through it changes your data or kicks off any work — it can only look things up. And it never hands over any integration credentials.

## What the AI can look up

Seven tools, all read-only:

| Tool | What it does |
| --- | --- |
| `get-dashboard-tool` | A portfolio overview for a period — client/site totals, the health split, reports sent vs still to prepare. |
| `list-clients-tool` | Your clients, with site counts and contact details. |
| `list-sites-tool` | Sites, optionally filtered by client, with their current health. |
| `get-site-tool` | One site in detail: health, connected integrations (never credentials), this period's report status. |
| `list-reports-tool` | Reports, filtered by site/client/status, newest first. |
| `get-report-tool` | A generated report's full contents — every block's numbers, from the frozen snapshot. |
| `get-site-metrics-tool` | The metrics collected for a site over a period (visitors, uptime, ecommerce, and so on). |

A tip worth knowing: metrics only exist for periods that have actually been collected. This month and last month are always kept warm, and each report collects its own date range — so `this_month`/`last_month` are your safest bets when asking for numbers.

## Two ways to connect

### Local (the simplest — recommended)

If your AI client runs on the same machine as Client Reporter (or can reach it over SSH), this is the easiest and safest option — there's no network endpoint and nothing to authenticate. The AI client launches the server itself:

```bash
php artisan mcp:start client-reporter
```

For **Claude Desktop**, add this to its MCP config (point it at your install's `artisan`):

```json
{
  "mcpServers": {
    "client-reporter": {
      "command": "php",
      "args": ["/path/to/client-reporter/artisan", "mcp:start", "client-reporter"]
    }
  }
}
```

For **Claude Code**, register it once:

```bash
claude mcp add client-reporter -- php /path/to/client-reporter/artisan mcp:start client-reporter
```

Whoever runs the command already has server access, so the local server trusts them and doesn't ask for a token.

### Over HTTP (for remote/hosted AI clients)

If you want to connect from an AI client that isn't on your server — a hosted assistant, say — there's an HTTP endpoint at `POST <APP_URL>/mcp`. This one is locked down: it needs a token.

First, mint a token for one of your staff accounts:

```bash
php artisan client-reporter:mcp-token you@example.com
```

It prints the token once — copy it there and then, you won't see it again. Then point your MCP client at `https://your-install.example.com/mcp` and send the token as a bearer token:

```
Authorization: Bearer <the-token-you-just-copied>
```

The token is tied to that staff user and carries a single `mcp:read` ability, so it can only ever read. If you ever need to pull access, delete the user's tokens (or the user) in the usual way.

## Who's allowed

Access mirrors the app's own roles:

- **Local (stdio)** trusts whoever runs the command — they already have server access.
- **HTTP** requires a Sanctum token belonging to active **agency staff** (Administrator, Manager or Viewer). Client-portal accounts can't be given a token, and a deactivated account's token stops working.

Either way it's read-only, and integration credentials are never included in anything the tools return.

## Debugging it

Laravel's MCP inspector is handy for poking at the server and seeing the tools and their responses:

```bash
php artisan mcp:inspector client-reporter
```

See the [security model](../security/README.md) for how tokens and access are handled, and [creating an integration](../creating-an-integration/README.md) if you want to add more data for the AI to read.
