<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Identity\Application\EffectiveRoles;
use App\Domains\Identity\Application\Navigation;
use App\Domains\Identity\Domain\Tenant;
use App\Domains\People\Application\WorkInbox;
use App\Domains\People\Domain\Site;
use App\Domains\Shared\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly Navigation $navegacion,
        private readonly EffectiveRoles $roles,
        private readonly WorkInbox $bandeja,
    ) {}

    /**
     * Datos compartidos con todas las páginas.
     *
     * Regla: nunca se comparte aquí un dato P3 ni P4. Lo compartido viaja en cada
     * respuesta de Inertia y quedaría fuera del control de propósito y auditoría que
     * exige `docs/architecture/data-classification.md`. Lo que va aquí es lo que pinta el
     * marco: quién eres, en qué colegio estás y qué secciones alcanzas.
     *
     * **La navegación se calcula por usuario, no es una constante.** Es la decisión de B7
     * hecha respuesta HTTP: el rector no recibe la entrada que no puede usar, así que no
     * hay nada que ocultar en el cliente.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $usuario = $request->user();

        return [
            ...parent::share($request),

            'app' => [
                'name' => config('app.name'),
                'locale' => app()->getLocale(),
            ],

            'tenant' => fn () => $this->tenant(),

            'usuario' => $usuario === null ? null : fn () => [
                'nombre' => $usuario->person?->given_names
                    ? trim("{$usuario->person->given_names} {$usuario->person->family_names}")
                    : $usuario->email,
                'rol' => $this->rolPrincipal($usuario),
                'alcance' => $this->navegacion->alcanceDe($usuario),
            ],

            'navegacion' => $usuario === null ? [] : fn () => $this->navegacion->para(
                $usuario,
                '/'.ltrim($request->path(), '/'),
                ['/mi-trabajo' => $this->bandeja->pendientesDe($usuario)],
                $this->bandeja->hayVencidosDe($usuario) ? ['/mi-trabajo'] : [],
            ),

            // Un solo aviso por respuesta: la pantalla tiene una zona viva y anunciar
            // tres cosas a la vez con un lector de pantalla es no anunciar ninguna.
            'aviso' => fn () => $request->session()->get('aviso'),
        ];
    }

    /**
     * @return array<string, ?string>
     */
    private function tenant(): array
    {
        $id = TenantContext::idOrNull();
        $tenant = $id === null ? null : Tenant::find($id);

        return [
            'nombre' => $tenant?->name,
            // La sede, no `tenant.region`: la región es dónde está desplegada la base de
            // datos —«co-central»— y ponerla bajo el nombre del colegio hacía que el rail
            // pareciera decir dónde está el colegio cuando decía otra cosa.
            //
            // Solo cuando hay una: en un colegio con varias, nombrar una sola en el marco
            // sería peor que no nombrar ninguna.
            'sede' => $this->sedeUnica(),
        ];
    }

    private function sedeUnica(): ?string
    {
        if (TenantContext::idOrNull() === null) {
            return null;
        }

        $sedes = Site::where('is_work_center', true)->limit(2)->get();

        return $sedes->count() === 1 ? $sedes->first()->name : null;
    }

    private function rolPrincipal(object $usuario): ?string
    {
        $roles = $this->roles->of($usuario);

        return $roles === [] ? null : ucfirst(str_replace('_', ' ', $roles[0]->value));
    }
}
