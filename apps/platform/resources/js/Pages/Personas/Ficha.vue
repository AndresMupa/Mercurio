<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppShell from '@/Components/AppShell.vue';
import Boton from '@/Components/Boton.vue';
import ConfirmWithImpact from '@/Components/ConfirmWithImpact.vue';
import Icono from '@/Components/Icono.vue';
import PurposeDialog from '@/Components/PurposeDialog.vue';
import StatusPill from '@/Components/StatusPill.vue';

const props = defineProps({
    persona: { type: Object, required: true },
    rastro: { type: Array, default: null },
    propositos: { type: Array, required: true },
    puedeEditar: { type: Boolean, default: false },
    puedeCerrar: { type: Boolean, default: false },
});

const pills = {
    active: { tono: 'ok', texto: 'Relación vigente' },
    planned: { tono: 'neutro', texto: 'Relación planeada', icono: 'reloj' },
    suspended: { tono: 'neutro', texto: 'Relación suspendida', icono: 'pausa' },
    ended: { tono: 'apagado', texto: 'Relación cerrada' },
};

/*
 * Los valores revelados viven solo en memoria y se pierden al recargar. Es a propósito:
 * guardarlos en `sessionStorage` haría que un dato P3 sobreviviera a la lectura que lo
 * autorizó, y la siguiente vez se vería sin declarar propósito otra vez.
 */
const revelados = ref({});
const campoPedido = ref(null);
const pidiendo = ref(false);
const errorRevelar = ref(null);

const nombresCampo = {
    documento: 'el documento',
    nacimiento: 'la fecha de nacimiento',
    sexo: 'el sexo registrado',
};

function pedir(campo) {
    errorRevelar.value = null;
    campoPedido.value = campo;
}

async function revelar(proposito) {
    pidiendo.value = true;

    try {
        const respuesta = await fetch(`/personas/${props.persona.id}/ver`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ campo: campoPedido.value, proposito }),
        });

        if (! respuesta.ok) {
            errorRevelar.value = (await respuesta.json()).mensaje ?? 'No se pudo mostrar el dato.';
            return;
        }

        revelados.value[campoPedido.value] = (await respuesta.json()).valor;
        campoPedido.value = null;
    } finally {
        pidiendo.value = false;
    }
}

// ── Cierre de relación · J5 ────────────────────────────────────────
const impacto = ref(null);
const cerrando = ref(false);
const motivo = ref('renuncia_voluntaria');
const ultimoDia = ref(new Date().toISOString().slice(0, 10));

