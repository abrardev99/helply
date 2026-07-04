<?php

namespace App\Enums;

enum ChatOutcome: string
{
    case Answered = 'answered';
    case RefusedLowRelevance = 'refused_low_relevance';
    case RefusedUngrounded = 'refused_ungrounded';
    case Unavailable = 'unavailable';

    /**
     * Get the display label for the outcome.
     */
    public function label(): string
    {
        return match ($this) {
            self::Answered => __('Answered'),
            self::RefusedLowRelevance => __('Refused (off-topic)'),
            self::RefusedUngrounded => __('Refused (ungrounded)'),
            self::Unavailable => __('Unavailable (not configured)'),
        };
    }

    /**
     * Whether this outcome represents a refusal (a flagged, non-answer response).
     */
    public function isRefusal(): bool
    {
        return $this !== self::Answered;
    }
}
