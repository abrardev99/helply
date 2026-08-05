<?php

namespace App\Enums;

enum ChatOutcome: string
{
    case Answered = 'answered';
    case Greeted = 'greeted';
    case RefusedLowRelevance = 'refused_low_relevance';
    case RefusedUngrounded = 'refused_ungrounded';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Answered => __('Answered'),
            self::Greeted => __('Greeted'),
            self::RefusedLowRelevance => __('Refused (off-topic)'),
            self::RefusedUngrounded => __('Refused (ungrounded)'),
            self::Unavailable => __('Unavailable (not configured)'),
        };
    }

    /**
     * Whether this outcome represents a refusal (a flagged, non-answer response).
     *
     * Greeting small talk is a successful exchange, not a refusal — it must not be
     * listed as something the agent failed to answer.
     */
    public function isRefusal(): bool
    {
        return match ($this) {
            self::Answered, self::Greeted => false,
            self::RefusedLowRelevance, self::RefusedUngrounded, self::Unavailable => true,
        };
    }
}
