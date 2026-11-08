import { expect, test } from '@playwright/test';
import { authFile, expectToast } from './support';

test('the demo banner and one-click logins are available', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByTestId('demo-banner')).toContainText('Public demo');

    await page.goto('/login');
    await expect(page.getByTestId('demo-login')).toContainText('password');
    await page.getByTestId('demo-login-editor').click();

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(
        page
            .locator('[data-sidebar="sidebar"]')
            .getByRole('link', { name: 'Categories' }),
    ).toBeVisible();
});

test.describe('shared demo accounts', () => {
    test.use({ storageState: authFile('student') });

    test('cannot change their profile', async ({ page }) => {
        await page.goto('/settings/profile');
        await page.getByLabel('Name').fill('Someone else');
        await page.getByTestId('update-profile-button').click();

        await expectToast(
            page,
            'This action is disabled for the shared demo accounts',
        );
        await page.reload();
        await expect(page.getByLabel('Name')).toHaveValue('Sam Student');
    });
});
