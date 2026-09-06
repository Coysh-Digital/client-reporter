/*
 | Captures every admin screen at desktop and phone widths, for checking a
 | change visually or refreshing the documentation images.
 |
 |   npm run shots                       # every screen → storage/app/screenshots
 |   npm run shots -- dashboard clients  # just those screens
 |   SHOTS_DOCS=1 npm run shots          # also refresh docs/images/*.png
 |
 | Environment:
 |   SHOTS_URL      base URL (default https://client-reporter.ddev.site)
 |   SHOTS_EMAIL    a staff login (default admin@example.com)
 |   SHOTS_PASSWORD its password (default password)
 |   SHOTS_CHROME   path to a Chrome/Chromium binary if Playwright cannot find one
 |   SHOTS_OUT      output directory
 |   SHOTS_CLIENT_EMAIL / SHOTS_CLIENT_PASSWORD
 |                  a client portal login; when set, the portal is captured too
 |
 | Needs the playwright-core dev dependency and a local Chrome install.
 */
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';

const BASE = process.env.SHOTS_URL ?? 'https://client-reporter.ddev.site';
const OUT = process.env.SHOTS_OUT ?? path.resolve('storage/app/screenshots');
const EMAIL = process.env.SHOTS_EMAIL ?? 'admin@example.com';
const PASSWORD = process.env.SHOTS_PASSWORD ?? 'password';
const DOCS = process.env.SHOTS_DOCS === '1';
const only = process.argv.slice(2);

fs.mkdirSync(OUT, { recursive: true });

const launch = { headless: true };
const chrome = process.env.SHOTS_CHROME
    ?? ['/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', '/usr/bin/google-chrome', '/usr/bin/chromium'].find((p) => fs.existsSync(p));
if (chrome) launch.executablePath = chrome;

const browser = await chromium.launch(launch);
const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
page.on('pageerror', (error) => console.log('JS error', page.url(), error.message.split('\n')[0]));

await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await page.screenshot({ path: `${OUT}/login-desktop.png`, fullPage: true });
await page.fill('input[type=email]', EMAIL);
await page.fill('input[type=password]', PASSWORD);
await page.keyboard.press('Enter');
await page.waitForURL(/dashboard|two-factor/, { timeout: 15000 });
await page.waitForLoadState('networkidle');

const firstLink = async (route, selector) => {
    await page.goto(`${BASE}${route}`, { waitUntil: 'networkidle' });
    const locator = page.locator(selector);
    return (await locator.count()) ? locator.first().getAttribute('href') : null;
};

const clientHref = await firstLink('/clients', 'main a[href*="/clients/"]:not([href$="create"])');
const siteHref = await firstLink('/sites', 'main a[href*="/sites/"]:not([href*="create"]):not([href*="import"])');
const reportHref = await firstLink('/reports', 'main a[href*="/reports/"]:not([href$="create"])');
const templateHref = await firstLink('/templates', 'main a[href*="/templates/"]:not([href$="create"])');

const screens = {
    dashboard: '/dashboard',
    clients: '/clients',
    'client-show': clientHref,
    'client-edit': clientHref && `${clientHref}/edit`,
    'client-branding': clientHref && `${clientHref}/branding`,
    sites: '/sites',
    'site-show': siteHref,
    'site-edit': siteHref && `${siteHref}/edit`,
    'site-create': '/sites/create',
    'site-import': '/sites/import',
    reports: '/reports',
    'reports-create': '/reports/create',
    'report-show': reportHref,
    'report-builder': reportHref && `${reportHref}/edit`,
    templates: '/templates',
    'template-edit': templateHref,
    integrations: '/integrations',
    activity: '/activity',
    branding: '/branding',
    users: '/users',
    'user-create': '/users/create',
    settings: '/settings',
    'settings-ai': '/settings/ai',
    'settings-2fa': '/settings/two-factor',
    profile: '/settings/profile',
};

// Which captures feed the documentation, and the file each one refreshes.
const docImages = {
    dashboard: 'dashboard.png',
    integrations: 'integrations.png',
    branding: 'branding.png',
    settings: 'settings.png',
    'report-builder': 'report-builder.png',
};

for (const [name, route] of Object.entries(screens)) {
    if (!route || (only.length && !only.includes(name))) continue;
    const url = route.startsWith('http') ? route : BASE + route;
    try {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(400);
        await page.screenshot({ path: `${OUT}/${name}-desktop.png`, fullPage: true });
        if (DOCS && docImages[name]) {
            await page.screenshot({ path: path.resolve('docs/images', docImages[name]), fullPage: false });
        }
        await page.setViewportSize({ width: 390, height: 844 });
        await page.waitForTimeout(300);
        await page.screenshot({ path: `${OUT}/${name}-mobile.png`, fullPage: true });
        console.log('ok  ', name);
    } catch (error) {
        console.log('FAIL', name, error.message.split('\n')[0]);
    }
}

// The client portal, seen as a portal user.
if (process.env.SHOTS_CLIENT_EMAIL && (!only.length || only.includes('portal'))) {
    const portal = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
    const client = await portal.newPage();
    try {
        await client.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
        await client.fill('input[type=email]', process.env.SHOTS_CLIENT_EMAIL);
        await client.fill('input[type=password]', process.env.SHOTS_CLIENT_PASSWORD ?? 'password');
        await client.keyboard.press('Enter');
        await client.waitForURL(/portal/, { timeout: 15000 });
        await client.waitForLoadState('networkidle');
        await client.screenshot({ path: `${OUT}/portal-desktop.png`, fullPage: true });
        if (DOCS) await client.screenshot({ path: path.resolve('docs/images/client-portal.png'), fullPage: false });
        await client.setViewportSize({ width: 390, height: 844 });
        await client.screenshot({ path: `${OUT}/portal-mobile.png`, fullPage: true });
        console.log('ok   portal');
    } catch (error) {
        console.log('FAIL portal', error.message.split('\n')[0]);
    }
    await portal.close();
}

await browser.close();
console.log(`Saved to ${OUT}`);
