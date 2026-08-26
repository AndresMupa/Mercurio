<script setup>
import Icono from './Icono.vue';

/*
 * Un vacío que dice qué es, por qué está vacío y cuál es la acción.
 *
 * Principio 5 del sistema de diseño: sin callejones sin salida. La distinción que más se
 * descuida —y que este componente obliga a hacer, porque `motivo` es requerido— es entre
 * «aquí nunca ha habido nada» y «tu filtro no encontró nada». Son estados distintos y
 * necesitan textos distintos: el primero explica qué vive aquí, el segundo dice qué
 * filtro quitar.
 *
 * `tono` existe porque no todo vacío es un problema. La auditoría de una semana sin
 * lecturas sensibles está vacía y eso es una buena noticia; pintarla en ámbar enseña a
 * la gente a desconfiar de la pantalla.
 */
defineProps({
    icono: { type: String, default: 'documento' },
    titulo: { type: String, required: true },
    // Por qué está vacío. Requerido a propósito: sin esto el componente no aporta nada
    // sobre un `<p>No hay datos</p>`.
    motivo: { type: String, required: true },
    tono: {
        type: String,
        default: 'neutro',
        validator: (v) => ['neutro', 'ok', 'aviso'].includes(v),
    },
});

const colorIcono = { neutro: 'text-muted', ok: 'text-ok', aviso: 'text-warn' };
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-2.5 px-6 py-12 text-center">
        <Icono :nombre="icono" :tamano="36" :grosor="1.5" :class="colorIcono[tono]" />

        <p class="text-lg font-semibold text-ink">{{ titulo }}</p>
        <p class="max-w-sm text-base text-ink"><slot name="motivo">{{ motivo }}</slot></p>

        <div v-if="$slots.acciones" class="mt-1 flex flex-wrap justify-center gap-2">
            <slot name="acciones" />
        </div>
    </div>
</template>
