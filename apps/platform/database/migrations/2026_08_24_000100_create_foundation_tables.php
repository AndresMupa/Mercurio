<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 0 — Foundation.
 *
 * Reglas que esta migración materializa (AGENTS.md §3, specs/identity/SPEC.md):
 *  - Toda tabla con datos de persona lleva tenant_id NOT NULL.
 *  - No existe ninguna tabla de estudiantes identificados (ADR 0003).
 *  - audit_events es append-only; los privilegios se retiran en la migración 000300.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('plan')->default('piloto');
            $t->string('region')->default('co-central');
            $t->string('status')->default('active');
            $t->string('dpa_version')->nullable();
            $t->timestampTz('dpa_accepted_at')->nullable();
            $t->timestampsTz();
        });

        Schema::create('organizations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->timestampsTz();
            $t->index('tenant_id');
        });

        Schema::create('legal_entities', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('organization_id')->constrained();
            $t->string('name');
            $t->char('country_code', 2)->default('CO');
            $t->string('tax_id')->nullable();               // NIT
            $t->string('ciiu_code')->nullable();
            $t->unsignedTinyInteger('risk_class')->nullable(); // I..V, Decreto 1072
            $t->string('tariff_regime')->nullable();           // solo educativos privados
            $t->timestampsTz();
            $t->unique(['tenant_id', 'tax_id']);
        });

        Schema::create('sites', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('legal_entity_id')->constrained();
            $t->string('name');
            $t->string('dane_code', 12)->nullable();        // llave del C600
            $t->string('address')->nullable();
            $t->string('area_type')->default('urbana');     // rural | urbana
            $t->string('department_code', 2)->nullable();   // DIVIPOLA
            $t->string('municipality_code', 5)->nullable();
            $t->boolean('is_work_center')->default(true);
            $t->timestampsTz();
            $t->index(['tenant_id', 'legal_entity_id']);
        });

        Schema::create('positions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('legal_entity_id')->constrained();
            $t->string('title');
            $t->string('personnel_type');   // taxonomía C600, ver core-entities.md
            $t->string('risk_level')->nullable();
            $t->timestampsTz();
            $t->index('tenant_id');
        });

        // people: SOLO adultos con relación. Nunca estudiantes (ADR 0003).
        Schema::create('people', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('given_names');                 // P2
            $t->string('family_names');                // P2
            $t->date('birth_date')->nullable();        // P3
            $t->string('sex')->nullable();             // P3 — exigido por el Módulo III del C600
            $t->string('education_level')->nullable(); // P2
            $t->string('teaching_statute')->nullable(); // P2 — 2277/1979, 1278/2002, 804/1995
            $t->string('teaching_grade')->nullable();  // P2 — alimenta el 3,2 % de tarifa
            $t->string('status')->default('active');
            $t->timestampsTz();
            $t->index('tenant_id');
        });

        Schema::create('identities', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('person_id')->constrained();
            $t->string('document_type');
            $t->string('document_number');              // P3
            $t->char('country_code', 2)->default('CO');
            $t->timestampsTz();
            $t->unique(['tenant_id', 'document_type', 'document_number']);
        });

        Schema::create('relationships', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('person_id')->constrained();
            $t->foreignUuid('legal_entity_id')->constrained();
            $t->foreignUuid('site_id')->nullable()->constrained();
            // Tabla P2 completa: el tipo de vínculo y su vigencia son dato laboral.
            $t->string('type');             // empleado | contratista | trabajador_contratista | ...
            $t->string('employment_type')->nullable();
            $t->date('valid_from');
            $t->date('valid_to')->nullable();  // null = vigente
            $t->string('status')->default('active'); // planned|active|suspended|ended
            $t->timestampsTz();
            $t->index(['tenant_id', 'person_id']);
            $t->index(['tenant_id', 'status', 'valid_to']);
        });

        Schema::create('assignments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('relationship_id')->constrained();
            $t->foreignUuid('position_id')->constrained();
            $t->string('teaching_level')->nullable();  // preescolar|basica_primaria|...
            $t->unsignedSmallInteger('weekly_hours')->nullable(); // P2
            $t->date('valid_from');
            $t->date('valid_to')->nullable();
            $t->timestampsTz();
            $t->index(['tenant_id', 'relationship_id']);
        });

        Schema::create('users', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('person_id')->nullable()->constrained();
            $t->string('email');                      // P2
            $t->string('password');                   // P3 — hash, nunca legible
            $t->boolean('mfa_enabled')->default(false);
            $t->text('mfa_secret')->nullable();       // P3, cifrado en aplicación
            $t->timestampTz('last_login_at')->nullable();
            $t->string('status')->default('active');
            $t->rememberToken();
            $t->timestampsTz();
            $t->unique(['tenant_id', 'email']);
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('key');      // owner | admin_rrhh | responsable_sst | ...
            $t->string('name');
            $t->timestampsTz();
            $t->unique(['tenant_id', 'key']);
        });

        Schema::create('role_assignments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('user_id')->constrained();
            $t->foreignUuid('role_id')->constrained();
            $t->string('scope_type');            // tenant | legal_entity | site | self
            $t->uuid('scope_id')->nullable();
            $t->date('valid_from');
            $t->date('valid_to')->nullable();
            $t->timestampsTz();
            $t->index(['tenant_id', 'user_id']);
        });

        // Estudiantes SOLO como conteo. Sin PII. Tabla P1 completa. (ADR 0003)
        Schema::create('enrollment_snapshots', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('site_id')->constrained();
            $t->unsignedSmallInteger('school_year');
            $t->string('level');              // preescolar|basica_primaria|basica_secundaria|media
            $t->string('grade')->nullable();  // prejardin..11
            $t->string('shift');              // completa|manana|tarde|nocturna|fin_de_semana
            $t->string('sex')->nullable();
            $t->string('age_range')->nullable();
            $t->string('condition')->default('ninguna'); // ninguna | discapacidad
            $t->string('educational_model')->default('tradicional');
            $t->unsignedInteger('headcount');
            $t->string('source')->default('manual');
            $t->timestampTz('captured_at');
            $t->timestampsTz();
            $t->index(['tenant_id', 'site_id', 'school_year']);
        });

        Schema::create('audit_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('tenant_id');
            $t->uuid('actor_user_id')->nullable();
            $t->string('actor_label')->nullable();
            $t->string('action');
            $t->string('resource_type');
            $t->string('resource_id')->nullable();
            $t->string('purpose')->nullable();
            $t->string('data_classification')->nullable();
            $t->uuid('correlation_id')->nullable();
            $t->string('source')->default('web');
            $t->string('result')->default('allowed'); // allowed | denied | error
            $t->jsonb('context')->nullable();         // P2 — nunca contenido P3/P4 dentro
            $t->timestampTz('occurred_at')->useCurrent();
            $t->index(['tenant_id', 'occurred_at']);
            $t->index(['tenant_id', 'resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_events', 'enrollment_snapshots', 'role_assignments', 'roles', 'users',
            'assignments', 'relationships', 'identities', 'people', 'positions',
            'sites', 'legal_entities', 'organizations', 'tenants',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
