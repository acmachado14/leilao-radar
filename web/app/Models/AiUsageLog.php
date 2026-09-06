<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'lote_id',
    'source',
    'billed',
    'estimated_cost_brl',
    'image_count',
])]
class AiUsageLog extends Model
{
    protected function casts(): array
    {
        return [
            'billed' => 'boolean',
            'estimated_cost_brl' => 'float',
            'image_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'lote_id', 'lote_id');
    }
}
