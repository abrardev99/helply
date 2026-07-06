<?php

namespace App\Ai\Exceptions;

use App\Models\Agent;
use RuntimeException;

class MissingOpenAiKeyException extends RuntimeException
{
    public function __construct(public Agent $agent)
    {
        parent::__construct("No OpenAI API key is configured for agent [{$agent->id}] or its team.");
    }
}
