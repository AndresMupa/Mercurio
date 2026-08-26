<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icono from './Icono.vue';

/*
 * El marco de toda pantalla del producto: rail de navegación, barra superior y contenido.
 *
 * **La navegación llega del servidor, no está escrita aquí.** Es lo que hace real la
 * decisión de B7 sobre el rector: no ve un «Personas · crear» en gris, no ve la entrada
 * siquiera, porque el servidor no se la manda. Un menú que el cliente filtra es un menú
 * que el cliente puede desfiltrar, y además enseña el mapa completo de lo que existe.
 *
 * Tres cosas de accesibilidad que viven aquí y no en cada pantalla: el salto al contenido
 * —sin él, quien navega con teclado recorre seis enlaces en cada página—, las regiones
 * `nav`/`main` con nombre, y la zona viva donde se anuncian los avisos del servidor.
 */
defineProps({
    titulo: { type: String, required: true },
});

const page = usePage();

const tenant = computed(() => page.props.tenant ?? {});
const usuario = computed(() => page.props.usuario ?? {});
const navegacion = computed(() => page.props.navegacion ?? []);
const aviso = computed(() => page.props.aviso ?? null);

const iniciales = computed(() => {
    const partes = (usuario.value.nombre ?? '').trim().split(/\s+/).filter((p) => p.length > 2);

    return ((partes[0]?.[0] ?? '') + (partes[1]?.[0] ?? '')).toUpperCase() || '?';
});

const hoy = new Date().toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <a href="#contenido" class="salto-contenido">Saltar al contenido</a>

    <div class="flex min-h-screen bg-ground">

        <!-- ── rail ─────────────────────────────────────── -->
        <nav
            aria-label="Secciones"
            class="hidden w-rail shrink-0 flex-col border-r border-rule bg-surface md:flex"
        >
            <div class="flex flex-col gap-1 border-b border-rule p-4">
                <p class="flex items-center gap-2 text-sm font-semibold text-ink">
                    <Icono nombre="colegio" :tamano="18" class="text-accent" />
                    {{ tenant.nombre }}
                </p>
                <p v-if="tenant.sede" class="text-micro text-muted">{{ tenant.sede }}</p>
            </div>

            <ul class="flex flex-grow flex-col gap-0.5 p-2">
                <li v-for="entrada in navegacion" :key="entrada.ruta">
                    <Link
                        :href="entrada.ruta"
                        class="flex min-h-[44px] items-center gap-2 rounded-control px-2 text-sm sm:min-h-[34px]"
                        :class="entrada.activa
                            ? 'border-l-2 border-accent bg-accent-soft font-semibold text-accent'
                            : 'text-ink hover:bg-ground'"
                        :aria-current="entrada.activa ? 'page' : undefined"
                    >
                        <Icono :nombre="entrada.icono" :tamano="16" :class="entrada.activa ? '' : 'text-muted'" />
                        <span class="flex-grow">{{ entrada.etiqueta }}</span>
                        <span
                            v-if="entrada.pendientes"
                            class="tabular rounded-control px-1.5 font-mono text-micro"
                            :class="entrada.urgente ? 'bg-critical text-white' : 'bg-accent text-white'"
                        >{{ entrada.pendientes }}<span class="sr-only"> pendientes</span></span>
                    </Link>
                </li>
            </ul>

            <div class="flex flex-col gap-2 border-t border-rule px-4 py-3">
                <p class="flex items-center gap-2">
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-control bg-accent-soft text-xs font-semibold text-accent"
                        aria-hidden="true"
                    >{{ iniciales }}</span>
                    <span class="min-w-0 flex-grow">
                        <span class="block truncate text-xs font-medium text-ink">{{ usuario.nombre }}</span>
                        <span class="block text-micro text-muted">{{ usuario.rol }}</span>
                    </span>
                </p>

                <!-- Lo que el rol alcanza, dicho una vez y sin regañar. -->
                <p v-if="usuario.alcance" class="rounded-control border border-rule px-1.5 py-1 text-[10px] leading-snug text-muted">
                    {{ usuario.alcance }}
                </p>

                <Link
                    href="/salir"
                    method="post"
                    as="button"
                    class="min-h-[44px] rounded-control text-left text-xs text-muted hover:text-ink sm:min-h-0"
                >Cerrar sesión</Link>
            </div>
        </nav>

        <!-- ── contenido ────────────────────────────────── -->
        <div class="flex min-w-0 flex-grow flex-col">
            <header class="flex h-topbar shrink-0 items-center gap-4 border-b border-rule bg-surface px-6">
                <h1 class="text-base font-semibold text-ink">{{ titulo }}</h1>
                <slot name="barra" />
                <p class="tabular ml-auto shrink-0 font-mono text-micro text-muted">{{ hoy }}</p>
            </header>

            <!--
              Zona viva: aquí aterrizan los avisos del servidor tras una acción. `polite`
              y no `assertive` porque interrumpir lo que el lector está diciendo para
              anunciar «guardado» es peor que esperar a que termine la frase.
            -->
            <div aria-live="polite" role="status">
                <p
                    v-if="aviso"
                    class="border-b px-6 py-2.5 text-base"
                    :class="aviso.tono === 'error'
                        ? 'border-critical-line bg-critical-soft text-critical'
                        : 'border-ok-line bg-ok-soft text-ok'"
                >{{ aviso.texto }}</p>
            </div>

            <main id="contenido" tabindex="-1" class="flex-grow p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
