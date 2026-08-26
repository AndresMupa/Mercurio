<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Application\TenantResolver;
use App\Domains\Identity\Domain\Role;
use App\Domains\Identity\Domain\Tenant;
use App\Domains\People\Domain\Assignment;
use App\Domains\People\Domain\EnrollmentSnapshot;
use App\Domains\People\Domain\LegalEntity;
use App\Domains\People\Domain\Organization;
use App\Domains\People\Domain\Person;
use App\Domains\People\Domain\PersonIdentity;
use App\Domains\People\Domain\Position;
use App\Domains\People\Domain\Relationship;
use App\Domains\People\Domain\RelationshipStatus;
use App\Domains\People\Domain\Site;
use App\Domains\Shared\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Tenant de demostración con **la forma** del colegio ancla (PLAN.md B6, CA-10).
 *
 * La forma, no los datos: 64 personas con la distribución real por tipo de personal, una
 * sede rural con 29 aulas, 883 estudiantes como conteos con 13 en condición de
 * discapacidad. Cada nombre y cada documento salen de `SyntheticPeople`, que los inventa.
 *
 * Que las cifras coincidan con `docs/anchor/colegio-finlandes.md` no es capricho: el Slice
 * 1 va a derivar el C600 de aquí, y si el demo sumara 881 estudiantes nadie sabría si el
 * error está en el motor de reportes o en los datos de prueba.
 */
final class AnchorTenantSeeder extends Seeder
{
    public function __construct(private readonly TenantResolver $resolver) {}

    /**
     * Plantilla del ancla: 47 docentes + 6 directivos + 2 apoyo + 4 administrativos +
     * 5 servicios generales = **64**. La suma se comprueba en la prueba de B6.
     *
     * `teaching_statute` refleja la situación real del colegio: **no alcanza** el 80 % de
     * la escala del Decreto 2277, que es justo por lo que pierde los 3,2 puntos de tarifa.
     * El simulador del paso C4 necesita partir de esa foto para que su respuesta signifique
     * algo; si el demo ya estuviera en el 80 %, no habría nada que simular.
     *
     * @var list<array{0: string, 1: int, 2: string, 3: ?string, 4: ?string, 5: string}>
     */
    private const STAFF = [
        // [cargo, cuántos, personnel_type, teaching_statute, teaching_level, education_level]
        ['Rector', 1, 'directivo_docente', '1278_2002', null, 'posgrado'],
        ['Coordinador', 4, 'directivo_docente', '1278_2002', null, 'posgrado'],
        ['Secretario académico', 1, 'directivo_docente', null, null, 'licenciado'],

        ['Docente de preescolar', 4, 'docente_aula', '1278_2002', 'preescolar', 'licenciado'],
        ['Docente de primaria', 18, 'docente_aula', '1278_2002', 'basica_primaria', 'licenciado'],
        ['Docente de secundaria', 15, 'docente_aula', '2277_1979', 'basica_secundaria', 'licenciado'],
        ['Docente de media', 10, 'docente_aula', '2277_1979', 'media', 'posgrado'],

        ['Orientadora escolar', 1, 'docente_orientador', null, null, 'posgrado'],
        ['Auxiliar de notas', 1, 'apoyo_en_aula', null, null, 'bachillerato_pedagogico'],

        // La enfermera pasante es la señal de riesgo 1 del documento del ancla: una sola
        // persona para 883 estudiantes. Aparece en el demo porque el Slice 3 la necesita.
        ['Enfermera pasante', 1, 'administrativo', null, null, 'normalista_superior'],
        ['Secretaria', 1, 'administrativo', null, null, 'bachillerato_pedagogico'],
        ['Contadora', 1, 'administrativo', null, null, 'licenciado'],
        ['Talento humano', 1, 'administrativo', null, null, 'licenciado'],

        ['Servicios generales', 5, 'servicios_generales', null, null, 'sin_titulacion'],
    ];

