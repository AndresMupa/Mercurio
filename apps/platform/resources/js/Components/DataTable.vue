<script setup>
import { computed } from 'vue';
import Icono from './Icono.vue';

/*
 * La tabla densa del producto: orden, selección múltiple, densidad y `tabular-nums`.
 *
 * **Sin lógica de negocio dentro.** No sabe qué es una persona ni qué significa una
 * columna: recibe columnas y filas y las pinta. Ordenar y filtrar ocurren en el servidor
 * —una tabla que ordena en el cliente solo ordena la página que tiene delante, y eso es
 * mentira en cuanto hay 64 filas y se ven 16—.
 *
 * Tres decisiones de accesibilidad que cuestan poco y se olvidan siempre:
 *
 *  - Cabeceras con `scope="col"` y `aria-sort`, para que un lector de pantalla anuncie
 *    «Nombre, ordenado ascendente» en vez de leer un `div`.
 *  - Cada casilla de selección con su etiqueta propia —«Seleccionar a Beatriz Duarte»—;
 *    veinte casillas que se llaman todas «Seleccionar» son veinte casillas inútiles.
 *  - Una `<caption>` que dice qué hay en la tabla y cuántas filas, escondida a la vista.
 */
const props = defineProps({
    // [{ clave, etiqueta, ancho, numerica, ordenable, sensible }]
    columnas: { type: Array, required: true },
    filas: { type: Array, required: true },
    // Qué campo de la fila la identifica.
    clave: { type: String, default: 'id' },
    descripcion: { type: String, required: true },
    seleccionables: { type: Boolean, default: false },
    seleccion: { type: Array, default: () => [] },
    // { columna, sentido: 'asc' | 'desc' }
    orden: { type: Object, default: null },
    densidad: { type: String, default: 'compacta' },
    total: { type: Number, default: null },
});

const emit = defineEmits(['ordenar', 'seleccionar', 'abrir']);

const altoFila = computed(() => (props.densidad === 'compacta' ? 'h-9' : 'h-12'));
const tipoFila = computed(() => (props.densidad === 'compacta' ? 'text-dense' : 'text-base'));

const rejilla = computed(() => {
    const anchos = props.columnas.map((c) => c.ancho ?? 'minmax(0, 1fr)');

    return props.seleccionables ? `32px ${anchos.join(' ')}` : anchos.join(' ');
});

const todasSeleccionadas = computed(
    () => props.filas.length > 0 && props.filas.every((f) => props.seleccion.includes(f[props.clave]))
);

function alternarTodas() {
    emit('seleccionar', todasSeleccionadas.value ? [] : props.filas.map((f) => f[props.clave]));
}

function alternarUna(id) {
    emit('seleccionar', props.seleccion.includes(id)
        ? props.seleccion.filter((s) => s !== id)
        : [...props.seleccion, id]);
}

function sentidoAria(columna) {
    if (props.orden?.columna !== columna.clave) {
        return columna.ordenable ? 'none' : undefined;
    }

    return props.orden.sentido === 'asc' ? 'ascending' : 'descending';
}
</script>

<template>
    <div class="overflow-x-auto rounded-card border border-rule bg-surface">
        <table class="w-full border-collapse" :style="{ minWidth: '720px' }">
            <caption class="sr-only">
                {{ descripcion }}<template v-if="total"> · {{ total }} filas en total</template>
            </caption>

            <thead>
                <tr class="grid items-center border-b border-rule bg-ground" :style="{ gridTemplateColumns: rejilla }">
                    <th v-if="seleccionables" scope="col" class="px-4">
                        <input
                            type="checkbox"
                            class="h-3.5 w-3.5 cursor-pointer rounded-control border-stroke accent-accent"
                            :checked="todasSeleccionadas"
                            aria-label="Seleccionar todas las filas de esta página"
                            @change="alternarTodas"
                        >
                    </th>

                    <th
                        v-for="columna in columnas"
                        :key="columna.clave"
                        scope="col"
                        :aria-sort="sentidoAria(columna)"
                        class="px-3 py-2 text-micro font-semibold uppercase tracking-wider text-muted"
                        :class="columna.numerica ? 'text-right' : 'text-left'"
                    >
                        <button
                            v-if="columna.ordenable"
                            type="button"
                            class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-ink"
                            @click="emit('ordenar', columna.clave)"
                        >
                            {{ columna.etiqueta }}
                            <Icono
                                v-if="orden?.columna === columna.clave"
                                nombre="abajo"
                                :tamano="11"
                                :grosor="2.4"
                                class="text-ink"
                                :class="orden.sentido === 'asc' ? 'rotate-180' : ''"
                            />
                        </button>

                        <span v-else class="inline-flex items-center gap-1">
                            {{ columna.etiqueta }}
                            <!-- El candado marca las columnas P3 antes de que nadie pulse nada. -->
                            <Icono v-if="columna.sensible" nombre="candado" :tamano="10" :grosor="2.2" class="text-warn" />
                        </span>
                    </th>
                </tr>
            </thead>

            <tbody>
                <tr
                    v-for="fila in filas"
                    :key="fila[clave]"
                    class="grid items-center border-b border-rule/70 last:border-b-0"
                    :class="[
                        altoFila,
                        tipoFila,
                        fila.atencion ? 'border-l-2 border-l-warn' : '',
                        seleccion.includes(fila[clave]) ? 'bg-accent-soft' : 'hover:bg-ground',
                        fila.apagada ? 'text-muted' : '',
                    ]"
                    :style="{ gridTemplateColumns: rejilla }"
                >
                    <td v-if="seleccionables" class="px-4">
                        <input
                            type="checkbox"
                            class="h-3.5 w-3.5 cursor-pointer rounded-control border-stroke accent-accent"
                            :checked="seleccion.includes(fila[clave])"
                            :aria-label="`Seleccionar a ${fila.etiquetaAccesible ?? fila[columnas[0].clave]}`"
                            @change="alternarUna(fila[clave])"
                        >
                    </td>

                    <td
                        v-for="columna in columnas"
                        :key="columna.clave"
                        class="truncate px-3"
                        :class="[
                            columna.numerica ? 'tabular text-right font-mono' : 'text-left',
                            columna.mono ? 'tabular font-mono' : '',
                        ]"
                    >
                        <slot :name="`celda-${columna.clave}`" :fila="fila">{{ fila[columna.clave] }}</slot>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
