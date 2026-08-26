<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

/*
 * Base de todo diálogo: foco atrapado, Escape cierra, y el foco vuelve donde estaba.
 *
 * No es un detalle de cortesía. Sin atrapar el foco, quien navega con teclado sale del
 * diálogo tabulando y sigue rellenando el formulario de detrás sin ver que hay una capa
 * encima; y sin devolver el foco al cerrar, aterriza al principio del documento y tiene
 * que recorrer la pantalla entera para volver a donde estaba. Se implementa una vez aquí
 * y no en cada diálogo, que es como se acaba con la mitad bien y la mitad mal.
 *
 * Se usa `<dialog>` nativo a propósito: el navegador ya sabe hacer el modal, el fondo
 * inerte y el Escape. Lo que hay que añadir es el ciclo del tabulador, que Safari todavía
 * no cierra solo, y devolver el foco.
 */
const props = defineProps({
    abierto: { type: Boolean, default: false },
    titulo: { type: String, required: true },
    // Los diálogos que confirman algo irreversible no se cierran haciendo clic fuera:
    // demasiado fácil de disparar sin querer.
    cierraAlPulsarFuera: { type: Boolean, default: true },
    ancho: { type: String, default: 'max-w-lg' },
});

const emit = defineEmits(['cerrar']);

const dialogo = ref(null);
const devolverFocoA = ref(null);

const seleccionEnfocable = [
    'a[href]', 'button:not([disabled])', 'input:not([disabled])',
    'select:not([disabled])', 'textarea:not([disabled])', '[tabindex]:not([tabindex="-1"])',
].join(',');

function enfocables() {
    return Array.from(dialogo.value?.querySelectorAll(seleccionEnfocable) ?? [])
        .filter((el) => el.offsetParent !== null || el === document.activeElement);
}

function alPulsarTecla(evento) {
    if (evento.key !== 'Tab') {
        return;
    }

    const lista = enfocables();

    if (lista.length === 0) {
        return;
    }

    const primero = lista[0];
    const ultimo = lista[lista.length - 1];

    // El ciclo se cierra a mano en los dos sentidos: sin esto el foco se escapa a la
    // barra del navegador y de ahí al contenido de detrás.
    if (evento.shiftKey && document.activeElement === primero) {
        evento.preventDefault();
        ultimo.focus();
    } else if (! evento.shiftKey && document.activeElement === ultimo) {
        evento.preventDefault();
        primero.focus();
    }
}

function alHacerClic(evento) {
    if (! props.cierraAlPulsarFuera) {
        return;
    }

    // `<dialog>` reparte el clic del telón al propio elemento, así que un clic cuyo
    // objetivo es el diálogo y no algo de dentro viene de fuera del recuadro.
    if (evento.target === dialogo.value) {
        emit('cerrar');
    }
}

watch(() => props.abierto, async (abierto) => {
    if (abierto) {
        devolverFocoA.value = document.activeElement;
        dialogo.value?.showModal();
        await nextTick();

        // Al primer elemento con `autofocus`, o al primero enfocable. Nunca al telón:
        // abrir un diálogo y no llevar el foco dentro es peor que no abrirlo.
        const preferido = dialogo.value?.querySelector('[autofocus]');
        (preferido ?? enfocables()[0])?.focus();
    } else if (dialogo.value?.open) {
        dialogo.value.close();
        devolverFocoA.value?.focus?.();
    }
});

onBeforeUnmount(() => {
    if (dialogo.value?.open) {
        dialogo.value.close();
    }
});
</script>

<template>
    <dialog
        ref="dialogo"
        class="w-full rounded-card border border-rule bg-surface p-0 text-ink shadow-xl backdrop:bg-ink/40"
        :class="ancho"
        :aria-label="titulo"
        @cancel.prevent="emit('cerrar')"
        @keydown="alPulsarTecla"
        @click="alHacerClic"
    >
        <slot />
    </dialog>
</template>
