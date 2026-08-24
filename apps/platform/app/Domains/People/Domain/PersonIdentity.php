<?php

declare(strict_types=1);

namespace App\Domains\People\Domain;

use App\Domains\Shared\Domain\PlatformModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento de identidad. `document_number` es P3.
 *
 * La clase se llama PersonIdentity y no Identity para no confundirse con el bounded
 * context Identity & Access, que trata del acceso a la plataforma y no del documento.
 */
final class PersonIdentity extends PlatformModel
{
    protected $table = 'identities';

    protected $fillable = ['person_id', 'document_type', 'document_number', 'country_code'];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
