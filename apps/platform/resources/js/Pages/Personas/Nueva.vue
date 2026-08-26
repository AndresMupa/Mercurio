<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppShell from '@/Components/AppShell.vue';
import Boton from '@/Components/Boton.vue';
import Icono from '@/Components/Icono.vue';
import WizardStepper from '@/Components/WizardStepper.vue';

/*
 * Recorrido J1: de buscar un documento a que la persona sume en la planta.
 *
 * La regla es que **nadie llegue al final y descubra que faltaba un dato**. Por eso:
 *
 *  - El paso 1 busca por documento antes de dejar escribir nada, que es lo que evita casi
 *    todos los duplicados.
 *  - Cada campo delicado se comprueba contra el servidor **al salir de él**, no al enviar.
 *  - El selector de sede solo ofrece las de la entidad elegida, así que el error de «sede
 *    ajena» prácticamente no puede ocurrir desde aquí. Sigue existiendo en el servidor
 *    para la carga masiva, donde nadie elige de una lista.
 */
const props = defineProps({
    catalogo: { type: Object, required: true },
});

const paso = ref(1);
const buscado = ref(null);
const avisos = ref({});
const bloqueos = ref({});

const formulario = useForm({
    document_type: 'cedula',
    document_number: '',
    given_names: '',
    family_names: '',
    birth_date: '',
    sex: 'no_informado',
    education_level: 'licenciado',
    teaching_statute: '',
    teaching_grade: '',
    legal_entity_id: props.catalogo.entidades[0]?.id ?? '',
    site_id: '',
    position_id: '',
    type: 'empleado',
    employment_type: 'indefinido',
    valid_from: new Date().toISOString().slice(0, 10),
    teaching_level: '',
    weekly_hours: null,
});

const sedesDeLaEntidad = computed(
    () => props.catalogo.sedes.filter((s) => s.entidadId === formulario.legal_entity_id)
);
const cargosDeLaEntidad = computed(
    () => props.catalogo.cargos.filter((c) => c.entidadId === formulario.legal_entity_id)
);

const pasos = computed(() => [
    { etiqueta: 'Buscar', estado: paso.value > 1 ? 'hecho' : 'actual' },
    { etiqueta: 'Persona', estado: paso.value > 2 ? 'hecho' : paso.value === 2 ? (tieneErrorEn(['given_names', 'family_names', 'birth_date']) ? 'error' : 'actual') : 'pendiente' },
    { etiqueta: 'Relación', estado: paso.value > 3 ? 'hecho' : paso.value === 3 ? (tieneErrorEn(['site_id', 'valid_from']) ? 'error' : 'actual') : 'pendiente' },
    { etiqueta: 'Asignación', estado: paso.value === 4 ? 'actual' : 'pendiente' },
]);

function tieneErrorEn(campos) {
    return campos.some((c) => bloqueos.value[c] || formulario.errors[c]);
}

async function llamar(ruta, cuerpo) {
    const respuesta = await fetch(ruta, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(cuerpo),
    });

    return respuesta.ok ? respuesta.json() : null;
}

async function buscar() {
    buscado.value = await llamar('/personas/buscar', { documento: formulario.document_number });

    if (buscado.value && ! buscado.value.encontrada) {
        paso.value = 2;
    }
}

/** Validación contextual: se dispara al salir del campo, nunca al enviar. */
async function revisar() {
    const resultado = await llamar('/personas/revisar', formulario.data());

    if (resultado) {
        bloqueos.value = resultado.bloqueos;
        avisos.value = resultado.avisos;
    }
}

function enviar() {
    formulario.post('/personas');
}
</script>

