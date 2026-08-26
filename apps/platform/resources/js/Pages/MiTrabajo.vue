<script setup>
import { Head } from '@inertiajs/vue3';
import AppShell from '@/Components/AppShell.vue';
import MyWorkInbox from '@/Components/MyWorkInbox.vue';
import Boton from '@/Components/Boton.vue';
import { computed } from 'vue';

/*
 * La bandeja. Una sola pregunta contestada: qué me toca hoy.
 *
 * No hay tarjetas de métricas por encima ni gráficos: en una bandeja, cualquier cosa por
 * encima de la primera fila es algo que hay que saltarse todos los días para llegar a lo
 * que se vino a hacer.
 */
const props = defineProps({
    asuntos: { type: Array, required: true },
    hoy: { type: String, required: true },
});

const mios = computed(() => props.asuntos.filter((a) => a.propio).length);
const esperando = computed(() => props.asuntos.length - mios.value);
</script>

<template>
    <Head title="Mi trabajo" />

    <AppShell titulo="Mi trabajo">
        <div class="flex flex-col gap-4">
            <p class="text-base text-muted">
                <template v-if="mios">
                    <strong class="text-ink">{{ mios }}</strong>
                    {{ mios === 1 ? 'asunto te toca a ti' : 'asuntos te tocan a ti' }}<template v-if="esperando">,
                    y {{ esperando }} {{ esperando === 1 ? 'espera' : 'esperan' }} a otra persona</template>.
                </template>
                <template v-else>
                    Nada te toca hoy<template v-if="esperando">; {{ esperando }}
                    {{ esperando === 1 ? 'asunto espera' : 'asuntos esperan' }} a otra persona</template>.
                </template>
            </p>

            <MyWorkInbox :asuntos="asuntos" :hoy="hoy">
                <template #vacio>
                    Las personas están al día y no hay ninguna obligación cerca de su plazo.
                </template>
                <template #accionesVacio>
                    <Boton href="/personas">Ver la planta</Boton>
                </template>
            </MyWorkInbox>
        </div>
    </AppShell>
</template>
