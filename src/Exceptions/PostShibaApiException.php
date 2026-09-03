<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Exceptions;

use Symfony\Component\Mailer\Exception\TransportException;

class PostShibaApiException extends TransportException
{
    public function __construct(
        public readonly int $status,
        public readonly ?string $error,
        string $message,
    ) {
        parent::__construct($message, $status);
    }
}