    /** Grados del escalafón, repartidos para que el simulador de C4 tenga variedad real. */
    private const TEACHING_GRADES = ['7', '8', '9', '10', '11', '12', '13', '14'];

    public function run(): void
    {
        // Se niega en vez de sobrescribir. Volver a sembrar encima exigiría borrar el
        // tenant anterior, y borrar un tenant arrastra en cascada todo lo suyo: es
        // justamente la operación irreversible que está esperando decisión humana en
        // STATE.md. Un seeder no la toma por su cuenta.
        foreach (['colegio-demo', 'colegio-vecino'] as $slug) {
            if ($this->resolver->bySlug($slug) !== null) {
                $this->command?->warn(
                    "El tenant «{$slug}» ya existe: no se siembra nada. ".
                    'Para empezar de cero: php artisan migrate:fresh --seed'
                );

                return;
            }
        }

        $demo = $this->createAnchorShapedTenant();

        // Cuentas para poder entrar. B6 dejó un colegio de 64 personas y ninguna forma de
        // mirarlo desde el navegador, que para un demo es como no tenerlo.
        (new DemoUsers)->crear();

        // Segundo tenant, exigido por B6: sin él, las pruebas de aislamiento no tienen
        // contra qué aislar y pasarían por no haber nada que filtrar.
        $this->createNeighbourTenant();

        $this->command?->info("Tenant demo «{$demo->slug}» creado con la forma del ancla.");
    }

