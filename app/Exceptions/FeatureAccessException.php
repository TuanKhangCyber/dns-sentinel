<?php

namespace App\Exceptions;

use RuntimeException;

class FeatureAccessException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly int $httpStatus, string $message)
    {
        parent::__construct($message);
    }
}
