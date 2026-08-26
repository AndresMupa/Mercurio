<script setup>
import Icono from './Icono.vue';

/*
 * Los filtros activos, visibles y quitables uno a uno.
 *
 * La razón de que los filtros se muestren como fichas y no escondidos tras un panel es
 * el estado vacío: cuando una búsqueda no devuelve nada, la pregunta es siempre «¿qué
 * filtro me lo está tapando?». Si están a la vista, se responde sola.
 *
 * El buscador emite al escribir y el servidor decide; no filtra en el cliente, que solo
 * podría filtrar la página que ya tiene descargada.
 */
defineProps({
    busqueda: { type: String, default: '' },
    marcador: { type: String, default: 'Buscar' },
    // [{ clave, etiqueta }] — ya resueltos a texto legible por el servidor.
    activos: { type: Array, default: () => [] },
    densidad: { type: String, default: 'compacta' },
});

const emit = defineEmits(['buscar', 'quitar', 'limpiar', 'densidad']);
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 border-b border-rule bg-surface px-6 py-3">
        <div class="flex h-[38px] w-full items-center gap-2 rounded-control border border-rule px-2.5 sm:h-[30px] sm:w-72">
            <Icono nombre="buscar" :tamano="14" class="text-muted" />
            <label class="sr-only" for="filtro-busqueda">{{ marcador }}</label>
            <input
                id="filtro-busqueda"
                type="search"
                :value="busqueda"
                :placeholder="marcador"
                class="w-full border-0 bg-transparent p-0 text-xs text-ink placeholder:text-muted focus:ring-0"
                @input="emit('buscar', $event.target.value)"
            >
        </div>

        <button
            v-for="filtro in activos"
            :key="filtro.clave"
            type="button"
            class="flex min-h-[38px] items-center gap-1.5 rounded-control border border-accent bg-accent-soft px-2.5 text-xs font-medium text-accent sm:min-h-[30px]"
            @click="emit('quitar', filtro.clave)"
        >
            {{ filtro.etiqueta }}
            <Icono nombre="equis" :tamano="12" :grosor="2.4" />
            <span class="sr-only">Quitar este filtro</span>
        </button>

        <button
            v-if="activos.length > 1"
            type="button"
            class="min-h-[38px] px-1 text-xs text-muted underline-offset-2 hover:text-ink hover:underline sm:min-h-[30px]"
            @click="emit('limpiar')"
        >Limpiar todo</button>

        <fieldset class="ml-auto flex items-center gap-1.5">
            <legend class="sr-only">Densidad de la tabla</legend>
            <span class="text-micro font-semibold uppercase tracking-wider text-muted" aria-hidden="true">Densidad</span>
            <div class="flex overflow-hidden rounded-control border border-rule">
                <button
                    v-for="opcion in ['compacta', 'cómoda']"
                    :key="opcion"
                    type="button"
                    class="min-h-[38px] px-2 text-micro capitalize sm:min-h-[26px]"
                    :class="densidad === opcion ? 'bg-ink font-medium text-white' : 'text-muted hover:bg-ground'"
                    :aria-pressed="densidad === opcion"
                    @click="emit('densidad', opcion)"
                >{{ opcion }}</button>
            </div>
        </fieldset>
    </div>
</template>
