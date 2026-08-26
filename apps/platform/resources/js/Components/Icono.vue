<script setup>
/*
 * Los iconos del sistema, dibujados a trazo sobre una rejilla de 24.
 *
 * Ni emoji ni una librería de iconos: emoji cambia de forma según el sistema operativo
 * —y un check verde de Apple no significa lo mismo que uno de Android en una pantalla
 * que un inspector puede fotografiar— y una librería entera para catorce iconos es
 * medio megabyte de los que se usan cuatro kilobytes.
 *
 * Ninguno lleva significado por sí solo: siempre acompañan a un texto. Por eso van con
 * `aria-hidden` y sin `<title>`, para que un lector de pantalla no lea dos veces lo mismo.
 */
defineProps({
    nombre: { type: String, required: true },
    tamano: { type: [Number, String], default: 16 },
    grosor: { type: [Number, String], default: 1.8 },
});

const trazos = {
    // Estados
    check: 'M4 12.5 9 18 20 6',
    equis: 'M6 6l12 12M18 6 6 18',
    aviso: 'M12 4 2.5 20h19L12 4Z',
    pausa: 'M9 5v14M15 5v14',
    reloj: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18M12 7.5v5l3 2',
    alerta: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18M12 7v6M12 16.5v.01',

    // Navegación
    bandeja: 'M3 5h18M3 12h18M3 19h11',
    personas: 'M9 4.8a3.2 3.2 0 1 0 0 6.4 3.2 3.2 0 0 0 0-6.4M3 20c0-3.2 2.7-5 6-5s6 1.8 6 5M17 11h4M19 9v4',
    calendario: 'M4 6h16v14H4zM8 3v5M16 3v5M4 11h16',
    barras: 'M4 19V9M10 19V5M16 19v-7M3 21h18',
    documento: 'M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h4',
    escudo: 'M12 3 4 6v6c0 5 3.4 8.2 8 9 4.6-.8 8-4 8-9V6l-8-3M9 12l2 2 4-4',
    colegio: 'M3 21V8l9-5 9 5v13M9 21v-6h6v6',

    // Acciones
    buscar: 'M11 4.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13M16 16l4.5 4.5',
    mas: 'M12 5v14M5 12h14',
    subir: 'M12 3v12M7 10l5 5 5-5M4 20h16',
    izquierda: 'M14 6l-6 6 6 6',
    abajo: 'm7 10 5 5 5-5',
    copiar: 'M10.6 9h8.8a1.6 1.6 0 0 1 1.6 1.6v8.8a1.6 1.6 0 0 1-1.6 1.6h-8.8A1.6 1.6 0 0 1 9 19.4v-8.8A1.6 1.6 0 0 1 10.6 9M5 15V5a1.6 1.6 0 0 1 1.6-1.6H15',

    // Dominio
    candado: 'M5 12a1.5 1.5 0 0 1 1.5-1.5h11A1.5 1.5 0 0 1 19 12v7a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 5 19zM8.5 10.5V7a3.5 3.5 0 0 1 7 0v3.5',
    persona: 'M12 4.6a3.4 3.4 0 1 0 0 6.8 3.4 3.4 0 0 0 0-6.8M5 20c0-3.6 3.1-5.6 7-5.6s7 2 7 5.6',
    correo: 'M4 6h16v12H4zm0 1 8 6 8-6',
    ojo: 'M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12ZM12 9.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5',
};
</script>

<template>
    <svg
        :width="tamano"
        :height="tamano"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        :stroke-width="grosor"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
        class="shrink-0"
    >
        <path :d="trazos[nombre]" />
    </svg>
</template>
