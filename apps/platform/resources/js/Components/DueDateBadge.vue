<script setup>
import { computed } from 'vue';

/*
 * Cuándo vence algo, y cuánto queda.
 *
 * Muestra el plazo relativo —«6 d», «−4 d»— porque es lo que se compara de un vistazo en
 * una columna de veinte filas, y guarda la fecha absoluta en el `title` y en un texto
 * accesible, porque a la hora de actuar lo que hace falta es el día exacto.
 *
 * El umbral de aviso no es una preferencia estética: 30 días es lo que tarda en tramitarse
 * una renovación de contrato o una corrección del C600. Antes de eso todavía se puede
 * hacer algo, después ya no.
 */
const props = defineProps({
    // ISO 8601, o null cuando no hay plazo.
    vence: { type: String, default: null },
    // Se inyecta en las pruebas para que no dependan del reloj del que las ejecuta.
    hoy: { type: String, default: null },
});

const DIA = 86_400_000;
const UMBRAL_AVISO = 30;

const dias = computed(() => {
    if (!props.vence) {
        return null;
    }

    const referencia = props.hoy ? new Date(`${props.hoy}T00:00:00`) : new Date();
    const objetivo = new Date(`${props.vence}T00:00:00`);

    return Math.round((objetivo - new Date(referencia.toDateString())) / DIA);
});

const tono = computed(() => {
    if (dias.value === null) {
        return 'text-muted';
    }

    if (dias.value < 0) {
        return 'font-semibold text-critical';
    }

    return dias.value <= UMBRAL_AVISO ? 'font-semibold text-warn' : 'text-muted';
});

/** Texto corto de la columna. El signo menos ya dice que se pasó. */
const relativo = computed(() => {
    if (dias.value === null) {
        return '—';
    }

    if (dias.value === 0) {
        return 'Hoy';
    }

    return `${dias.value > 0 ? '' : '−'}${Math.abs(dias.value)} d`;
});

const absoluto = computed(() => {
    if (!props.vence) {
        return 'sin plazo';
    }

    return new Date(`${props.vence}T00:00:00`).toLocaleDateString('es-CO', {
        day: 'numeric', month: 'long', year: 'numeric',
    });
});

/*
 * Lo que oye quien usa lector de pantalla. «−4 d» leído en voz alta no significa nada;
 * «venció hace 4 días, el 21 de agosto de 2026» sí.
 */
const parlante = computed(() => {
    if (dias.value === null) {
        return 'Sin plazo';
    }

    if (dias.value < 0) {
        return `Venció hace ${Math.abs(dias.value)} días, el ${absoluto.value}`;
    }

    if (dias.value === 0) {
        return `Vence hoy, ${absoluto.value}`;
    }

    return `Vence en ${dias.value} días, el ${absoluto.value}`;
});
</script>

<template>
    <span class="tabular font-mono text-xs" :class="tono" :title="absoluto">
        <span aria-hidden="true">{{ relativo }}</span>
        <span class="sr-only">{{ parlante }}</span>
    </span>
</template>
