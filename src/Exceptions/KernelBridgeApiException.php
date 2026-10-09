<?php

namespace KernelBridge\LicensingClient\Exceptions;

use RuntimeException;

final class KernelBridgeApiException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'api_error', public readonly int $status = 0)
    {
        parent::__construct($message);
    }
}
