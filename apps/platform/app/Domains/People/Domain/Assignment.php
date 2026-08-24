<?php

declare(strict_types=1);

namespace App\Domains\People\Domain;

use App\Domains\Shared\Domain\PlatformModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Assignment extends PlatformModel
{
    protected $fillable = [
        'relationship_id', 'position_id', 'teaching_level',
        'weekly_hours', 'valid_from', 'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'weekly_hours' => 'integer',
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(Relationship::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
