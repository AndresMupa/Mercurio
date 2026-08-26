<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/*
 * El botón del sistema. No está en la lista de componentes base porque nadie lo pone en
 * una lista, y por eso mismo acaba habiendo nueve variantes distintas.
 *
 * Las tres reglas que aplica y que son difíciles de mantener a mano: 44 px de alto en
 * móvil —objetivo táctil mínimo de WCAG 2.2— aunque en escritorio baje a 32; foco visible
 * heredado del `:focus-visible` global; y `destructivo` en rojo, que es el único caso en
 * que un botón usa color semántico y no el acento de marca.
 */
const props = defineProps({
    variante: {
        type: String,
        default: 'secundario',
        validator: (v) => ['primario', 'secundario', 'destructivo', 'silencioso'].includes(v),
    },
    tamano: { type: String, default: 'normal', validator: (v) => ['normal', 'compacto'].includes(v) },
    // Cuando lleva a otro sitio es un enlace, no un botón: el teclado, el lector de
    // pantalla y el «abrir en pestaña nueva» dependen de que el elemento sea el correcto.
    href: { type: String, default: null },
    method: { type: String, default: 'get' },
    deshabilitado: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
});

const clases = computed(() => {
    const base = [
        'inline-flex items-center justify-center gap-1.5 rounded-control font-sans font-medium',
        'transition-colors',
        props.tamano === 'compacto'
            ? 'min-h-[32px] px-2.5 text-xs'
            : 'min-h-[44px] px-3 text-base sm:min-h-[36px]',
    ];

    const porVariante = {
        primario: 'border-0 bg-accent text-white hover:bg-accent/90',
        secundario: 'border border-rule bg-surface text-ink hover:bg-ground',
        destructivo: 'border border-critical-line bg-surface text-critical hover:bg-critical-soft',
        silencioso: 'border-0 bg-transparent text-accent hover:bg-accent-soft',
    };

    return [
        ...base,
        porVariante[props.variante],
        props.deshabilitado ? 'cursor-not-allowed opacity-55' : 'cursor-pointer',
    ];
});
</script>

<template>
    <Link v-if="href && ! deshabilitado" :href="href" :method="method" :class="clases">
        <slot />
    </Link>

    <button v-else :type="type" :class="clases" :disabled="deshabilitado">
        <slot />
    </button>
</template>