<template>
    <Head title="Nueva persona" />

    <AppShell titulo="Nueva persona">
        <template #barra>
            <p class="hidden text-xs text-muted sm:block">
                Desde buscar el documento hasta que suma en el C600
            </p>
        </template>

        <div class="-m-6">
            <WizardStepper :pasos="pasos" />

            <div class="mx-auto max-w-3xl p-6">
                <form class="flex flex-col gap-6" @submit.prevent="enviar">

                    <!-- ── 1 · buscar ────────────────────────────── -->
                    <section v-if="paso === 1" class="flex flex-col gap-4 rounded-card border border-rule bg-surface p-6">
                        <div>
                            <h2 class="text-lg font-semibold text-ink">¿Ya está registrada?</h2>
                            <p class="text-base text-muted">
                                Buscar antes de crear evita la mitad de los duplicados. El documento
                                se busca completo, no por fragmentos.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-end gap-3">
                            <div class="flex min-w-56 flex-grow flex-col gap-1">
                                <label for="doc" class="text-xs font-medium text-ink">Número de documento</label>
                                <input
                                    id="doc"
                                    v-model="formulario.document_number"
                                    type="text"
                                    inputmode="numeric"
                                    class="tabular h-11 rounded-control border-rule font-mono text-sm focus:border-accent focus:ring-0"
                                >
                            </div>
                            <Boton variante="primario" :deshabilitado="! formulario.document_number" @click="buscar">
                                <Icono nombre="buscar" :tamano="15" />
                                Buscar
                            </Boton>
                        </div>

                        <p
                            v-if="buscado?.encontrada"
                            role="alert"
                            class="flex items-start gap-3 rounded-control border border-critical-line border-l-[3px] border-l-critical bg-critical-soft px-4 py-3"
                        >
                            <Icono nombre="alerta" :tamano="18" class="mt-0.5 text-critical" />
                            <span class="text-dense leading-relaxed">
                                <strong class="text-critical">Ya existe.</strong>
                                El documento {{ formulario.document_number }} está en
                                <a :href="`/personas/${buscado.id}`" class="font-medium text-accent underline">{{ buscado.nombre }}</a>.
                                Abre esa ficha en vez de crear una segunda.
                            </span>
                        </p>

                        <p v-else-if="buscado" class="flex items-center gap-2 text-base text-ok">
                            <Icono nombre="check" :tamano="16" :grosor="2.6" />
                            No hay nadie con ese documento. Puedes continuar.
                        </p>
                    </section>

                    <!-- ── 2 · persona ───────────────────────────── -->
                    <section v-if="paso >= 2" class="flex flex-col gap-4 rounded-card border border-rule bg-surface p-6">
                        <h2 class="text-lg font-semibold text-ink">Datos de la persona</h2>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="flex flex-col gap-1">
                                <label for="nombres" class="text-xs font-medium text-ink">Nombres</label>
                                <input id="nombres" v-model="formulario.given_names" type="text" required
                                       class="h-11 rounded-control border-rule text-sm focus:border-accent focus:ring-0">
                            </div>
                            <div class="flex flex-col gap-1">
                                <label for="apellidos" class="text-xs font-medium text-ink">Apellidos</label>
                                <input id="apellidos" v-model="formulario.family_names" type="text" required
                                       class="h-11 rounded-control border-rule text-sm focus:border-accent focus:ring-0">
                            </div>

                            <div class="flex flex-col gap-1">
                                <label for="nacimiento" class="text-xs font-medium text-ink">Fecha de nacimiento</label>
                                <input
                                    id="nacimiento"
                                    v-model="formulario.birth_date"
                                    type="date"
                                    required
                                    :aria-invalid="Boolean(bloqueos.birth_date)"
                                    :aria-describedby="bloqueos.birth_date ? 'error-nacimiento' : undefined"
                                    class="tabular h-11 rounded-control font-mono text-sm focus:ring-0"
                                    :class="bloqueos.birth_date ? 'border-2 border-critical' : 'border-rule focus:border-accent'"
                                    @blur="revisar"
                                >
                                <p v-if="bloqueos.birth_date" id="error-nacimiento" role="alert"
                                   class="flex items-start gap-1.5 text-micro leading-relaxed text-critical">
                                    <Icono nombre="alerta" :tamano="13" :grosor="2.2" class="mt-px" />
                                    {{ bloqueos.birth_date }}
                                </p>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label for="nivel" class="text-xs font-medium text-ink">Nivel educativo</label>
                                <select id="nivel" v-model="formulario.education_level"
                                        class="h-11 rounded-control border-rule text-sm focus:border-accent focus:ring-0">
                                    <option v-for="(texto, clave) in catalogo.nivelesEducativos" :key="clave" :value="clave">{{ texto }}</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="paso === 2" class="flex justify-end">
                            <Boton variante="primario" @click="paso = 3">Continuar a Relación</Boton>
                        </div>
                    </section>

                    <!-- ── 3 · relación ──────────────────────────── -->
                    <section v-if="paso >= 3" class="flex flex-col gap-4 rounded-card border border-rule bg-surface p-6">
                        <h2 class="text-lg font-semibold text-ink">Relación laboral</h2>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="flex flex-col gap-1">
                                <label for="entidad" class="text-xs font-medium text-ink">Entidad jurídica</label>
                                <select id="entidad" v-model="formulario.legal_entity_id"
                                        class="h-11 rounded-control border-rule text-sm focus:border-accent focus:ring-0"
                                        @change="formulario.site_id = ''; formulario.position_id = ''">
                                    <option v-for="e in catalogo.entidades" :key="e.id" :value="e.id">{{ e.nombre }}</option>
                                </select>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label for="sede" class="text-xs font-medium text-ink">Sede</label>
                                <select id="sede" v-model="formulario.site_id" required
                                        class="h-11 rounded-control border-rule text-sm focus:border-accent focus:ring-0">
                                    <option value="" disabled>Elige una sede</option>
                                    <!-- Solo las de la entidad elegida: así el error 4 de J1 no ocurre desde aquí. -->
                                    <option v-for="s in sedesDeLaEntidad" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                                </select>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label for="vinculo" class="text-xs font-medium text-ink">Tipo de vínculo</label>
                                <select id="vinculo" v-model="formulario.type"
                                        class="h-11 rounded-control border-rule text-sm focus:border-accent focus:ring-0">
                                    <option v-for="(texto, clave) in catalogo.tiposVinculo" :key="clave" :value="clave">{{ texto }}</option>
                                </select>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label for="modalidad" class="text-xs font-medium text-ink">Modalidad</label>
                                <select id="modalidad" v-model="formulario.employment_type"
                                        class="h-11 rounded-control border-rule text-sm focus:border-accent focus:ring-0">
                                    <option v-for="(texto, clave) in catalogo.vinculaciones" :key="clave" :value="clave">{{ texto }}</option>
                                </select>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label for="desde" class="text-xs font-medium text-ink">Vigente desde</label>
                                <input id="desde" v-model="formulario.valid_from" type="date" required
                                       class="tabular h-11 rounded-control border-rule font-mono text-sm focus:border-accent focus:ring-0"
                                       @blur="revisar">
                            </div>
                        </div>

                        <!-- Solapamiento: advierte y ofrece la corrección con la fecha calculada. -->
                        <p v-if="avisos.valid_from" role="status"
                           class="flex items-start gap-3 rounded-control border border-warn-line border-l-[3px] border-l-warn bg-warn-soft px-4 py-3">
                            <Icono nombre="aviso" :tamano="16" :grosor="2" class="mt-0.5 text-warn" />
                            <span class="flex flex-col gap-2">
                                <span class="text-dense font-semibold text-warn">Se solapa con una relación vigente</span>
                                <span class="text-dense leading-relaxed text-ink">{{ avisos.valid_from.texto }}</span>
                                <span class="text-micro text-muted">
                                    Es un aviso, no un bloqueo: un traslado dentro del mismo día es legítimo.
                                </span>
                            </span>
                        </p>

                        <p v-if="bloqueos.site_id" role="alert"
                           class="flex items-start gap-2 text-micro leading-relaxed text-critical">
                            <Icono nombre="alerta" :tamano="14" :grosor="2.2" class="mt-px" />
                            {{ bloqueos.site_id }}
                        </p>

                        <div v-if="paso === 3" class="flex justify-end">
                            <Boton variante="primario" @click="paso = 4">Continuar a Asignación</Boton>
                        </div>
                    </section>

                    <!-- ── 4 · asignación ────────────────────────── -->
                    <section v-if="paso >= 4" class="flex flex-col gap-4 rounded-card border border-rule bg-surface p-6">
                        <h2 class="text-lg font-semibold text-ink">Asignación</h2>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="flex flex-col gap-1">
                                <label for="cargo" class="text-xs font-medium text-ink">Cargo</label>
                                <select id="cargo" v-model="formulario.position_id" required
                                        class="h-11 rounded-control border-rule text-sm focus:border-accent focus:ring-0">
                                    <option value="" disabled>Elige un cargo</option>
                                    <option v-for="c in cargosDeLaEntidad" :key="c.id" :value="c.id">{{ c.titulo }}</option>
                                </select>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label for="ensenanza" class="text-xs font-medium text-ink">Nivel de enseñanza</label>
                                <select id="ensenanza" v-model="formulario.teaching_level"
                                        class="h-11 rounded-control border-rule text-sm focus:border-accent focus:ring-0">
                                    <option value="">No aplica</option>
                                    <option v-for="(texto, clave) in catalogo.nivelesEnsenanza" :key="clave" :value="clave">{{ texto }}</option>
                                </select>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label for="estatuto" class="text-xs font-medium text-ink">Estatuto docente</label>
                                <select id="estatuto" v-model="formulario.teaching_statute"
                                        class="h-11 rounded-control border-rule text-sm focus:border-accent focus:ring-0">
                                    <option value="">No aplica</option>
                                    <option v-for="(texto, clave) in catalogo.estatutos" :key="clave" :value="clave">{{ texto }}</option>
                                </select>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label for="horas" class="text-xs font-medium text-ink">Horas semanales</label>
                                <input id="horas" v-model.number="formulario.weekly_hours" type="number" min="1" max="60"
                                       class="tabular h-11 rounded-control border-rule font-mono text-sm focus:border-accent focus:ring-0">
                            </div>
                        </div>
                    </section>

                    <!-- ── qué va a pasar ────────────────────────── -->
                    <section v-if="paso >= 4" class="flex flex-col gap-3 rounded-card border border-rule bg-surface p-6">
                        <h2 class="text-sm font-semibold text-ink">Al guardar</h2>
                        <ul class="flex flex-col gap-2">
                            <li class="flex items-start gap-2 text-dense leading-relaxed">
                                <Icono nombre="check" :tamano="14" :grosor="2.6" class="mt-1 text-ok" />
                                Aparece en <strong>Personas</strong> como vigente
                            </li>
                            <li class="flex items-start gap-2 text-dense leading-relaxed">
                                <Icono nombre="check" :tamano="14" :grosor="2.6" class="mt-1 text-ok" />
                                Suma en el C600 del año en curso
                            </li>
                            <li class="flex items-start gap-2 text-dense leading-relaxed">
                                <Icono nombre="check" :tamano="14" :grosor="2.6" class="mt-1 text-ok" />
                                Queda un evento de auditoría con tu nombre
                            </li>
                            <li class="flex items-start gap-2 text-dense leading-relaxed">
                                <Icono nombre="aviso" :tamano="14" :grosor="2.2" class="mt-1 text-warn" />
                                No recibe acceso todavía: crear la cuenta es un paso aparte
                            </li>
                        </ul>

                        <div class="mt-2 flex justify-end gap-2">
                            <Boton href="/personas">Cancelar</Boton>
                            <Boton
                                type="submit"
                                variante="primario"
                                :deshabilitado="formulario.processing || Object.keys(bloqueos).length > 0"
                            >{{ formulario.processing ? 'Guardando…' : 'Crear persona' }}</Boton>
                        </div>

                        <p v-if="Object.keys(bloqueos).length" class="text-right text-micro text-critical">
                            Corrige lo marcado en rojo antes de guardar.
                        </p>
                    </section>
                </form>
            </div>
        </div>
    </AppShell>
</template>
