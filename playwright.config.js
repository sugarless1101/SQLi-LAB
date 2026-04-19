import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30000,
    use: {
        baseURL: 'http://127.0.0.1:8080',
        headless: true,
    },
    reporter: [['list']],
});
