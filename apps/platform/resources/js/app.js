import './bootstrap';

/*
 * IBM Plex servida por la propia aplicación, no por Google Fonts.
 *
 * Un enlace a fonts.googleapis.com filtra la IP de cada persona del colegio a un tercero
 * en cada carga, y eso en un producto que guarda datos de personas es una transferencia
 * que habría que declarar. Empaquetada pesa unos pocos kilobytes y desaparece el problema.
 *
 * Solo los subconjuntos y pesos que se usan: latin y latin-ext —que trae ñ, tildes y
 * diéresis— en 400, 500 y 600.
 */
import '@fontsource/ibm-plex-sans/latin-400.css';
import '@fontsource/ibm-plex-sans/latin-500.css';
import '@fontsource/ibm-plex-sans/latin-600.css';
import '@fontsource/ibm-plex-sans/latin-ext-400.css';
import '@fontsource/ibm-plex-sans/latin-ext-500.css';
import '@fontsource/ibm-plex-sans/latin-ext-600.css';
import '@fontsource/ibm-plex-mono/latin-400.css';
import '@fontsource/ibm-plex-mono/latin-500.css';
import '@fontsource/ibm-plex-mono/latin-ext-400.css';
import '@fontsource/ibm-plex-mono/latin-ext-500.css';

import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

const appName = import.meta.env.VITE_APP_NAME || 'Plataforma';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),

    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];

        if (!page) {
            throw new Error(`No existe la página Inertia "${name}".`);
        }

        return page;
    },

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },

    progress: { color: '#1d4ed8' },
});
