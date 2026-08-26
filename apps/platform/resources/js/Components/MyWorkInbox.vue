<script setup>
import { Link } from '@inertiajs/vue3';
import DueDateBadge from './DueDateBadge.vue';
import EmptyState from './EmptyState.vue';
import OwnerChip from './OwnerChip.vue';
import StatusPill from './StatusPill.vue';

/*
 * La bandeja: qué te toca, quién responde, qué sigue y cuándo vence.
 *
 * Es el principio 1 del sistema de diseño hecho componente. Las cuatro columnas —ESTADO ·
 * RESPONSABLE · SIGUIENTE ACCIÓN · VENCE— no son configurables a propósito: una bandeja
 * donde cada pantalla elige sus columnas deja de ser una bandeja y vuelve a ser una lista.
 *
 * Cada fila la arma el servidor. El componente no sabe qué es una relación laboral ni un
 * C600; recibe asuntos y los pinta.
 */
defineProps({
    /*
     * [{ id, tono, estado, icono, titulo, detalle, responsable, esRol, propio,
     *    accion, ruta, vence }]
     */
    asuntos: { type: Array, required: true },
    hoy: { type: String, default: null },
});
</script>

<template>
    <div class="rounded-card border border-rule bg-surface">
        <div v-if="asuntos.length" role="list">
            <!-- Cabecera de columnas: decorativa para el lector, que ya oye cada fila entera. -->
            <div
                class="hidden grid-cols-[128px_minmax(0,1fr)_156px_168px_104px] gap-3 border-b border-rule bg-ground px-4 py-2 lg:grid"
                aria-hidden="true"
            >
                <span class="text-micro font-semibold uppercase tracking-wider text-muted">Estado</span>
                <span class="text-micro font-semibold uppercase tracking-wider text-muted">Asunto</span>
                <span class="text-micro font-semibold uppercase tracking-wider text-muted">Responsable</span>
                <span class="text-micro font-semibold uppercase tracking-wider text-muted">Siguiente acción</span>
                <span class="text-right text-micro font-semibold uppercase tracking-wider text-muted">Vence</span>
            </div>

            <div
                v-for="asunto in asuntos"
                :key="asunto.id"
                role="listitem"
                class="grid grid-cols-1 gap-2 border-b border-rule px-4 py-3 last:border-b-0 lg:grid-cols-[128px_minmax(0,1fr)_156px_168px_104px] lg:items-center lg:gap-3"
                :class="{
                    'border-l-2 border-l-critical': asunto.tono === 'critico',
                    'border-l-2 border-l-warn': asunto.tono === 'aviso',
                }"
            >
                <StatusPill :tono="asunto.tono" :texto="asunto.estado" :icono="asunto.icono" />

                <div class="min-w-0">
                    <p class="text-sm font-medium text-ink">{{ asunto.titulo }}</p>
                    <p v-if="asunto.detalle" class="text-micro text-muted" :class="asunto.detalleMono ? 'tabular font-mono' : ''">
                        {{ asunto.detalle }}
                    </p>
                </div>

                <OwnerChip
                    :nombre="asunto.responsable"
                    :propio="asunto.propio"
                    :es-rol="asunto.esRol"
                />

                <p class="text-xs">
                    <Link
                        v-if="asunto.ruta"
                        :href="asunto.ruta"
                        class="font-medium text-accent underline-offset-2 hover:underline"
                    >{{ asunto.accion }}</Link>
                    <span v-else class="text-muted">{{ asunto.accion ?? '—' }}</span>
                </p>

                <p class="lg:text-right">
                    <DueDateBadge :vence="asunto.vence" :hoy="hoy" />
                </p>
            </div>
        </div>

        <EmptyState
            v-else
            icono="check"
            tono="ok"
            titulo="Nada pendiente hoy"
            motivo="No hay ningún asunto esperándote."
        >
            <template #motivo>
                <slot name="vacio">No hay ningún asunto esperándote.</slot>
            </template>
            <template v-if="$slots.accionesVacio" #acciones>
                <slot name="accionesVacio" />
            </template>
        </EmptyState>
    </div>
</template>
