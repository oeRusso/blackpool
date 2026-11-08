import { test as setup } from '@playwright/test';
import { authFile, login, USERS, type Role } from './support';

for (const role of Object.keys(USERS) as Role[]) {
    setup(`authenticate as ${role}`, async ({ page }) => {
        await login(page, USERS[role]);
        await page.context().storageState({ path: authFile(role) });
    });
}
