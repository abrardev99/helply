<?php

namespace App\Models;

use Database\Factories\ChunkFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chunk extends Model
{
    /** @use HasFactory<ChunkFactory> */
    use HasFactory;

    use HasUuids;

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'embedded_at' => 'datetime',
        ];
    }
}
