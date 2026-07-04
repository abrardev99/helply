<?php

namespace App\Ai\Exceptions;

use App\Models\Bot;
use RuntimeException;

class MissingOpenAiKeyException extends RuntimeException
{
    public function __construct(public readonly Bot $bot)
    {
        parent::__construct("No OpenAI API key is configured for bot [{$bot->id}] or its team.");
    }
}
