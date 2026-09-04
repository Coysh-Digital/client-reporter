# Branding and white-labelling

White-labelling is the bit of Client Reporter I most wanted to get right. Client-facing reports can be branded completely as your agency, with no Client Reporter references anywhere your clients can see.

You control the logo, colours and name that show up on reports and across the client-facing experience, so your clients see the report as yours. This section covers how to set that up and how far the branding reaches.

## The branding cascade

Branding is resolved down a chain, from the most general to the most specific. **The most specific non-empty value wins**, field by field:

```
Global agency branding  →  Client override  →  Site override
        (base)                                    (most specific)
```

Each level is a **branding profile** that overrides only the fields it sets. A blank field falls through to the level above, and anything still unset drops back to a sensible built-in default. So you set your agency identity once, globally, and only override the handful of fields that differ for a particular client or site.

- **Global** is the base — your agency's own identity, used everywhere unless something below overrides it. It's created automatically the first time you open it.
- **Client** overrides let you re-brand reports for a specific client (handy for sub-brands, or reseller/partner arrangements).
- **Site** overrides are the most specific and win over both.

Logo and favicon cascade the same way: the effective logo is the one set at the most specific level that has one.

## What you can brand

![Branding and white-label settings](../images/branding.png)

Every branding profile can set:

- **Identity** — agency name and tagline.
- **Logo** — shown on the report cover, in emails and in the portal header.
- **Favicon** — the browser-tab icon for client-facing pages.
- **Colours** — a primary colour and a secondary colour, applied as CSS custom properties (`--brand-primary`, `--brand-secondary`) throughout the report, cover, headings, tables and accents. Both are validated as hex colours.
- **Typography** — a heading font and a body font, each chosen from a curated **Google Fonts** picker. The report stores a full font *stack* (e.g. `'Source Serif 4', Georgia, serif`), so the document loads the chosen web font from Google Fonts and still falls back sensibly where the web font cannot load — for example in the dompdf PDF driver, which uses the fallback in the stack.
- **Report cover style** — `minimal`, `standard` or `bold`.
- **Contact details** — website, email, phone and address (used in closing blocks, emails and footers).
- **Footer text** — a report footer and a separate email footer.
- **Custom CSS** — advanced per-brand CSS injected directly into the client-facing report document, for fine adjustments beyond the built-in controls.

If you set nothing anywhere, the defaults are a deep indigo primary, a muted gold secondary, a Source Serif 4 heading font, a Hanken Grotesk body font and the `standard` cover style. And when no agency name is set at any level, the report falls back to the app's own product name.

## Full white-label

The client-facing **report document** is fully white-labelled: it uses only the agency's own favicon and identity, and shows **no Client Reporter references and no fallback icon**. If you haven't uploaded a favicon, the report just leaves it out rather than giving the product away.

The **client portal** is agency-branded too — your logo and favicon are used wherever you've set them. The portal is the one surface that falls back to Client Reporter's own icon, and *only* when you haven't uploaded a favicon of your own. It never shows clients the product name in place of yours.

## Where to set it

Branding lives in the Branding area and on each client/site:

- **Global agency branding** — `/branding` (needs the *manage branding* permission, i.e. an Administrator).
- **Per-client branding** — from a client's page, `/clients/{client}/branding` (needs *manage clients*).
- **Per-site branding** — from a site's page, `/sites/{site}/branding` (needs *manage sites*).

All three use the same editor, scoped to whichever level you opened it at.

### Live cover preview

The branding editor shows a **live report-cover preview** that updates as you type — logo, colours, chosen fonts and the selected cover style all show up straight away, so you can see how a client's report cover will look before you save.

## How branding is frozen into a render

Branding is resolved for a Site by cascading global → client → site, exactly as above. When you **generate** a report, the resolver works out the effective branding and the generator **freezes it into the report render** alongside the block data (see [Reports](../reports/README.md#generation-and-the-frozen-render)).

From then on, every client-facing copy of that report — the public share link, the emailed copy and the PDF — comes from that **branding snapshot**, not from the live profiles. So a report keeps the exact look it had when you generated it, even if you later change your global branding or a client override. Want to apply new branding to an existing report? Regenerate it.

## Related

- **[Reports](../reports/README.md)** — how reports are built, generated and delivered.
- **[Configuration](../configuration/README.md)** — the PDF driver (dompdf / Browsershot), which affects how branded fonts render in exported PDFs.
