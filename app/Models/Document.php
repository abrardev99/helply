<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $agent_id
 * @property DocumentType $type
 * @property string|null $source_url
 * @property string|null $title
 * @property DocumentStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent $agent
 * @property-read Collection<int, Chunk> $chunks
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use HasUuids;

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return HasMany<Chunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
        ];
    }
}
