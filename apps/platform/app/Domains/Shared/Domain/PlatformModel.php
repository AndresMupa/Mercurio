<?php

declare(strict_types=1);

namespace App\Domains\Shared\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de todo modelo del núcleo: clave uuid y aislamiento por tenant desde el primer
 * momento, para que ningún modelo nuevo pueda nacer fuera de la primera capa por olvido.
 */
abstract class PlatformModel extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
}
