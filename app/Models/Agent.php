<?php

namespace App\Models;

use App\Enums\AgentStatus;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $team_id
 * @property string $name
 * @property array<int, string>|null $embed_origins
 * @property AgentStatus $status
 * @property string|null $openai_api_key
 * @property string $embedding_model
 * @property string $chat_model
 * @property string|null $system_prompt
 * @property float $confidence_threshold
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $documents_count
 * @property-read Team $team
 * @property-read Collection<int, Document> $documents
 * @property-read Collection<int, Chunk> $chunks
 * @property-read Collection<int, Conversation> $conversations
 */
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Keeps the decrypted per-agent OpenAI key out of any Inertia/JSON payload.
     *
     * @var list<string>
     */
    protected $hidden = ['openai_api_key'];

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<Chunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'embed_origins' => 'array',
            'status' => AgentStatus::class,
            'openai_api_key' => 'encrypted',
            'confidence_threshold' => 'float',
        ];
    }
}
