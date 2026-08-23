import { defineConfig, devices } from '@playwright/test';

/*
 * E2E del paso A2. Cubre lo que Pest no puede: que la página llegue montada al
 * navegador. Pest verifica la respuesta de Inertia; solo un navegador real prueba
 * que Vue montó y pintó.
 *
 * PLAYWRIGHT_CHROMIUM_PATH permite usar un Chromium ya instalado en la máquina en
 * lugar del que descarga Playwright. En un entorno normal se deja sin definir y se
 * usa `npx playwright install chromium`.
 */
const chromiumPath = process.env.PLAYWRIGHT_CHROMIUM_PATH || undefined;
const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8080';

export default defineConfig({
    testDir: './tests/E2E',
    // El aislamiento no se prueba aquí: eso vive en el grupo tenant-isolation de Pest,
    // que es lo que bloquea el commit.
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? 'line' : 'list',

    use: {
        baseURL,
        trace: 'on-first-retry',
        launchOptions: chromiumPath ? { executablePath: chromiumPath } : {},
    },

    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],

    // Sin reutilizar servidor en CI: un servidor heredado de otra corrida serviría
    // assets viejos y el fallo aparecería en un sitio que no es el que lo causa.
    webServer: process.env.E2E_BASE_URL
        ? undefined
        : {
            command: 'php artisan serve --host=127.0.0.1 --port=8080',
            url: 'http://127.0.0.1:8080/up',
            reuseExistingServer: !process.env.CI,
            timeout: 60_000,
        },
});
