import { expect, test } from '@playwright/test';
import { authFile, expectToast } from './support';

test.use({ storageState: authFile('editor') });
test.describe.configure({ mode: 'serial' });

async function openLesson(
    page: import('@playwright/test').Page,
    search: string,
) {
    await page.goto(`/admin/lessons?search=${encodeURIComponent(search)}`);
    await page.locator('[data-test^="lesson-row-"] a').first().click();
    await expect(page.getByTestId('video-manager')).toBeVisible();
}

test('upload a video in chunks and watch it get transcoded to HLS', async ({
    page,
}) => {
    test.setTimeout(180_000);
    await openLesson(page, 'present perfect explained');

    await page
        .getByTestId('video-file')
        .setInputFiles('e2e/fixtures/sample.mp4');

    // Count chunk requests to prove the file is split (1 MB chunks in the e2e env).
    const chunkRequests: string[] = [];
    page.on('request', (request) => {
        if (request.method() === 'PUT' && request.url().includes('/chunks/')) {
            chunkRequests.push(request.url());
        }
    });

    await page.getByTestId('start-upload').click();
    await expect(page.getByTestId('video-status')).toHaveText('processing', {
        timeout: 60_000,
    });
    expect(chunkRequests.length).toBeGreaterThan(1);

    // The page polls until the queue worker finishes the ffmpeg job.
    await expect(page.getByTestId('video-status')).toHaveText('ready', {
        timeout: 120_000,
    });
    await expect(page.getByText('360p · 720p')).toBeVisible();

    const video = page.getByTestId('video-player').locator('video');
    await expect(video).toBeVisible();
    await expect
        .poll(
            async () => video.evaluate((el: HTMLVideoElement) => el.readyState),
            { timeout: 30_000 },
        )
        .toBeGreaterThanOrEqual(1);
    const duration = await video.evaluate(
        (el: HTMLVideoElement) => el.duration,
    );
    expect(Math.round(duration)).toBe(6);
});

test('the HLS stream requires an account and is served to students for free lessons', async ({
    browser,
    page,
}) => {
    await openLesson(page, 'present perfect explained');
    // "Present perfect explained" is a free B1 lesson; the 360p playlist is reachable for students.
    const slug = 'present-perfect-explained';

    const anonymous = await browser.newContext({
        storageState: { cookies: [], origins: [] },
    });
    const guest = await anonymous.request.get(
        `/lessons/${slug}/video/master.m3u8`,
    );
    expect(guest.status()).toBe(403);
    await anonymous.close();

    const student = await browser.newContext({
        storageState: authFile('student'),
    });
    const allowed = await student.request.get(
        `/lessons/${slug}/video/master.m3u8`,
    );
    expect(allowed.status()).toBe(200);
    expect(await allowed.text()).toContain('#EXTM3U');
    await student.close();
});

test('link a YouTube video and remove it', async ({ page }) => {
    await openLesson(page, 'job interviews');

    await page.getByRole('tab', { name: 'YouTube / Vimeo' }).click();
    await page
        .getByPlaceholder('https://www.youtube.com/watch?v=…')
        .fill('https://www.youtube.com/watch?v=jNQXAC9IVRw');
    await page.getByTestId('link-video').click();

    await expectToast(page, 'Video linked.');
    await expect(page.getByTestId('video-status')).toHaveText('ready');
    await expect(page.getByTestId('play-embed')).toBeVisible();

    await page.getByTestId('remove-video').click();
    await page
        .getByRole('alertdialog')
        .getByRole('button', { name: 'Remove' })
        .click();
    await expectToast(page, 'Video removed.');
    await expect(page.getByTestId('video-status')).toHaveCount(0);
});

test('invalid video links are rejected', async ({ page }) => {
    await openLesson(page, 'job interviews');
    await page.getByRole('tab', { name: 'YouTube / Vimeo' }).click();
    await page
        .getByPlaceholder('https://www.youtube.com/watch?v=…')
        .fill('https://example.com/movie.mp4');
    await page.getByTestId('link-video').click();
    await expect(
        page.getByText('Paste a valid YouTube or Vimeo link.'),
    ).toBeVisible();
});

test('unsupported files are rejected before uploading', async ({ page }) => {
    await openLesson(page, 'job interviews');
    await page.getByTestId('video-file').setInputFiles({
        name: 'notes.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('hello'),
    });
    await page.getByTestId('start-upload').click();
    await expect(page.getByText(/Allowed formats/)).toBeVisible();
});
