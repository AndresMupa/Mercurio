<script setup>
import { ref } from 'vue';
import Boton from './Boton.vue';
import Icono from './Icono.vue';
import StatusPill from './StatusPill.vue';

/*
 * Carga masiva: la puerta por la que entra la planta entera de un colegio.
 *
 * Dos decisiones que la hacen distinta de un `<input type="file">` con un mensaje al final:
 *
 *  1. **Se aceptan las filas buenas y se devuelven las malas.** Rechazar el fichero entero
 *     porque tres de 52 filas tienen un documento repetido obliga a repetir el trabajo de
 *     las 49 que estaban bien. Aquí, las 49 entran y las 3 vuelven con su motivo y su
 *     número de fila, que es lo que alguien necesita para corregir en su Excel.
 *  2. **El error dice la fila y el motivo en palabras**, no un código. «Fila 14: el
 *     documento 90000012 ya está en Beatriz Duarte Neira» se corrige; «error de validación
 *     en la columna 3» no.
 *
 * Los errores del alta manual y los de aquí son los mismos cuatro del recorrido J1: por
 * eso el catálogo vive en el servidor y este componente solo los muestra.
 */
defineProps({
    // [{ fila, motivo, dato }] — devuelto por el servidor tras procesar.
    rechazadas: { type: Array, default: () => [] },
    aceptadas: { type: Number, default: null },
    procesando: { type: Boolean, default: false },
    // Extensiones que se aceptan, en palabras para quien las lea con lector de pantalla.
    formatos: { type: String, default: '.xlsx, .csv' },
});

const emit = defineEmits(['cargar']);

const entrada = ref(null);
const arrastrando = ref(false);
const elegido = ref(null);

function tomar(archivo) {
    if (archivo) {
        elegido.value = archivo;
        emit('cargar', archivo);
    }
}

function alSoltar(evento) {
    arrastrando.value = false;
    tomar(evento.dataTransfer?.files?.[0]);
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <!--
          Arrastrar es un atajo, nunca el único camino: el botón es un `<label>` real
          sobre un `<input type="file">`, así que funciona con teclado y con lector de
          pantalla sin que haya que reimplementar nada.
        -->
        <div
            class="flex flex-col items-center gap-3 rounded-card border-2 border-dashed p-8 text-center"
            :class="arrastrando ? 'border-accent bg-accent-soft' : 'border-rule bg-surface'"
            @dragover.prevent="arrastrando = true"
            @dragleave.prevent="arrastrando = false"
            @drop.prevent="alSoltar"
        >
            <Icono nombre="subir" :tamano="32" :grosor="1.5" class="text-muted" />

            <p class="text-base font-medium text-ink">
                {{ elegido ? elegido.name : 'Arrastra aquí la planta del colegio' }}
            </p>
            <p class="text-xs text-muted">Formatos aceptados: {{ formatos }}</p>

            <label class="mt-1 inline-flex min-h-[44px] cursor-pointer items-center rounded-control border border-rule bg-surface px-3 text-base font-medium text-ink hover:bg-ground sm:min-h-[36px]">
                Elegir archivo
                <input
                    ref="entrada"
                    type="file"
                    class="sr-only"
                    :accept="formatos"
                    @change="tomar($event.target.files?.[0])"
                >
            </label>
        </div>

        <div v-if="procesando" class="text-base text-muted" role="status">Procesando el archivo…</div>

        <!-- Resultado. `role="status"` para que el lector anuncie el resumen al terminar. -->
        <div v-else-if="aceptadas !== null" class="flex flex-col gap-3" role="status">
            <p class="flex flex-wrap items-center gap-2 text-base">
                <StatusPill tono="ok" :texto="`${aceptadas} aceptadas`" />
                <StatusPill
                    v-if="rechazadas.length"
                    tono="aviso"
                    :texto="`${rechazadas.length} para corregir`"
                />
                <span v-if="rechazadas.length" class="text-muted">
                    Las aceptadas ya están cargadas. Corrige estas y vuelve a subir solo esas filas.
                </span>
            </p>

            <div v-if="rechazadas.length" class="overflow-x-auto rounded-card border border-rule bg-surface">
                <table class="w-full border-collapse text-dense">
                    <caption class="sr-only">Filas que no se pudieron cargar, con el motivo de cada una</caption>
                    <thead>
                        <tr class="border-b border-rule bg-ground">
                            <th scope="col" class="w-16 px-4 py-2 text-left text-micro font-semibold uppercase tracking-wider text-muted">Fila</th>
                            <th scope="col" class="px-4 py-2 text-left text-micro font-semibold uppercase tracking-wider text-muted">Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="fallo in rechazadas" :key="fallo.fila" class="border-b border-rule last:border-b-0">
                            <td class="tabular px-4 py-2.5 font-mono text-muted">{{ fallo.fila }}</td>
                            <td class="px-4 py-2.5">
                                {{ fallo.motivo }}
                                <span v-if="fallo.dato" class="tabular font-mono text-muted"> · {{ fallo.dato }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="$slots.acciones" class="flex gap-2">
            <slot name="acciones" />
        </div>
    </div>
</template>
