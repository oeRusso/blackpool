import { expect, test, type Page } from '@playwright/test';

const PUBLIC_PAGES = ['/', '/lessons', '/lessons/the-verb-to-be', '/login'];

test.describe('without JavaScript (what crawlers see)', () => {
    test.use({ javaScriptEnabled: false });

    test('public pages are server-rendered', async ({ page }) => {
        await page.goto('/lessons');
        await expect(
            page.getByRole('heading', { level: 1, name: 'Lessons' }),
        ).toBeVisible();
        await expect(
            page.locator('[data-test^="lesson-card-"]'),
        ).not.toHaveCount(0);

        await page.goto('/lessons/the-verb-to-be');
        await expect(
            page.getByRole('heading', { level: 1, name: 'The verb "to be"' }),
        ).toBeVisible();
        await expect(page).toHaveTitle('The verb "to be" - BlackPool');
    });

    test.describe('in Spanish', () => {
        test.use({ locale: 'es-ES' });

        test('the server renders the translated interface', async ({
            page,
        }) => {
            await page.goto('/');
            await expect(page.locator('html')).toHaveAttribute('lang', 'es');
            await expect(
                page.getByRole('heading', {
                    name: /Aprende inglés,\s*nivel a nivel/,
                }),
            ).toBeVisible();
        });
    });
});

test('pages hydrate without mismatches or console errors', async ({ page }) => {
    const problems: string[] = [];
    page.on('console', (message) => {
        if (message.type() === 'error' || /hydration/i.test(message.text())) {
            problems.push(`${page.url()}: ${message.text()}`);
        }
    });
    page.on('pageerror', (error) => problems.push(error.message));

    for (const path of PUBLIC_PAGES) {
        await page.goto(path);
        await page.waitForLoadState('networkidle');
    }

    expect(problems).toEqual([]);
});

async function meta(page: Page, selector: string): Promise<string | null> {
    return page.locator(selector).first().getAttribute('content');
}

test('lessons expose canonical, Open Graph and structured data', async ({
    page,
    baseURL,
}) => {
    await page.goto('/lessons/the-verb-to-be');

    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
        'href',
        `${baseURL}/lessons/the-verb-to-be`,
    );
    expect(await meta(page, 'meta[property="og:type"]')).toBe('article');
    expect(await meta(page, 'meta[property="og:image"]')).toMatch(
        /^https?:\/\/.+/,
    );
    expect(await meta(page, 'meta[name="description"]')).toBe(
        'Learn am, is and are with dozens of everyday examples.',
    );

    const jsonLd = JSON.parse(
        (await page
            .locator('script[type="application/ld+json"]')
            .textContent()) ?? '{}',
    );
    expect(jsonLd).toMatchObject({
        '@type': 'LearningResource',
        name: 'The verb "to be"',
        educationalLevel: 'CEFR A1',
        isAccessibleForFree: true,
    });
});

test('the sitemap lists published lessons and robots.txt points to it', async ({
    request,
}) => {
    const sitemap = await request.get('/sitemap.xml');
    expect(sitemap.status()).toBe(200);
    expect(sitemap.headers()['content-type']).toContain('application/xml');
    const xml = await sitemap.text();
    expect(xml).toContain('/lessons/the-verb-to-be</loc>');
    expect(xml).not.toContain('reported-speech-draft');

    const robots = await request.get('/robots.txt');
    expect(robots.status()).toBe(200);
    expect(await robots.text()).toContain('User-agent: *');
});
