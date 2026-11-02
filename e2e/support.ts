import { expect, type Page } from '@playwright/test';

export const PASSWORD = 'password';

export const USERS = {
    admin: 'admin@blackpool.test',
    editor: 'editor@blackpool.test',
    premium: 'premium@blackpool.test',
    student: 'student@blackpool.test',
} as const;

export type Role = keyof typeof USERS;

export const authFile = (role: Role): string => `e2e/.auth/${role}.json`;

export async function login(
    page: Page,
    email: string,
    password = PASSWORD,
): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await page.getByTestId('login-button').click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

/** Waits for a Sonner toast containing the given text. */
export async function expectToast(
    page: Page,
    text: string | RegExp,
): Promise<void> {
    await expect(
        page.locator('[data-sonner-toast]').filter({ hasText: text }).first(),
    ).toBeVisible();
}
