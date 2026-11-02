import { expect, test } from '@playwright/test';
import { authFile } from './support';

const adminPages = [
    '/admin',
    '/admin/categories',
    '/admin/lessons',
    '/admin/users',
    '/admin/roles',
];

test.describe('students', () => {
    test.use({ storageState: authFile('student') });

    test('do not see the admin navigation', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page.getByRole('link', { name: 'Lessons' })).toHaveCount(
            0,
        );
        await expect(page.getByText('Admin', { exact: true })).toHaveCount(0);
    });

    for (const url of adminPages) {
        test(`get 403 on ${url}`, async ({ page }) => {
            const response = await page.goto(url);
            expect(response?.status()).toBe(403);
        });
    }

    test('cannot create categories with a forged request', async ({ page }) => {
        await page.goto('/dashboard');
        const status = await page.evaluate(async () => {
            const token = decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            );
            const response = await fetch('/admin/categories', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': token,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ name: 'Hacked' }),
            });
            return response.status;
        });
        expect(status).toBe(403);
    });
});

test.describe('editors', () => {
    test.use({ storageState: authFile('editor') });

    test('see content management but not users or roles', async ({ page }) => {
        await page.goto('/admin');
        const sidebar = page.locator('[data-sidebar="sidebar"]');
        await expect(
            sidebar.getByRole('link', { name: 'Lessons' }),
        ).toBeVisible();
        await expect(
            sidebar.getByRole('link', { name: 'Categories' }),
        ).toBeVisible();
        await expect(sidebar.getByRole('link', { name: 'Users' })).toHaveCount(
            0,
        );

        expect((await page.goto('/admin/users'))?.status()).toBe(403);
        expect((await page.goto('/admin/roles'))?.status()).toBe(403);
    });
});

test.describe('admins', () => {
    test.use({ storageState: authFile('admin') });

    for (const url of adminPages) {
        test(`can open ${url}`, async ({ page }) => {
            const response = await page.goto(url);
            expect(response?.status()).toBe(200);
        });
    }
});
