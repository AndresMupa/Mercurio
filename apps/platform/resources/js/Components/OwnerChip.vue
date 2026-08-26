<script setup>
import { computed } from 'vue';

/*
 * Quién es responsable de algo.
 *
 * Existe porque el principio 1 del sistema de diseño exige RESPONSABLE en toda pantalla
 * de proceso, y una tarea sin dueño visible es una tarea que nadie hace. Cuando el dueño
 * no eres tú, el componente lo apaga: la bandeja tiene que distinguir de un vistazo lo
 * que te toca de lo que estás esperando.
 */
const props = defineProps({
    nombre: { type: String, required: true },
    // `false` cuando la tarea espera a otra persona o a otro rol.
    propio: { type: Boolean, default: true },
    // Etiqueta de rol para dueños que no son una persona concreta: «Coordinación».
    esRol: { type: Boolean, default: false },
});

/**
 * Iniciales de un nombre compuesto español: «Adriana Acosta Fajardo» → AA.
 * Se toman el primer nombre y el primer apellido, no las dos primeras palabras, porque
 * «María del Carmen» daría MD.
 */
const iniciales = computed(() => {
    if (props.esRol) {
        return props.nombre.slice(0, 2).toUpperCase();
    }

    const partes = props.nombre.trim().split(/\s+/).filter((p) => p.length > 2);

    return ((partes[0]?.[0] ?? '') + (partes[1]?.[0] ?? '')).toUpperCase() || '?';
});

/** «Adriana Acosta Fajardo» → «Adriana A.», que es como se nombra a alguien en una tabla. */
const corto = computed(() => {
    if (props.esRol) {
        return props.nombre;
    }

    const partes = props.nombre.trim().split(/\s+/);

    return partes.length > 1 ? `${partes[0]} ${partes[1][0]}.` : props.nombre;
});
</script>

<template>
    <span class="inline-flex items-center gap-1.5" :title="nombre">
        <span
            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-control text-[9px] font-semibold"
            :class="propio ? 'bg-accent-soft text-accent' : 'border border-rule bg-ground text-muted'"
            aria-hidden="true"
        >{{ iniciales }}</span>
        <span class="truncate text-xs" :class="propio ? 'text-ink' : 'text-muted'">{{ corto }}</span>
    </span>
</template>
