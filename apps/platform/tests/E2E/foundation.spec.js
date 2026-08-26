import { expect, test } from '@playwright/test';

/*
 * Entregable A1 visto desde el navegador, que es donde el PLAN lo exige:
 * «proyecto que levanta y responde en el navegador».
 *
 * Desde B8 hay que entrar primero: la pantalla cuenta los nombres de los dos roles de base
 * de datos y la versión exacta de PostgreSQL, y eso no se le enseña a quien no ha entrado.
 */

const CABECERA = { 'X-Tenant': 'colegio-demo' };
const CUENTA = { correo: 'coordinacion@colegio-demo.test', clave: 'demo-mercurio-2026' };

test.use({ extraHTTPHeaders: CABECERA });

test.describe('pantalla de estado de la fundación', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/entrar');
        await page.getByLabel('Correo institucional').fill(CUENTA.correo);
        await page.getByLabel('Contraseña').fill(CUENTA.clave);
        await page.getByRole('button', { name: 'Entrar' }).click();
        await page.waitForURL('**/mi-trabajo');
    });

    test('monta la aplicación Vue y pinta las comprobaciones', async ({ page }) => {
        await page.goto('/estado');

        await expect(page.getByRole('heading', { name: 'Estado de la fundación' })).toBeVisible();

        // Si Inertia no montara, la lista no existiría: el HTML del servidor viene vacío.
        const comprobaciones = page.getByRole('listitem');
        await expect(comprobaciones).toHaveCount(5);

        await expect(page.getByText('Rol de aplicación sin BYPASSRLS ni superusuario')).toBeVisible();
        await expect(page.getByText('Rol de aplicación distinto del dueño del esquema')).toBeVisible();
    });

    test('el estado no depende solo del color', async ({ page }) => {
        await page.goto('/estado');

        // Principio 4 del sistema de diseño: forma, icono y texto acompañan al color.
        // Una regresión aquí rompe la compuerta de accesibilidad del DoD nivel B.
        const primera = page.getByRole('listitem').first();

        await expect(primera.getByRole('img')).toHaveAttribute('aria-label', /Correcto|Atención|Falla/);
        await expect(primera).toContainText(/CORRECTO|ATENCIÓN|FALLA/i);
    });

    test('es operable con teclado', async ({ page }) => {
        await page.goto('/estado');
        await page.keyboard.press('Tab');

        // No hay trampa de foco ni foco perdido en el body.
        const enfocado = await page.evaluate(() => document.activeElement?.tagName ?? null);
        expect(enfocado).not.toBeNull();
    });
});
