import { expect, test } from '@playwright/test';
import { authFile, trackCspViolations } from './support';

test('responses carry security headers', async ({ request }) => {
    const response = await request.get('/');
    const headers = response.headers();

    expect(headers['content-security-policy']).toContain("object-src 'none'");
    expect(headers['x-frame-options']).toBe('SAMEORIGIN');
    expect(headers['x-content-type-options']).toBe('nosniff');
    expect(headers['referrer-policy']).toBe('strict-origin-when-cross-origin');
});

test.describe('no CSP violations', () => {
    test('on public pages', async ({ page }) => {
        const violations = trackCspViolations(page);
        for (const url of [
            '/',
            '/lessons',
            '/lessons/the-verb-to-be',
            '/login',
        ]) {
            await page.goto(url);
            await page.waitForLoadState('networkidle');
        }
        expect(violations).toEqual([]);
    });

    test.describe('as an admin', () => {
        test.use({ storageState: authFile('admin') });

        test('on admin pages', async ({ page }) => {
            const violations = trackCspViolations(page);
            for (const url of [
                '/admin',
                '/admin/lessons',
                '/admin/lessons/6/edit',
                '/admin/users',
            ]) {
                await page.goto(url);
                await page.waitForLoadState('networkidle');
            }
            expect(violations).toEqual([]);
        });
    });
});
