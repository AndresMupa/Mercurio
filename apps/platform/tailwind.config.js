/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,vue}',
    ],
    theme: {
        extend: {
            // Se exponen los tokens del sistema de diseño, no colores sueltos.
            colors: {
                ground: 'var(--color-ground)',
                surface: 'var(--color-surface)',
                ink: 'var(--color-ink)',
                muted: 'var(--color-muted)',
                rule: 'var(--color-rule)',
                accent: 'var(--color-accent)',
                ok: 'var(--color-ok)',
                warn: 'var(--color-warn)',
                critical: 'var(--color-critical)',
                clinical: 'var(--color-clinical)',

                /*
                 * Fondos y bordes tenues de cada estado semántico (lienzo B7).
                 *
                 * Existen porque una píldora de estado necesita tres valores del mismo
                 * matiz —texto, fondo y borde— y dejarlos a la improvisación de cada
                 * pantalla es como se acaba con doce verdes distintos. El contraste de
                 * cada par texto/fondo está verificado por encima de 4.5:1.
                 */
                'ok-soft': 'var(--color-ok-soft)',
                'ok-line': 'var(--color-ok-line)',
                'warn-soft': 'var(--color-warn-soft)',
                'warn-line': 'var(--color-warn-line)',
                'critical-soft': 'var(--color-critical-soft)',
                'critical-line': 'var(--color-critical-line)',
                'accent-soft': 'var(--color-accent-soft)',
                'accent-line': 'var(--color-accent-line)',
                'clinical-soft': 'var(--color-clinical-soft)',
                'clinical-line': 'var(--color-clinical-line)',

                // Gris de trazo para iconos y bordes de control; más oscuro que `rule`,
                // que es solo para separadores.
                stroke: 'var(--color-stroke)',
            },

            /*
             * IBM Plex, decidido en B7: superfamilia real, pensada para interfaz técnica
             * densa y con acentuación española completa. La monoespaciada no es decorativa
             * —va en documentos, fechas, códigos DANE y toda columna numérica— y al ser
             * de la misma familia no rompe la línea base al mezclarse con el texto.
             */
            fontFamily: {
                sans: ['IBM Plex Sans', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                mono: ['IBM Plex Mono', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
            },

            /*
             * Escala fija (design-system.md: «nada de tamaños improvisados»).
             * El suelo es 11 px y no hay nada por debajo: se comprobó en B7 que siete
             * artboards se habían ido a 10 px sin que nadie lo decidiera.
             */
            fontSize: {
                micro: ['0.6875rem', { lineHeight: '1rem' }],      // 11 — etiquetas de columna
                xs: ['0.75rem', { lineHeight: '1.125rem' }],       // 12 — metadatos
                dense: ['0.78125rem', { lineHeight: '1.125rem' }], // 12.5 — fila de tabla compacta
                sm: ['0.8125rem', { lineHeight: '1.25rem' }],      // 13 — fila cómoda
                base: ['0.875rem', { lineHeight: '1.5rem' }],      // 14 — lectura y formularios
                lg: ['1rem', { lineHeight: '1.5rem' }],            // 16 — encabezado de tarjeta
                xl: ['1.25rem', { lineHeight: '1.625rem' }],       // 20 — sección
                '2xl': ['1.5rem', { lineHeight: '1.875rem' }],     // 24 — título de pantalla
            },

            borderRadius: {
                control: '2px',
                card: '4px',
            },

            spacing: {
                rail: '14rem',   // 224 px — ancho del rail de navegación
                topbar: '3.5rem', // 56 px — alto de la barra superior
            },
        },
    },
    plugins: [],
};
