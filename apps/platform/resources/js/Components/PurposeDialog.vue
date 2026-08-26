<script setup>
import { ref, watch } from 'vue';
import Boton from './Boton.vue';
import Icono from './Icono.vue';
import Modal from './Modal.vue';

/*
 * Antes de leer un dato P3, para qué. El componente propio de este producto.
 *
 * No es fricción gratuita: es el registro que sostiene la auditoría, y es lo que hace
 * que CA-04 —«leer un dato sensible sin propósito devuelve 403 y deja evento»— tenga una
 * forma humana en vez de ser un error.
 *
 * Por eso tiene que ser **rápido**: el propósito viene sugerido por el contexto desde el
 * servidor y se confirma en un clic. Si hiciera falta pensarlo cada vez, la gente
 * aprendería a elegir el primero de la lista y el registro dejaría de significar nada.
 *
 * La lista es cerrada. Un propósito escrito a mano no se puede auditar ni contar, y sería
 * otra forma de no declarar nada.
 */
const props = defineProps({
    abierto: { type: Boolean, default: false },
    // Qué se va a ver, en palabras: «el documento de Beatriz Duarte Neira».
    que: { type: String, required: true },
    clasificacion: { type: String, default: 'P3' },
    // [{ valor, titulo, sugerido }] — del servidor, según desde dónde se abrió.
    propositos: { type: Array, required: true },
    procesando: { type: Boolean, default: false },
});

const emit = defineEmits(['confirmar', 'cerrar']);

const elegido = ref(null);

// Se resetea al sugerido en cada apertura: arrastrar la elección anterior convertiría el
// propósito en una preferencia pegajosa en vez de una declaración por lectura.
watch(() => props.abierto, (abierto) => {
    if (abierto) {
        elegido.value = props.propositos.find((p) => p.sugerido)?.valor ?? props.propositos[0]?.valor ?? null;
    }
});
</script>

<template>
    <Modal
        :abierto="abierto"
        :titulo="`Ver ${que}`"
        ancho="max-w-md"
        @cerrar="emit('cerrar')"
    >
        <form @submit.prevent="elegido && emit('confirmar', elegido)">
            <div class="flex items-start gap-3 border-b border-rule p-4">
                <Icono nombre="candado" :tamano="20" class="mt-0.5 text-warn" />
                <div>
                    <h2 class="text-base font-semibold text-ink">Ver {{ que }}</h2>
                    <p class="text-xs text-muted">
                        Dato <span class="tabular font-mono font-medium">{{ clasificacion }}</span> ·
                        queda registrado con tu nombre
                    </p>
                </div>
            </div>

            <fieldset class="flex flex-col gap-3 p-4">
                <legend class="text-micro font-semibold uppercase tracking-wider text-muted">
                    ¿Para qué lo necesitas?
                </legend>

                <div class="flex flex-col gap-1.5">
                    <label
                        v-for="proposito in propositos"
                        :key="proposito.valor"
                        class="flex cursor-pointer items-center gap-2.5 rounded-control border px-3 py-2.5"
                        :class="elegido === proposito.valor
                            ? 'border-accent bg-accent-soft'
                            : 'border-rule bg-surface hover:bg-ground'"
                    >
                        <input
                            v-model="elegido"
                            type="radio"
                            name="proposito"
                            :value="proposito.valor"
                            class="h-3.5 w-3.5 border-stroke accent-accent"
                        >
                        <span class="min-w-0 flex-grow">
                            <span class="block text-sm font-medium text-ink">{{ proposito.titulo }}</span>
                            <span class="tabular block font-mono text-[10.5px] text-muted">{{ proposito.valor }}</span>
                        </span>
                        <span
                            v-if="proposito.sugerido"
                            class="shrink-0 rounded-control border border-accent-line bg-accent-soft px-1.5 text-[10px] font-semibold text-accent"
                        >SUGERIDO</span>
                    </label>
                </div>

                <p class="border-t border-rule pt-3 text-micro leading-relaxed text-muted">
                    La lista es cerrada. Un propósito escrito a mano no se podría auditar ni contar,
                    y sería otra forma de no declarar nada.
                </p>
            </fieldset>

            <div class="flex gap-2 border-t border-rule p-4">
                <Boton
                    type="submit"
                    variante="primario"
                    class="flex-grow"
                    :deshabilitado="! elegido || procesando"
                >{{ procesando ? 'Registrando…' : `Ver ${que}` }}</Boton>
                <Boton @click="emit('cerrar')">Cancelar</Boton>
            </div>
        </form>
    </Modal>
</template>
