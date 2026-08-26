<script setup>
import Icono from './Icono.vue';

/*
 * Dónde estoy y cuánto falta, en un alta de varios pasos.
 *
 * La regla del recorrido J1 es que **nadie llegue al final y descubra que faltaba un
 * dato**: la validación ocurre al salir de cada campo. Este componente es la mitad
 * visible de esa promesa —muestra qué pasos ya están completos y cuáles no— y por eso
 * marca los pasos con error en rojo en vez de simplemente «no completado»: no es lo
 * mismo «todavía no llegué» que «volví atrás y hay algo mal».
 */
defineProps({
    // [{ etiqueta, estado: 'hecho' | 'actual' | 'pendiente' | 'error' }]
    pasos: { type: Array, required: true },
});
</script>

<template>
    <nav aria-label="Progreso del alta">
        <ol class="flex flex-wrap items-center gap-y-3 border-b border-rule bg-surface px-6 py-4">
            <li
                v-for="(paso, i) in pasos"
                :key="paso.etiqueta"
                class="flex items-center"
                :class="i < pasos.length - 1 ? 'flex-grow' : ''"
                :aria-current="paso.estado === 'actual' ? 'step' : undefined"
            >
                <span class="flex items-center gap-2">
                    <span
                        class="tabular flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full font-mono text-micro font-semibold"
                        :class="{
                            'bg-ok text-white': paso.estado === 'hecho',
                            'bg-accent text-white': paso.estado === 'actual',
                            'bg-critical text-white': paso.estado === 'error',
                            'border border-stroke text-muted': paso.estado === 'pendiente',
                        }"
                        aria-hidden="true"
                    >
                        <Icono v-if="paso.estado === 'hecho'" nombre="check" :tamano="12" :grosor="3.4" />
                        <Icono v-else-if="paso.estado === 'error'" nombre="equis" :tamano="11" :grosor="3" />
                        <template v-else>{{ i + 1 }}</template>
                    </span>

                    <span
                        class="text-dense"
                        :class="{
                            'font-medium text-ink': paso.estado === 'hecho',
                            'font-semibold text-accent': paso.estado === 'actual',
                            'font-semibold text-critical': paso.estado === 'error',
                            'text-muted': paso.estado === 'pendiente',
                        }"
                    >
                        {{ paso.etiqueta }}
                        <span class="sr-only">
                            <template v-if="paso.estado === 'hecho'">, completado</template>
                            <template v-else-if="paso.estado === 'actual'">, paso actual</template>
                            <template v-else-if="paso.estado === 'error'">, con errores por corregir</template>
                            <template v-else>, pendiente</template>
                        </span>
                    </span>
                </span>

                <span
                    v-if="i < pasos.length - 1"
                    class="mx-3 hidden h-px flex-grow sm:block"
                    :class="paso.estado === 'hecho' ? 'bg-ok' : 'bg-rule'"
                    aria-hidden="true"
                />
            </li>
        </ol>
    </nav>
</template>
