<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Boton from '@/Components/Boton.vue';
import Icono from '@/Components/Icono.vue';

/*
 * Acceso, con el segundo factor en el mismo formulario.
 *
 * El campo del código aparece cuando el servidor dice que hace falta, y no antes: pedirlo
 * siempre confundiría a los roles con techo P2, que no lo necesitan.
 *
 * Todos los fallos —correo inexistente, contraseña incorrecta, código erróneo— llegan con
 * el mismo mensaje. Distinguirlos convertiría este formulario en un directorio del
 * personal del colegio, y esa decisión se tomó en B5.
 */
defineProps({
    colegio: { type: String, default: null },
});

const page = usePage();
const pideCodigo = computed(() => Boolean(page.props.flash?.mfa ?? page.props.mfa));

const formulario = useForm({ email: '', password: '', codigo: '' });
const verContrasena = ref(false);

function enviar() {
    formulario.post('/entrar', {
        onFinish: () => formulario.reset('password', 'codigo'),
    });
}
</script>

<template>
    <Head title="Entrar" />

    <main class="flex min-h-screen items-center justify-center bg-ground px-4 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-6 flex flex-col gap-1">
                <p class="flex items-center gap-2 text-lg font-semibold text-ink">
                    <Icono nombre="colegio" :tamano="20" class="text-accent" />
                    {{ colegio ?? 'Mercurio' }}
                </p>
                <p class="text-xs text-muted">Personas, trabajo, salud y seguridad</p>
            </div>

            <form
                class="flex flex-col gap-6 rounded-card border border-rule bg-surface p-8"
                @submit.prevent="enviar"
            >
                <!--
                  El error va antes de los campos y con `role="alert"`: quien usa lector de
                  pantalla lo oye al llegar, no después de haber recorrido el formulario.
                -->
                <p
                    v-if="formulario.errors.email"
                    role="alert"
                    class="flex items-start gap-3 rounded-control border border-critical-line border-l-[3px] border-l-critical bg-critical-soft px-4 py-3"
                >
                    <Icono nombre="alerta" :tamano="18" :grosor="1.8" class="mt-0.5 text-critical" />
                    <span>
                        <span class="block text-sm font-semibold text-critical">No pudimos validar esos datos</span>
                        <span class="block text-xs text-ink">{{ formulario.errors.email }}</span>
                    </span>
                </p>

                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <label for="email" class="text-xs font-medium text-ink">Correo institucional</label>
                        <input
                            id="email"
                            v-model="formulario.email"
                            type="email"
                            name="email"
                            autocomplete="username"
                            required
                            autofocus
                            class="tabular h-11 rounded-control border-rule font-mono text-sm text-ink focus:border-accent focus:ring-0"
                        >
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="password" class="text-xs font-medium text-ink">Contraseña</label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="formulario.password"
                                :type="verContrasena ? 'text' : 'password'"
                                name="password"
                                autocomplete="current-password"
                                required
                                class="h-11 w-full rounded-control border-rule pr-11 text-sm text-ink focus:border-accent focus:ring-0"
                            >
                            <button
                                type="button"
                                class="absolute right-0 top-0 flex h-11 w-11 items-center justify-center text-muted hover:text-ink"
                                :aria-pressed="verContrasena"
                                @click="verContrasena = ! verContrasena"
                            >
                                <Icono nombre="ojo" :tamano="18" />
                                <span class="sr-only">{{ verContrasena ? 'Ocultar' : 'Mostrar' }} la contraseña</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="pideCodigo" class="flex flex-col gap-1.5">
                        <label for="codigo" class="text-xs font-medium text-ink">Código de tu aplicación</label>
                        <input
                            id="codigo"
                            v-model="formulario.codigo"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            class="tabular h-14 rounded-control border-rule text-center font-mono text-xl tracking-[0.4em] text-ink focus:border-accent focus:ring-0"
                        >
                        <p class="flex items-center gap-1.5 text-micro text-muted">
                            <Icono nombre="reloj" :tamano="13" :grosor="1.8" />
                            Cambia cada 30 s. Se aceptan el anterior y el siguiente por si tu reloj va desfasado.
                        </p>
                    </div>
                </div>

                <Boton type="submit" variante="primario" :deshabilitado="formulario.processing">
                    {{ formulario.processing ? 'Comprobando…' : (pideCodigo ? 'Verificar' : 'Entrar') }}
                </Boton>

                <p class="border-t border-rule pt-4 text-xs text-muted">
                    Tu rol puede exigir un segundo factor. Si lo exige y no lo tienes configurado,
                    habla con quien administra la plataforma.
                </p>
            </form>
        </div>
    </main>
</template>
