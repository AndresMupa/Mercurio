<?php

declare(strict_types=1);

namespace App\Domains\People\Domain;

use App\Domains\Shared\Domain\PlatformModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estudiantes como conteo agregado, nunca como identidad (ADR 0003, invariante 9).
 *
 * Si algún día alguien intenta añadir aquí un nombre, un documento o una fecha de
 * nacimiento, hay una prueba que rompe. La regla no depende de que se lea este comentario.
 */
final class EnrollmentSnapshot extends PlatformModel
{
    protected $fillable = [
        'site_id', 'school_year', 'level', 'grade', 'shift', 'sex', 'age_range',
        'condition', 'educational_model', 'headcount', 'source', 'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'school_year' => 'integer',
            'headcount' => 'integer',
            'captured_at' => 'immutable_datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
