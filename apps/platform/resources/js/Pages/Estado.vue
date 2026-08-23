<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    paso: { type: String, required: true },
    slice: { type: String, required: true },
    comprobaciones: { type: Array, required: true },
});

// El color nunca es el único canal (design-system.md, principio 4):
// cada estado lleva además símbolo y texto.
const marca = {
    ok: { simbolo: '✓', etiqueta: 'Correcto', clase: 'text-ok border-ok' },
    warn: { simbolo: '!', etiqueta: 'Atención', clase: 'text-warn border-warn' },
    critical: { simbolo: '✕', etiqueta: 'Falla', clase: 'text-critical border-critical' },
};
</script>

<template>
    <Head title="Estado de la fundación" />

    <main class="mx-auto max-w-3xl px-6 py-12">
        <header class="border-b border-rule pb-6">
            <p class="text-xs uppercase tracking-widest text-muted">{{ slice }}</p>
            <h1 class="mt-2 text-2xl font-semibold text-ink">Estado de la fundación</h1>
            <p class="mt-2 text-sm text-muted">
                Paso en ejecución: <span class="tabular font-medium text-ink">{{ paso }}</span>
            </p>
        </header>

        <section class="mt-8" aria-labelledby="comprobaciones">
            <h2 id="comprobaciones" class="text-sm font-semibold uppercase tracking-wide text-muted">
                Comprobaciones de infraestructura
            </h2>

            <ul class="mt-4 divide-y divide-rule rounded-card border border-rule bg-surface">
                <li
                    v-for="c in comprobaciones"
                    :key="c.nombre"
                    class="flex items-start gap-4 px-4 py-3"
                >
                    <span
                        class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-control border text-xs font-bold"
                        :class="marca[c.estado].clase"
                        :aria-label="marca[c.estado].etiqueta"
                        role="img"
                    >{{ marca[c.estado].simbolo }}</span>

                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-ink">{{ c.nombre }}</span>
                        <span class="block text-sm text-muted">{{ c.detalle }}</span>
                    </span>

                    <span class="shrink-0 text-xs font-medium uppercase tracking-wide"
                          :class="marca[c.estado].clase.split(' ')[0]">
                        {{ marca[c.estado].etiqueta }}
                    </span>
                </li>
            </ul>
        </section>

        <p class="mt-8 border-l-2 border-rule pl-4 text-sm text-muted">
            Esta pantalla existe solo para dar por cumplido el entregable del paso A1: el proyecto
            levanta y responde en el navegador. Las pantallas del producto se diseñan en el paso B7
            y se construyen en el B8, no antes.
        </p>
    </main>
</template>