    private function createAnchorShapedTenant(): Tenant
    {
        $tenant = $this->createTenant('colegio-demo', 'Colegio Demostración Juan Pablo II');

        $organization = Organization::create(['name' => 'Colegio Demostración Juan Pablo II']);

        $legalEntity = LegalEntity::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Colegio Demostración Juan Pablo II S.A.S.',
            'country_code' => 'CO',
            'tax_id' => '900123456-7',            // NIT ficticio
            'ciiu_code' => '8521',                // educación básica primaria
            'risk_class' => 1,
            'tariff_regime' => 'libertad_regulada',
        ]);

        $site = Site::create([
            'legal_entity_id' => $legalEntity->getKey(),
            'name' => 'Sede principal — vereda Boyero',
            'dane_code' => '325430001368',        // forma del código DANE del ancla
            'address' => 'Vereda Boyero',
            'area_type' => 'rural',
            'department_code' => '25',            // Cundinamarca
            'municipality_code' => '25430',       // Madrid
            'is_work_center' => true,
            'classroom_count' => 29,
            'built_area_m2' => 2476,
            'lot_area_m2' => 4633,
        ]);

        $this->createRoles();
        $this->createStaff($legalEntity, $site);
        $this->createEnrollment($site);

        return $tenant;
    }

    /** El vecino existe para que las pruebas de aislamiento tengan algo que no deban ver. */
    private function createNeighbourTenant(): void
    {
        $tenant = $this->createTenant('colegio-vecino', 'Colegio Vecino');

        $organization = Organization::create(['name' => 'Colegio Vecino']);
        $legalEntity = LegalEntity::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Colegio Vecino S.A.S.',
            'tax_id' => '900987654-3',
        ]);
        $site = Site::create([
            'legal_entity_id' => $legalEntity->getKey(),
            'name' => 'Sede única',
            'area_type' => 'urbana',
            'classroom_count' => 12,
        ]);

        $generator = new SyntheticPeople;
        $position = Position::create([
            'legal_entity_id' => $legalEntity->getKey(),
            'title' => 'Docente de aula',
            'personnel_type' => 'docente_aula',
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->createPersonWithRelationship(
                $generator->next($i % 2 === 0 ? 'femenino' : 'masculino', 30 + $i),
                $legalEntity,
                $site,
                $position,
                null,
                null,
                'licenciado',
            );
        }

        $this->command?->info("Tenant «{$tenant->slug}» creado para las pruebas de aislamiento.");
    }

    private function createTenant(string $slug, string $name): Tenant
    {
        $id = (string) Str::uuid();

        // `tenants` está bajo RLS con su propio `id` como llave: hay que fijar el contexto
        // al id nuevo **antes** de insertar, o el WITH CHECK lo rechaza.
        TenantContext::set($id);

        $tenant = new Tenant([
            'name' => $name,
            'slug' => $slug,
            'plan' => 'piloto',
            'region' => 'co-central',
            'status' => 'active',
        ]);
        $tenant->id = $id;
        $tenant->save();

        return $tenant;
    }

    private function createRoles(): void
    {
        $roles = [
            'owner' => 'Propietario',
            'admin_rrhh' => 'Talento humano',
            'rector' => 'Rector',
            'coordinador' => 'Coordinación',
            'responsable_sst' => 'Responsable de SST',
            'trabajador' => 'Trabajador',
            'auditor' => 'Auditoría',
        ];

        foreach ($roles as $key => $name) {
            Role::create(['key' => $key, 'name' => $name]);
        }
    }

    private function createStaff(LegalEntity $legalEntity, Site $site): void
    {
        $generator = new SyntheticPeople;
        $indice = 0;

        foreach (self::STAFF as [$titulo, $cuantos, $tipo, $estatuto, $nivel, $educacion]) {
            $position = Position::create([
                'legal_entity_id' => $legalEntity->getKey(),
                'title' => $titulo,
                'personnel_type' => $tipo,
                'risk_level' => 'I',
            ]);

            for ($i = 0; $i < $cuantos; $i++) {
                // Mayoría femenina, como la planta docente colombiana real.
                $sexo = $indice % 10 < 7 ? 'femenino' : 'masculino';
                $edad = 26 + (($indice * 7) % 34);   // entre 26 y 59: siempre adultos

                $this->createPersonWithRelationship(
                    $generator->next($sexo, $edad),
                    $legalEntity,
                    $site,
                    $position,
                    $estatuto,
                    $estatuto === null ? null : self::TEACHING_GRADES[$indice % count(self::TEACHING_GRADES)],
                    $educacion,
                    $nivel,
                );

                $indice++;
            }
        }
    }

    /** @param  array<string, string>  $datos */
    private function createPersonWithRelationship(
        array $datos,
        LegalEntity $legalEntity,
        Site $site,
        Position $position,
        ?string $estatuto,
        ?string $gradoEscalafon,
        string $nivelEducativo,
        ?string $nivelEnsenanza = null,
    ): Person {
        $person = Person::create([
            'given_names' => $datos['given_names'],
            'family_names' => $datos['family_names'],
            'birth_date' => $datos['birth_date'],
            'sex' => $datos['sex'],
            'education_level' => $nivelEducativo,
            'teaching_statute' => $estatuto,
            'teaching_grade' => $gradoEscalafon,
            'status' => 'active',
        ]);

        PersonIdentity::create([
            'person_id' => $person->getKey(),
            'document_type' => 'cedula',
            'document_number' => $datos['document_number'],
            'country_code' => 'CO',
        ]);

        $relationship = Relationship::create([
            'person_id' => $person->getKey(),
            'legal_entity_id' => $legalEntity->getKey(),
            'site_id' => $site->getKey(),
            'type' => 'empleado',
            'employment_type' => 'indefinido',
            'valid_from' => now()->subYears(2)->startOfYear()->toDateString(),
            'status' => RelationshipStatus::Active,
        ]);

        Assignment::create([
            'relationship_id' => $relationship->getKey(),
            'position_id' => $position->getKey(),
            'teaching_level' => $nivelEnsenanza,
            'weekly_hours' => $nivelEnsenanza === null ? 44 : 40,
            'valid_from' => $relationship->valid_from->toDateString(),
        ]);

        return $person;
    }

    private function createEnrollment(Site $site): void
    {
        foreach (AnchorEnrollment::rows() as $fila) {
            EnrollmentSnapshot::create([
                ...$fila,
                'site_id' => $site->getKey(),
                'captured_at' => now(),
            ]);
        }
    }
}
