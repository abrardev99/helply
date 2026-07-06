<?php

namespace App\Models;

use App\Enums\MessageRole;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $conversation_id
 * @property MessageRole $role
 * @property string $content
 * @property array<int, mixed>|null $sources
 * @property float|null $retrieval_score
 * @property bool $flagged
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conversation $conversation
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    use HasUuids;

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'sources' => 'array',
            'retrieval_score' => 'float',
            'flagged' => 'boolean',
        ];
    }
}
