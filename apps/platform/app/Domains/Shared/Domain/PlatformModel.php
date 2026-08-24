<?php

declare(strict_types=1);

namespace App\Domains\Shared\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de todo modelo del núcleo: clave uuid, aislamiento por tenant y auditoría de
 * escrituras desde el primer momento, para que ningún modelo nuevo pueda nacer fuera de
 * esas garantías por olvido. Quien añada un modelo no tiene que acordarse de nada.
 */
abstract class PlatformModel extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use RecordsAuditTrail;

    public $incrementing = false;

    protected $keyType = 'string';
}
