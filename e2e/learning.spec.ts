import { expect, test } from '@playwright/test';
import { authFile, expectToast } from './support';

test.describe('guests', () => {
    test('browse and filter the catalog', async ({ page }) => {
        await page.goto('/lessons');
        await expect(
            page.getByRole('heading', { name: 'Lessons', level: 1 }),
        ).toBeVisible();
        await expect(page.getByText('30 lessons')).toBeVisible();

        await page.getByTestId('level-C2').click();
        await expect(page.locator('[data-test^="lesson-card-"]')).toHaveCount(
            5,
        );
        await expect(page).toHaveURL(/level=C2/);

        await page.getByLabel('Access').selectOption('free');
        await expect(page.locator('[data-test^="lesson-card-"]')).toHaveCount(
            1,
        );
        await expect(
            page.getByTestId('lesson-card-nuance-and-register'),
        ).toBeVisible();

        await page.goto('/lessons?q=zzzz-nothing');
        await expect(
            page.getByText('No lessons match your search'),
        ).toBeVisible();
    });

    test('see a locked lesson that invites them to register', async ({
        page,
    }) => {
        await page.goto('/lessons/the-verb-to-be');
        await expect(
            page.getByRole('heading', { name: 'The verb "to be"' }),
        ).toBeVisible();
        await expect(page.getByTestId('locked-lesson')).toContainText(
            'Create a free account to watch this lesson.',
        );
        await expect(
            page.getByRole('link', { name: 'Create free account' }),
        ).toBeVisible();
        await expect(page.getByTestId('quiz')).toHaveCount(0);
    });

    test('get 403 on draft lessons', async ({ page }) => {
        const response = await page.goto('/lessons/reported-speech-draft');
        expect(response?.status()).toBe(403);
    });
});

test.describe('students', () => {
    test.use({ storageState: authFile('student') });
    test.describe.configure({ mode: 'serial' });

    test('see their progress on the dashboard', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page.getByTestId('stat-completed')).toHaveText('1/30');
        await expect(page.getByTestId('level-progress-A1')).toContainText(
            '1/5',
        );
        await expect(page.getByTestId('favorites')).toContainText(
            'Hello! Greetings and introductions',
        );
        await expect(page.getByTestId('next-lesson')).toBeVisible();
    });

    test('fail and then pass a quiz, completing the lesson', async ({
        page,
    }) => {
        await page.goto('/lessons/the-verb-to-be');
        const quiz = page.getByTestId('quiz');
        await expect(quiz).toBeVisible();

        // Wrong answers first.
        await quiz
            .getByRole('group', { name: '1. She ___ a teacher.' })
            .getByLabel('are')
            .check();
        await quiz
            .getByRole('group', { name: '2. We ___ from Spain.' })
            .getByLabel('is')
            .check();
        await quiz
            .getByRole('group', { name: '3. I ___ hungry.' })
            .getByLabel('is')
            .check();
        await page.getByTestId('submit-quiz').click();
        await expect(page.getByTestId('quiz-result')).toContainText(
            'You scored 0/3',
        );
        await expect(page.getByTestId('completed-badge')).toHaveCount(0);

        await page.getByTestId('retry-quiz').click();
        await quiz
            .getByRole('group', { name: '1. She ___ a teacher.' })
            .getByLabel('is')
            .check();
        await quiz
            .getByRole('group', { name: '2. We ___ from Spain.' })
            .getByLabel('are')
            .check();
        await quiz
            .getByRole('group', { name: '3. I ___ hungry.' })
            .getByLabel('am')
            .check();
        await page.getByTestId('submit-quiz').click();

        await expectToast(page, 'Great job! You scored 3/3');
        await expect(page.getByTestId('quiz-result')).toContainText('Passed!');
        await expect(page.getByTestId('completed-badge')).toBeVisible();

        await page.goto('/dashboard');
        await expect(page.getByTestId('stat-completed')).toHaveText('2/30');
        await expect(page.getByTestId('stat-quizzes')).toHaveText('1');
    });

    test('save a lesson and mark it complete manually', async ({ page }) => {
        await page.goto('/lessons/numbers-dates-and-time');
        await expect(page.getByTestId('locked-lesson')).toContainText(
            'This is a premium lesson.',
        );
        await expect(page.getByTestId('toggle-complete')).toHaveCount(0);

        await page.goto('/lessons/present-perfect-explained');
        await page.getByTestId('toggle-favorite').click();
        await expectToast(page, 'Saved to your favourites.');
        await page.getByTestId('toggle-complete').click();
        await expectToast(page, 'Lesson completed. Keep going!');
        await expect(page.getByTestId('completed-badge')).toBeVisible();
    });

    test('navigate between lessons of the same level', async ({ page }) => {
        await page.goto('/lessons/hello-greetings-and-introductions');
        await page.getByTestId('next-lesson').click();
        await expect(page).toHaveURL(/\/lessons\/the-verb-to-be$/);
        await page.getByTestId('previous-lesson').click();
        await expect(page).toHaveURL(
            /\/lessons\/hello-greetings-and-introductions$/,
        );
    });
});

test.describe('premium members', () => {
    test.use({ storageState: authFile('premium') });

    test('can open premium lessons', async ({ page }) => {
        await page.goto('/lessons/numbers-dates-and-time');
        await expect(page.getByTestId('locked-lesson')).toHaveCount(0);
        await expect(page.getByTestId('toggle-complete')).toBeVisible();
    });
});
