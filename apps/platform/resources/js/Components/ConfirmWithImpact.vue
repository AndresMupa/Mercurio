<script setup>
import { ref, watch } from 'vue';
import Boton from './Boton.vue';
import Icono from './Icono.vue';
import Modal from './Modal.vue';

/*
 * Confirmar algo grave diciendo antes, y con cifras, qué se pierde **y qué no**.
 *
 * La columna de la derecha importa tanto como la de la izquierda: buena parte del miedo a
 * cerrar una relación laboral viene de no saber si además desaparece el histórico. Decir
 * «la ficha se queda, sigue contando en el C600 de este año» quita ese miedo y evita que
 * alguien no haga la operación que debe hacer.
 *
 * El impacto lo calcula el servidor al abrir el diálogo, con los números de esta persona
 * concreta. Una frase del tipo «esta acción no se puede deshacer» no le dice nada a nadie.
 *
 * `responsable` no es decoración: la regla 5 exige una persona identificada detrás de toda
 * decisión de este tipo. El diálogo la nombra antes de confirmar, no después.
 */
const props = defineProps({
    abierto: { type: Boolean, default: false },
    titulo: { type: String, required: true },
    sujeto: { type: String, default: null },
    pierde: { type: Array, default: () => [] },
    conserva: { type: Array, default: () => [] },
    // Aviso extra: tareas que quedan sin dueño, por ejemplo.
    advertencia: { type: String, default: null },
    // El texto que hay que entender antes de poder confirmar.
    reconocimiento: { type: String, required: true },
    responsable: { type: String, required: true },
    etiquetaConfirmar: { type: String, default: 'Confirmar' },
    procesando: { type: Boolean, default: false },
});

const emit = defineEmits(['confirmar', 'cerrar']);

const entendido = ref(false);

watch(() => props.abierto, (abierto) => {
    if (abierto) {
        entendido.value = false;
    }
});
</script>

<template>
    <!--
      No se cierra al pulsar fuera: quien está a punto de confirmar algo irreversible no
      puede perder el diálogo por un clic despistado en el fondo.
    -->
    <Modal
        :abierto="abierto"
        :titulo="titulo"
        ancho="max-w-2xl"
        :cierra-al-pulsar-fuera="false"
        @cerrar="emit('cerrar')"
    >
        <div class="flex items-start gap-4 border-b border-rule p-6">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control border border-critical-line bg-critical-soft">
                <Icono nombre="candado" :tamano="20" :grosor="2" class="text-critical" />
            </span>
            <div>
                <h2 class="text-lg font-semibold text-ink">{{ titulo }}</h2>
                <p v-if="sujeto" class="text-xs text-muted">{{ sujeto }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 border-b border-rule sm:grid-cols-2">
            <section class="flex flex-col gap-3 border-b border-rule p-6 sm:border-b-0 sm:border-r">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-critical">
                    <Icono nombre="equis" :tamano="16" :grosor="2.4" />
                    Pierde
                </h3>
                <ul class="flex flex-col gap-2.5">
                    <li v-for="(item, i) in pierde" :key="i" class="flex items-start gap-2.5 text-dense leading-relaxed">
                        <span class="mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full bg-critical" aria-hidden="true" />
                        <span v-html="item" />
                    </li>
                </ul>

                <p
                    v-if="advertencia"
                    class="mt-1 flex items-start gap-2 rounded-control border border-warn-line bg-warn-soft px-3 py-2.5 text-micro leading-relaxed text-ink"
                >
                    <Icono nombre="aviso" :tamano="14" :grosor="2.2" class="mt-0.5 text-warn" />
                    <span v-html="advertencia" />
                </p>
            </section>

            <section class="flex flex-col gap-3 p-6">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-ok">
                    <Icono nombre="check" :tamano="16" :grosor="2.6" />
                    No se pierde
                </h3>
                <ul class="flex flex-col gap-2.5">
                    <li v-for="(item, i) in conserva" :key="i" class="flex items-start gap-2.5 text-dense leading-relaxed">
                        <span class="mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full bg-ok" aria-hidden="true" />
                        <span v-html="item" />
                    </li>
                </ul>
            </section>
        </div>

        <form class="flex flex-col gap-4 p-6" @submit.prevent="entendido && emit('confirmar')">
            <label class="flex cursor-pointer items-start gap-2.5">
                <input
                    v-model="entendido"
                    type="checkbox"
                    class="mt-0.5 h-4 w-4 shrink-0 rounded-control border-stroke accent-accent"
                >
                <span class="text-dense leading-relaxed text-ink" v-html="reconocimiento" />
            </label>

            <p class="flex items-start gap-2.5 rounded-control border border-rule bg-ground px-3 py-3 text-micro leading-relaxed text-muted">
                <Icono nombre="persona" :tamano="15" :grosor="1.9" class="mt-0.5" />
                <span>
                    Queda registrado que <strong class="text-ink">{{ responsable }}</strong> tomó esta decisión.
                    Ninguna decisión de este tipo la toma el software solo: siempre hay una persona
                    responsable con nombre.
                </span>
            </p>

            <div class="flex justify-end gap-2">
                <Boton @click="emit('cerrar')">Cancelar</Boton>
                <Boton
                    type="submit"
                    variante="destructivo"
                    :deshabilitado="! entendido || procesando"
                    class="border-0 bg-critical text-white hover:bg-critical/90"
                >{{ procesando ? 'Procesando…' : etiquetaConfirmar }}</Boton>
            </div>
        </form>
    </Modal>
</template>
