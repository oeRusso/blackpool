import { expect, test } from '@playwright/test';
import { authFile, expectToast } from './support';

test.use({ storageState: authFile('editor') });

test.describe.configure({ mode: 'serial' });

test('dashboard shows content statistics', async ({ page }) => {
    await page.goto('/admin');
    await expect(page.getByRole('heading', { name: 'Overview' })).toBeVisible();
    await expect(page.getByTestId('stat-lessons')).toHaveText('31');
    await expect(page.getByText('Lessons per CEFR level')).toBeVisible();
});

test('create, rename, and delete a category', async ({ page }) => {
    await page.goto('/admin/categories');

    await page.getByTestId('create-category').click();
    await page.getByLabel('Name').fill('Pronunciation');
    await page.getByLabel('Description').fill('Sounds, stress and intonation.');
    await page.getByTestId('save-category').click();
    await expectToast(page, 'Category "Pronunciation" created.');
    await expect(page.getByTestId('category-row-pronunciation')).toBeVisible();

    await page.getByRole('button', { name: 'Edit Pronunciation' }).click();
    await page.getByLabel('Name').fill('Pronunciation & Accent');
    await page.getByTestId('save-category').click();
    await expectToast(page, 'Category updated.');
    await expect(
        page.getByTestId('category-row-pronunciation-accent'),
    ).toBeVisible();

    await page
        .getByRole('button', { name: 'Delete Pronunciation & Accent' })
        .click();
    await page
        .getByRole('alertdialog')
        .getByRole('button', { name: 'Delete' })
        .click();
    await expectToast(page, 'Category deleted.');
    await expect(
        page.getByTestId('category-row-pronunciation-accent'),
    ).toHaveCount(0);
});

test('duplicate category names are rejected', async ({ page }) => {
    await page.goto('/admin/categories');
    await page.getByTestId('create-category').click();
    await page.getByLabel('Name').fill('Grammar');
    await page.getByTestId('save-category').click();
    await expect(
        page.getByText('A category with this name already exists.'),
    ).toBeVisible();
});

test('a category with lessons cannot be deleted', async ({ page }) => {
    await page.goto('/admin/categories?search=Grammar');
    await page.getByRole('button', { name: 'Delete Grammar' }).click();
    await page
        .getByRole('alertdialog')
        .getByRole('button', { name: 'Delete' })
        .click();
    await expectToast(page, 'This category still has lessons');
    await expect(page.getByTestId('category-row-grammar')).toBeVisible();
});

test('search and filter lessons', async ({ page }) => {
    await page.goto('/admin/lessons');
    await page.getByPlaceholder('Search lessons…').fill('passive');
    await expect(
        page.getByTestId('lesson-row-mastering-the-passive-voice'),
    ).toBeVisible();
    await expect(page.locator('[data-test^="lesson-row-"]')).toHaveCount(1);

    await page.goto('/admin/lessons?status=draft');
    await expect(
        page.getByTestId('lesson-row-reported-speech-draft'),
    ).toBeVisible();
});

test('create a lesson with cover and worksheet, then publish and delete it', async ({
    page,
}) => {
    await page.goto('/admin/lessons/create');

    await page.getByLabel('Title').fill('Modal verbs of deduction');
    await page
        .getByLabel('Summary')
        .fill('Must, might and can’t for making deductions.');
    await page
        .getByLabel('Lesson notes')
        .fill('We use must when we are sure something is true.');
    await page
        .getByLabel('CEFR level')
        .selectOption({ label: 'B2 · Upper intermediate' });
    await page.getByLabel('Grammar').check();
    await page.getByTestId('is-free').check();
    await page
        .getByLabel('Cover image')
        .setInputFiles('e2e/fixtures/cover.png');
    await page
        .getByLabel('PDF worksheet')
        .setInputFiles('e2e/fixtures/worksheet.pdf');
    await page.getByTestId('save-lesson').click();

    await expectToast(page, 'Lesson created.');
    await expect(page).toHaveURL(/\/admin\/lessons\/\d+\/edit$/);
    await expect(page.getByTestId('has-pdf')).toBeVisible();
    await expect(page.getByAltText('Cover preview')).toHaveAttribute(
        'src',
        /\/storage\/covers\/.+\.webp$/,
    );

    await page.getByLabel('Status').selectOption('published');
    await page.getByTestId('save-lesson').click();
    await expectToast(page, 'Lesson saved.');

    await page.goto('/admin/lessons?search=deduction');
    const row = page.getByTestId('lesson-row-modal-verbs-of-deduction');
    await expect(row.getByText('published')).toBeVisible();

    await row.getByRole('button', { name: /Delete/ }).click();
    await page
        .getByRole('alertdialog')
        .getByRole('button', { name: 'Delete' })
        .click();
    await expectToast(page, 'Lesson moved to trash.');
    await expect(row).toHaveCount(0);
});

test('lesson validation errors are displayed', async ({ page }) => {
    await page.goto('/admin/lessons/create');
    await page.getByLabel('Title').fill('Incomplete');
    await page.getByLabel('Summary').fill('Missing level and categories');
    await page.evaluate(() =>
        document
            .querySelectorAll('[required]')
            .forEach((el) => el.removeAttribute('required')),
    );
    await page.getByTestId('save-lesson').click();
    await expect(page.getByText('The level field is required.')).toBeVisible();
    await expect(
        page.getByText('The categories field is required.'),
    ).toBeVisible();
});
