<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppShell from '@/Components/AppShell.vue';
import Boton from '@/Components/Boton.vue';
import DataTable from '@/Components/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FilterBar from '@/Components/FilterBar.vue';
import Icono from '@/Components/Icono.vue';
import StatusPill from '@/Components/StatusPill.vue';

const props = defineProps({
    filas: { type: Array, required: true },
    total: { type: Number, required: true },
    pagina: { type: Number, required: true },
    paginas: { type: Number, required: true },
    orden: { type: Object, required: true },
    recuento: { type: Object, required: true },
    filtros: { type: Object, default: () => ({}) },
    puedeCrear: { type: Boolean, default: false },
});

const busqueda = ref(props.filtros.q ?? '');
const densidad = ref('compacta');
const seleccion = ref([]);

const columnas = [
    { clave: 'nombre', etiqueta: 'Nombre', ancho: 'minmax(0, 1.5fr)', ordenable: true },
    { clave: 'documento', etiqueta: 'Documento', ancho: '116px', mono: true, sensible: true },
    { clave: 'cargo', etiqueta: 'Cargo', ancho: 'minmax(0, 1.1fr)' },
    { clave: 'tipo', etiqueta: 'Tipo', ancho: '136px' },
    { clave: 'estatuto', etiqueta: 'Estatuto', ancho: '88px', mono: true },
    { clave: 'estado', etiqueta: 'Estado', ancho: '116px' },
    { clave: 'desde', etiqueta: 'Desde', ancho: '92px', numerica: true, ordenable: true },
];

const pills = {
    active: { tono: 'ok', texto: 'Vigente' },
    planned: { tono: 'neutro', texto: 'Planeada', icono: 'reloj' },
    suspended: { tono: 'neutro', texto: 'Suspendida', icono: 'pausa' },
    ended: { tono: 'apagado', texto: 'Cerrada' },
};

const activos = computed(() => {
    const lista = [];
    if (props.filtros.estado) {
        lista.push({ clave: 'estado', etiqueta: `Estado: ${pills[props.filtros.estado]?.texto ?? props.filtros.estado}` });
    }
    return lista;
});

// El buscador consulta al servidor con un respiro: sin él, escribir «Duarte» dispara seis
// consultas y las respuestas pueden llegar desordenadas.
let temporizador = null;
watch(busqueda, (valor) => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => navegar({ q: valor || undefined, pagina: 1 }), 250);
});

function navegar(cambios) {
    router.get('/personas', { ...props.filtros, ...cambios }, {
        preserveState: true, preserveScroll: true, replace: true,
    });
}

function ordenar(columna) {
    const sentido = props.orden.columna === columna && props.orden.sentido === 'asc' ? 'desc' : 'asc';
    navegar({ orden: columna, sentido });
}

/** «01·01·24» ocupa la mitad que la fecha larga y en una columna se compara igual. */
function fechaCorta(iso) {
    if (! iso) return '—';
    const [a, m, d] = iso.split('-');
    return `${d}·${m}·${a.slice(2)}`;
}
</script>

<template>
    <Head title="Personas" />

    <AppShell titulo="Personas">
        <template #barra>
            <p class="tabular hidden rounded-control border border-rule px-2 py-1 font-mono text-micro text-muted sm:block">
                {{ recuento.active ?? 0 }} vigentes · {{ recuento.suspended ?? 0 }} suspendidas ·
                {{ recuento.ended ?? 0 }} cerradas
            </p>
            <Boton v-if="puedeCrear" href="/personas/nueva" variante="primario" tamano="compacto" class="ml-auto">
                <Icono nombre="mas" :tamano="14" :grosor="2.1" />
                Nueva persona
            </Boton>
        </template>

        <div class="-m-6 flex flex-col">
            <FilterBar
                v-model:busqueda="busqueda"
                marcador="Nombre o documento completo"
                :activos="activos"
                :densidad="densidad"
                @buscar="busqueda = $event"
                @quitar="navegar({ [$event]: undefined, pagina: 1 })"
                @limpiar="navegar({ q: undefined, estado: undefined, pagina: 1 })"
                @densidad="densidad = $event"
            />

            <div class="p-6">
                <DataTable
                    v-if="filas.length"
                    :columnas="columnas"
                    :filas="filas"
                    :orden="orden"
                    :densidad="densidad"
                    :total="total"
                    :seleccion="seleccion"
                    seleccionables
                    descripcion="Personas con relación laboral en este colegio"
                    @ordenar="ordenar"
                    @seleccionar="seleccion = $event"
                >
                    <template #celda-nombre="{ fila }">
                        <a :href="`/personas/${fila.id}`" class="font-medium text-ink underline-offset-2 hover:underline">
                            {{ fila.nombre }}
                        </a>
                    </template>

                    <template #celda-documento="{ fila }">
                        <span v-if="fila.documento" class="text-muted">{{ fila.documento }}</span>
                        <span v-else class="inline-flex items-center gap-1 font-sans text-warn">
                            <Icono nombre="aviso" :tamano="11" :grosor="2.2" />
                            Falta
                        </span>
                    </template>

                    <template #celda-estado="{ fila }">
                        <StatusPill v-if="fila.estado" v-bind="pills[fila.estado]" />
                    </template>

                    <template #celda-desde="{ fila }">
                        <span class="text-muted">{{ fechaCorta(fila.desde) }}</span>
                    </template>

                    <template #celda-tipo="{ fila }">
                        <span class="text-muted">{{ fila.tipo ?? '—' }}</span>
                    </template>

                    <template #celda-estatuto="{ fila }">
                        <span class="text-muted">{{ fila.estatuto ?? '—' }}</span>
                    </template>
                </DataTable>

                <div v-else class="rounded-card border border-rule bg-surface">
                    <EmptyState
                        v-if="filtros.q || filtros.estado"
                        icono="buscar"
                        titulo="Ninguna persona coincide"
                        motivo="Ajusta la búsqueda o quita un filtro."
                    >
                        <template #motivo>
                            Ninguna de las {{ recuento.active ?? 0 }} personas vigentes coincide con lo que buscaste.
                        </template>
                        <template #acciones>
                            <Boton variante="primario" @click="navegar({ q: undefined, estado: undefined, pagina: 1 })">
                                Limpiar la búsqueda
                            </Boton>
                        </template>
                    </EmptyState>

                    <EmptyState
                        v-else
                        icono="personas"
                        titulo="Todavía no hay nadie registrado"
                        motivo="Aquí vive la planta del colegio."
                    >
                        <template #motivo>
                            Aquí vive la planta del colegio: quién trabaja, con qué vínculo y desde cuándo.
                            Es de donde el C600 saca sus cifras.
                        </template>
                        <template #acciones>
                            <Boton v-if="puedeCrear" href="/personas/nueva" variante="primario">Crear la primera</Boton>
                        </template>
                    </EmptyState>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-4 border-t border-rule bg-surface px-6 py-2.5">
                <p class="flex items-center gap-1.5 text-micro text-ink">
                    <Icono nombre="candado" :tamano="13" :grosor="2" class="text-warn" />
                    La columna <strong>Documento</strong> es P3: se muestra enmascarada. Verla completa
                    exige declarar un propósito y queda registrado.
                </p>

                <p class="tabular ml-auto font-mono text-micro text-muted">
                    Página {{ pagina }} de {{ paginas }} · {{ total }} en total
                </p>
            </div>
        </div>
    </AppShell>
</template>
