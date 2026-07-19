<?php

namespace App\Exceptions;

use RuntimeException;

class RecaptchaException extends RuntimeException
{
    public function __construct(public readonly string $errorCode = 'recaptcha_failed')
    {
        parent::__construct(__('platform.errors.'.$errorCode));
    }
}
