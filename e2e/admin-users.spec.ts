import { expect, test } from '@playwright/test';
import { authFile, expectToast, USERS } from './support';

test.use({ storageState: authFile('admin') });

test('an admin grants and revokes premium access', async ({ page }) => {
    await page.goto('/admin/users?search=student');
    const row = page.getByTestId(`user-row-${USERS.student}`);
    await row.getByRole('button', { name: 'Roles' }).click();

    await page.getByTestId('role-premium').check();
    await page.getByTestId('save-roles').click();
    await expectToast(page, 'Roles updated for Sam Student.');
    await expect(row.getByText('premium')).toBeVisible();

    await row.getByRole('button', { name: 'Roles' }).click();
    await page.getByTestId('role-premium').uncheck();
    await page.getByTestId('save-roles').click();
    await expectToast(page, 'Roles updated for Sam Student.');
    await expect(row.getByText('premium')).toHaveCount(0);
});

test('admins cannot edit their own roles', async ({ page }) => {
    await page.goto('/admin/users?search=admin');
    const row = page.getByTestId(`user-row-${USERS.admin}`);
    await expect(row.getByText('You')).toBeVisible();
    await expect(row.getByRole('button', { name: 'Roles' })).toHaveCount(0);
});

test('role permissions can be edited except for admin', async ({ page }) => {
    await page.goto('/admin/roles');
    await expect(page.getByTestId('save-role-admin')).toHaveCount(0);

    const premium = page.getByTestId('role-card-premium');
    await premium.getByLabel('Publish and unpublish lessons').check();
    await page.getByTestId('save-role-premium').click();
    await expectToast(page, 'Permissions updated for premium.');

    await premium.getByLabel('Publish and unpublish lessons').uncheck();
    await page.getByTestId('save-role-premium').click();
    await expectToast(page, 'Permissions updated for premium.');
});
