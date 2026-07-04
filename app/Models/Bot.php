<?php

namespace App\Models;

use App\Enums\BotStatus;
use Database\Factories\BotFactory;
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
 * @property BotStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $documents_count
 * @property-read Team $team
 * @property-read Collection<int, Document> $documents
 * @property-read Collection<int, Chunk> $chunks
 * @property-read Collection<int, Conversation> $conversations
 */
class Bot extends Model
{
    /** @use HasFactory<BotFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the team that owns the bot.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the documents for the bot.
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get the chunks for the bot.
     *
     * @return HasMany<Chunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }

    /**
     * Get the conversations for the bot.
     *
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embed_origins' => 'array',
            'status' => BotStatus::class,
        ];
    }
}
