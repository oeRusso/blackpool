import { expect, test } from '@playwright/test';
import { login, USERS } from './support';

test('a visitor can register and lands on email verification', async ({
    page,
}) => {
    const email = `learner-${Date.now()}@example.com`;

    await page.goto('/register');
    await page.getByLabel('Name').fill('New Learner');
    await page.getByLabel('Email address').fill(email);
    await page
        .getByRole('textbox', { name: 'Password', exact: true })
        .fill('a-Very-strong-pass-123');
    await page
        .getByRole('textbox', { name: 'Confirm password' })
        .fill('a-Very-strong-pass-123');
    await page.getByTestId('register-user-button').click();

    await expect(page).toHaveURL(/email\/verify/);
    await expect(page.getByText(/verify your email/i).first()).toBeVisible();
});

test('wrong credentials are rejected', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(USERS.student);
    await page
        .getByRole('textbox', { name: 'Password' })
        .fill('not-the-password');
    await page.getByTestId('login-button').click();

    await expect(
        page.getByText(/these credentials do not match/i),
    ).toBeVisible();
});

test('every demo account can log in', async ({ page }) => {
    for (const email of Object.values(USERS)) {
        await page.context().clearCookies();
        await login(page, email);
    }
});
