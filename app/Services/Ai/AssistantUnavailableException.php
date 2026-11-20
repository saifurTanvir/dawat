<?php

namespace App\Services\Ai;

use RuntimeException;
use Throwable;

class AssistantUnavailableException extends RuntimeException
{
    /** @param  bool  $busy  the AI was busy or unreachable, so trying again later can work */
    public function __construct(string $message = '', ?Throwable $previous = null, public readonly bool $busy = false)
    {
        parent::__construct($message, 0, $previous);
    }
}
