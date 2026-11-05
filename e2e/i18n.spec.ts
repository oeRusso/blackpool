import { expect, test, type Page } from '@playwright/test';
import { authFile, expectToast, USERS } from './support';

async function switchLanguage(page: Page, locale: 'en' | 'es'): Promise<void> {
    await page.getByTestId('locale-switcher').first().click();
    await page.getByTestId(`locale-${locale}`).click();
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
}

test.describe('guests', () => {
    test('switch the interface to Spanish and keep it while browsing', async ({
        page,
    }) => {
        await page.goto('/');
        await expect(page.getByTestId('hero-cta')).toHaveText(/Start for free/);

        await switchLanguage(page, 'es');
        await expect(
            page.getByRole('heading', {
                name: /Aprende inglés,\s*nivel a nivel/,
            }),
        ).toBeVisible();
        await expect(page.getByTestId('hero-cta')).toHaveText(/Empieza gratis/);

        await page.getByTestId('home-level-A1').click();
        await expect(
            page.getByRole('heading', { level: 1, name: 'Lecciones' }),
        ).toBeVisible();
        await expect(page.getByTestId('level-A1')).toContainText(
            'Principiante',
        );
        // Lesson content itself stays in English.
        await expect(
            page.getByTestId('lesson-card-the-verb-to-be'),
        ).toContainText('The verb "to be"');

        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('lang', 'es');
        await expect(
            page.getByRole('heading', { level: 1, name: 'Lecciones' }),
        ).toBeVisible();

        await switchLanguage(page, 'en');
        await expect(
            page.getByRole('heading', { level: 1, name: 'Lessons' }),
        ).toBeVisible();
    });
});

test.describe('Spanish-speaking browsers', () => {
    test.use({ locale: 'es-ES' });

    test('get Spanish by default, including server messages', async ({
        page,
    }) => {
        await page.goto('/login');
        await expect(page.locator('html')).toHaveAttribute('lang', 'es');
        await expect(
            page.getByRole('heading', { name: 'Inicia sesión en tu cuenta' }),
        ).toBeVisible();

        await page.getByLabel('Correo electrónico').fill(USERS.student);
        await page
            .getByRole('textbox', { name: 'Contraseña' })
            .fill('wrong-password');
        await page.getByTestId('login-button').click();
        await expect(
            page.getByText(
                'Estas credenciales no coinciden con nuestros registros.',
            ),
        ).toBeVisible();
    });

    test('see translated error pages', async ({ page }) => {
        const response = await page.goto('/this-page-does-not-exist');
        expect(response?.status()).toBe(404);
        await expect(page.getByTestId('error-page')).toContainText(
            'Página no encontrada',
        );
    });
});

test.describe('signed-in users', () => {
    test.use({ storageState: authFile('premium') });

    test('save the language on their account', async ({ page, browser }) => {
        await page.goto('/dashboard');
        await switchLanguage(page, 'es');
        await expect(
            page.getByRole('heading', { name: /Hola de nuevo/ }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Ver lecciones' }).first(),
        ).toBeVisible();

        // Server-side messages follow the account language.
        await page.goto('/lessons/numbers-dates-and-time');
        await page.getByTestId('toggle-favorite').click();
        await expectToast(page, 'Guardada en tus favoritos.');
        await page.getByTestId('toggle-favorite').click();
        await expectToast(page, 'Eliminada de tus favoritos.');

        // The account remembers it even without the cookie (another device).
        const state = await page.context().storageState();
        const other = await browser.newContext({
            storageState: {
                ...state,
                cookies: state.cookies.filter((c) => c.name !== 'locale'),
            },
        });
        const otherPage = await other.newPage();
        await otherPage.goto('/dashboard');
        await expect(otherPage.locator('html')).toHaveAttribute('lang', 'es');
        await other.close();

        await switchLanguage(page, 'en');
        await expect(
            page.getByRole('link', { name: 'My learning' }).first(),
        ).toBeVisible();
    });
});
