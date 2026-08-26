import { expect, test } from '@playwright/test';

/*
 * La compuerta de accesibilidad del DoD nivel B, sobre las pantallas de verdad.
 *
 * No comprueba que exista un atributo ARIA: comprueba que los recorridos **se puedan
 * hacer**. La diferencia importa —una pantalla puede tener todos los `aria-label` del
 * mundo y no ser operable con teclado— y es la razón de que estas pruebas naveguen en
 * vez de inspeccionar.
 *
 * El tenant se resuelve por la cabecera `X-Tenant` porque en local no hay subdominios.
 * Es el mismo camino que documenta `TenantResolver`, no un atajo de pruebas.
 */

const CABECERA = { 'X-Tenant': 'colegio-demo' };
const CUENTA = { correo: 'coordinacion@colegio-demo.test', clave: 'demo-mercurio-2026' };

test.use({ extraHTTPHeaders: CABECERA });

async function entrar(page) {
    await page.goto('/entrar');
    await page.getByLabel('Correo institucional').fill(CUENTA.correo);
    await page.getByLabel('Contraseña').fill(CUENTA.clave);
    await page.getByRole('button', { name: 'Entrar' }).click();
    await page.waitForURL('**/mi-trabajo');
}

test.describe('acceso', () => {
    test('el formulario se completa y se envía solo con el teclado', async ({ page }) => {
        await page.goto('/entrar');

        // El correo ya viene enfocado: es una pantalla de un solo propósito y llevar el
        // foco al primer campo ahorra una parada a todo el mundo. Tabular desde aquí lo
        // pasaría de largo, así que se escribe directamente.
        await expect(page.getByLabel('Correo institucional')).toBeFocused();
        await page.keyboard.type(CUENTA.correo);

        await page.keyboard.press('Tab');
        await expect(page.getByLabel('Contraseña')).toBeFocused();
        await page.keyboard.type(CUENTA.clave);

        await page.keyboard.press('Enter');
        await page.waitForURL('**/mi-trabajo');
    });

    test('el fallo se anuncia como alerta y no dice cuál de los dos datos falló', async ({ page }) => {
        await page.goto('/entrar');
        await page.getByLabel('Correo institucional').fill('nadie@colegio-demo.test');
        await page.getByLabel('Contraseña').fill('lo-que-sea');
        await page.getByRole('button', { name: 'Entrar' }).click();

        const alerta = page.getByRole('alert');
        await expect(alerta).toBeVisible();

        // No enumeración: el texto no distingue «no existe» de «contraseña incorrecta».
        const texto = (await alerta.textContent()).toLowerCase();
        expect(texto).not.toContain('no existe');
        expect(texto).not.toContain('contraseña incorrecta');
    });
});

test.describe('marco de la aplicación', () => {
    test.beforeEach(async ({ page }) => entrar(page));

    test('el salto al contenido es lo primero que recibe el foco', async ({ page }) => {
        await page.keyboard.press('Tab');

        const saltar = page.getByRole('link', { name: 'Saltar al contenido' });
        await expect(saltar).toBeFocused();
        // Y es visible al enfocarlo: un salto invisible es un salto que nadie usa.
        await expect(saltar).toBeVisible();

        await page.keyboard.press('Enter');
        await expect(page.locator('#contenido')).toBeFocused();
    });

    test('las regiones tienen nombre y hay un solo encabezado de nivel 1', async ({ page }) => {
        await expect(page.getByRole('navigation', { name: 'Secciones' })).toBeVisible();
        await expect(page.getByRole('main')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
    });

    test('la sección actual se marca con aria-current, no solo con color', async ({ page }) => {
        const actual = page.locator('nav[aria-label="Secciones"] [aria-current="page"]');
        await expect(actual).toHaveCount(1);
        await expect(actual).toContainText('Mi trabajo');
    });

    test('el foco es visible en cada parada del teclado', async ({ page }) => {
        for (let i = 0; i < 6; i++) {
            await page.keyboard.press('Tab');

            const contorno = await page.evaluate(() => {
                const el = document.activeElement;
                if (! el || el === document.body) return null;
                const s = getComputedStyle(el);
                return { ancho: s.outlineWidth, estilo: s.outlineStyle };
            });

            if (contorno) {
                expect(contorno.estilo).not.toBe('none');
                expect(parseFloat(contorno.ancho)).toBeGreaterThan(0);
            }
        }
    });
});

test.describe('el color nunca es el único canal', () => {
    test.beforeEach(async ({ page }) => entrar(page));

    test('cada estado se lee sin color', async ({ page }) => {
        await page.goto('/personas');
        await page.waitForSelector('table');

        // Se le quita el color a la página entera y todo tiene que seguir leyéndose.
        await page.addStyleTag({
            content: '*, *::before, *::after { filter: grayscale(100%) !important; }',
        });

        const estados = page.locator('tbody span:has(svg)');
        const cuantos = await estados.count();
        expect(cuantos).toBeGreaterThan(0);

        for (let i = 0; i < Math.min(cuantos, 8); i++) {
            const pildora = estados.nth(i);
            // Texto: la palabra que dice el estado.
            expect((await pildora.textContent()).trim().length).toBeGreaterThan(2);
            // Forma: el icono propio de ese estado.
            expect(await pildora.locator('svg').count()).toBeGreaterThan(0);
        }
    });
});

test.describe('tabla densa', () => {
    test.beforeEach(async ({ page }) => entrar(page));

    test('la tabla se anuncia con su descripción y sus cabeceras', async ({ page }) => {
        await page.goto('/personas');

        const tabla = page.getByRole('table');
        await expect(tabla).toBeVisible();
        await expect(tabla.locator('caption')).toContainText('Personas con relación laboral');

        // Cabeceras reales, no divs con aspecto de cabecera.
        expect(await tabla.locator('th[scope="col"]').count()).toBeGreaterThan(4);
    });

    test('cada casilla de selección dice a quién selecciona', async ({ page }) => {
        await page.goto('/personas');
        await page.waitForSelector('tbody input[type="checkbox"]');

        const casilla = page.locator('tbody input[type="checkbox"]').first();
        const etiqueta = await casilla.getAttribute('aria-label');

        // Veinte casillas que se llaman todas «Seleccionar» son veinte casillas inútiles.
        expect(etiqueta).toMatch(/^Seleccionar a .+/);
    });

    test('los documentos salen enmascarados', async ({ page }) => {
        await page.goto('/personas');
        await page.waitForSelector('tbody');

        const cuerpo = await page.locator('tbody').textContent();

        // Los documentos del demo empiezan en 90000000. Ninguno completo puede estar aquí.
        expect(cuerpo).not.toMatch(/\b9000\d{4}\b/);
        expect(cuerpo).toContain('••••');
    });
});
