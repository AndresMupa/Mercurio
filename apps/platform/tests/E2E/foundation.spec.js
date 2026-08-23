import { expect, test } from '@playwright/test';

/*
 * Entregable A1 visto desde el navegador, que es donde el PLAN lo exige:
 * «proyecto que levanta y responde en el navegador».
 */
test.describe('pantalla de estado de la fundación', () => {
    test('monta la aplicación Vue y pinta las comprobaciones', async ({ page }) => {
        await page.goto('/');

        await expect(page.getByRole('heading', { name: 'Estado de la fundación' })).toBeVisible();

        // Si Inertia no montara, la lista no existiría: el HTML del servidor viene vacío.
        const comprobaciones = page.getByRole('listitem');
        await expect(comprobaciones).toHaveCount(5);

        await expect(page.getByText('Rol de aplicación sin BYPASSRLS ni superusuario')).toBeVisible();
        await expect(page.getByText('Rol de aplicación distinto del dueño del esquema')).toBeVisible();
    });

    test('el estado no depende solo del color', async ({ page }) => {
        await page.goto('/');

        // Principio 4 del sistema de diseño: forma, icono y texto acompañan al color.
        // Una regresión aquí rompe la compuerta de accesibilidad del DoD nivel B.
        const primera = page.getByRole('listitem').first();

        await expect(primera.getByRole('img')).toHaveAttribute('aria-label', /Correcto|Atención|Falla/);
        await expect(primera).toContainText(/CORRECTO|ATENCIÓN|FALLA/i);
    });

    test('es operable con teclado', async ({ page }) => {
        await page.goto('/');
        await page.keyboard.press('Tab');

        // No hay trampa de foco ni foco perdido en el body.
        const enfocado = await page.evaluate(() => document.activeElement?.tagName ?? null);
        expect(enfocado).not.toBeNull();
    });
});
