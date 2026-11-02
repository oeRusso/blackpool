import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { authFile } from './support';

/** Fails on serious or critical WCAG 2.1 AA violations. */
async function expectAccessible(page: Page): Promise<void> {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();
    const blocking = results.violations.filter((v) =>
        ['serious', 'critical'].includes(v.impact ?? ''),
    );
    expect(
        blocking.map(
            (v) =>
                `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`,
        ),
    ).toEqual([]);
}

test('landing page shows levels, free lessons and a call to action', async ({
    page,
}) => {
    await page.goto('/');
    await expect(
        page.getByRole('heading', { name: /Learn English,\s*level by level/ }),
    ).toBeVisible();
    await expect(page.getByTestId('hero-cta')).toHaveAttribute(
        'href',
        '/register',
    );
    await expect(page.locator('[data-test^="home-level-"]')).toHaveCount(6);
    await expect(page.locator('[data-test^="lesson-card-"]')).toHaveCount(6);

    await page.getByTestId('home-level-B2').click();
    await expect(page).toHaveURL(/\/lessons\?level=B2/);
});

test('unknown pages render the branded 404 page', async ({ page }) => {
    const response = await page.goto('/this-page-does-not-exist');
    expect(response?.status()).toBe(404);
    await expect(page.getByTestId('error-page')).toContainText(
        'Page not found',
    );
});

test.describe('accessibility (axe, WCAG 2.1 AA)', () => {
    for (const url of [
        '/',
        '/lessons',
        '/lessons/the-verb-to-be',
        '/login',
        '/register',
    ]) {
        test(`public page ${url}`, async ({ page }) => {
            await page.goto(url);
            await expectAccessible(page);
        });
    }

    test.describe('student pages', () => {
        test.use({ storageState: authFile('student') });
        for (const url of [
            '/dashboard',
            '/lessons/hello-greetings-and-introductions',
        ]) {
            test(url, async ({ page }) => {
                await page.goto(url);
                await expectAccessible(page);
            });
        }
    });

    test.describe('admin pages', () => {
        test.use({ storageState: authFile('admin') });
        for (const url of [
            '/admin',
            '/admin/lessons',
            '/admin/categories',
            '/admin/users',
            '/admin/roles',
        ]) {
            test(url, async ({ page }) => {
                await page.goto(url);
                await expectAccessible(page);
            });
        }
    });
});

test.describe('screenshots for the README', () => {
    test.skip(!process.env.SCREENSHOTS, 'Set SCREENSHOTS=1 to capture.');

    const shots: [string, string, string | undefined][] = [
        ['home', '/', undefined],
        ['catalog', '/lessons', undefined],
        ['lesson', '/lessons/hello-greetings-and-introductions', 'student'],
        ['dashboard', '/dashboard', 'student'],
        ['admin-dashboard', '/admin', 'admin'],
        ['admin-lessons', '/admin/lessons', 'admin'],
        ['admin-lesson-edit', '/admin/lessons/6/edit', 'admin'],
    ];

    for (const [name, url, role] of shots) {
        test(name, async ({ browser }) => {
            for (const scheme of ['light', 'dark'] as const) {
                const context = await browser.newContext({
                    storageState: role
                        ? authFile(role as 'admin')
                        : { cookies: [], origins: [] },
                    colorScheme: scheme,
                    viewport: { width: 1440, height: 900 },
                });
                const page = await context.newPage();
                await page.goto(url);
                await page.waitForLoadState('networkidle');
                await page.screenshot({
                    path: `docs/screenshots/${name}-${scheme}.png`,
                });
                await context.close();
            }
        });
    }
});