async function abrirCierre() {
    const respuesta = await fetch(`/relaciones/${props.persona.relacion.id}/impacto`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (respuesta.ok) {
        impacto.value = await respuesta.json();
    }
}

function confirmarCierre() {
    cerrando.value = true;
    router.post(`/relaciones/${props.persona.relacion.id}/cierre`, {
        motivo: motivo.value,
        ultimo_dia: ultimoDia.value,
    }, { onFinish: () => { cerrando.value = false; impacto.value = null; } });
}

function fechaLarga(iso) {
    return iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('es-CO', { day: 'numeric', month: 'long', year: 'numeric' }) : '—';
}
</script>

<template>
    <Head :title="persona.nombre" />

    <AppShell :titulo="persona.nombre">
        <template #barra>
            <a href="/personas" class="flex items-center gap-1 text-xs text-accent underline-offset-2 hover:underline">
                <Icono nombre="izquierda" :tamano="13" :grosor="2.2" />
                Personas
            </a>
        </template>

        <div class="flex flex-col gap-6">

            <!-- ── encabezado: estado antes que dato ──────────────── -->
            <section class="flex flex-wrap items-start gap-6 rounded-card border border-rule bg-surface p-6">
                <div class="flex min-w-0 flex-grow flex-col gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-xl font-semibold text-ink">{{ persona.nombre }}</h2>
                        <StatusPill v-if="persona.relacion" v-bind="pills[persona.relacion.estado]" />
                    </div>

                    <dl v-if="persona.relacion" class="grid grid-cols-2 gap-6 lg:grid-cols-4">
                        <div>
                            <dt class="text-micro font-semibold uppercase tracking-wider text-muted">Estado</dt>
                            <dd class="text-sm font-medium text-ink">{{ persona.relacion.tipo }} · {{ persona.relacion.vinculacion }}</dd>
                        </div>
                        <div>
                            <dt class="text-micro font-semibold uppercase tracking-wider text-muted">Cargo</dt>
                            <dd class="text-sm text-ink">{{ persona.relacion.cargo ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-micro font-semibold uppercase tracking-wider text-muted">Sede</dt>
                            <dd class="text-sm text-ink">{{ persona.relacion.sede ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-micro font-semibold uppercase tracking-wider text-muted">Vigente desde</dt>
                            <dd class="tabular font-mono text-sm text-ink">{{ fechaLarga(persona.relacion.desde) }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="puedeEditar || puedeCerrar" class="flex shrink-0 gap-2">
                    <Boton v-if="puedeCerrar && persona.relacion?.vigente" variante="destructivo" tamano="compacto" @click="abrirCierre">
                        Cerrar relación
                    </Boton>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">

                <!-- ── datos, con los P3 enmascarados ─────────────── -->
                <section class="rounded-card border border-rule bg-surface">
                    <header class="flex h-10 items-center gap-2 border-b border-rule px-4">
                        <h3 class="text-sm font-semibold text-ink">Datos de la persona</h3>
                        <StatusPill
                            class="ml-auto"
                            tono="aviso"
                            icono="candado"
                            :texto="`${Object.keys(persona.sensibles).length - Object.keys(revelados).length} campos P3 ocultos`"
                        />
                    </header>

                    <p v-if="errorRevelar" role="alert" class="border-b border-critical-line bg-critical-soft px-4 py-2.5 text-xs text-critical">
                        {{ errorRevelar }}
                    </p>

                    <dl class="grid grid-cols-2 gap-4 p-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-micro font-semibold uppercase tracking-wider text-muted">Nombres</dt>
                            <dd class="text-sm text-ink">{{ persona.nombres }}</dd>
                        </div>
                        <div>
                            <dt class="text-micro font-semibold uppercase tracking-wider text-muted">Apellidos</dt>
                            <dd class="text-sm text-ink">{{ persona.apellidos }}</dd>
                        </div>
                        <div>
                            <dt class="text-micro font-semibold uppercase tracking-wider text-muted">Nivel educativo</dt>
                            <dd class="text-sm text-ink">{{ persona.nivelEducativo ?? '—' }}</dd>
                        </div>

                        <div v-for="(campo, clave) in persona.sensibles" :key="clave">
                            <dt class="flex items-center gap-1 text-micro font-semibold uppercase tracking-wider text-muted">
                                {{ clave === 'documento' ? 'Documento' : clave === 'nacimiento' ? 'Nacimiento' : 'Sexo' }}
                                <Icono nombre="candado" :tamano="9" :grosor="2.4" class="text-warn" />
                            </dt>
                            <dd class="flex items-center gap-2">
                                <span class="tabular font-mono text-sm" :class="revelados[clave] ? 'text-ink' : 'text-muted'">
                                    {{ revelados[clave] ?? campo.mascara ?? 'Sin registrar' }}
                                </span>
                                <button
                                    v-if="! revelados[clave] && campo.mascara"
                                    type="button"
                                    class="text-xs font-medium text-accent underline-offset-2 hover:underline"
                                    @click="pedir(clave)"
                                >
                                    Ver<span class="sr-only"> {{ nombresCampo[clave] }}, declarando un propósito</span>
                                </button>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-micro font-semibold uppercase tracking-wider text-muted">Estatuto docente</dt>
                            <dd class="text-sm text-ink">{{ persona.estatuto ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-micro font-semibold uppercase tracking-wider text-muted">Grado en el escalafón</dt>
                            <dd class="tabular font-mono text-sm text-ink">{{ persona.grado ?? '—' }}</dd>
                        </div>
                    </dl>

                    <!-- ── línea de vida de la relación ───────────── -->
                    <div v-if="persona.relacion" class="border-t border-rule p-4">
                        <h3 class="mb-3 text-sm font-semibold text-ink">Relación laboral</h3>
                        <ol class="flex items-center">
                            <li
                                v-for="(hito, i) in persona.recorrido"
                                :key="hito.estado"
                                class="flex flex-1 items-center"
                            >
                                <span class="flex w-24 flex-col items-center gap-1.5">
                                    <span
                                        class="h-3 w-3 rounded-full"
                                        :class="hito.alcanzado ? 'bg-ok' : 'border-2 border-stroke bg-surface'"
                                        aria-hidden="true"
                                    />
                                    <span class="text-micro" :class="hito.alcanzado ? 'font-semibold text-ink' : 'text-muted'">
                                        {{ hito.etiqueta }}
                                    </span>
                                    <span class="tabular font-mono text-[10.5px] text-muted">{{ hito.cuando ?? '—' }}</span>
                                </span>
                                <span
                                    v-if="i < persona.recorrido.length - 1"
                                    class="h-0.5 flex-grow"
                                    :class="hito.alcanzado ? 'bg-ok' : 'bg-rule'"
                                    aria-hidden="true"
                                />
                            </li>
                        </ol>
                        <p class="mt-3 text-micro leading-relaxed text-muted">
                            Una relación <strong>se cierra, no se borra</strong>. La fila cerrada sigue en la ficha
                            y en los reportes del periodo en que estuvo vigente.
                        </p>
                    </div>
                </section>

                <!-- ── quién ha visto sus datos ───────────────────── -->
                <section v-if="rastro" class="flex flex-col rounded-card border border-rule bg-surface">
                    <header class="flex h-10 items-center gap-2 border-b border-rule px-4">
                        <Icono nombre="escudo" :tamano="15" class="text-muted" />
                        <h3 class="text-sm font-semibold text-ink">Quién ha visto sus datos</h3>
                    </header>

                    <ol v-if="rastro.length">
                        <li v-for="(evento, i) in rastro" :key="i" class="flex flex-col gap-1 border-b border-rule px-4 py-3 last:border-b-0">
                            <p class="flex items-center gap-2">
                                <span class="tabular w-20 shrink-0 font-mono text-[10.5px] text-muted">
                                    {{ new Date(evento.cuando).toLocaleString('es-CO', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) }}
                                </span>
                                <span
                                    class="rounded-control border px-1 text-[10px] font-semibold"
                                    :class="evento.permitido ? 'border-ok-line bg-ok-soft text-ok' : 'border-critical-line bg-critical-soft text-critical'"
                                >{{ evento.permitido ? 'OK' : 'NO' }}</span>
                                <span class="truncate text-xs font-medium text-ink">{{ evento.quien }}</span>
                            </p>
                            <p class="pl-[88px] text-micro text-ink">
                                {{ evento.que }}
                                <span v-if="evento.proposito" class="tabular font-mono text-accent"> · {{ evento.proposito }}</span>
                                <span v-else-if="evento.regla" class="tabular font-mono text-critical"> · {{ evento.regla }}</span>
                            </p>
                        </li>
                    </ol>

                    <p v-else class="px-4 py-6 text-center text-xs text-muted">
                        Nadie ha consultado sus datos sensibles todavía.
                    </p>

                    <p class="mt-auto border-t border-rule bg-ground px-4 py-3 text-micro leading-relaxed text-muted">
                        Esta lista es la misma que el producto entregaría a la titular si la pidiera.
                        Que ella también pueda verla es el punto: la auditoría no es solo para el inspector.
                    </p>
                </section>
            </div>
        </div>

        <PurposeDialog
            :abierto="campoPedido !== null"
            :que="nombresCampo[campoPedido] ?? 'este dato'"
            :propositos="propositos"
            :procesando="pidiendo"
            @confirmar="revelar"
            @cerrar="campoPedido = null"
        />

        <ConfirmWithImpact
            v-if="impacto"
            abierto
            :titulo="`Cerrar la relación de ${impacto.persona}`"
            :sujeto="impacto.sujeto"
            :pierde="impacto.pierde"
            :conserva="impacto.conserva"
            :advertencia="impacto.advertencia"
            :reconocimiento="`Entiendo que a partir del día siguiente al ${ultimoDia} esta persona <strong>pierde el acceso</strong>.`"
            :responsable="$page.props.usuario?.nombre ?? 'quien ha entrado'"
            etiqueta-confirmar="Cerrar la relación"
            :procesando="cerrando"
            @confirmar="confirmarCierre"
            @cerrar="impacto = null"
        />
    </AppShell>
</template>
