<script setup>
import Icono from './Icono.vue';

/*
 * El estado de algo, en cuatro canales a la vez: forma, icono, texto y color.
 *
 * Principio 4 del sistema de diseño. La prueba está en el E2E: se le quita el color a la
 * página entera y todo tiene que seguir leyéndose. Un daltónico no es un caso raro —uno
 * de cada doce hombres— y una pantalla que un inspector fotografía en blanco y negro
 * tampoco.
 *
 * `tono` es semántico, no decorativo, y es independiente del acento de marca.
 */
const props = defineProps({
    tono: {
        type: String,
        default: 'neutro',
        validator: (v) => ['ok', 'aviso', 'critico', 'neutro', 'apagado'].includes(v),
    },
    texto: { type: String, required: true },
    // Cuando el estado no es el habitual del tono: «Suspendida» es neutra pero se marca
    // con la pausa, no con el check.
    icono: { type: String, default: null },
});

const iconoPorTono = {
    ok: 'check',
    aviso: 'aviso',
    critico: 'equis',
    neutro: 'reloj',
    apagado: 'equis',
};

const clasesPorTono = {
    ok: 'bg-ok-soft border-ok-line text-ok',
    aviso: 'bg-warn-soft border-warn-line text-warn',
    critico: 'bg-critical-soft border-critical-line text-critical',
    neutro: 'bg-ground border-stroke text-muted',
    apagado: 'bg-surface border-rule text-muted',
};

const grosorPorIcono = { check: 2.8, equis: 2.6, aviso: 2.4, pausa: 3 };
const nombreIcono = () => props.icono ?? iconoPorTono[props.tono];
</script>

<template>
    <span
        class="inline-flex w-fit items-center gap-1.5 rounded-control border px-1.5 py-0.5 text-micro font-semibold"
        :class="clasesPorTono[tono]"
    >
        <Icono :nombre="nombreIcono()" :tamano="10" :grosor="grosorPorIcono[nombreIcono()] ?? 2.4" />
        {{ texto }}
    </span>
</template>
