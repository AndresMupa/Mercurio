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
            },
            borderRadius: {
                control: '2px',
                card: '4px',
            },
        },
    },
    plugins: [],
};
