<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InsufficientBalanceException extends RuntimeException
{
    public function __construct(string $message = 'Insufficient balance.')
    {
        parent::__construct($message);
    }
}
