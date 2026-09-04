# Branding and white-labelling

White-labelling is a headline feature of Client Reporter. Client-facing reports can be fully branded as your agency, with no Client Reporter references anywhere your clients can see.

You control the logo, colours and name that appear on reports and in the client-facing experience, so that your clients see the report as yours. This section covers how to set that up and how far the branding extends.

## The branding cascade

Branding is resolved down a chain, from the most general to the most specific. **The most specific non-empty value wins**, field by field:

```
Global agency branding  →  Client override  →  Site override
        (base)                                    (most specific)
```

Each level is a **branding profile** that overrides only the fields it sets. A blank field falls through to the level above, and anything still unset falls back to a sensible built-in default. So you configure your agency identity once, globally, and only override the handful of fields that differ for a particular client or site.

- **Global** is the base — your agency's own identity, used everywhere unless something below overrides it. It is created automatically on first access.
- **Client** overrides let you re-brand reports for a specific client (useful for sub-brands, or for reseller/partner arrangements).
- **Site** overrides are the most specific and win over both.

Logo and favicon cascade the same way: the effective logo is the one set at the most specific level that has one.

## What you can brand

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

Defaults (when nothing is set anywhere) are a deep indigo primary, a muted gold secondary, a Source Serif 4 heading font, a Hanken Grotesk body font and the `standard` cover style. When no agency name is set at any level, the report falls back to the app's own product name.

## Full white-label

The client-facing **report document** is fully white-labelled: it uses only the agency's own favicon and identity, and shows **no Client Reporter references and no fallback icon**. If the agency has not uploaded a favicon, the report simply omits one rather than revealing the product.

The **client portal** is agency-branded too — the agency logo and favicon are used where set. The portal is the one surface that falls back to Client Reporter's own icon, and *only* when the agency has not uploaded a favicon of its own. It never surfaces the product name to clients in place of the agency's.

## Where to set it

Branding lives in the Branding area and on each client/site:

- **Global agency branding** — `/branding` (requires the *manage branding* permission, i.e. an Administrator).
- **Per-client branding** — from a client's page, `/clients/{client}/branding` (requires *manage clients*).
- **Per-site branding** — from a site's page, `/sites/{site}/branding` (requires *manage sites*).

All three use the same editor, scoped to the level you opened it at.

### Live cover preview

The branding editor shows a **live report-cover preview** that updates as you type — logo, colours, chosen fonts and the selected cover style are all reflected immediately, so you can see how a client's report cover will look before saving.

## How branding is frozen into a render

Branding is resolved for a Site by cascading global → client → site, exactly as above. When a report is **generated**, the resolver produces the effective branding and the generator **freezes it into the report render** alongside the block data (see [Reports](../reports/README.md#generation-and-the-frozen-render)).

From then on, every client-facing copy of that report — the public share link, the emailed copy and the PDF — is rendered from that **branding snapshot**, not from the live profiles. This means a report keeps the exact look it had when it was generated, even if you later change your global branding or a client override. To apply new branding to an existing report, regenerate it.

## Related

- **[Reports](../reports/README.md)** — how reports are built, generated and delivered.
- **[Configuration](../configuration/README.md)** — the PDF driver (dompdf / Browsershot), which affects how branded fonts render in exported PDFs.
